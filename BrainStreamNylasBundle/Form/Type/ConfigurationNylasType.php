<?php

/**
 * Configuration Nylas Type.
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

use Oro\Bundle\EmailBundle\Form\Type\EmailFolderTreeType;
use Oro\Bundle\ImapBundle\Mail\Storage\GmailImap;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ImapBundle\Form\EventListener\ApplySyncSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\OriginFolderSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\DecodeFolderSubscriber;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * Configuration Nylas Type.
 *
 * Form type for Nylas configuration.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Form\Type
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigurationNylasType extends AbstractType
{
    public const NAME = 'oro_configuration_nylas';

    protected TranslatorInterface $translator;

    protected ConfigManager $userConfigManager;

    protected TokenAccessorInterface $tokenAccessor;

    /**
     * Constructor for ConfigurationNylasType.
     *
     * @param TranslatorInterface        $translator        The translator service
     * @param ConfigManager             $userConfigManager The user config manager
     * @param TokenAccessorInterface    $tokenAccessor     The token accessor
     */
    public function __construct(
        TranslatorInterface $translator,
        ConfigManager $userConfigManager,
        TokenAccessorInterface $tokenAccessor
    ) {
        $this->translator = $translator;
        $this->userConfigManager = $userConfigManager;
        $this->tokenAccessor = $tokenAccessor;
    }

    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The form options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'check',
                ButtonType::class,
                [
                    'label' => $this->translator->trans('oro.imap.configuration.connect'),
                    'attr' => ['class' => 'btn btn-primary']
                ]
            )
            ->add(
                'accessToken',
                HiddenType::class,
                [
                    'required' => true
                ]
            )
            ->add(
                'accountId',
                HiddenType::class,
                [
                    'required' => true
                ]
            )
            ->add(
                'user',
                HiddenType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'tokenType',
                HiddenType::class,
                [
                    'required' => true
                ]
            )
            ->add(
                'imapHost',
                HiddenType::class,
                [
                    'required' => true,
                    'data' => GmailImap::DEFAULT_GMAIL_HOST
                ]
            )
            ->add(
                'imapPort',
                HiddenType::class,
                [
                    'required' => true,
                    'data' => GmailImap::DEFAULT_GMAIL_PORT
                ]
            )
            ->add(
                'imapEncryption',
                HiddenType::class,
                [
                    'required' => true,
                    'data' => GmailImap::DEFAULT_GMAIL_SSL
                ]
            )
            ->add(
                'clientId',
                HiddenType::class,
                [
                    'data' => $this->userConfigManager->get('oro_google_integration.client_id')
                ]
            )
            ->add(
                'smtpHost',
                HiddenType::class,
                [
                    'required' => false,
                    'data' => GmailImap::DEFAULT_GMAIL_SMTP_HOST
                ]
            )
            ->add(
                'smtpPort',
                HiddenType::class,
                [
                    'required' => false,
                    'data' => GmailImap::DEFAULT_GMAIL_SMTP_PORT
                ]
            )
            ->add(
                'smtpEncryption',
                HiddenType::class,
                [
                    'required' => false,
                    'data' => GmailImap::DEFAULT_GMAIL_SMTP_SSL
                ]
            );
        $builder->add(
            'folders',
            EmailFolderTreeType::class,
            [
                'label' => $this->translator->trans('oro.email.folders.label'),
                'attr' => ['class' => 'folder-tree'],
                'tooltip' => 'If a folder is uncheked, all the data saved in it will be deleted',
            ]
        );
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
        $resolver->setDefaults(
            [
                'data_class' => NylasEmailOrigin::class,
                'allow_extra_fields' => true
            ]
        );
    }

    /**
     * Get the form name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->getBlockPrefix();
    }

    /**
     * Get the block prefix.
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return self::NAME;
    }

    /**
     * Add owner organization event listener.
     *
     * @param FormBuilderInterface $builder The form builder
     *
     * @return void
     */
    protected function addOwnerOrganizationEventListener(FormBuilderInterface $builder): void
    {
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var UserEmailOrigin $data */
                $data = $event->getData();
                if ($data !== null) {
                    if (($data->getOwner() === null) && ($data->getMailbox() === null)) {
                        $data->setOwner($this->tokenAccessor->getUser());
                    }
                    if ($data->getOrganization() === null) {
                        $organization = $this->tokenAccessor->getOrganization()
                            ? $this->tokenAccessor->getOrganization()
                            : $this->tokenAccessor->getUser()->getOrganization();
                        $data->setOrganization($organization);
                    }

                    $event->setData($data);
                }
            }
        );
    }

    /**
     * Add new origin create event listener.
     *
     * @param FormBuilderInterface $builder The form builder
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function addNewOriginCreateEventListener(FormBuilderInterface $builder): void
    {
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = (array)$event->getData();
                /** @var UserEmailOrigin|null $entity */
                $entity = $event->getForm()->getData();
                $filtered = array_filter(
                    $data,
                    function ($item) {
                        return !empty($item);
                    }
                );
                if (count($filtered) > 0) {
                    if ($entity instanceof UserEmailOrigin
                        && $entity->getImapHost() !== null
                        && array_key_exists('imapHost', $data) && $data['imapHost'] !== null
                        && array_key_exists('user', $data) && $data['user'] !== null
                        && ($entity->getImapHost() !== $data['imapHost']
                            || $entity->getUser() !== $data['user'])
                    ) {
                        $newConfiguration = new NylasEmailOrigin();
                        $event->getForm()->setData($newConfiguration);
                    }
                } elseif ($entity instanceof UserEmailOrigin) {
                    $event->getForm()->setData(null);
                }
            },
            3
        );
    }
}
