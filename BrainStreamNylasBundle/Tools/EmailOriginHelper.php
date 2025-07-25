<?php

namespace BrainStream\Bundle\NylasBundle\Tools;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProvider;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
//use Oro\Bundle\SecurityBundle\SecurityFacade;
//use Oro\Component\DependencyInjection\ServiceLink;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper as ParentEmailOriginHelper;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

class EmailOriginHelper extends ParentEmailOriginHelper
{
    protected $tokenAccessor;

    /** @var  EmailOwnerProvider */
    protected $emailOwnerProvider;

    /** @var EmailAddressHelper */
    protected $emailAddressHelper;

    /** @var array */
    protected $origins = [];

    /**
     * @param DoctrineHelper     $doctrineHelper
     * @param TokenAccessorInterface  $tokenAccessor
     * @param EmailOwnerProvider $emailOwnerProvider
     * @param EmailAddressHelper $emailAddressHelper
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        TokenAccessorInterface $tokenAccessor,
        EmailOwnerProvider $emailOwnerProvider,
        EmailAddressHelper $emailAddressHelper
    ) {
        parent::__construct($doctrineHelper, $tokenAccessor, $emailOwnerProvider, $emailAddressHelper);
        $this->doctrineHelper     = $doctrineHelper;
        $this->tokenAccessor     = $tokenAccessor;
        $this->emailOwnerProvider = $emailOwnerProvider;
        $this->emailAddressHelper = $emailAddressHelper;
    }

    /**
     * Find existing email origin entity by email string or create and persist new one.
     *
     * @param string                $email
     * @param User                  $emailOwner
     * @param OrganizationInterface $organization
     * @param string                $originName
     * @param boolean               $enableUseUserEmailOrigin
     *
     * @return EmailOrigin
     */
    public function getEmailOrigin1(
        $email,
        User $emailOwner,
        OrganizationInterface $organization = null,
        $originName = InternalEmailOrigin::BAP,
        $enableUseUserEmailOrigin = true
    ) {
        $originKey = $originName . $email;
        if (!array_key_exists($originKey, $this->origins)) {
            $pureEmailAddress = $this->emailAddressHelper->extractPureEmailAddress($email);

            $origin = $this
                ->findCustomEmailOrigin($emailOwner, $pureEmailAddress, $organization, $originName, $enableUseUserEmailOrigin);

            $this->origins[$originKey] = $origin;
        }
        return $this->origins[$originKey];
    }

    /**
     * @param mixed                 $emailOwner
     * @param string                $pureEmailAddress
     * @param OrganizationInterface $organization
     * @param string                $originName
     * @param bool                  $enableUseUserEmailOrigin
     *
     * @return mixed|null|object|InternalEmailOrigin|UserEmailOrigin
     */
    public function findCustomEmailOrigin($emailOwner, $pureEmailAddress, $organization, $originName, $enableUseUserEmailOrigin)
    {
        $originQ = $this->getEntityManager()
            ->getRepository(NylasEmailOrigin::class)
            ->createQueryBuilder('origin')
            ->where('origin.owner = :owner')
            ->setParameter('owner', $emailOwner);

        if (!$enableUseUserEmailOrigin) {
            $originQ->andWhere('origin.isDefault = :isDefault')
                ->setParameter('isDefault', true);
        } else {
            $originQ->andWhere('origin.mailboxName = :mailBoxName')
                ->setParameter('mailBoxName', $pureEmailAddress);
        }
        $origin = $originQ->getQuery()->execute();

        if (count($origin) > 0) {
            return $origin[0];
        } else {
            return $this->getEntityManager()->getRepository(NylasEmailOrigin::class)->findOneBy(['mailboxName' => $pureEmailAddress]);
        }
    }
}
