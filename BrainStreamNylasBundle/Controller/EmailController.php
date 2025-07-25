<?php

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
use Oro\Bundle\EmailBundle\Entity\Manager\EmailManager;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailThreadProvider;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EmailBundle\Entity\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Oro\Bundle\EmailBundle\Controller\EmailController as BaseEmailController;

#[Route('/email')]
class EmailController extends BaseEmailController
{
    private Security $security;
    private NylasClient $nylasClient;
    private $doctrine;
    private NylasEmailManager $nylasEmailManager;
    private TranslatorInterface $translator;

    public function __construct(
        Security $security,
        NylasClient $nylasClient,
        ManagerRegistry $managerRegistry,
        NylasEmailManager $nylasEmailManager,
        TranslatorInterface $translator
    ) {
        $this->security = $security;
        $this->nylasClient = $nylasClient;
        $this->doctrine = $managerRegistry->getManager();
        $this->nylasEmailManager = $nylasEmailManager;
        $this->translator = $translator;
    }

   /* #[Route('/nylas/custom', name: 'nylas_test')]
    public function testCustomAction(): Response
    {
        return new Response('Nylas Test Route Works!');
    }*/

    /*#[Route('/nylas/acl', name: 'brainstream_test_acl', methods: ['GET'])]
    public function testAclAction(): JsonResponse
    {
        $granted = $this->isGranted('oro_email_emailtemplate_index');
        return new JsonResponse([
            'user' => $this->getUser()->getUsername(),
            'roles' => $this->getUser()->getRoles(),
            'granted' => $granted,
        ]);
    }*/

   /* public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    public function setNylasClient(NylasClient $nylasClient): void
    {
        $this->nylasClient = $nylasClient;
    }*/

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
                    // add more mappings as needed
                ];

                if (isset($serviceMap[$id])) {
                    return $this->container->get($serviceMap[$id]);
                }
                return $this->container->get($id, $invalidBehavior);
            }

            public function has(string $id): bool
            {
                $serviceMap = [
                    EmailManager::class => 'oro_email.email.manager',
                    EmailThreadProvider::class => 'oro_email.email.thread.provider',
                ];
                return isset($serviceMap[$id]) || $this->container->has($id);
            }

            public function set(string $id, ?object $service): void
            {
                $this->container->set($id, $service);
            }

            public function initialized(string $id): bool
            {
                return $this->container->initialized($id);
            }
        };

        parent::setContainer($this->container);
        return $this->container;
    }


    /**
     * view nylas email, load nylas email body
     *
     * @param Email $entity
     * @return Email[]|JsonResponse
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[Route(path: '/view/user-thread/{id}', name: 'oro_email_user_thread_view', requirements: ['id' => '\d+'])]
    #[Template('@BrainStreamNylas/Email/Thread/userEmails.html.twig')]
    #[AclAncestor('oro_email_email_view')]
    public function viewUserThreadAction(Email $entity)
    {
        //EmailController::getAction(Email $entity) old code
        try {
            $res = $this->doctrine->getRepository(NylasEmailOrigin::class)->loadEmailBody(
                $entity,
                $this->security->getUser(),
                $this->nylasClient,
                $this->container->get('BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager'),
                $this->container->get('event_dispatcher'));

            if (!$res) {
                return new JsonResponse(
                    ['error' => $this->translator->trans('email.origin.error.not_configurated')],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (\Exception $e) {
            //throw new \Exception('Error : ' . $e->getMessage());
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        // set value of seen status
        $this->container->get('oro_email.email.manager')->setSeenStatus($entity, true);
        return ['entity' => $entity];
    }

    #[Route(path: '/user-emails', name: 'oro_email_user_emails')]
    #[Template('@BrainStreamNylas/Email/userEmails.html.twig')]
    public function userEmailsAction()
    {
        return [];
    }



    /*public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $this->container = $container;
        parent::setContainer($container);
        return $container;
        //$this->security = $container->get('security.helper');
        //$user = $this->security->getUser();
    }*/

}

