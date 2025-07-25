<?php


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

class NylasEmailRemoveManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var EntityManager */
    protected $em;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->em = $doctrine->getManager();
    }


    /**
     * Deletes all empty outdated folders
     *
     * @param EmailOrigin $origin
     * @param             $logger
     */
    public function cleanupOutdatedFolders(EmailOrigin $origin, $logger)
    {
        $logger->info('Removing empty outdated folders ...');

       /** @var  $EmailFolders */
        $EmailFolders = $this->em->getRepository(NylasEmailFolder::class)
                                 ->findBy(['origin' => $origin]);
        $folders      = new ArrayCollection();

        /** @var EmailFolder $emailFolder */
        foreach ($EmailFolders as $emailFolder) {
            //$emailFolder = $nylasFolder->getFolder();
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

                $this->em->remove($emailFolder);
            }
        }

        if (count($folders) > 0) {
            $this->em->flush();
            $logger->info(sprintf('Removed %d folder(s).', count($folders)));
        }
    }

    /**
     * Removes email from all outdated folders
     *
     * @param Email[] $nylasEmails The list of all related NYLAS emails
     * @param EmailOrigin $emailOrigin
     */
    public function removeEmailFromOutdatedFolders(array $nylasEmails, EmailOrigin $emailOrigin)
    {

        /** @var EmailUser  $emailUser */
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
