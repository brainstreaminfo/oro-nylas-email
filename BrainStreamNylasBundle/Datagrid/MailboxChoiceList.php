<?php

/**
 * Nylas Mailbox Choice List.
 *
 * This file is part of the BrainStream Nylas Bundle.
 * Used to hide local mailbox at https://oronylasext.local/email/user-emails
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Datagrid
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Datagrid;

use Oro\Bundle\EmailBundle\Datagrid\MailboxChoiceList as BaseMailboxChoiceList;
use Oro\Bundle\EmailBundle\Entity\Mailbox;
use Oro\Bundle\EmailBundle\Entity\Manager\MailboxManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\EmailBundle\Datagrid\MailboxNameHelper;

/**
 * Nylas Mailbox Choice List.
 *
 * Extends the base mailbox choice list to hide local mailboxes.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Datagrid
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class MailboxChoiceList extends BaseMailboxChoiceList
{
    private TokenAccessorInterface $tokenAccessor;

    private MailboxManager $mailboxManager;

    private MailboxNameHelper $mailboxNameHelper;

    /**
     * Constructor for MailboxChoiceList.
     *
     * @param TokenAccessorInterface $tokenAccessor     The token accessor
     * @param MailboxManager         $mailboxManager    The mailbox manager
     * @param MailboxNameHelper      $mailboxNameHelper The mailbox name helper
     */
    public function __construct(
        TokenAccessorInterface $tokenAccessor,
        MailboxManager $mailboxManager,
        MailboxNameHelper $mailboxNameHelper
    ) {
        $this->tokenAccessor = $tokenAccessor;
        $this->mailboxManager = $mailboxManager;
        $this->mailboxNameHelper = $mailboxNameHelper;
    }

    /**
     * Returns array of mailbox choices without local option.
     *
     * @return array
     */
    public function getChoiceList(): array
    {
        /** @var Mailbox[] $systemMailboxes */
        $systemMailboxes = $this->mailboxManager->findAvailableMailboxes(
            $this->tokenAccessor->getUser(),
            $this->getOrganization()
        );
        $origins = $this->mailboxManager->findAvailableOrigins(
            $this->tokenAccessor->getUser(),
            $this->getOrganization()
        );

        $choiceList = [];

        // Only add system mailboxes (excluding local ones)
        foreach ($systemMailboxes as $mailbox) {
            $origin = $mailbox->getOrigin();
            if (null !== $origin) {
                $choiceList[str_replace('@', '@', $mailbox->getLabel())] = $origin->getId();
            }
        }

        // Filter out local origins and only add origins with folders
        foreach ($origins as $origin) {
            // Skip if this origin is already in the choice list
            if (in_array($origin->getId(), $choiceList, true)) {
                continue;
            }

            // Skip if no folders
            if (count($origin->getFolders()) === 0) {
                continue;
            }

            // Skip local origins (you might need to adjust this condition based on your specific needs)
            // This is a common way to identify local origins
            if (method_exists($origin, 'getMailboxName') && $origin->getMailboxName() === 'Local') {
                continue;
            }

            // Alternatively, you can check by class type if you know the specific local origin class
            // if ($origin instanceof \Path\To\LocalOriginClass) {
            //     continue;
            // }

            $mailboxName = $this->mailboxNameHelper->getMailboxName(
                get_class($origin),
                $origin->getMailboxName(),
                null
            );

            // Additional check to filter out "Local" by name
            if (strtolower($mailboxName) === 'local') {
                continue;
            }

            $choiceList[str_replace('@', '@', $mailboxName)] = $origin->getId();
        }

        return $choiceList;
    }

    /**
     * Get organization.
     *
     * @return Organization|null
     */
    protected function getOrganization(): ?Organization
    {
        return $this->tokenAccessor->getOrganization();
    }
}
