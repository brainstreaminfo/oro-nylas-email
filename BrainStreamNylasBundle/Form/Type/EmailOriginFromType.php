<?php

namespace BrainStream\Bundle\NylasBundle\Form\Type;

use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\EmailBundle\Form\Type\EmailOriginFromType as BaseEmailOriginFromType;

class EmailOriginFromType extends BaseEmailOriginFromType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        // $organization = $this->tokenAccessor->getOrganization();
        $choices = $this->createChoices();
        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => [],
        ]);

        $resolver->setNormalizer('attr', function (Options $options, $value) {
            $value['readonly'] = (count($options['choices']) === 1);
            return $value;
        });
    }

    protected function createChoices(): array
    {
        $user = $this->tokenAccessor->getUser();
        if (!$user instanceof User) {
            return [];
        }
        $origins = [];
        return $this->fillMailboxOrigins($user, $this->fillUserOrigins($user,$origins ));
    }

    protected function fillUserOrigins(User $user, $origins): array
    {
        $origins = [];
        $userOrigins = $user->getEmailOrigins();
        foreach ($userOrigins as $origin) {
            // dd(strtolower($origin->getMailboxName()) != "local" || $origin->getMailboxName() != "local");
            if(strtolower($origin->getMailboxName()) != "local" || $origin->getMailboxName() != "local"){
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

    protected function fillMailboxOrigins(User $user,$origins)
    {
        $mailboxes = $this->mailboxManager->findAvailableMailboxes($user, $this->tokenAccessor->getOrganization());
        // dd($mailboxes);
        foreach ($mailboxes as $mailbox) {
            $origin = $mailbox->getOrigin();
            /**
             * if in mailbox configuration neither of IMAP or SMTP was configured, origin will be NULL
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
