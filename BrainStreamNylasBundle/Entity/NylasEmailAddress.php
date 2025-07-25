<?php

namespace BrainStream\Bundle\NylasBundle\Entity;

use Oro\Bundle\EmailBundle\Entity\EmailAddress;
use Oro\Bundle\EmailBundle\Entity\EmailOwnerInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'oro_email_address')]
#[ORM\Entity(repositoryClass: 'BrainStream\Bundle\NylasBundle\Entity\Repository\NylasEmailAddressRepository')]
class NylasEmailAddress extends EmailAddress
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $owner = null;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        $this->setUpdated(new \DateTime());
    }

    public function getOwner(): ?EmailOwnerInterface
    {
        return $this->owner;
    }

    public function setOwner(EmailOwnerInterface $owner = null): self
    {
        $this->owner = $owner instanceof User ? $owner : null;

        return $this;
    }
}
