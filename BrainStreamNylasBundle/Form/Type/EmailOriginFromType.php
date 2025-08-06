<?php

/**
 * Email Origin From Type.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Form\Type
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Form\Type;

use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\EmailBundle\Form\Type\EmailOriginFromType as BaseEmailOriginFromType;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\EmailBundle\Builder\Helper\EmailModelBuilderHelper;
use Oro\Bundle\EmailBundle\Entity\Manager\MailboxManager;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper;

/**
 * Email Origin From Type.
 *
 * Form type for email origin selection.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Form\Type
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailOriginFromType extends BaseEmailOriginFromType
{
    /**
     * Constructor for EmailOriginFromType.
     *
     * @param TokenAccessorInterface    $tokenAccessor     The token accessor
     * @param EmailModelBuilderHelper   $helper            The email model builder helper
     * @param MailboxManager           $mailboxManager    The mailbox manager
     * @param ManagerRegistry          $doctrine          The doctrine registry
     * @param EmailOriginHelper        $emailOriginHelper The email origin helper
     */
    public function __construct(
        TokenAccessorInterface $tokenAccessor,
        EmailModelBuilderHelper $helper,
        MailboxManager $mailboxManager,
        ManagerRegistry $doctrine,
        EmailOriginHelper $emailOriginHelper
    ) {
        parent::__construct($tokenAccessor, $helper, $mailboxManager, $doctrine, $emailOriginHelper);
    }

    /**
     * Configure form options.
     *
     * @param OptionsResolver $resolver The options resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = $this->createChoices();
        $resolver->setDefaults(
            [
                'choices' => $choices,
                'attr' => [],
            ]
        );

        $resolver->setNormalizer(
            'attr',
            function (Options $options, $value) {
                $value['readonly'] = (count($options['choices']) === 1);
                return $value;
            }
        );
    }

    /**
     * Create choices for the form.
     *
     * @return array
     * @phpstan-ignore-next-line
     */
    private function createChoices(): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user instanceof User) {
            return [];
        }
        $origins = [];
        $origins = $this->fillUserOrigins($user, $origins);
        return $this->fillMailboxOrigins($user, $origins);
    }

    /**
     * Fill user origins.
     *
     * @param User  $user    The user
     * @param array $origins The origins array
     *
     * @return array
     * @phpstan-ignore-next-line
     */
    private function fillUserOrigins(User $user, array $origins): array
    {
        $origins = [];
        $userOrigins = $user->getEmailOrigins();
        foreach ($userOrigins as $origin) {
            if (strtolower($origin->getMailboxName()) != "local" || $origin->getMailboxName() != "local") {
                if (($origin instanceof UserEmailOrigin) && $origin->isActive()) {
                    $owner = $origin->getOwner();
                    $email = $origin->getOwner()->getEmail();
                    $this->helper->preciseFullEmailAddress($email, ClassUtils::getClass($owner), $owner->getId());
                    $origins[$email] = $origin->getId() . '|' . $origin->getOwner()->getEmail();
                }
                return $origins;
            }
        }

        return [];
    }

    /**
     * Fill mailbox origins.
     *
     * @param User  $user    The user
     * @param array $origins The origins array
     *
     * @return array
     * @phpstan-ignore-next-line
     */
    private function fillMailboxOrigins(User $user, array $origins): array
    {
        $mailboxes = $this->mailboxManager->findAvailableMailboxes($user, $this->tokenAccessor->getOrganization());
        // dd($mailboxes);
        foreach ($mailboxes as $mailbox) {
            $origin = $mailbox->getOrigin();
            /**
             * If in mailbox configuration neither of IMAP or SMTP was configured, origin will be NULL
             */
            if ($origin && $origin->isActive() && $origin->getMailboxName() !== 'Local') {
                $email = $mailbox->getEmail();
                $this->helper->preciseFullEmailAddress($email);
                $email .= ' (Mailbox)';
                $origins[$email] = $origin->getId() . '|' . $mailbox->getEmail();
            }
        }

        return $origins;
    }
}
