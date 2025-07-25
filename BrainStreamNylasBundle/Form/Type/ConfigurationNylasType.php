<?php


namespace BrainStream\Bundle\NylasBundle\Form\Type;

use Oro\Bundle\EmailBundle\Form\Type\EmailFolderTreeType;
use Oro\Bundle\ImapBundle\Mail\Storage\GmailImap;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
//use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ImapBundle\Form\EventListener\ApplySyncSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\OriginFolderSubscriber;
use Oro\Bundle\ImapBundle\Form\EventListener\DecodeFolderSubscriber;
//use Oro\Bundle\SecurityBundle\SecurityFacade;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigurationNylasType extends AbstractType
{
    const NAME = 'oro_configuration_nylas';

    /** @var TranslatorInterface */
    protected $translator;

    /** ConfigManager */
    protected $userConfigManager;

    /** @var SecurityFacade */
    protected $securityFacade;

    /**
     * @param TranslatorInterface $translator
     * @param ConfigManager $userConfigManager
     * @param SecurityFacade $securityFacade
     */
    public function __construct(
        TranslatorInterface $translator,
        ConfigManager $userConfigManager,
        //SecurityFacade $securityFacade
    ) {
        $this->translator = $translator;
        $this->userConfigManager = $userConfigManager;
        //$this->securityFacade = $securityFacade;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //$builder->addEventSubscriber(new DecodeFolderSubscriber());
        //$this->addOwnerOrganizationEventListener($builder);
        //$this->addNewOriginCreateEventListener($builder);

        $builder
            ->add('check', ButtonType::class, [
                'label' => $this->translator->trans('oro.imap.configuration.connect'),
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->add('accessToken', HiddenType::class, [
                'required' => true
            ])
            //->add('provider', HiddenType::class, [
            //    'required' => true
            //])
            ->add('accountId', HiddenType::class, [
                'required' => true
            ])
            ->add('user', HiddenType::class, [
                'required' => true,
            ])
            ->add('tokenType', HiddenType::class, [
                'required' => true
            ])
            ->add('imapHost', HiddenType::class, [
                'required' => true,
                'data' => GmailImap::DEFAULT_GMAIL_HOST
            ])
            ->add('imapPort', HiddenType::class, [
                'required' => true,
                'data' => GmailImap::DEFAULT_GMAIL_PORT
            ])
            ->add('user', HiddenType::class, [
                'required' => true,
            ])
            ->add('imapEncryption', HiddenType::class, [
                'required' => true,
                'data' => GmailImap::DEFAULT_GMAIL_SSL
            ])
            ->add('clientId', HiddenType::class, [
                'data' => $this->userConfigManager->get('oro_google_integration.client_id')
            ])
            ->add('smtpHost', HiddenType::class, [
                'required' => false,
                'data' => GmailImap::DEFAULT_GMAIL_SMTP_HOST
            ])
            ->add('smtpPort', HiddenType::class, [
                'required' => false,
                'data' => GmailImap::DEFAULT_GMAIL_SMTP_PORT
            ])
            ->add('smtpEncryption', HiddenType::class, [
                'required' => false,
                'data' => GmailImap::DEFAULT_GMAIL_SMTP_SSL
            ]);
        $builder->add('folders', EmailFolderTreeType::class, [
            'label' => $this->translator->trans('oro.email.folders.label'),
            'attr' => ['class' => 'folder-tree'],
            'tooltip' => 'If a folder is uncheked, all the data saved in it will be deleted',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NylasEmailOrigin::class,
            'allow_extra_fields' => true
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addOwnerOrganizationEventListener(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var UserEmailOrigin $data */
                $data = $event->getData();
                if ($data !== null) {
                    if (($data->getOwner() === null) && ($data->getMailbox() === null)) {
                        $data->setOwner($this->securityFacade->getLoggedUser());
                    }
                    if ($data->getOrganization() === null) {
                        $organization = $this->securityFacade->getOrganization()
                            ? $this->securityFacade->getOrganization()
                            : $this->securityFacade->getLoggedUser()->getOrganization();
                        $data->setOrganization($organization);
                    }

                    $event->setData($data);
                }
            }
        );
    }

    /**
     * @param FormBuilderInterface $builder
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function addNewOriginCreateEventListener(FormBuilderInterface $builder)
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
                    if (
                        $entity instanceof UserEmailOrigin
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
