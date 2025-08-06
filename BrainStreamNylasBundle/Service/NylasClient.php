<?php

/**
 * Nylas Client.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Service;

use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Form\FormInterface;
use Oro\Bundle\EmailBundle\Model\FolderType;
//use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\FormFactoryInterface;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use Oro\Bundle\ImapBundle\Form\Model\AccountTypeModel;
use Oro\Bundle\ImapBundle\Form\Model\EmailFolderModel;
use Oro\Bundle\UserBundle\Form\Type\EmailSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Nylas Client.
 *
 * Service for managing Nylas API client operations and email folder management.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasClient
{
    /**
     * Nylas NylasApiClient Instance.
     */
    public NylasApiClient $nylasClient;

    /**
     * Form factory interface.
     */
    private FormFactoryInterface $formFactory;

    /**
     * Email origin.
     */
    private NylasEmailOrigin $emailOrigin;

    /**
     * Download path.
     */
    public string $downloadPath;

    /**
     * Possible junk folder name map.
     */
    protected array $possibleJunkFolderNameMap = [
        'Junk',
        'Spam',
        'E-mail de Lixo'
    ];

    /**
     * Flag type map.
     */
    protected array $flagTypeMap = [
        FolderType::INBOX,
        FolderType::SENT,
        //FolderType::DRAFTS,
        FolderType::TRASH,
        FolderType::SPAM,
    ];

    /**
     * Entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * Logger.
     */
    private LoggerInterface $logger;

    /**
     * NylasClient constructor.
     *
     * @param bool                   $debug_mode             The debug mode
     * @param string                 $log_dir                The log directory
     * @param ConfigService          $configService          The config service
     * @param FormFactoryInterface   $formFactory            The form factory
     * @param string                 $attachmentDownloadPath The attachment download path
     * @param EntityManagerInterface $entityManager          The entity manager
     * @param LoggerInterface        $logger                 The logger
     */
    public function __construct(
        bool $debug_mode,
        string $log_dir,
        ConfigService $configService,
        FormFactoryInterface $formFactory,
        string $attachmentDownloadPath,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $options = [
            'debug' => $debug_mode,
            'log_file' => $log_dir . '/nylas.log',
            'client_id' => $configService->getClientId(),
            'region' => strtolower($configService->getRegion()),
            'api_key' => $configService->getClientSecret() //same as client_secret, name changed to api_key in new nylas api
        ];

        $this->nylasClient = new NylasApiClient($options);
        $this->formFactory = $formFactory;
        //Create directory if not exist
        if (!is_dir($attachmentDownloadPath)) {
            mkdir($attachmentDownloadPath, 0777, true);
        }
        $this->downloadPath = $attachmentDownloadPath;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Set email origin.
     *
     * @param EmailOrigin $emailOrigin The email origin
     *
     * @return void
     */
    public function setEmailOrigin(EmailOrigin $emailOrigin): void
    {
        $this->emailOrigin = $emailOrigin;
        $this->nylasClient->Options->setGrantId($emailOrigin->getAccountId());
    }

    /**
     * Get folder list based on organization unit.
     *
     * @param int $nylasFolderCounter The folder counter
     *
     * @return array|null The folders
     */
    public function getFolders(int $nylasFolderCounter): ?array
    {
        $counter = 0;
        //Fetch account details
        $organizationUnit = 'folder';
        do {
            $formFolders = $this->getAccountFolders($organizationUnit);
            $counter++;
        } while (count($formFolders) == 0 && $counter < $nylasFolderCounter);

        $formFolders = $formFolders['data'];
        $formFolders = $this->assignParents(array_reverse($formFolders));
        $formFolders = $this->parseTree($formFolders);
        $emailFolders = $this->createParentChild($formFolders);

        return $emailFolders;
    }

    /**
     * Get JSON folders.
     *
     * @param int $nylasFolderCounter The folder counter
     *
     * @return array|null
     */
    public function getJsonFolders(int $nylasFolderCounter): ?array
    {
        $counter = 0;
        //Fetch account details
        $organizationUnit = 'folder';

        do {
            $formFolders = $this->getAccountFolders($organizationUnit);
            $counter++;
        } while (count($formFolders) == 0 && $counter < $nylasFolderCounter);

        $formFolders = $formFolders['data'];
        $formFolders = $this->assignParents(array_reverse($formFolders));
        $formFolders = $this->parseTree($formFolders);

        return $formFolders;
    }

    /**
     * Fetch account folders/labels.
     *
     * @param string $organizationUnit The organization unit
     *
     * @return array
     */
    public function getAccountFolders(string $organizationUnit): array
    {
        //Fetch folder list
        $responseFolders = [[]];
        $limit = 100;
        $start = 0;
        //below code modified as now there is no concept if label and folder, only folder type is there
        do {
            $formFolders = $this->nylasClient->Folders->Folder->list(
                $this->nylasClient->Options->getGrantId(),
            );
            $parentFolders = array_filter($formFolders, function ($folder) {
                return empty($folder->parent_id);
            });

            $start += $limit;
            $responseFolders = array_merge($responseFolders, $parentFolders);
        } while (count($formFolders) >= $limit);

        return $responseFolders;
    }

    /**
     * Assign parents to folders.
     *
     * @param array $folders The folders
     *
     * @return array|null
     */
    public function assignParents(array $folders): ?array
    {
        foreach ($folders as $key => $value) {
            $display_name = str_replace("\\", "/", $value['name']);
            if (strpos($display_name, '/')) {
                $explode = explode('/', $display_name);
                array_pop($explode);
                $searchElement = implode('/', $explode);
                $parentKey = array_search($searchElement, array_column($folders, 'name'), true);
                $folders[$key]['parent'] = $parentKey;
            }
        }
        return $folders;
    }

    /**
     * Parse tree structure.
     *
     * @param array $folders The folders
     *
     * @return array|null
     */
    private function parseTree(array $folders): ?array
    {
        foreach ($folders as $key => $value) {
            if (isset($value['parent'])) {
                $display_name = str_replace("\\", "/", $value['name']);
                //$folders[$key]['name'] = end(explode('/', $display_name));
                $name_parts = explode('/', $display_name);
                $folders[$key]['name'] = end($name_parts);
                unset($folders[$key]['parent']);
                $folders[$value['parent']]['children'][] = $folders[$key];
                unset($folders[$key]);
            }
        }
        return array_reverse(array_values($folders));
    }

    /**
     * Set parent child in EmailFolder entity.
     *
     * @param array                $formFolders The form folders
     * @param EmailFolderModel|null $parent      The parent folder model
     *
     * @return array
     */
    public function createParentChild(array $formFolders, ?EmailFolderModel $parent = null): array
    {
        $emailFolderModels = [];

        foreach ($formFolders as $folder) {
            if (isset($folder['name']) && $folder['name'] !== 'all' && trim($folder['name']) != "") {

                $newFolder = new NylasEmailFolder();
                $newFolder->setFullName($folder['name']);
                $newFolder->setName($folder['name']);
                $newFolder->setType($this->guessFolderType($folder));
                $newFolder->setOrigin($this->emailOrigin);
                $newFolder->setFolderUid($folder['id']);

                //create emailFolderModel
                $emailFolderModel = new EmailFolderModel();
                $emailFolderModel->setEmailFolder($newFolder);
                $emailFolderModel->setUidValidity($folder['id']);

                /** @var EmailFolderModel $parent */
                if ($parent) {
                    $newFolder->setParentFolder($parent->getEmailFolder());
                    $parent->addSubFolderModel($emailFolderModel);
                }
                if (isset($folder['children'])) {
                    $childModels = $this->createParentChild($folder['children'], $emailFolderModel);
                    $childFolders = [];
                    /** @var EmailFolderModel $childModel */
                    foreach ($childModels as $childModel) {
                        $childFolders[] = $childModel->getEmailFolder();
                    }
                    $newFolder->setSubFolders($childFolders);
                }

                $emailFolderModels[] = $emailFolderModel;
            }
        }
        return $emailFolderModels;
    }

    /**
     * Get folder collection.
     *
     * @param bool                $byPassOutdatedAt Whether to bypass outdated at
     * @param ArrayCollection     $folders          The folders
     * @param array|null          $folderData       The folder data
     * @param int                 $maxDepth         The max depth
     * @param bool                $hasChildren      Whether has children
     *
     * @return array
     */
    public function getFolderCollection(
        bool $byPassOutdatedAt,
        ArrayCollection $folders,
        ?array &$folderData,
        int $maxDepth = 10,
        bool $hasChildren = false
    ): array {
        if ($folderData === null) {
            $folderData = [];
        }
        if ($maxDepth) {
            foreach ($folders as $folder) {
                if ($folder === null) {
                    $this->logger->error('Encountered null folder in getFolderCollection', [
                        'folders_count' => count($folders),
                    ]);
                    continue;
                }

                $folderUid = null;
                if ($folder instanceof NylasEmailFolder) {
                    $folderUid = $folder->getFolderUid();
                }

                $folderId = $folder->getId();

                if (is_null($folderId)) {
                    // First, attempt to reload the folder
                    $folder = $this->entityManager->getRepository(NylasEmailFolder::class)->findOneBy(['folderUid' => $folderUid]);
                    if ($folder === null) {
                        $this->logger->error('Folder ID is null and folder not found in repository', [
                            'folder_uid' => $folderUid,
                        ]);
                        continue;
                    }
                    $folderId = $folder->getId();
                }

                $folderItem = [
                    'id' => $folderId,
                    'fullName' => $folder->getFullName(),
                    'name' => $folder->getName(),
                    'folderUid' => $folderUid,
                    'type' => $folder->getType() ?? FolderType::OTHER,
                    'owner' => $folder->getOrigin()->getOwner()->getFullName(),
                    'syncEnabled' => $folder->isSyncEnabled(),
                ];

                if ($folder->getSubFolders()->count() > 0 and $maxDepth > 1) {
                    $folderItem['children'] = $this->getFolderCollection(
                        $byPassOutdatedAt,
                        new ArrayCollection($folder->getSubFolders()->toArray()),
                        $folderItem['children'],
                        $maxDepth - 1,
                        true
                    );
                }
                if ($hasChildren == true) {
                    $folderData[] = $folderItem;
                }
                if ($byPassOutdatedAt) {
                    if ($hasChildren == false and $folder->getParentFolder() == null) {
                        $folder->setOutdatedAt(null);
                        $folderData[] = $folderItem;
                    }
                } else {
                    if ($hasChildren == false and $folder->getParentFolder() == null and $folder->getOutdatedAt() == null) {
                        $folderData[] = $folderItem;
                    }
                }
            }
        }
        return $folderData;
    }

    /**
     * Prepare form.
     *
     * @param AccountTypeModel $accountTypeModel The account type model
     *
     * @return FormInterface
     */
    public function prepareForm(AccountTypeModel $accountTypeModel): FormInterface
    {
        $data = $user = new User();
        $data->setImapAccountType($accountTypeModel);
        $form = $this->formFactory->createNamed(
            '',
            EmailSettingsType::class,
            null,
            ['csrf_protection' => false]
        );
        $form->setData($data);

        return $form;
    }

    /**
     * Create account model.
     *
     * @param string        $type             The type
     * @param NylasEmailOrigin $oauthEmailOrigin The OAuth email origin
     *
     * @return AccountTypeModel
     */
    public function createAccountModel(string $type, NylasEmailOrigin $oauthEmailOrigin): AccountTypeModel
    {
        $accountTypeModel = new AccountTypeModel();
        $accountTypeModel->setAccountType($type);
        $accountTypeModel->setUserEmailOrigin($oauthEmailOrigin);

        return $accountTypeModel;
    }

    /**
     * Get folder message IDs.
     *
     * @param NylasEmailFolder $emailFolder The email folder
     *
     * @return array
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getFolderMessageIds(NylasEmailFolder $emailFolder): array
    {
        $responseMessage = [[]];
        $limit = 500;
        $start = 0;
        do {
            $messages = $this->nylasClient->Messages->Message->list(
                $this->nylasClient->nylasClient->Options->getGrantId(),
                [
                    'in'     => $emailFolder->getFolderUid(),
                    //'view'   => 'ids',
                    'limit'  => $limit,
                    'offset' => $start
                ]
            );
            $start             += $limit;
            $responseMessage[] = $messages;
        } while (count($messages) > 499);

        $responseMessage = array_merge(...$responseMessage);
        return $responseMessage;
    }

    /**
     * Get message body content.
     *
     * @param string $messageId The message ID
     *
     * @return array
     */
    public function getMessageBody(string $messageId): array
    {
        $message = $this->nylasClient->Messages->Message->find(
            $this->nylasClient->Options->getGrantId(),
            $messageId,
            ['fields' => 'include_headers']
        );

        return $message;
    }

    /**
     * Mark message as unread.
     *
     * @param string $messageId The message ID
     * @param bool   $isSeen    Whether the message is seen
     *
     * @return void
     */
    public function updateMailSeenStatus(string $messageId, bool $isSeen = false): void
    {
        if ($isSeen) {
            $this->nylasClient->Messages->Message->update(
                $this->nylasClient->Options->getGrantId(),
                $messageId,
                ['unread' => false]
            );
        } else {
            $this->nylasClient->Messages->Message->update(
                $this->nylasClient->Options->getGrantId(),
                $messageId,
                ['unread' => true]
            );
        }
    }

    /**
     * Guess folder type based on it's flags.
     *
     * @param array $srcFolder The source folder
     *
     * @return string
     */
    public function guessFolderType(array $srcFolder): string
    {
        $type = FolderType::OTHER;
        foreach ($this->flagTypeMap as $flagType) {
            if (strtolower($srcFolder['name']) === strtolower($flagType)) {
                $type = strtolower($srcFolder['name']);
                break;
            }
        }
        // if junk box do not include flag for correct type guess
        if ($type === FolderType::OTHER && $this->guessJunkTypeByName($srcFolder['name'])) {
            $type = FolderType::SPAM;
        }

        return $type;
    }

    /**
     * Try to guess sent folder by folder name.
     *
     * @param string $name The folder name
     *
     * @return bool
     */
    public function guessJunkTypeByName(string $name): bool
    {
        if (in_array($name, $this->possibleJunkFolderNameMap, true)) {
            return true;
        }
        return false;
    }

    /**
     * Guess api version based on uid format.
     *
     * @param string $uid The UID
     *
     * @return string
     */
    public function guessApiVersion(string $uid): string
    {
        if (strlen($uid) >= 15 && strlen($uid) <= 20) {
            return 'v3';
        } else {
            return 'v2';
        }
    }

    /**
     * Get Massage Id from Headers.
     *
     * @param array  $nylasEmailContent The Nylas email content
     * @param string $name              Key in $headers
     * @param string $defaultVal        The default value
     *
     * @return string
     */
    public function getMessageId(array $nylasEmailContent, string $name, string $defaultVal): string
    {
        $header = $nylasEmailContent['headers'];
        if ($header === false) {
            return $defaultVal;
        }
        $filtered = array_filter($header, function ($item) use ($name) {
            return strtolower($item['name']) == strtolower($name); //Message-Id or Message-ID
        });
        $messageId = array_values($filtered)[0]['value'] ?? null;

        return $messageId;
    }

    /**
     * Get message object from email entity.
     *
     * @param mixed $email The email entity
     *
     * @return array
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMessage($email): array
    {
        $subject = $email->getSubject();
        $fromName = $email->getFromName();
        preg_match('/<([^>]+)>/', html_entity_decode($fromName), $matches);
        $fromEmail = $matches[1] ?? $fromName;
        $toAddress = $email->getTo();
        $toEmails = [];
        foreach ($toAddress as $address) {
            foreach ($address->getEmail()->getRecipients() as $recipient) {
                if ($recipient->getType() === 'to') {
                    $toEmails[] = $recipient->getEmailAddress()->getEmail();
                }
            }
        }

        $message = $this->nylasClient->Messages->Message->find(
            $this->nylasClient->Options->getGrantId(),
            '',
            [
                'subject' => $subject,
                'from' => $fromEmail,
                'to' => rtrim(implode(',', $toEmails)),
                'fields' => 'include_headers'
            ]
        );

        return $message;
    }
}
