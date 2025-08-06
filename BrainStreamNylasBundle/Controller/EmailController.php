<?php

/**
 * Email Controller for Nylas integration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Controller
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Controller;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;
use Oro\Bundle\SecurityBundle\Attribute\AclAncestor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EmailBundle\Entity\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Oro\Bundle\EmailBundle\Controller\EmailController as BaseEmailController;
use Oro\Bundle\EmailBundle\Form\Model\Email as EmailModel;
use Oro\Bundle\EmailBundle\Builder\EmailModelBuilder;
use Psr\Log\LoggerInterface;

#[Route('/email')]
/**
 * Email Controller for handling Nylas email operations.
 *
 * Extends the base EmailController to provide Nylas-specific functionality
 * for email composition and sending.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Controller
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailController extends BaseEmailController
{
    private Security $security;
    private NylasClient $nylasClient;
    private EntityManagerInterface $doctrine;
    private NylasEmailManager $nylasEmailManager;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;

    /**
     * Constructor for EmailController.
     *
     * @param Security              $security           The security service
     * @param NylasClient           $nylasClient        The Nylas client service
     * @param ManagerRegistry       $managerRegistry    The doctrine manager registry
     * @param NylasEmailManager     $nylasEmailManager  The Nylas email manager
     * @param TranslatorInterface   $translator         The translator service
     * @param LoggerInterface       $logger             The logger service
     */
    public function __construct(
        Security $security,
        NylasClient $nylasClient,
        ManagerRegistry $managerRegistry,
        NylasEmailManager $nylasEmailManager,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->security = $security;
        $this->nylasClient = $nylasClient;
        $this->doctrine = $managerRegistry->getManager();
        $this->nylasEmailManager = $nylasEmailManager;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * Set the container for this controller.
     *
     * @param ContainerInterface $container The container
     *
     * @return ContainerInterface|null
     */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $originalContainer = $container;

        // create the proxy container with service mappings
        $this->container = new class($originalContainer) implements ContainerInterface {
            private $container;

            public function __construct(ContainerInterface $container)
            {
                $this->container = $container;
            }

            public function get(
                string $id,
                int $invalidBehavior = SymfonyContainerInterface::EXCEPTION_ON_INVALID_REFERENCE
            ): mixed {
                $serviceMap = [
                    'Oro\Bundle\EmailBundle\Entity\Manager\EmailManager' => 'oro_email.email.manager',
                    'Oro\Bundle\EmailBundle\Entity\Provider\EmailThreadProvider' => 'oro_email.email.thread.provider',
                    'Oro\Bundle\EmailBundle\Entity\Manager\MailboxManager' => 'oro_email.mailbox.manager',
                    'Oro\Bundle\EmailBundle\Cache\EmailCacheManager' => 'oro_email.email.cache.manager',
                    'Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper' => 'oro_email.email.routing.helper',
                    'oro_email.email.routing.helper' => 'oro_entity.routing_helper',
                    'Oro\Bundle\EmailBundle\Builder\EmailModelBuilder' => 'oro_email.email.model.builder',
                    'Oro\Bundle\EmailBundle\Form\Handler\EmailHandler' => 'oro_email.form.handler.email',
                    'Oro\Bundle\EmailBundle\Provider\EmailRecipientsProvider'=> 'oro_email.email_recipients.provider',
                    'Oro\Bundle\EmailBundle\Provider\EmailRecipientsHelper' => 'oro_email.provider.email_recipients.helper',
                    'translator' => 'translator',
                    'Symfony\Contracts\Translation\TranslatorInterface' => 'translator',
                    'Oro\Bundle\AttachmentBundle\Manager\FileManager' => 'oro_attachment.file_manager',
                ];

                $serviceId = $serviceMap[$id] ?? $id;
                return $this->container->get($serviceId, $invalidBehavior);
            }

            public function has(string $id): bool
            {
                return $this->container->has($id);
            }

            public function set(string $id, ?object $service): void
            {
                $this->container->set($id, $service);
            }

            public function initialized(string $id): bool
            {
                return true; // Always return true since we don't need this
            }
        };

        return parent::setContainer($this->container);
    }

    /**
     * Create a new email.
     *
     * @return array|Response
     */
    #[Route(
        path: '/create',
        name: 'oro_email_email_create',
        condition: "request !== null && request.get('_widgetContainer')"
    )]
    #[Template('@OroEmail/Email/update.html.twig')]
    #[AclAncestor('oro_email_email_create')]
    public function createAction()
    {
        $emailModel = $this->container->get('Oro\Bundle\EmailBundle\Builder\EmailModelBuilder')->createEmailModel();

        // Check if user has Nylas email origin configured
        $user = $this->security->getUser();
        $repository = $this->doctrine->getRepository(NylasEmailOrigin::class);
        $nylasOrigin = $repository->findOneBy(['owner' => $user]);

        // Use parent's process method - NylasEmailProcessor will handle sending
        return parent::process($emailModel);
    }

    /**
     * Override reply action to use Nylas when available.
     *
     * @param Email $email The email to reply to
     *
     * @return array|Response
     */
    #[Route(
        path: '/reply/{id}',
        name: 'oro_email_email_reply',
        requirements: ['id' => '\d+'],
        condition: "request !== null && request.get('_widgetContainer')"
    )]
    #[Template('@OroEmail/Email/update.html.twig')]
    #[AclAncestor('oro_email_email_create')]
    public function replyAction(Email $email)
    {
        // Use OroCRM's base EmailModelBuilder to create the reply model
        $emailModelBuilder = $this->container->get('Oro\Bundle\EmailBundle\Builder\EmailModelBuilder');
        $emailModel = $emailModelBuilder->createReplyEmailModel($email);

        // Check for Nylas origin
        $user = $this->security->getUser();
        $repository = $this->doctrine->getRepository(NylasEmailOrigin::class);
        $nylasOrigin = $repository->findOneBy(['owner' => $user]);

        return parent::process($emailModel);
    }

    /**
     * Override reply all action to use Nylas when available.
     *
     * @param Email $email The email to reply all to
     *
     * @return array|Response
     */
    #[Route(
        path: '/replyall/{id}',
        name: 'oro_email_email_reply_all',
        requirements: ['id' => '\d+'],
        condition: "request !== null && request.get('_widgetContainer')"
    )]
    #[Template('@OroEmail/Email/update.html.twig')]
    #[AclAncestor('oro_email_email_create')]
    public function replyAllAction(Email $email)
    {
        // Use OroCRM's base EmailModelBuilder to create the reply all model
        $emailModelBuilder = $this->container->get('Oro\Bundle\EmailBundle\Builder\EmailModelBuilder');
        $emailModel = $emailModelBuilder->createReplyAllEmailModel($email);

        // Check for Nylas origin
        $user = $this->security->getUser();
        $repository = $this->doctrine->getRepository(NylasEmailOrigin::class);
        $nylasOrigin = $repository->findOneBy(['owner' => $user]);

        return parent::process($emailModel);
    }

    /**
     * Override forward action to use Nylas when available.
     *
     * @param Email $email The email to forward
     *
     * @return array|Response
     */
    #[Route(
        path: '/forward/{id}',
        name: 'oro_email_email_forward',
        requirements: ['id' => '\d+']
    )]
    #[Template('@OroEmail/Email/update.html.twig')]
    #[AclAncestor('oro_email_email_create')]
    public function forwardAction(Email $email)
    {
        // Test log to see if our controller is being called 'email_id' => $email->getId()
        // Use OroCRM's base EmailModelBuilder to create the forward model
        $emailModelBuilder = $this->container->get('Oro\Bundle\EmailBundle\Builder\EmailModelBuilder');
        $emailModel = $emailModelBuilder->createForwardEmailModel($email);

        // Debug: Log the EmailModel after creation
        $this->logger->info('EmailModel after createForwardEmailModel', [
            'subject' => $emailModel->getSubject(),
            'body' => substr($emailModel->getBody(), 0, 100),
            'bodyFooter' => substr($emailModel->getBodyFooter(), 0, 100)
        ]);

        // Check for Nylas origin
        $user = $this->security->getUser();
        $repository = $this->doctrine->getRepository(NylasEmailOrigin::class);
        $nylasOrigin = $repository->findOneBy(['owner' => $user]);

        return parent::process($emailModel);
    }

    /**
     * View nylas email, load nylas email body.
     *
     * @param Email $entity The email entity to view
     *
     * @return array|JsonResponse
     */
    #[Route(path: '/view/user-thread/{id}', name: 'oro_email_user_thread_view', requirements: ['id' => '\d+'])]
    #[Template('@BrainStreamNylas/Email/Thread/userEmails.html.twig')]
    #[AclAncestor('oro_email_email_view')]
    public function viewUserThreadAction(Email $entity)
    {
        try {
            $res = $this->doctrine->getRepository(NylasEmailOrigin::class)->loadEmailBody(
                $entity,
                $this->security->getUser(),
                $this->nylasClient,
                $this->container->get('BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager'),
                $this->container->get('event_dispatcher')
            );

            if (!$res) {
                return new JsonResponse(
                    ['error' => $this->translator->trans('email.origin.error.not_configurated')],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // set value of seen status
        $this->container->get('oro_email.email.manager')->setSeenStatus($entity, true);
        return ['entity' => $entity];
    }

    /**
     * Display user emails.
     *
     * @return array
     */
    #[Route(path: '/user-emails', name: 'oro_email_user_emails')]
    #[Template('@BrainStreamNylas/Email/userEmails.html.twig')]
    public function userEmailsAction()
    {
        return [];
    }
}
