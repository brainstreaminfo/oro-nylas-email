<?php

/**
 * Nylas Email Synchronization Processor.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Sync
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Sync;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Manager\DTO\Email;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailRemoveManager;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;
use BrainStream\Bundle\NylasBundle\Service\NylasEmailIterator;
use BrainStream\Bundle\NylasBundle\Service\NylasMessageIterator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\ActivityBundle\Manager\ActivityManager;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Entity\Email as OroEmail;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Entity\Mailbox;
use Oro\Bundle\EmailBundle\Model\EmailHeader;
use Oro\Bundle\EmailBundle\Provider\EmailActivityListProvider;
use Oro\Bundle\EmailBundle\Sync\AbstractEmailSynchronizationProcessor;
use Oro\Bundle\EmailBundle\Sync\EmailSyncNotificationBag;
use Oro\Bundle\EmailBundle\Sync\KnownEmailAddressCheckerInterface;
use Oro\Bundle\ImapBundle\Mail\Protocol\Exception\InvalidEmailFormatException;
use Oro\Bundle\ImapBundle\Mail\Storage\Exception\UnselectableFolderException;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * Nylas Email Synchronization Processor.
 *
 * Handles the synchronization processing of Nylas emails.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Sync
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailSynchronizationProcessor extends AbstractEmailSynchronizationProcessor
{
    /** Determines how many emails can be loaded from Nylas server at once */
    public const READ_BATCH_SIZE = 100;

    /** Determines how often "Processed X of N emails" hint should be added to a log */
    public const READ_HINT_COUNT = 500;

    /** Determines how often the clearing of outdated folders routine should be executed */
    public const CLEANUP_EVERY_N_RUN = 100;

    /** Max time in seconds between saved DB batches */
    public const DB_BATCH_TIME = 60;

    /** Time limit to sync origin in seconds */
    public const MAX_ORIGIN_SYNC_TIME = 60;

    private NylasEmailRemoveManager $removeManager;

    private NylasClient $nylasClient;

    private NylasEmailManager $nyalsEmailManager;

    private string $emailSyncInterval;

    private EmailActivityListProvider $emailActivityProvider;

    private ActivityManager $activityManager;

    private ?\DateTime $lastEmailSyncDate = null;

    /**
     * Constructor for NylasEmailSynchronizationProcessor.
     *
     * @param EntityManagerInterface             $em                        The entity manager
     * @param EmailEntityBuilder                $emailEntityBuilder        The email entity builder
     * @param KnownEmailAddressCheckerInterface $knownEmailAddressChecker The known email address checker
     * @param NylasEmailRemoveManager           $removeManager             The remove manager
     * @param NylasClient                       $nylasClient               The Nylas client
     * @param NylasEmailManager                 $nylasEmailManager         The Nylas email manager
     * @param string                            $emailSyncInterval         The email sync interval
     * @param EmailActivityListProvider         $activityListProvider      The activity list provider
     * @param ActivityManager                   $activityManager           The activity manager
     */
    public function __construct(
        EntityManagerInterface $em,
        EmailEntityBuilder $emailEntityBuilder,
        KnownEmailAddressCheckerInterface $knownEmailAddressChecker,
        NylasEmailRemoveManager $removeManager,
        NylasClient $nylasClient,
        NylasEmailManager $nylasEmailManager,
        string $emailSyncInterval,
        EmailActivityListProvider $activityListProvider,
        ActivityManager $activityManager
    ) {
        parent::__construct($em, $emailEntityBuilder, $knownEmailAddressChecker);
        $this->removeManager = $removeManager;
        $this->nylasClient = $nylasClient;
        $this->nyalsEmailManager = $nylasEmailManager;
        $this->emailSyncInterval = $emailSyncInterval;
        $this->emailActivityProvider = $activityListProvider;
        $this->activityManager = $activityManager;
    }

    /**
     * Process email synchronization.
     *
     * @param EmailOrigin              $origin          The email origin
     * @param mixed                    $syncStartTime   The sync start time
     * @param EmailSyncNotificationBag $notificationBag The notification bag
     *
     * @return void
     */
    public function process(EmailOrigin $origin, $syncStartTime, EmailSyncNotificationBag $notificationBag): void
    {
        if ($origin instanceof NylasEmailOrigin && $origin->getAccountId() !== null) {
            // make sure that the entity builder is empty
            $this->emailEntityBuilder->clear();

            $this->initEnv($origin);
            $processStartTime = time();

            //set email origin
            $this->nylasClient->setEmailOrigin($origin);

            // iterate through all folders enabled for sync and do a synchronization of emails for each one
            $nylasFolders = $this->getSyncEnabledNylasFolders($origin);

            foreach ($nylasFolders as $emailFolder) {
                // ask an email server to select the current folder
                try {
                    $this->logger->info(sprintf('The folder "%s" is selected.', $emailFolder->getFullName()));

                    // register the current folder in the entity builder
                    $this->emailEntityBuilder->setFolder($emailFolder);

                    // sync emails using this search query
                    $this->lastEmailSyncDate = $this->syncEmails($emailFolder);

                    $startDate = $this->lastEmailSyncDate;
                    if ($startDate) {
                        $checkStartDate = clone $startDate;
                        $checkStartDate->modify('-6 month');
                    }

                    $this->logger->info(
                        sprintf('Sync process completed successfully.')
                    );
                } catch (UnselectableFolderException $e) {
                    $emailFolder->setSyncEnabled(false);
                    $this->logger->info(
                        sprintf('The folder "%s" cannot be selected and was skipped and disabled.', $emailFolder->getFullName())
                    );
                } catch (InvalidEmailFormatException $e) {
                    $emailFolder->setSyncEnabled(false);
                    $this->logger->info(
                        sprintf('The folder "%s" has unsupported email format and was skipped and disabled.', $emailFolder->getFullName())
                    );
                } finally {
                    if (!$this->em->isOpen()) {
                        $this->em = $this->em->create(
                            $this->em->getConnection(),
                            $this->em->getConfiguration()
                        );

                        $emailFolder = $this->em->getRepository(EmailFolder::class)->find($emailFolder->getId());
                    }

                    //this will always update
                    $emailFolder->setSynchronizedAt(($this->lastEmailSyncDate) ? $this->lastEmailSyncDate : $syncStartTime);
                    $emailFolder->setSyncStartDate(new \DateTime());
                    $this->em->persist($emailFolder);
                    $this->em->flush($emailFolder);
                }

                $this->cleanUp(true, $emailFolder);

                $processSpentTime = time() - $processStartTime;

                if (false === $this->getSettings()->isForceMode() && $processSpentTime > self::MAX_ORIGIN_SYNC_TIME) {
                    break;
                }
            }

            // run removing of empty outdated folders every N synchronizations
//            if ($origin->getSyncCount() > 0 && $origin->getSyncCount() % self::CLEANUP_EVERY_N_RUN == 0) {
//                $this->removeManager->cleanupOutdatedFolders($origin, $this->logger);
//            }
        }
    }

    /**
     * Cleans doctrine's UOF to prevent:
     *  - "eating" too much memory
     *  - storing too many object which cause slowness of sync process
     * Tracks time when last batch was saved.
     * Calculates time between batch saves.
     *
     * @param bool             $isFolderSyncComplete
     * @param null|EmailFolder $folder
     */
    protected function cleanUp($isFolderSyncComplete = false, $folder = null)
    {
        $this->emailEntityBuilder->getBatch()->clear();

        /**
         * Clear entity manager.
         */
        $map = $this->entitiesToClear();
        foreach ($map as $entityClass) {
            $this->em->clear($entityClass);
        }

        /**
         * In case folder sync completed and batch save time exceeded limit - throws exception.
         */
        if ($isFolderSyncComplete
            && $folder != null
            && $this->dbBatchSaveTime > 0
            && $this->dbBatchSaveTime > self::DB_BATCH_TIME
        ) {
            //throw new SyncFolderTimeoutException($folder->getOrigin()->getId(), $folder->getFullName());
        } elseif ($isFolderSyncComplete) {
            /**
             * In case folder sync completed without batch save time exceed - reset dbBatchSaveTime.
             */
            $this->dbBatchSaveTime = -1;
        } else {
            /**
             * After batch save - calculate time difference between batches
             */
            if ($this->dbBatchSaveTimestamp !== 0) {
                $this->dbBatchSaveTime = time() - $this->dbBatchSaveTimestamp;

                $this->logger->info(sprintf('Batch save time: "%d" seconds.', $this->dbBatchSaveTime));
            }
        }
        $this->dbBatchSaveTimestamp = time();
    }

    /**
     * Performs synchronization of emails retrieved by the given search query in the given folder
     *
     * @param EmailOrigin      $origin
     * @param EmailFolder $emailFolder
     *
     * @return \DateTime The max sent date
     */
    protected function syncEmails(EmailFolder $emailFolder)
    {
        /** @var EmailFolder $emailFolders */
        $emailFolders = $emailFolder;
        $lastSynchronizedAt = $emailFolder->getSynchronizedAt();
        $emails             = $this->getEmailIterator($emailFolders);
        $count              = $processed = $invalid = $totalInvalid = 0;
        $emails->setIterationOrder(false);
        if ($lastSynchronizedAt) {
            $this->logger->info(sprintf('Before interval timestamp => %s', $lastSynchronizedAt->getTimestamp()));

            // Fetch emails before 1 hour from last synchronized date
            // $lastSynchronizedAt->modify("-" . $this->emailSyncInterval . " minutes");
            $lastSynchronizedAt->modify("-1 hour");
            $this->logger->info(sprintf('After interval timestamp => %s', $lastSynchronizedAt->getTimestamp()));
            #ref:adbrain comment temporary to retrieve emails, remove below comment later
            $emails->setLastSynchronizedAt($lastSynchronizedAt->getTimestamp());
        }
        $emails->setBatchSize(self::READ_BATCH_SIZE);
        $emails->setConvertErrorCallback(
            function (\Exception $e) use (&$invalid) {
                $invalid++;
                $this->logger->error(
                    sprintf('Error occurred while trying to process email: %s', $e->getMessage()),
                    ['exception' => $e]
                );
            }
        );
        $this->logger->info(sprintf('Found %d email(s)....', count($emails)));
        $batch = [];
        /** @var Email $email */
        foreach ($emails as $email) {
            $processed++;
            if ($processed % self::READ_HINT_COUNT === 0) {
                $this->logger->info(
                    sprintf(
                        'Processed %d of %d emails.%s',
                        $processed,
                        count($emails),
                        $invalid === 0 ? '' : sprintf(' Detected %d invalid email(s).', $invalid)
                    )
                );
                $totalInvalid += $invalid;
                $invalid      = 0;
            }
            if ($email->getSentAt() > $lastSynchronizedAt) {
                $lastSynchronizedAt = $email->getSentAt();
            }

            $count++;
            $batch[] = $email;
            if ($count === self::DB_BATCH_SIZE) {
                $this->saveEmails(
                    $batch,
                    $emailFolders
                );
                $count = 0;
                $batch = [];
            }
        }
        if ($count > 0) {
            $this->saveEmails(
                $batch,
                $emailFolders
            );
        }

        $totalInvalid += $invalid;
        if ($totalInvalid > 0) {
            $this->logger->warning(
                sprintf('Detected %d invalid email(s) in "%s" folder.', $totalInvalid, $emailFolder)
            );
        }

        return $lastSynchronizedAt;
    }

    /**
     * Saves emails into the database
     *
     * @param Email[]          $emails
     * @param EmailFolder $emailFolder
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function saveEmails(array $emails, EmailFolder $emailFolder)
    {
        $this->emailEntityBuilder->removeEmails();
        //$emailFolder->getOrigin()->getOwner();

        $existingUids        = $this->getExistingUids($emailFolder, $emails);
        $messageIds          = $this->getMessagesIds($emails);
        $existingNylasEmails = $this->getExistingNylasEmails($emailFolder->getOrigin(), $messageIds);
        $existingEmailUsers  = $this->getExistingEmailUsers($this->getOroEmailFolderObject($emailFolder), $messageIds);
        /** @var $newNylasEmails */
        $newNylasEmails     = new ArrayCollection();
        $existingMessageIds = [];

        foreach ($emails as $email) {
            //$email = $objEmail->getEmail();
            #ref:adbrain remove below contiune, comment for testing
            if (!$this->checkOnOldEmailForMailbox($emailFolder, $email, $emailFolder->getOrigin()->getMailbox())) {
                continue;
            }

            $emailUserData = null;
            if (isset($existingEmailUsers[$email->getMessageId()])) {
                $emailUserData = $existingEmailUsers[$email->getMessageId()];
            }
            if ($this->checkToSkipSyncEmail($email, $existingUids, $existingMessageIds, $emailFolder->getOrigin()->getId(), $emailUserData)) {
               continue;
            }

            /** @var EmailFolder[] $relatedExistingNylasEmails */
            $relatedExistingNylasEmails = array_filter(
                $existingNylasEmails,
                function (OroEmail $oroemail) use ($email) {
                    return $oroemail->getMessageId() === $email->getMessageId();
                }
            );

            try {
                if (!isset($existingEmailUsers[$email->getMessageId()])) {

                    $emailUser = $this->addEmailUser(
                        $email,
                        $emailFolder,
                        $email->getUnread(),
                        $emailFolder->getOrigin()->getOwner(),
                        $this->currentOrganization,
                        //$email->getId(),
                        //$email->getHasAttachment()
                    );

                } else {
                    $emailUser = $existingEmailUsers[$email->getMessageId()];
                    $exitUserCount = $this->em->getRepository(EmailUser::class)
                            ->createQueryBuilder('eu')
                            ->select('eu.id')
                            ->where('eu.email = :emailId')
                            ->setParameter('emailId', $emailUser->getEmail()->getId())
                            ->getQuery()->getResult();
                    if(count($exitUserCount) === 0) {
                        if (!$emailUser->getFolders()->contains($emailFolder)) {
                            $emailUser->addFolder($this->getOroEmailFolderObject($emailFolder));
                        }
                    } else {
                        $emailUser->getEmail()->setUid($email->getUid());
                    }
                }

                //copy parent contexts if any
                $xMessageId = $email->getXMessageId();
                $xThreadId = $email->getXThreadId();
                if(!empty($xMessageId) && !empty($xThreadId)) {
                    $parentMessage = $this->em->getRepository('Oro\Bundle\EmailBundle\Entity\Email')
                                              ->findOneBy(['xThreadId' => $xThreadId]);

                    if(!empty($parentMessage) && $parentMessage->getXThreadId() === $xThreadId) {
                        $contexts = $this->emailActivityProvider->getTargetEntities($parentMessage);
                        if(count($contexts) > 0) {
                            $this->activityManager->addActivityTargets($emailUser->getEmail(), $contexts);
                        }
                    }
                }

                $this->logger->info(
                    sprintf(
                        'The "%s" (UID: %d) email was persisted. Nylas folder id: %d and MsgID %s',
                        $email->getSubject(),
                        $emailUser->getEmail()->getId(),
                        $email->getId(),
                        $email->getMessageId()
                    )
                );

                $this->lastEmailSyncDate = $email->getSentAt();
            } catch (\Exception $e) {
                $this->logger->warning(
                    sprintf(
                        'Failed to persist "%s" (UID: %d) email. Error: %s',
                        $email->getSubject(),
                        $email->getId(),
                        $e->getMessage()
                    )
                );
            }

            if (count($relatedExistingNylasEmails) > 0) {
                $this->removeManager->removeEmailFromOutdatedFolders($relatedExistingNylasEmails, $emailFolder->getOrigin());
            }
        }

        $this->emailEntityBuilder->getBatch()->persist($this->em);

        // update references if needed
        /* If not required for uid set in oroEmail */
        $changes = $this->emailEntityBuilder->getBatch()->getChanges();
        $this->em->flush();
        $this->cleanUp();
    }

    /**
     * @param EmailFolder $folder
     * @param array       $emails
     *
     * @return array
     */
    protected function getExistingUids(EmailFolder $folder, array $emails){
        if (empty($emails)) {
            return [];
        }
        $uids = array_map(
            function ($el) {
                /* @var Email $el */
                return $el->getId();
            },
            $emails
        );
        $rows = $this->em->getRepository(EmailUser::class)
                ->createQueryBuilder('e')
                ->select('email.uid')
                ->leftJoin('e.email', 'email')
                ->where('e.origin = :origin')
                ->setParameter('origin', $folder->getOrigin())
                ->andWhere('email.uid IN (:uids)')
                ->setParameter('uids', $uids)
                ->getQuery()
                ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row['uid'];
        }

        return $result;
    }

    /**
     * Check allowing to save email by date
     *
     * @param EmailFolder $folder
     * @param Email       $email
     * @param Mailbox     $mailbox
     *
     * @return bool
     */
    protected function checkOnOldEmailForMailbox(EmailFolder $folder, Email $email, $mailbox)
    {
        /**
         * @description Will select max of those dates because emails in folder `sent` could have no received date
         *              or same date.
         */
        $dateForCheck = $email->getSentAt(); //max($email->getReceivedAt(), $email->getSentAt());

        if ($mailbox && $folder->getSyncStartDate() > $dateForCheck) {
            $this->logger->info(
                sprintf(
                    'Skip "%s" (UID: %d) email, because it was sent earlier than the start synchronization is set',
                    $email->getSubject(),
                    $email->getMessageId()
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check the email was synced by Uid or wasn't.
     *
     * @param Email $email
     * @param array $existingUids
     *
     * @return bool
     */
    protected function checkOnExistsSavedEmail(Email $email, array $existingUids)
    {
        if (in_array($email->getId(), $existingUids)) {
            return true;
        }

        return false;
    }

    /**
     * Check allowing to save email by uid
     *
     * @param Email $email
     * @param array      $existingUids
     * @param array      $existingMessageIds
     *
     * @return bool
     */
    protected function checkToSkipSyncEmail($email, $existingUids, $existingMessageIds, $originId, $emailUser = null)
    {
        $existsSavedEmail = $this->checkOnExistsSavedEmail($email, $existingUids);

        $existEmailUser = false;
        if($emailUser){
            $emailId = $emailUser->getEmail()->getId();
            $checkEmail = $this->em->getRepository(EmailUser::class)
                                   ->findOneBy(['email' => $emailId, 'origin' => $originId]);
            if($checkEmail){
                $existEmailUser = true;
            }
        }

        $skipSync = false;
        if ($existsSavedEmail && $existEmailUser) {
            $msg      = 'Skip "%s" (UID: %d) email, because it is already synchronised.';
            $skipSync = true;

            if ($this->getSettings()->isForceMode()) {
                $msg = null;
                if ($this->getSettings()->needShowMessage()) {
                    $msg = 'Sync "%s" (UID: %d) email, because force mode is enabled. ';
                }

                $skipSync = false;
            }

            if ($msg) {
                $this->logger->info(
                    sprintf(
                        $msg,
                        $email->getSubject(),
                        $email->getId()
                    )
                );
            }
        }

        return $skipSync;
    }


    /**
     * Gets the list of Message-IDs for emails
     *
     * @param Email[] $emails
     *
     * @return string[]
     */
    protected function getMessageIds(array $emails)
    {
        $result = [];
        foreach ($emails as $email) {
            $result[] = $email->getMessageId();
        }

        return $result;
    }

    /**
     * Gets the list of Uids for emails
     *
     * @param Email[] $emails
     *
     * @return string[]
     */
    protected function getUids(array $emails)
    {
        $result = [];
        foreach ($emails as $email) {
            $result[] = $email->getUid();
        }

        return $result;
    }

    /**
     * Gets the list of Message-IDs for emails
     *
     * @param Email[] $emails
     *
     * @return string[]
     */
    protected function getMessagesIds(array $emails)
    {
        $result = [];
        foreach ($emails as $email) {
            $result[] = $email->getMessageId();
        }

        return $result;
    }


    /**
     * Get email ids and create iterator
     *
     * @param EmailFolder $emailFolder
     * @param EmailFolder      $folder
     *
     * @return NylasEmailIterator
     */
    protected function getEmailIterator(
        EmailFolder $folder
    )
    {
        $this->logger->info(sprintf('Loading emails from "%s" folder ...', $folder->getFullName()));

        return new NylasEmailIterator(
            new NylasMessageIterator(
                $this->nylasClient,
                $folder
            ),
            $this->nyalsEmailManager
        );
    }

    /**
     * Gets the list of folders enabled for sync
     * The outdated folders are ignored
     *
     * @param EmailOrigin $origin
     *
     * @return EmailFolder[]
     */
    protected function getSyncEnabledNylasFolders(EmailOrigin $origin)
    {
        $this->logger->info('Get folders enabled for sync...');

        /** @var  $repo */
        $repo         = $this->em->getRepository(NylasEmailFolder::class);
        $nylasFolders = $repo->createQueryBuilder('ef')
            ->select(
                'ef',
                'COALESCE(ef.syncStartDate, :minDate) AS HIDDEN nullsFirstDate'
            );

        $nylasFolders->setParameter('minDate', new \DateTime('1970-01-01', new \DateTimeZone('UTC')));
        if (EmailFolder::SYNC_ENABLED_TRUE !== EmailFolder::SYNC_ENABLED_IGNORE) {
            $nylasFolders->andWhere('ef.syncEnabled = :syncEnabled')
               ->setParameter('syncEnabled', (bool)EmailFolder::SYNC_ENABLED_TRUE);
        }
        $nylasFolders->andWhere('ef.folderUid IS NOT NULL');
        $nylasFolders->andWhere('ef.outdatedAt IS NULL');
        $nylasFolders->andWhere('ef.type != :type')
                   ->setParameter('type', 'all')
                   ->andWhere('ef.origin = :origin')
                   ->setParameter('origin', $origin)
                    ->addOrderBy('nullsFirstDate', Criteria::ASC);

        $nylasFolderData = $nylasFolders->getQuery()->getResult();

        $this->logger->info(sprintf('Got %d folder(s).', count($nylasFolderData)));

        return $nylasFolderData;
    }

    /**
     * @return array
     */
    protected function entitiesToClear()
    {
        return [
            'Oro\Bundle\EmailBundle\Entity\Email',
            'Oro\Bundle\EmailBundle\Entity\EmailUser',
            'Oro\Bundle\EmailBundle\Entity\EmailRecipient',
            'Oro\Bundle\ImapBundle\Entity\ImapEmail',
            'Oro\Bundle\EmailBundle\Entity\EmailBody',
        ];
    }

    /**
     * Creates email entity and register it in the email entity batch processor
     *
     * @param EmailHeader           $email
     * @param EmailFolder           $folder
     * @param bool                  $isSeen
     * @param User|Mailbox          $owner
     * @param OrganizationInterface $organization
     * @param string                $uid
     * @param bool                  $hasAttachment
     *
     * @return EmailUser
     */

    protected function addEmailUser(
        EmailHeader $email,
        EmailFolder $folder,
        $isSeen = false,
        $owner = null,
        OrganizationInterface $organization = null,
        //$uid,
        //$hasAttachment
    ) {
        $emailUser = $this->emailEntityBuilder->emailUser(
            $email->getSubject(),
            $email->getFrom(),
            $email->getToRecipients(),
            $email->getSentAt(),
            $email->getReceivedAt(),
            $email->getInternalDate(),
            $email->getImportance(),
            $email->getCcRecipients(),
            $email->getBccRecipients(),
            $owner,
            $organization,
           // $uid,
           // $hasAttachment
        );

        //$emailUser->setIsEmailPrivate(false) wont work here, its handled from post persist

        $emailUser
            ->addFolder($this->getOroEmailFolderObject($folder))
            ->setSeen($isSeen)
            ->setOrigin($folder->getOrigin())
            ->getEmail()
            ->setMessageId($email->getMessageId())
            ->setUid($email->getUid())
            ->setHasAttachments($email->getHasAttachment())
            ->setMultiMessageId($email->getMultiMessageId())
            ->setRefs($email->getRefs())
            ->setXMessageId($email->getXMessageId())
            ->setXThreadId($email->getXThreadId())
            ->setAcceptLanguageHeader($email->getAcceptLanguageHeader());

        return $emailUser;
    }

    /**
     * @param EmailFolder $emailFolder
     *
     * @return object|EmailFolder|null
     */
    protected function getOroEmailFolderObject(NylasEmailFolder $emailFolder){
        return $this->em->getRepository(NylasEmailFolder::class)->find($emailFolder);
    }


    /**
     * @param EmailOrigin $origin
     * @param array       $messageIds
     *
     * @return array
     */
    protected function getExistingNylasEmails(EmailOrigin $origin, array $messageIds)
    {
        if (empty($messageIds)) {
            return [];
        }

        $qb = $this->em->getRepository('Oro\Bundle\EmailBundle\Entity\Email')
            ->createQueryBuilder('email');

        $emailData = $qb
            ->select('email')
            ->innerJoin(EmailUser::class, 'email_users', Join::WITH, $qb->expr()->andx(
                $qb->expr()->eq('email_users.email', 'email'),
                $qb->expr()->eq('email_users.origin', ':origin'),
                $qb->expr()->in('email.messageId', ':messageIds')
            ))
            ->setParameter('origin', $origin)
            ->setParameter('messageIds', $messageIds)
            ->getQuery()
            ->getResult();

        return $emailData;
    }

}
