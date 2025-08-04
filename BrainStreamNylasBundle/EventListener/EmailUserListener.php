<?php

/**
 * Email User Listener.
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

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\EmailBundle\Entity\EmailUser;

/**
 * Email User Listener.
 *
 * Listener for handling email user entity lifecycle events.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailUserListener
{
    /**
     * Set user to private.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof EmailUser) {
            $entity->setIsEmailPrivate(false);
        }
    }
}
