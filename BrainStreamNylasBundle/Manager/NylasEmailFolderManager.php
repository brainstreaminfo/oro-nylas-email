<?php

/**
 * Nylas Email Folder Manager.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
//use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Model\FolderType;
use Oro\Bundle\ImapBundle\Form\Model\EmailFolderModel;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;

/**
 * Nylas Email Folder Manager.
 *
 * Manages Nylas email folder operations.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailFolderManager
{
    protected NylasClient $client;

    protected EmailOrigin $origin;

    protected EntityManager $em;

    protected array $flagTypeMap = [
        FolderType::INBOX,
        FolderType::SENT,
        // FolderType::DRAFTS,
        FolderType::TRASH,
        FolderType::SPAM,
    ];

    /**
     * Constructor for NylasEmailFolderManager.
     *
     * @param NylasClient   $client The Nylas client
     * @param EntityManager $em     The entity manager
     * @param EmailOrigin   $origin The email origin
     */
    public function __construct(NylasClient $client, EntityManager $em, EmailOrigin $origin)
    {
        $this->client = $client;
        $this->em = $em;
        $this->origin = $origin;
    }

    /**
     * Get folders.
     *
     * @param bool $byPassOutdatedAt Whether to bypass outdated check
     *
     * @return EmailFolder[]
     */
    public function getFolders(bool $byPassOutdatedAt = false): array
    {
        // retrieve folders from imap
        $emailFolderModels = $this->client->getFolders(5);
        //echo 'email folder models';
        //dump($emailFolderModels);

        // transform folders into tree of models, commented as we store top level folders only
        //$emailFolderModels = $this->processFolders($folders);
        if ($this->origin->getId()) {
            // renew existing folders if origin already exists, referesh was causing issues so its removed
            $existingFolders = $this->getExistingFolders();
            /*echo 'existing folder======';
            dump($existingFolders);
            */

            // merge synced and existing folders
            $emailFolderModels = $this->mergeFolders($emailFolderModels, $existingFolders);
            // mark old folders as outdated
            $this->markAsOutdated($existingFolders, $byPassOutdatedAt);

            // flush changes
            $this->em->flush();
        }

        return $this->extractEmailFolders($emailFolderModels)->toArray();
    }

    /**
     * Mark folders as outdated.
     *
     * @param ArrayCollection|NylasEmailFolder[] $existingFolders  The existing folders
     * @param bool                               $byPassOutdatedAt Whether to bypass outdated check
     *
     * @return void
     */
    protected function markAsOutdated($existingFolders, bool $byPassOutdatedAt = false): void
    {
        $outdatedAt = new \DateTime('now', new \DateTimeZone('UTC'));

        /** @var NylasEmailFolder $existingFolder */
        if (!$byPassOutdatedAt) {
            foreach ($existingFolders as $emailFolder) {
                $emailFolder->setOutdatedAt($outdatedAt);
                $emailFolder->setSyncEnabled(false);
            }
        }
    }

    /**
     * Merge folders.
     *
     * @param array                              $syncedFolderModels   The synced folder models
     * @param ArrayCollection|NylasEmailFolder[] $existingEmailFolders The existing email folders
     *
     * @return array
     */
    protected function mergeFolders(array $syncedFolderModels, &$existingEmailFolders): array
    {
        foreach ($syncedFolderModels as $syncedFolderModel) {
            $flagTypes = $this->flagTypeMap;
            $f = $existingEmailFolders->filter(
                function ($emailFolder) use ($syncedFolderModel, $flagTypes) {
                    //ref:adbrain skip invalid types, skipped child folders for now, only parent folder will be saved
                    if ($emailFolder->getParentFolder() != null) {
                        return false; // Skip invalid types
                    }
                    if (in_array($emailFolder->getType(), $flagTypes)) {
                        return ($emailFolder->getType() === $syncedFolderModel->getEmailFolder()->getType() && $emailFolder->getOrigin() === $syncedFolderModel->getEmailFolder()->getOrigin());
                    } else {
                        return strtolower($syncedFolderModel->getEmailFolder()->getName()) == 'draft' || ((strtolower($emailFolder->getName()) === strtolower(str_replace('_', ' ', $syncedFolderModel->getEmailFolder()->getName())) || strtolower($emailFolder->getName()) === strtolower($syncedFolderModel->getEmailFolder()->getName())) && $emailFolder->getOrigin() === $syncedFolderModel->getEmailFolder()->getOrigin() && strtolower($emailFolder->getType()) === FolderType::OTHER);
                    }
                }
            );
            //ref:adbrain skip invalid types, skipped child folders for now, only parent folder will be saved
            if ($syncedFolderModel->getEmailFolder()->getParentFolder() != null) {
                continue;
            }
            if ($f->isEmpty()) {
                $nylasEmailFolder = new NylasEmailFolder();
                // there is a new folder on server, create it
                // persist ImapEmailFolder and (by cascade) EmailFolder
                $nylasEmailFolder->setOrigin($syncedFolderModel->getEmailFolder()->getOrigin());
                $nylasEmailFolder->setFolderUid($syncedFolderModel->getUidValidity());
                $nylasEmailFolder->setName($syncedFolderModel->getEmailFolder()->getName());
                $nylasEmailFolder->setFullName($syncedFolderModel->getEmailFolder()->getFullName());
                $nylasEmailFolder->setType($syncedFolderModel->getEmailFolder()->getType());
                //$syncedFolderModel->getEmailFolder()->setFolderUid($syncedFolderModel->getUidValidity());
                $this->em->persist($nylasEmailFolder);
            } else {
                /** @var EmailFolder $existingEmailFolder */
                $emailFolder = $f->first();
                //ref:adbrain need to fix refresh gives error, so commented
                //$this->em->refresh($emailFolder);
                $emailFolder->setName($syncedFolderModel->getEmailFolder()->getName());
                $emailFolder->setFullName($syncedFolderModel->getEmailFolder()->getFullName());
                $syncedFolderModel->setEmailFolder($emailFolder);
                $existingEmailFolders->removeElement($emailFolder);
                $emailFolderData = $this->em->getRepository(NylasEmailFolder::class)->find($emailFolder->getId());
                // dump('new folder data...');
                // dump($emailFolderData);
                if (!$emailFolderData) {
                    //echo 'email folder does not exist========='. $emailFolderData->getName();
                }
                if ($emailFolderData) {
                    $emailFolderData->setFolderUid($syncedFolderModel->getUidValidity());
                }
            }

            if ($syncedFolderModel->hasSubFolderModels()) {
                $syncedFolderModel->setSubFolderModels(
                    $this->mergeFolders(
                        $syncedFolderModel->getSubFolderModels()->toArray(),
                        $existingEmailFolders
                    )
                );
            }
        }

        return $syncedFolderModels;
    }

    /**
     * Extract email folders.
     *
     * @param EmailFolderModel[]|ArrayCollection $emailFolderModels The email folder models
     *
     * @return EmailFolder[]|ArrayCollection
     */
    protected function extractEmailFolders($emailFolderModels)
    {
        $emailFolders = new ArrayCollection();
        /** @var EmailFolderModel $emailFolderModel */
        foreach ($emailFolderModels as $emailFolderModel) {
            $emailFolder = $emailFolderModel->getEmailFolder();
            if ($emailFolderModel->hasSubFolderModels()) {
                $emailFolder->setSubFolders($this->extractEmailFolders($emailFolderModel->getSubFolderModels()));
            } else {
                $emailFolder->setSubFolders([]);
            }
            $emailFolders->add($emailFolder);
        }
        return $emailFolders;
    }

    /**
     * Get existing folders.
     *
     * @return NylasEmailFolder[]|ArrayCollection
     */
    protected function getExistingFolders()
    {
        //Oro\Bundle\EmailBundle\Entity\EmailFolder
        //OroEmailBundle:EmailFolder

        $qb = $this->em->createQueryBuilder()
            ->select('ef')
            ->from(NylasEmailFolder::class, 'ef')
            ->where('ef.origin = :origin')
            ->setParameter('origin', $this->origin);

        return new ArrayCollection($qb->getQuery()->getResult());
    }
}
