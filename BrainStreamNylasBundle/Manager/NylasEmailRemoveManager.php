<?php

/**
 * Nylas Email Remove Manager.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Manager;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Oro\Bundle\EmailBundle\Entity\Email;
use Psr\Log\LoggerInterface;

/**
 * Nylas Email Remove Manager.
 *
 * Manages removal of Nylas emails and folders.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailRemoveManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected EntityManager $em;

    /**
     * Constructor for NylasEmailRemoveManager.
     *
     * @param ManagerRegistry $doctrine The doctrine manager registry
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->em = $doctrine->getManager();
    }

    /**
     * Deletes all empty outdated folders.
     *
     * @param EmailOrigin     $origin The email origin
     * @param LoggerInterface $logger The logger
     *
     * @return void
     */
    public function cleanupOutdatedFolders(EmailOrigin $origin, LoggerInterface $logger): void
    {
        $logger->info('Removing empty outdated folders ...');

        $emailFolders = $this->em->getRepository(NylasEmailFolder::class)
            ->findBy(['origin' => $origin]);
        $folders = new ArrayCollection();

        foreach ($emailFolders as $emailFolder) {
            if ($emailFolder->getSubFolders()->count() === 0) {
                $logger->info(sprintf('Remove "%s" folder.', $emailFolder->getFullName()));

                if (!$folders->contains($emailFolder)) {
                    $folders->add($emailFolder);
                    $this->em->remove($emailFolder);
                }

                if (count($emailFolder) > 0) {
                    foreach ($emailFolder as $item) {
                        $emailFolder->removeNylasEmail($item);
                    }
                }
            }
        }

        if (count($folders) > 0) {
            $this->em->flush();
            $logger->info(sprintf('Removed %d folder(s).', count($folders)));
        }
    }

    /**
     * Removes email from all outdated folders.
     *
     * @param Email[]     $nylasEmails The list of all related NYLAS emails
     * @param EmailOrigin $emailOrigin The email origin
     *
     * @return void
     */
    public function removeEmailFromOutdatedFolders(array $nylasEmails, EmailOrigin $emailOrigin): void
    {
        $emailUsers = $this->em->getRepository(EmailUser::class)
            ->findBy(['origin' => $emailOrigin, 'email' => $nylasEmails]);

        foreach ($emailUsers as $emailUser) {
            foreach ($emailUser->getOrigin()->getFolders() as $emailFolder) {
                $emailUser->removeFolder($emailFolder);
                if (!$emailUser->getFolders()->count()) {
                    $emailUser->getEmail()->getEmailUsers()->removeElement($emailUser->getEmail());
                }
            }
            $this->em->remove($emailUser->getEmail());
        }
    }
}
