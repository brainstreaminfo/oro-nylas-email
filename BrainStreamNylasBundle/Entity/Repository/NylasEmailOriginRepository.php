<?php

/**
 * Nylas Email Origin Repository.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Entity\Repository
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailAttachmentContent;
use Oro\Bundle\EmailBundle\Entity\EmailBody;
use Oro\Bundle\EmailBundle\Event\EmailBodyLoaded;
use Oro\Bundle\EmailBundle\Entity\EmailAttachment;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Oro\Bundle\UserBundle\Entity\User;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;

/**
 * Nylas Email Origin Repository.
 *
 * Repository for managing Nylas email origin entities and related operations.
 *
 * @package BrainStream\Bundle\NylasBundle\Entity\Repository
 */
class NylasEmailOriginRepository extends EntityRepository
{
    /**
     * Fetch client Nylas status.
     *
     * @param string $manager_url      The manager URL
     * @param string $clientIdentifier The client identifier
     *
     * @return bool|null The Nylas status or null if not found
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getClientNylasStatus($manager_url, $clientIdentifier): ?bool
    {
        $client   = new \GuzzleHttp\Client(['verify' => false]);
        $response = $client->request('GET', $manager_url . 'client/nylas/' . $clientIdentifier);

        if ($response && $response->getBody()) {
            $clientDetails = json_decode($response->getBody(), true);
            return (bool)$clientDetails['nylasStatus'];
        }

        return null;
    }

    /**
     * Get inactive origins for a user.
     *
     * @param NylasEmailOrigin $newOrigin The new origin
     *
     * @return array The inactive origins
     */
    public function getInactiveOrigins(NylasEmailOrigin $newOrigin)
    {
        return $this->createQueryBuilder('old_origin')
            ->where('old_origin.isActive = :inactive')
            ->andWhere('old_origin.owner = :owner')
            ->setParameter('inactive', false)
            ->setParameter('owner', $newOrigin->getOwner())
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user has old email sync settings or new nylas.
     *
     * @param User      $user      The user
     * @param int|null  $originId  The origin ID
     *
     * @return mixed The Nylas origin or null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getNylasOrigins(User $user, $originId = null)
    {
        $nylasOriginQuery = $this->createQueryBuilder('origin')
            ->where('origin.accountId IS NOT NULL')
            ->andWhere('origin.owner = :owner')
            ->setParameter('owner', $user);

        if ($originId) {
            $nylasOriginQuery->andWhere('origin.id = :origin')->setParameter('origin', $originId);
        }
        return $nylasOriginQuery->getQuery()->getOneOrNullResult();
    }

    /**
     * Fetch all origins of the user.
     *
     * @param User $user The user
     *
     * @return array The user origins data
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getAllUserOrigins(User $user)
    {
        $response = [
            'total'  => 0,
            'active' => 0,
            'isMultipleEmail' => false
        ];
        $origins  = $this->createQueryBuilder('origin')
            ->select("origin.id, origin.isActive, origin.syncCode, origin.isDefault, origin.user, origin.provider, origin.syncCodeUpdatedAt, origin.createdAt, CONCAT(owner.firstName, ' ', owner.lastName) as username, origin.signature, owner.is_multiple_email as isMultipleEmail")
            ->innerJoin('origin.owner', 'owner')
            ->where('origin.accountId IS NOT NULL')
            ->andWhere('origin.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getArrayResult();
        if (count($origins) > 0) {
            $totalActive = 0;
            foreach ($origins as $key => $origin) {
                if (isset($origin['createdAt']) && !empty($origin['createdAt'])) {
                    $origins[$key]['createdAt'] = $origin['createdAt']->format('d-m-Y H:i:s');
                }
                if (isset($origin['syncCodeUpdatedAt']) && !empty($origin['syncCodeUpdatedAt'])) {
                    $origins[$key]['syncCodeUpdatedAt'] = $origin['syncCodeUpdatedAt']->format('d-m-Y H:i:s');
                }
                if ($origin['isActive'] && $origin['syncCode'] == 3) {
                    $totalActive++;
                }
            }
            $response['active'] = $totalActive;
            $response['total']  = count($origins);
            $response['isMultipleEmail']  = $origin['isMultipleEmail'];
        }
        $response['origins'] = $origins;

        return $response;
    }

    /**
     * Fetch array result of user origin.
     *
     * @param User $user The user
     *
     * @return array|int
     */
    private function userOriginQuery(User $user)
    {
        return $this->createQueryBuilder('origin')
            ->select("origin.id, origin.isActive, origin.user, origin.provider, origin.syncCodeUpdatedAt, origin.createdAt, CONCAT(owner.firstName, ' ', owner.lastName) as username, origin.signature")
            ->innerJoin('origin.owner', 'owner')
            ->where('origin.accountId IS NOT NULL')
            ->andWhere('origin.owner = :owner')
            ->andWhere('origin.isDefault = :default')
            ->setParameter('owner', $user)
            ->setParameter('default', 0)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Updating remove sync.
     *
     * @param int $emailOriginId The email origin ID
     *
     * @return array
     */
    public function removeSyncEmailOrigin($emailOriginId)
    {
        return $this->createQueryBuilder('neo')
            ->update()
            ->set('neo.isActive', 0)
            ->where('neo.id = :emailOrigin')
            ->setParameter('emailOrigin', $emailOriginId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count origins of the user.
     *
     * @param User   $user         The user
     * @param string $manager_url  The manager URL
     * @param object $translation  The translation object
     * @param array  $data         The data array
     *
     * @return array
     */
    public function countAllUserOrigins(User $user, $manager_url, $translation, $data)
    {
        $result                  = [
            'total'  => 0,
            'status' => true
        ];
        $origins                 = $this->userOriginQuery($user);
        $originsEmailCount       = $this->getTotalExtraEmails($user);
        $data['extraEmailCount'] = $data['extraEmailCount'] + $originsEmailCount;
        $result['total']         = count($origins);

        /**
         * Check multiple email account status on manager
         */

        $client   = new \GuzzleHttp\Client(['base_uri' => $manager_url, 'verify' => false]);
        $response = $client->request('POST', 'clients/multiple-email/check/access', ['form_params' => $data]);

        if ($response && $response->getBody()) {
            $response = json_decode($response->getBody()->getContents(), true);
        }

        if (isset($response['allowEmail']) && !$response['allowEmail']) {
            $result['status']  = false;
            $result['message'] = $translation->trans('user.user.notAllowedMultipleEmail');
        } elseif (isset($response['allowExtraEmails']) && !$response['allowExtraEmails']) {
            $result['status']  = false;
            $result['message'] = $translation->trans('user.user.exceedMultipleEmail', ['%emailCount%' => $response['allowExtraEmailsCounts']]);
        }
        return $result;
    }

    /**
     * Getting multiple email details.
     *
     * @param User $user The user
     *
     * @return array
     */
    public function getMultipleEmailList($user)
    {
        return $this->createQueryBuilder('neo')
            ->select('neo.id, neo.mailboxName, neo.isDefault, neo.isActive')
            ->where('neo.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Getting all multiple email details.
     *
     * @return array
     */
    public function getMultipleEmails()
    {
        return $this->createQueryBuilder('origin')
            ->select("origin.id, origin.isActive, origin.user, origin.provider, origin.syncCodeUpdatedAt, origin.createdAt, origin.accountId, origin.syncCode, CONCAT(owner.firstName, ' ', owner.lastName) as username, origin.synchronizedAt, origin.isDefault")
            ->innerJoin('origin.owner', 'owner')
            ->where('origin.accountId IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Getting total extra emails count.
     *
     * @param User $user The user
     *
     * @return array|int
     */
    public function getTotalExtraEmails($user)
    {
        // Getting owner ids
        $emailCount = $this->createQueryBuilder('origin')
            ->select("SUM(su.number_of_email) as emailCount")
            ->innerJoin(User::class, 'su', 'WITH', 'su.id = origin.owner')
            ->where('origin.accountId IS NOT NULL')
            ->andWhere('origin.owner NOT IN (:owner)')
            ->andWhere('origin.isDefault = :default')
            ->setParameter('owner', $user)
            ->setParameter('default', 0)
            ->getQuery()
            ->getArrayResult();

        $emailCount = array_column($emailCount, "emailCount");

        if (empty($emailCount[0])) {
            return 0;
        }

        return $emailCount[0];
    }

    /**
     * Updating isDefault to 0 for owner.
     *
     * @param User $owner The owner
     *
     * @return void
     */
    public function updateIsDefault($owner)
    {
        $this->createQueryBuilder('neo')
            ->update()
            ->set('neo.isDefault', 0)
            ->where('neo.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->execute();
    }

    /**
     * Fetch default origin for user.
     *
     * @param User $user The user
     *
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function fetchDefaultOrigin(User $user)
    {
        return $this->createQueryBuilder('nylas_email_origin')
            ->select('nylas_email_origin.mailboxName')
            ->where('nylas_email_origin.isDefault = :isDefault')
            ->andWhere('nylas_email_origin.isActive = :isActive')
            ->andWhere('nylas_email_origin.owner = :owner')
            ->setParameter('isDefault', true)
            ->setParameter('isActive', true)
            ->setParameter('owner', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Load email body and attachments.
     *
     * @param Email             $entity            The email entity
     * @param User              $user              The user
     * @param NylasClient       $nylasClient       The Nylas client
     * @param NylasEmailManager $nylasEmailManager The Nylas email manager
     * @param object            $dispatcher        The dispatcher
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function loadEmailBody(Email $entity, User $user, NylasClient $nylasClient, NylasEmailManager $nylasEmailManager, $dispatcher)
    {
        if ($entity->getUid()) {
            $messageId = $entity->getUid();
            $headerMessageId = $entity->getMessageId();
            //Set access token and account id based on origin
            $emailOrigin = $entity->getEmailUsers()[0]->getOrigin();
            $nylasClient->setEmailOrigin($emailOrigin);

            $apiVersion = $nylasClient->guessApiVersion($messageId);

            //check added for nylas v2 api, if uid found is of v2 then find res. v3 uid using subject, from and to
            if ($apiVersion == 'v2') {
                $message = $nylasClient->getMessage($entity);
                $uid = '';
                $xThreadId = '';
                if (count($message['data']) == 1) {
                    $uid = $message['data'][0]['id'];
                    $xThreadId = $message['data'][0]['thread_id'];
                } else {
                    foreach ($message['data'] as $message) {
                        $msgId = $nylasClient->getMessageId($message, 'Message-Id', $message['id']);
                        if ($headerMessageId == $msgId) {
                            $uid = $message['id'];
                            $xThreadId = $message['thread_id'];
                            break;
                        }
                    }
                }
                if ($uid) {
                    $messageId = $uid;
                    $entity->setUid($uid);
                    $entity->setXThreadId($xThreadId);
                }
            }
            //check message exists
            $message = $nylasClient->getMessageBody($messageId);

            if ($entity->getEmailBody() === null) {
                //Convert nylas array to DTO Email instance
                /* @var \BrainStream\Bundle\NylasBundle\Manager\DTO\Email*/
                $message = $nylasEmailManager->convertToEmail($message['data'], false);

                if ($message->getBody() instanceof \BrainStream\Bundle\NylasBundle\Manager\DTO\EmailBody) {
                    $emailBody = new EmailBody();
                    $emailBody->setBodyContent($message->getBody()->getContent());
                    $emailBody->setBodyIsText($message->getBody()->getBodyIsText());
                    $emailBody->setEmail($entity);

                    if (count($message->getAttachments()) > 0) {
                        foreach ($message->getAttachments() as $attachment) {
                            $emailAttachment        = new EmailAttachment();
                            $emailAttachmentContent = new EmailAttachmentContent();

                            $emailAttachmentContent
                                ->setEmailAttachment($emailAttachment)
                                ->setContentTransferEncoding($attachment->getContentTransferEncoding())
                                ->setContent($attachment->getFileName());

                            $emailAttachment
                                ->setFileName($attachment->getFileName())
                                ->setContentType($attachment->getContentType())
                                ->setContent($emailAttachmentContent)
                                ->setEmbeddedContentId($attachment->getContentId());
                                //->setEmbeddedFileId($attachment->getFileContentId());
                            $emailAttachment->setEmailBody($emailBody);

                            $emailBody->addAttachment($emailAttachment);
                        }
                    }

                    $entity->setEmailBody($emailBody);

                    $entity->setBodySynced(true);
                    $this->getEntityManager()->persist($entity);
                    $this->getEntityManager()->flush();

                    //Set message read as true
                    $nylasClient->updateMailSeenStatus($messageId, true);
                }
            } else {
                //Set message read as true
                $nylasClient->updateMailSeenStatus($messageId, true);
            }
            //$dispatcher->dispatch(EmailBodyLoaded::NAME, new EmailBodyLoaded($entity));
            //ref:adbrain
            $dispatcher->dispatch(new EmailBodyLoaded($entity), EmailBodyLoaded::NAME);
        } elseif ($entity->getEmailBody() != null) {
            //$dispatcher->dispatch(EmailBodyLoaded::NAME, new EmailBodyLoaded($entity));
            //ref:adbrain
            $dispatcher->dispatch(new EmailBodyLoaded($entity), EmailBodyLoaded::NAME);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Add a nylas exception to manager's table.
     *
     * @param string $manager_url The manager URL
     * @param array  $data        The data array
     *
     * @return bool|\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function postNylasException($manager_url, $data)
    {
        try {
            $client  = new \GuzzleHttp\Client(['base_uri' => $manager_url, 'verify' => false]);
            $response = $client->request('POST', '/client/exception', ['form_params' => $data]);
            $response = json_decode($response->getBody()->getContents(), true);
            if ($response) {
                return true;
            }
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * Get email origin mailbox count.
     *
     * @param array $emailOriginIds The email origin IDs
     *
     * @return int
     */
    public function getEmailOriginMailboxCount($emailOriginIds)
    {
        $resultCount = 0;
        $first = $this->createQueryBuilder('origin')
            ->where('origin.id = :id')
            ->setParameter('id', $emailOriginIds[0])
            ->getQuery()->getArrayResult();

        $second = $this->createQueryBuilder('origin')
            ->where('origin.mailboxName = :mailboxName')
            ->setParameter('mailboxName', $first[0]['mailboxName'])
            ->getQuery()->getArrayResult();

        if (count($second) > 1) {
            $resultCount = count($second);
        }

        return $resultCount;
    }

    /**
     * Updating remove sync.
     *
     * @param string $email The email address
     *
     * @return array
     */
    public function verifyMassEmail($email)
    {
        return $this->createQueryBuilder('neo')
            ->update()
            ->set('neo.verifyMassEmail', 1)
            ->where('neo.mailboxName = :mailboxName')
            ->setParameter('mailboxName', $email)
            ->andWhere('neo.verifyMassEmail = :verifyMassEmail')
            ->setParameter('verifyMassEmail', 0)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get verified mass email for user.
     *
     * @param User $user The user
     *
     * @return array
     */
    public function getVerifiedMassEmail($user)
    {
        return $this->createQueryBuilder('neo')
            ->select('neo.id', 'neo.mailboxName', 'neo.signature')
            ->where('neo.owner = :owner')
            ->andWhere('neo.verifyMassEmail = :verifyMassEmail')
            ->andWhere('neo.isActive = :isActive')
            ->setParameter('verifyMassEmail', 1)
            ->setParameter('owner', $user)
            ->setParameter('isActive', 1)
            ->orderBy('neo.isDefault', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get verified emails of all users.
     *
     * @return array
     */
    public function getVerifiedEmailsOfAllUsers()
    {
        return $this->createQueryBuilder('neo')
            ->select('neo.mailboxName')
            ->andWhere('neo.verifyMassEmail = :verifyMassEmail')
            ->andWhere('neo.isActive = :isActive')
            ->setParameter('verifyMassEmail', 1)
            ->setParameter('isActive', 1)
            ->groupBy('neo.mailboxName')
            ->getQuery()
            ->getArrayResult();
    }
}
