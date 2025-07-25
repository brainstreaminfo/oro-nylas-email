<?php

namespace BrainStream\Bundle\NylasBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\EmailBundle\Entity\EmailUser;

class EmailUserListener
{
    /**
     * set user to private
     * @param LifecycleEventArgs $args
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof EmailUser) {
            $entity->setIsEmailPrivate(false);
        }
    }
}
