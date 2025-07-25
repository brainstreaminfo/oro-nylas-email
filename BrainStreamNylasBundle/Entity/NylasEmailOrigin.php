<?php

namespace BrainStream\Bundle\NylasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;


/**
 * Nylas Email Origin
 */
#[ORM\Entity(repositoryClass: "BrainStream\Bundle\NylasBundle\Entity\Repository\NylasEmailOriginRepository")]
#[ORM\Table(name: "oro_email_origin")]

class NylasEmailOrigin extends UserEmailOrigin //implements ExtendEntityInterface
{
    //use ExtendEntityTrait;

    protected $name = 'nylasemailorigin';

    #[ORM\Column(name: 'account_id', type: 'string', length: 255, nullable: true)]
    protected $accountId;

    #[ORM\Column(name: 'provider', type: 'string', length: 255, nullable: true)]
    protected $provider;

    #[ORM\Column(name: 'token_type', type: 'string', length: 255, nullable: true)]
    protected $tokenType;

    #[ORM\Column(name: 'is_default', type: 'boolean', length: 255)]
    protected $isDefault;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    protected $createdAt;

    private $logger;

    public function __construct()
    {
        parent::__construct();
        $this->name = 'nylasemailorigin';
        // $this->setCreatedAt(new \DateTime());
        // $this->setVerifyMassEmail(0);
    }

    public function getAccountId()
    {
        return $this->accountId;
    }


    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
        return $this;
    }


    public function getProvider()
    {
        return $this->provider;
    }

    public function setProvider($value)
    {
        $this->provider = $value;

        return $this;
    }


    public function getTokenType()
    {
        return $this->tokenType;
    }


    public function setTokenType($value)
    {
        $this->tokenType = $value;

        return $this;
    }

    public function getIsDefault()
    {
        return $this->isDefault;
    }


    public function setIsDefault($isDefault)
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    public function setCreatedAt(\DateTime $datetime = null)
    {
        $this->createdAt = $datetime;

        return $this;
    }

    public function setName($name)
    {
        // Always use 'nylas' regardless of what's passed
        $this->name = 'nylasemailorigin';
        return $this;
    }

    public function getName()
    {
        return 'nylasemailorigin';
    }

}
