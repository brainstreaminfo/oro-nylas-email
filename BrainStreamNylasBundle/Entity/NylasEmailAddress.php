<?php

/**
 * Nylas Email Address Entity.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Entity
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Entity;

use Oro\Bundle\EmailBundle\Entity\EmailAddress;
use Oro\Bundle\EmailBundle\Entity\EmailOwnerInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nylas Email Address Entity.
 *
 * Extends the base email address entity for Nylas integration.
 *
 * @package BrainStream\Bundle\NylasBundle\Entity
 */
#[ORM\Table(name: 'oro_email_address')]
#[ORM\Entity(repositoryClass: 'BrainStream\Bundle\NylasBundle\Entity\Repository\NylasEmailAddressRepository')]
class NylasEmailAddress extends EmailAddress
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * Constructor for NylasEmailAddress.
     *
     * Initializes the entity with creation and update timestamps.
     *
     * @return void
     */
    public function __construct()
    {
        $this->setCreated(new \DateTime());
        $this->setUpdated(new \DateTime());
    }

    /**
     * Get the owner of this email address.
     *
     * @return EmailOwnerInterface|null The owner or null if not set
     */
    public function getOwner(): ?EmailOwnerInterface
    {
        return $this->owner;
    }

    /**
     * Set the owner of this email address.
     *
     * @param EmailOwnerInterface|null $owner The owner to set
     *
     * @return self
     */
    public function setOwner(EmailOwnerInterface $owner = null): self
    {
        $this->owner = $owner instanceof User ? $owner : null;

        return $this;
    }
}
