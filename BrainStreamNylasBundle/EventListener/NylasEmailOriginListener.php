<?php

/**
 * Nylas Email Origin Listener.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\EventListener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Psr\Log\LoggerInterface;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\Mailbox;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * Nylas Email Origin Listener.
 *
 * Listener for handling Nylas email origin entity lifecycle events.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailOriginListener
{
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger The logger service
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Create mailbox on post persist of NylasEmailOrigin.
     *
     * @param PostPersistEventArgs $args The post persist event arguments
     *
     * @return void
     * @throws \Exception
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof NylasEmailOrigin) {
            return;
        }

        $this->logger->info(sprintf('PostPersist email origin called: %s', $entity->getUser() ?? 'null'));

        try {
            $entityManager = $args->getObjectManager();

            // Check for existing Mailbox for this specific origin
            $mailbox = $entityManager->getRepository(Mailbox::class)->findOneBy(['origin' => $entity]);
            if (!$mailbox) {
                $this->logger->info('No existing Mailbox found, creating new one', ['origin_id' => $entity->getId()]);
                $mailbox = $this->createMailbox($entity, $entity->getOwner());
                $entity->setMailbox($mailbox);
                $entityManager->persist($mailbox);
                $entityManager->flush(); // Flush here to ensure the relationship is saved
                $this->logger->info('New Mailbox created and persisted', ['mailbox_id' => $mailbox->getId()]);
            } else {
                $this->logger->info('Mailbox already exists, skipping creation', ['mailbox_id' => $mailbox->getId()]);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error in NylasEmailOrigin postPersist',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Create a new mailbox instance.
     *
     * @param NylasEmailOrigin $entity   The Nylas email origin entity
     * @param User             $userData The user data
     *
     * @return Mailbox
     * @throws \LogicException
     */
    private function createMailbox(NylasEmailOrigin $entity, User $userData): Mailbox
    {
        if (!$userData) {
            throw new \LogicException('Owner is required to create Mailbox');
        }

        $this->logger->info('Creating new Mailbox instance');
        $mailBox = new Mailbox();
        $mailBox->setOrganization($entity->getOrganization());
        $mailBox->setOrigin($entity);
        $mailBox->setEmail($entity->getUser());
        $mailBox->setLabel($entity->getUser());
        $mailBox->setCreatedAt(new \DateTime());
        $mailBox->addAuthorizedUser($userData);

        return $mailBox;
    }
}
