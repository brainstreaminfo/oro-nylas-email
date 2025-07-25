<?php

namespace BrainStream\Bundle\NylasBundle\Service;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Form\Model\NylasAccountTypeModel;
use BrainStream\Bundle\NylasBundle\Form\Type\ConfigurationNylasType;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailFolderManager;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\ImapBundle\Mail\Storage\GmailImap;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class NylasApiService
{
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;
    private NylasClient $nylasClient;
    private ConfigService $configService;
    private LoggerInterface $logger;

    private FormFactoryInterface $formFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        NylasClient $nylasClient,
        ConfigService $configService,
        LoggerInterface $logger,
        FormFactoryInterface $formFactory
    ) {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->nylasClient = $nylasClient;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->formFactory = $formFactory;
    }
    public function saveNylasToken(int $userId, array $tokenData): NylasEmailOrigin
    {
        // fetch or create email origin
        $countEmailOrigin = $this->entityManager->getRepository(NylasEmailOrigin::class)
            ->count(['owner' => $userId]);

        $emailOrigin = $this->entityManager->getRepository(NylasEmailOrigin::class)
            ->findOneBy(['owner' => $userId, 'mailboxName' => $tokenData['email']]);

        if (!$emailOrigin) {
            $user = $this->entityManager->getRepository(User::class)->findOneById($userId);

            if (!$user) {
                $this->logger->error('User not found for userId', ['userId' => $userId]);
                throw new \RuntimeException('Cannot create NylasEmailOrigin: User not found');
            }

            $emailOrigin = new NylasEmailOrigin();
            $emailOrigin->setOwner($user);
            $emailOrigin->setMailboxName($tokenData['email']);
            $emailOrigin->setUser($tokenData['email']);
            $emailOrigin->setIsDefault($countEmailOrigin ? false : true);
            $emailOrigin->setAccountType(NylasAccountTypeModel::ACCOUNT_TYPE_NYLAS);

            // Set organization
            $organization = $user->getOrganization(); // Or fetch from security context
            if (!$organization) {
                $organization = $this->entityManager->getRepository(Organization::class)->findOneBy([]); // Fallback
            }
            if (!$organization) {
                $this->logger->error('No organization found for user', ['userId' => $userId]);
                throw new \RuntimeException('Cannot create NylasEmailOrigin: No organization');
            }
            $emailOrigin->setOrganization($organization);

        }

        // Ensure owner is always set for existing origins
        if ($emailOrigin->getOwner() === null) {
            $user = $this->entityManager->getRepository(User::class)->findOneById($userId);
            if (!$user) {
                $this->logger->error('User not found for userId', ['userId' => $userId]);
                throw new \RuntimeException('Cannot update NylasEmailOrigin: User not found');
            }
            $emailOrigin->setOwner($user);
        }

        $emailOrigin->setName('nylasemailorigin');
        $emailOrigin->setProvider($tokenData['provider']);
        $emailOrigin->setTokenType($tokenData['token_type']);
        $emailOrigin->setAccountId($tokenData['grant_id']);
        $emailOrigin->setAccessToken($tokenData['access_token']);
        //$emailOrigin->setExpiresAt((new \DateTime())->modify("+{$expiresIn} seconds"));
        //$this->entityManager->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());
        $this->entityManager->persist($emailOrigin);
        $this->entityManager->flush();

        // Explicitly set as default if it's the only or newly added account
        if ($countEmailOrigin === 0 || !$this->entityManager->getRepository(NylasEmailOrigin::class)->findOneBy(['owner' => $userId, 'isDefault' => true])) {
            $this->setDefaultAccount($emailOrigin->getId());
        }

        return $emailOrigin;
    }

    public function getAccountInfo(int $userId): array
    {
        $response = [];
        $response['origins'] = [];

        $user = $this->entityManager->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            $this->logger->error('User not found', ['userId' => $userId]);
            throw new NotFoundHttpException($this->translator->trans('User not found'));
        }
        $response['isMultipleEmail'] = $user->getIsMultipleEmail();

        $emailOrigins = $this->entityManager->getRepository(NylasEmailOrigin::class)
            ->findBy(['owner' => $userId]);

        if ($emailOrigins) {
            foreach ($emailOrigins as $emailOrigin) {
                $response['origins'][] = [
                    'id' => $emailOrigin->getId(),
                    'accountId' => $emailOrigin->getAccountId(),
                    'provider' => $emailOrigin->getProvider(),
                    'tokenType' => $emailOrigin->getTokenType(),
                    'email' => $emailOrigin->getMailboxName(),
                    'isDefault' => $emailOrigin->getIsDefault(),
                    'activeStatus' => $emailOrigin->isActive(),
                ];
            }
        }
        return $response;
    }

    public function saveEmailFolders(array $selectedFolders, int $originId): bool
    {
        try {
            // Validate origin
            $origin = $this->entityManager->getRepository(NylasEmailOrigin::class)->find($originId);
            if (!$origin) {
                $this->logger->error('NylasEmailOrigin not found', ['origin_id' => $originId]);
                throw new NotFoundHttpException($this->translator->trans('Email origin not found'));
            }

            $owner = $origin->getOwner();
            if (!$owner || !$owner->getId()) {
                // Explicitly load User by owner_id from database Query owner_id directly from oro_email_origin
                $connection = $this->entityManager->getConnection();
                $stmt = $connection->executeQuery(
                    'SELECT owner_id FROM oro_email_origin WHERE id = :originId',
                    ['originId' => $originId]
                );
                $ownerId = $stmt->fetchOne();
                $owner = $this->entityManager->getRepository(User::class)->find($ownerId);
                if ($owner) {
                    $origin->setOwner($owner);
                    $this->entityManager->persist($origin);
                    $this->logger->info('Loaded User',
                        ['owner_id' => $owner->getId(), 'username' => $owner->getUsername()]);
                } else {
                    $this->logger->error('User not found for owner_id',
                        ['owner_id' => $ownerId, 'origin_id' => $originId]);
                    throw new \RuntimeException('Cannot save EmailFolders: User not found');
                }
            }
            $stmt = $connection->executeQuery(
                'SELECT id, folder_uid FROM oro_email_folder WHERE origin_id = :originId',
                ['originId' => $originId]
            );
            $allFolders = $stmt->fetchAllAssociative();

            // Update sync status for each folder
            foreach ($allFolders as $folder) {
                $folderEntity = $this->entityManager->getRepository(EmailFolder::class)->find($folder['id']);
                if ($folderEntity && $folderEntity->getParentFolder() === null) {
                    $folderUid = $folder['folder_uid'];
                    $syncEnabled = in_array($folderUid, $selectedFolders, true);
                    $folderEntity->setSyncEnabled($syncEnabled);
                    $this->entityManager->persist($folderEntity);
                }
            }

            $this->logger->info('Flushing EntityManager for EmailFolders', ['origin_id' => $originId]);
            $this->entityManager->flush();
            $this->logger->info('Database flush saveEmailFoldersAction() completed');

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to save email folders', [
                'origin_id' => $originId,
                'selected_folders' => $selectedFolders,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Old Nylacheck action
     * @param $params
     * @return array
     * @throws \Exception
     */
    public function getEmailFolders($params): array
    {
        $byPassOutdatedAt = false; // if multiplemail is false and same origin(mailbox name) reconnect then used
        $userId = $params['userId'];
        $email = $params['email'];
        // validate user and email
        if (empty($userId) || empty($email)) {
            throw new BadRequestHttpException($this->translator->trans('email.origin.error.invalid_input'));
        }

        // Fetch user and email origin
        $user = $this->entityManager->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw new BadRequestHttpException($this->translator->trans('email.origin.error.user_not_found'));
        }

        $origin = $this->entityManager->getRepository(NylasEmailOrigin::class)->findOneBy(['owner' => $userId]);
        if ($origin && $email === $origin->getMailboxName()) {
            $byPassOutdatedAt = true;
        }

        // Validate multiple email setting
        if (!$origin && !$user->getIsMultipleEmail() && $user->getUsername() !== $email) {
            throw new BadRequestHttpException($this->translator->trans('email.origin.error.defaultEmail'));
        }

        $data = $this->entityManager->getRepository(NylasEmailOrigin::class)->findOneBy([
            'owner' => $userId,
            'mailboxName' => $email
        ]);
        $form = $this->formFactory->create(ConfigurationNylasType::class, null, ['csrf_protection' => false]);
        $form->setData($data);
        $form->submit([
            'accessToken' => $this->configService->getClientSecret(),
            'accountId' => $params['accountId'],
        ], false);

        if (!$form->isValid()) {
            throw new \Exception("Incorrect setting for IMAP authentication");
        }

        /** @var NylasEmailOrigin $origin */
        $origin = $form->getData();
        $origin->setImapHost(GmailImap::DEFAULT_GMAIL_HOST);
        $origin->setImapPort(GmailImap::DEFAULT_GMAIL_PORT);
        $origin->setImapEncryption(GmailImap::DEFAULT_GMAIL_SSL);
        $origin->setSmtpHost(GmailImap::DEFAULT_GMAIL_SMTP_HOST);
        $origin->setSmtpPort(GmailImap::DEFAULT_GMAIL_SMTP_PORT);
        $origin->setSmtpEncryption(GmailImap::DEFAULT_GMAIL_SMTP_SSL);

        //Fetch email folders
        /** @var \BrainStream\Bundle\NylasBundle\Service\NylasClient $nylasClient */
        $nylasClient = $this->nylasClient;
        $nylasClient->setEmailOrigin($origin);

        $manager = new NylasEmailFolderManager($nylasClient, $this->entityManager, $origin);
        $emailFolders = $manager->getFolders($byPassOutdatedAt);

        if (count($emailFolders) === 0) {
            throw new BadRequestHttpException($this->translator->trans('email.origin.error.configuration'));
        }
        $origin->setFolders($emailFolders);

        $accountTypeModel = $nylasClient->createAccountModel(
            NylasAccountTypeModel::ACCOUNT_TYPE_NYLAS,
            $origin
        );
        try {
            $form2 = $nylasClient->prepareForm($accountTypeModel);

            // Safely get the form data with null checks
            $imapAccountType = $form2->has('imapAccountType') ? $form2->get('imapAccountType') : null;
            if (!$imapAccountType) {
                $this->logger->error('Could not find imapAccountType in form', ['form_fields' => array_keys($form2->all())]);
                throw new \RuntimeException('Email configuration form is not properly initialized');
            }

            $accountTypeData = $imapAccountType->getData();
            if (!$accountTypeData) {
                $this->logger->error('No data found for imapAccountType');
                throw new \RuntimeException('Could not load email account configuration');
            }

            $userEmailOrigin = method_exists($accountTypeData, 'getUserEmailOrigin') ? $accountTypeData->getUserEmailOrigin() : null;
            if (!$userEmailOrigin) {
                $this->logger->warning('Could not get userEmailOrigin from form data, using existing folders', [
                    'email' => $email,
                    'originId' => $origin ? $origin->getId() : null,
                    'accountTypeData' => get_class($accountTypeData)
                ]);
            } else {
                // If we have userEmailOrigin, get folders from it
                $formFolders = $userEmailOrigin->getFolders();
                $this->logger->debug('Retrieved folders from userEmailOrigin', [
                    'folderCount' => is_countable($formFolders) ? count($formFolders) : 0
                ]);
            }

            // Process folders using the folder collection
            $folderData = [];
            $folders = $nylasClient->getFolderCollection($byPassOutdatedAt, $emailFolders, $folderData);

            $this->logger->debug('Processed folders for display', [
                'folderCount' => is_countable($folders) ? count($folders) : 0,
                'email' => $email
            ]);

            return [
                'folders' => $folders ?: [],
                'mailboxName' => $email,
                'success' => 1
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error processing email folders', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty folder list on error to prevent breaking the UI
            return [
                'folders' => [],
                'mailboxName' => $email,
                'success' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getDefaultAccountInfo(int $userId): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            $this->logger->error('User not found', ['userId' => $userId]);
            throw new NotFoundHttpException($this->translator->trans('User not found'));
        }

        $emailOrigin = $this->entityManager->getRepository(NylasEmailOrigin::class)
            ->findOneBy(['owner' => $userId, 'isDefault' => true]);

        return $emailOrigin ? [
            'isMultipleEmail' => $user->getIsMultipleEmail(),
            'id' => $emailOrigin->getId(),
            'accountId' => $emailOrigin->getAccountId(),
            'provider' => $emailOrigin->getProvider(),
            'tokenType' => $emailOrigin->getTokenType(),
            'email' => $emailOrigin->getMailboxName(),
        ] : [];
    }

    public function setDefaultAccount(int $originId): int
    {
        if ($originId <= 0) {
            throw new BadRequestHttpException('Invalid ID provided');
        }

        $emailOrigin = $this->entityManager->getRepository(EmailOrigin::class)->find($originId);
        if (!$emailOrigin) {
            throw new NotFoundHttpException('Email origin not found');
        }

        if($emailOrigin->isActive() === false){
            return 0;
        }

        $allOrigins = $this->entityManager->getRepository(NylasEmailOrigin::class)->findAll();
        foreach ($allOrigins as $origin) {
            $origin->setIsDefault(false);
        }
        $emailOrigin->setIsDefault(true);
        $this->entityManager->flush();
        return 1;
    }

    public function setAccountStatus(int $originId, string $status): void
    {
        if ($originId <= 0) {
            throw new BadRequestHttpException('Invalid ID provided');
        }

        $emailOrigin = $this->entityManager->getRepository(NylasEmailOrigin::class)->find($originId);
        if (!$emailOrigin) {
            throw new NotFoundHttpException('Email origin not found');
        }
        // Validate status
        $default = $emailOrigin->getIsDefault();

        // If active is true, we can set it as default
        if ($status === "true") {
            // Set all other origins to inactive
            $emailOrigin->setActive(true);
        } else {
            $emailOrigin->setActive(false);
            $emailOrigin->setIsDefault(false);
        }
        // $allOrigins = $this->entityManager->getRepository(NylasEmailOrigin::class)->findAll();
        // foreach ($allOrigins as $origin) {
        //     $origin->setIsDefault(false);
        // }
        $this->entityManager->flush();
    }
}
