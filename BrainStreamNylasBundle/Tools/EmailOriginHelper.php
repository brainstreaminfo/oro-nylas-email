<?php

/**
 * Nylas Email Origin Helper.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Tools
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Tools;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProvider;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper as ParentEmailOriginHelper;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * Nylas Email Origin Helper.
 *
 * Helper for managing Nylas email origins.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Tools
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailOriginHelper extends ParentEmailOriginHelper
{
    /**
     * Constructor for EmailOriginHelper.
     *
     * @param DoctrineHelper         $doctrineHelper     The doctrine helper
     * @param TokenAccessorInterface $tokenAccessor      The token accessor
     * @param EmailOwnerProvider     $emailOwnerProvider The email owner provider
     * @param EmailAddressHelper     $emailAddressHelper The email address helper
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        TokenAccessorInterface $tokenAccessor,
        EmailOwnerProvider $emailOwnerProvider,
        EmailAddressHelper $emailAddressHelper
    ) {
        parent::__construct($doctrineHelper, $tokenAccessor, $emailOwnerProvider, $emailAddressHelper);
        $this->doctrineHelper = $doctrineHelper;
        $this->tokenAccessor = $tokenAccessor;
        $this->emailOwnerProvider = $emailOwnerProvider;
        $this->emailAddressHelper = $emailAddressHelper;
    }

    /**
     * Find existing email origin entity by email string or create and persist new one.
     *
     * @param string                $email                    The email address
     * @param User                  $emailOwner               The email owner
     * @param OrganizationInterface $organization             The organization
     * @param string                $originName               The origin name
     * @param bool                  $enableUseUserEmailOrigin Whether to enable user email origin
     *
     * @return EmailOrigin
     */
    public function getEmailOrigin1(
        string $email,
        User $emailOwner,
        OrganizationInterface $organization = null,
        string $originName = InternalEmailOrigin::BAP,
        bool $enableUseUserEmailOrigin = true
    ): EmailOrigin {
        $originKey = $originName . $email;
        if (!array_key_exists($originKey, $this->origins)) {
            $pureEmailAddress = $this->emailAddressHelper->extractPureEmailAddress($email);

            $origin = $this->findCustomEmailOrigin(
                $emailOwner,
                $pureEmailAddress,
                $organization,
                $originName,
                $enableUseUserEmailOrigin
            );

            $this->origins[$originKey] = $origin;
        }
        return $this->origins[$originKey];
    }

    /**
     * Find custom email origin.
     *
     * @param mixed                 $emailOwner               The email owner
     * @param string                $pureEmailAddress         The pure email address
     * @param OrganizationInterface $organization             The organization
     * @param string                $originName               The origin name
     * @param bool                  $enableUseUserEmailOrigin Whether to enable user email origin
     *
     * @return mixed|null|object|InternalEmailOrigin|UserEmailOrigin
     */
    public function findCustomEmailOrigin(
        $emailOwner,
        string $pureEmailAddress,
        OrganizationInterface $organization,
        string $originName,
        bool $enableUseUserEmailOrigin
    ) {
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
