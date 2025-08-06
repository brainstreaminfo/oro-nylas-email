<?php

/**
 * Nylas Email Processor for handling email sending via Nylas API v3.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category  BrainStream
 * @package   BrainStream\Bundle\NylasBundle\Sender
 * @author    BrainStream Team <info@brainstream.tech>
 * @license   MIT
 * @link      https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Sender;

use Oro\Bundle\EmailBundle\Builder\EmailUserFromEmailModelBuilder;
use Oro\Bundle\EmailBundle\EventListener\EntityListener;
use Oro\Bundle\EmailBundle\Sender\EmailFactory;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper;
use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Decoder\ContentDecoder;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailAttachment;
use Oro\Bundle\EmailBundle\Entity\EmailAttachmentContent;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin;
use Oro\Bundle\EmailBundle\Entity\Manager\EmailActivityManager;
use Oro\Bundle\EmailBundle\Event\EmailBodyAdded;
use Oro\Bundle\EmailBundle\Form\Model\Email as EmailModel;
use Oro\Bundle\EmailBundle\Form\Model\EmailAttachment as EmailAttachmentModel;
use Oro\Bundle\EmailBundle\Model\FolderType;
use Oro\Bundle\EmailBundle\Sender\EmailModelSender;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;
use Oro\Bundle\EmailBundle\EmbeddedImages\EmbeddedImagesInEmailModelHandler;
use Psr\Log\LoggerInterface;
use BrainStream\Bundle\NylasBundle\Service\ConfigService;

/**
 * Nylas Email Processor for handling email sending via Nylas API v3.
 *
 * This class decorates the default EmailModelSender to intercept email sending
 * and route it through Nylas API when a NylasEmailOrigin is configured.
 *
 * @category  BrainStream
 * @package   BrainStream\Bundle\NylasBundle\Sender
 * @author    BrainStream Team <info@brainstream.tech>
 * @license   MIT
 * @link      https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailProcessor extends EmailModelSender
{
    private NylasClient $nylasClient;
    private TranslatorInterface $translator;
    private EmailOriginHelper $extendEmailOriginHelper;
    private EventDispatcherInterface $eventDispatcher;
    private EmailEntityBuilder $emailEntityBuilder;
    private EmailActivityManager $emailActivityManager;
    private EntityManagerInterface $entityManager;
    private EmailAddressHelper $emailAddressHelper;
    private LoggerInterface $logger;
    private ConfigService $configService;

    /**
     * Constructor for NylasEmailProcessor.
     *
     * @param MailerInterface                    $mailer                    The mailer service
     * @param EmbeddedImagesInEmailModelHandler  $embeddedImagesHandler     The embedded images handler
     * @param EmailFactory                       $emailFactory              The email factory
     * @param EmailUserFromEmailModelBuilder     $emailUserBuilder          The email user builder
     * @param EventDispatcherInterface           $eventDispatcher           The event dispatcher
     * @param EntityListener                     $entityListener            The entity listener
     * @param EmailAddressHelper                 $emailAddressHelper        The email address helper
     * @param EmailEntityBuilder                 $emailEntityBuilder        The email entity builder
     * @param EmailActivityManager               $emailActivityManager      The email activity manager
     * @param EmailOriginHelper                  $emailOriginHelper         The email origin helper
     * @param NylasClient                        $nylasClient               The Nylas client
     * @param TranslatorInterface                $translator                The translator
     * @param EntityManagerInterface             $entityManager             The entity manager
     * @param LoggerInterface                    $logger                    The logger
     * @param ConfigService                      $configService             The config service
     */
    public function __construct(
        MailerInterface $mailer,
        EmbeddedImagesInEmailModelHandler $embeddedImagesHandler,
        EmailFactory $emailFactory,
        EmailUserFromEmailModelBuilder $emailUserBuilder,
        EventDispatcherInterface $eventDispatcher,
        EntityListener $entityListener,
        EmailAddressHelper $emailAddressHelper,
        EmailEntityBuilder $emailEntityBuilder,
        EmailActivityManager $emailActivityManager,
        EmailOriginHelper $emailOriginHelper,
        NylasClient $nylasClient,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ConfigService $configService,
    ) {
        parent::__construct(
            $mailer,
            $embeddedImagesHandler,
            $emailFactory,
            $emailUserBuilder,
            $eventDispatcher,
            $entityListener
        );
        $this->nylasClient = $nylasClient;
        $this->translator = $translator;
        $this->extendEmailOriginHelper = $emailOriginHelper;
        $this->entityManager = $entityManager;
        $this->emailActivityManager = $emailActivityManager;
        $this->emailAddressHelper = $emailAddressHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->emailEntityBuilder = $emailEntityBuilder;
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * Enhanced send method with improved Nylas integration.
     *
     * @param EmailModel    $model   The email model to send
     * @param mixed         $origin  The email origin (optional)
     * @param bool          $persist Whether to persist the email (default: true)
     *
     * @return EmailUser
     * @throws \Exception
     */
    public function send(EmailModel $model, $origin = null, $persist = true): EmailUser
    {
        $messageDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $parentMessageId = $this->getParentMessageId($model);

        if ($origin instanceof NylasEmailOrigin && $origin->getAccountId() != null) {
            $fromEmailAddress = $origin->getMailboxName();
            $this->nylasClient->setEmailOrigin($origin);

            // Verify the origin account and get account info including display name
            $accountStatus = $this->verifyAccountStatus();

            // Get display name from Nylas API response, fallback to email address
            $displayName = $fromEmailAddress; // Default to email address
            if (isset($accountStatus['email']) && !empty($accountStatus['email'])) {
                // Try to get name from account data, fallback to email
                $displayName = $accountStatus['name'] ?? $accountStatus['email'];
            }

            // New way: Use display name from Nylas API
            $model->setFrom($displayName . '<' . $fromEmailAddress . '>');

            // Old way: Use current user's name (commented out)
            // $model->setFrom($origin->getOwner()->getFullName() . '<' . $fromEmailAddress . '>');

            $message = $this->prepareMessageNew($model, $parentMessageId, $messageDate);

            // Send an email only if the account is in valid state
            if (isset($accountStatus['grant_status']) && $accountStatus['grant_status'] == 'valid') {
                try {
                    $sentMessageDetail = $this->processSendNew($message, $origin);

                    if (!$sentMessageDetail) {
                        throw new \Exception('Failed to send email via Nylas API');
                    }

                    $this->logger->info(
                        'Email sent successfully via Nylas',
                        [
                            'message_id' => $sentMessageDetail['data']['id'] ?? 'unknown',
                            'grant_id' => $this->nylasClient->nylasClient->Options->getGrantId()
                        ]
                    );

                } catch (\Exception $e) {
                    $this->logger->error(
                        'Failed to send email via Nylas',
                        [
                            'error' => $e->getMessage(),
                            'grant_id' => $this->nylasClient->nylasClient->Options->getGrantId()
                        ]
                    );
                    throw $e;
                }
            } else {
                throw new \Exception('Access token is invalid. Please try re-connecting your email account');
            }
        } else {
            throw new \Exception($this->translator->trans("automation.origin.config.message"));
        }

        $emailUser = $this->prepareEmailUser($model, $origin, $sentMessageDetail, $messageDate, $parentMessageId);
        $emailUser->setOrigin($origin);

        if ($persist) {
            // persist the email and all related entities such as folders, email addresses etc.
            $this->emailEntityBuilder->getBatch()->persist($this->entityManager);
            $this->persistAttachments($model, $emailUser->getEmail());

            // associate the email with the target entity if exist
            $contexts = $model->getContexts();
            foreach ($contexts as $context) {
                $this->emailActivityManager->addAssociation($emailUser->getEmail(), $context);
            }

            // flush all changes to the database
            $this->entityManager->flush();
        }

        $event = new EmailBodyAdded($emailUser->getEmail());
        $this->eventDispatcher->dispatch($event);

        return $emailUser;
    }

    /**
     * Enhanced process send method based on user's processSendNew.
     *
     * @param array            $message      The message array to send
     * @param NylasEmailOrigin $emailOrigin  The email origin
     *
     * @return array
     * @throws \Exception
     */
    public function processSendNew($message, $emailOrigin): array
    {
        try {
            $client = HttpClient::create(
                [
                    'timeout' => 60,
                    'max_duration' => 60,
                    'verify_peer' => false,
                    'verify_host' => false,
                    //'http_version' => '1.1'
                ]
            );

            $grantId = $this->nylasClient->nylasClient->Options->getGrantId();
            $apiKey = $this->nylasClient->nylasClient->Options->getApiKey();

            // Validate required fields for Nylas API v3
            $requiredFields = ['from', 'to', 'subject', 'body', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($message[$field]) || empty($message[$field])) {
                    throw new \Exception("Missing required field: $field");
                }
            }

            // Ensure 'to' field is an array
            if (!is_array($message['to']) || empty($message['to'])) {
                throw new \Exception("'to' field must be a non-empty array");
            }

            $defaultOptions = [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $message
            ];

            $response = $client->request('POST', $this->configService->getApiUrl() . "/v3/grants/$grantId/messages/send", $defaultOptions);
            $responseData = $response->toArray();


            return $responseData;

        } catch (\Exception $e) {
            $this->logger->error(
                'Error occurred while sending email via Nylas',
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'grant_id' => $grantId ?? 'unknown',
                    'message_payload' => $message ?? 'unknown'
                ]
            );

            // If it's an HTTP exception, try to get the response body
            if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
                try {
                    $response = $e->getResponse();
                    $responseBody = $response->getContent(false);
                    $this->logger->error(
                        'Nylas API error response body',
                        [
                            'status_code' => $response->getStatusCode(),
                            'response_body' => $responseBody
                        ]
                    );
                } catch (\Exception $responseException) {
                    $this->logger->error(
                        'Could not read Nylas API error response',
                        [
                            'response_error' => $responseException->getMessage()
                        ]
                    );
                }
            }

            throw $e;
        }
    }

    /**
     * Enhanced prepare message method based on user's prepareMessageNew
     */
    protected function prepareMessageNew(EmailModel $model, $parentMessageId, $messageDate): array
    {
        $message = [];

        if ($parentMessageId) {
            $message['reply_to_message_id'] = $parentMessageId;
        }

        $addresses = $this->getAddresses($model->getFrom());
        // Fix: 'from' should be an array of objects, not a single object
        $message['from'] = !empty($addresses) ? [$addresses[0]] : [
            [
                'name' => 'Unknown Sender',
                'email' => 'default@example.com'
            ]
        ];
        // Fix: 'reply_to' should be an array of objects, not a single object
        $message['reply_to'] = $message['from']; // Use the same as 'from' unless specified otherwise

        // Use getAddresses method to properly extract email addresses
        $message['to'] = $this->getAddresses($model->getTo());
        $message['cc'] = $this->getAddresses($model->getCc() ?? []);
        $message['bcc'] = $this->getAddresses($model->getBcc() ?? []);

        $message['body'] = mb_convert_encoding($model->getBody(), 'UTF-8', 'auto');
        $message['subject'] = mb_convert_encoding($model->getSubject(), 'UTF-8', 'auto');

        // Add required fields for Nylas API v3
        $message['type'] = $model->getType() === 'html' ? 'html' : 'text';

        // Handle attachments
        $attachments = $this->addEmailAttachments($model);
        if (!empty($attachments)) {
            $message['attachments'] = $attachments;
        }

        // Process embedded images
        $response = $this->processEmbeddedImages($message, $model);
        $message['body'] = $response[0];
        if (!empty($response[1])) {
            $inlineAttachments = $response[1];
            if (!isset($message['attachments'])) {
                $message['attachments'] = [];
            }
            $message['attachments'] = array_merge($message['attachments'], $inlineAttachments);
        }

        return $message;
    }

    /**
     * ref:adbrain added to support existing code, taken from processor class of old oro
     *
     * @param EmailModel $model
     * @param Email $email
     * @return void
     */
    protected function persistAttachments(EmailModel $model, Email $email)
    {
        /** @var EmailAttachmentModel $emailAttachmentModel */
        foreach ($model->getAttachments() as $emailAttachmentModel) {
            $attachment = $emailAttachmentModel->getEmailAttachment();

            if (!$attachment->getId()) {
                $this->entityManager->persist($attachment);
            } else {
                $attachmentContent = clone $attachment->getContent();
                $attachment        = clone $attachment;
                $attachment->setContent($attachmentContent);
                $this->entityManager->persist($attachment);
            }

            $email->getEmailBody()->addAttachment($attachment);
            $attachment->setEmailBody($email->getEmailBody());
        }
    }

    /**
     * @param EmailModel $model
     *
     * @return string
     */
    protected function getParentMessageId(EmailModel $model)
    {
        $messageId     = '';
        $parentEmailId = $model->getParentEmailId();
        if ($parentEmailId && $model->getMailType() == EmailModel::MAIL_TYPE_REPLY) {
            $parentEmail = $this->entityManager
                                ->getRepository('Oro\Bundle\EmailBundle\Entity\Email')
                                ->find($parentEmailId);
            $messageId   = $parentEmail->getUid();
        }
        return $messageId;
    }

    /**
     * Verify the origin before sending an email just to confirm whether the account is stopped already or in Running Status
     *
     * @return array
     * @throws \Exception
     */
    public function verifyAccountStatus(): array
    {
        //getAccount() is replaced with grant id in v3
        $response = $this->nylasClient->nylasClient->Administration->Grants->find(
            $this->nylasClient->nylasClient->Options->getGrantId()
        );
        if (!$response) {
            throw new \Exception('The account was not exist or the access token are invalid');
        }
        return $response['data'];
    }


    /**
     * Process send email message. In case exist custom smtp host/port use it
     *
     * @param array            $message
     * @param NylasEmailOrigin $emailOrigin
     *
     * @return array
     * @throws \Exception
     */
    public function processSend($message, $emailOrigin): array
    {
        $responseData = [];
        try {
            $apiKey = $this->nylasClient->nylasClient->Options->getApiKey();
            $grantId = $this->nylasClient->nylasClient->Options->getGrantId();
            $defaultOptions = [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $message
            ];
            $client = HttpClient::create([
                'timeout' => 60,
                'max_duration' => 60,
                'verify_peer' => false,
                'verify_host' => false,
                //'http_version' => '1.1'
            ]);
            $response = $client->request('POST', $this->configService->getApiUrl() . "/v3/grants/$grantId/messages/send", $defaultOptions);
            $responseData = $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via Nylas', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'grant_id' => $grantId,
            ]);
        }
        return $responseData;
    }

    /**
     * @param EmailModel $model
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getAddresses($addresses): array
    {
        $result = [];

        if (is_string($addresses)) {
            $addresses = [$addresses];
        }
        if (!is_array($addresses) && !$addresses instanceof \Iterator) {
            throw new \InvalidArgumentException(
                'The $addresses argument must be a string or a list of strings (array or Iterator)'
            );
        }

        foreach ($addresses as $address) {
            $name = $this->emailAddressHelper->extractEmailAddressName($address);
            if (empty($name)) {
                $result[] = ['email' => $this->emailAddressHelper->extractPureEmailAddress($address)];
            } else {
                $result[] = ['name' => $name, 'email' => $this->emailAddressHelper->extractPureEmailAddress($address)];
            }
        }

        return $result;
    }

    /**
     * Process inline images. Convert it to embedded attachments and update message body.
     *
     * @param array           $message
     * @param EmailModel|null $model
     */
    public function processEmbeddedImages(&$message, EmailModel $model = null)
    {
        if ($model->getType() !== 'html') {
            return [$message['body'], []];
        }
        $fileArray = [];
        //ref:adbrain mime guess way changed $guesser = ExtensionGuesser::getInstance() wont work
        $guesser = MimeTypes::getDefault();
        $body    = preg_replace_callback(
            '/<img(.*)src(\s*)=(\s*)["\'](.*)["\']/U',
            function ($matches) use (&$message, $guesser, $model, &$fileArray) {
                if (count($matches) === 5) {
                    $imgConfig = $matches[1];
                    // 4th match contains src attribute value
                    $srcData = $matches[4];

                    if (strpos($srcData, 'data:image') === 0) {
                        list($mime, $content) = explode(';', $srcData);
                        list($encoding, $file) = explode(',', $content);
                        $mime               = str_replace('data:', '', $mime);
                        //$fileName           = sprintf('%s.%s', uniqid('', false), $guesser->guess($mime));
                        $extension = $guesser->getExtensions($mime);
                        $fileName           = sprintf('%s.%s', uniqid('', false), is_array($extension) ? $extension[0] : $extension);
                        //$fileContent        = $file;
                        $file = ContentDecoder::decode($file, $encoding);
                        if ($file === false) {
                            throw new \Exception('Invalid Base64 encoding in inline image');
                        }
                        $fileContent = base64_encode($file);
                        $contentSize = strlen($file); // Size of the decoded content in bytes

                        //there is no separate files endpoint to upload files in v3, uploading files separately not needed any more
                        $id = 'inline-image-' . uniqid();
                        $cidString          = 'cid:' . $id;

                        $fileArray[]        = [
                            'content' => $fileContent,
                            'filename' => $fileName,
                            'content_type' => $mime,
                            'content_id' => $id,
                            'size' => $contentSize,
                            'content_disposition' => 'inline'
                        ];

                        if ($model) {
                            $attachmentContent = new EmailAttachmentContent();
                            $attachmentContent->setContent($fileContent);
                            $attachmentContent->setContentTransferEncoding($encoding);

                            $emailAttachment = new EmailAttachment();
                            $emailAttachment->setEmbeddedContentId($id);
                            $emailAttachment->setFileName($fileName);
                            $emailAttachment->setContentType($mime);
                            $attachmentContent->setEmailAttachment($emailAttachment);
                            $emailAttachment->setContent($attachmentContent);

                            $emailAttachmentModel = new EmailAttachmentModel();
                            $emailAttachmentModel->setEmailAttachment($emailAttachment);
                            $model->addAttachment($emailAttachmentModel);
                        }

                        return sprintf('<img%ssrc="%s"', $imgConfig, $cidString);
                    }
                }

                return $matches[0];
            },
            $message['body']
        );

        // Base64-encode the content of inline attachments if needed
       /* foreach ($fileArray as &$attachment) {
            if (isset($attachment['content'])) {
                $attachment['content'] = base64_encode($attachment['content']);
            }
        }*/

        return [$body, $fileArray];
    }

    /**
     * @param EmailModel $model
     *
     * @return array
     */
    protected function addEmailAttachments(EmailModel $model): array
    {
        /** @var array */
        $uploadedFiles = $fileArray = $fileAttachmentArray = [];

        /** @var EmailAttachmentModel $emailAttachmentModel */
        foreach ($model->getAttachments() as $emailAttachmentModel) {
            $attachment = $emailAttachmentModel->getEmailAttachment();
            $fileContent = $attachment->getContent()->getContent();

            if ($attachment->getContent()->getContentTransferEncoding() === 'base64') {
                $fileContent = base64_decode($attachment->getContent()->getContent());
            }

            // Re-encode to ensure valid base64
            $base64Content = base64_encode($fileContent);
            $contentSize = strlen($fileContent); // Size of the decoded content

            // Validate UTF-8 for filename and content type
            $fileName = mb_convert_encoding($attachment->getFileName(), 'UTF-8', 'auto');
            $contentType = mb_convert_encoding($attachment->getContentType(), 'UTF-8', 'auto');

            $fileArray[]  = [
                'content' => $base64Content,
                'filename' => $fileName,
                'content_type' => $contentType,
                'size' => $contentSize // Add the size field
            ];
        }
        return $fileArray;
    }

    /**
     * @param EmailModel       $model
     * @param \DateTime        $messageDate
     * @param NylasEmailOrigin $origin
     *
     * @return EmailUser
     */
    protected function createEmailUser(EmailModel $model, $messageDate, $origin)
    {
        $owner        = null;
        $organization = null;
        if ($origin) {
            $owner        = $origin->getOwner();
            $organization = $origin->getOrganization();
        }
        $emailUser = $this->emailEntityBuilder->emailUser(
            $model->getSubject(),
            $model->getFrom(),
            $model->getTo(),
            $messageDate,
            $messageDate,
            $messageDate,
            Email::NORMAL_IMPORTANCE,
            $model->getCc(),
            $model->getBcc(),
            $owner,
            $organization
        );
        if ($origin) {
            $emailUser->setOrigin($origin);
            if ($origin instanceof UserEmailOrigin) {
                if ($origin->getMailbox() !== null) {
                    //$emailUser->setOwner(null);
                    $emailUser->setMailboxOwner($origin->getMailbox());
                }
            }
        }

        return $emailUser;
    }

    /**
     * @param EmailModel $model
     * @param null       $origin
     * @param array      $message
     * @param \DateTime  $messageDate
     * @param string     $parentMessageId
     *
     * @return EmailUser
     */
    protected function prepareEmailUser(EmailModel $model, $origin, $message, $messageDate, $parentMessageId)
    {
        $messageId = $message['data']['id'];
        $messageInfo = $this->nylasClient->getMessageBody($messageId);
        $messageHeaderId = $this->nylasClient->getMessageId($messageInfo['data'], 'Message-Id', $messageId);

        //Set mark message as read
        $this->nylasClient->nylasClient->Messages->Message->update($this->nylasClient->nylasClient->Options->getGrantId(), $messageId, ['unread' => false]);
        $emailUser       = $this->createEmailUser($model, $messageDate, $origin);
        if ($origin) {
            $folder = $this->getFolder($model->getFrom(), $origin);
            if ($folder instanceof EmailFolder) {
                $emailUser->addFolder($this->getFolder($model->getFrom(), $origin));
            }
        }

        $emailUser->getEmail()->setEmailBody(
            $this->emailEntityBuilder->body($message['data']['body'], $model->getType() === 'html', true)
        );
        $emailUser->getEmail()->setXThreadId($message['data']['thread_id']);
        $emailUser->getEmail()->setMessageId($messageHeaderId);
        $emailUser->setSeen(true);
        $emailUser->getEmail()->setUid($messageId);

        if (isset($message['data']['attachments']) && count($message['data']['attachments']) > 0) {
            $emailUser->getEmail()->setHasAttachments(1);
        }

        if ($parentMessageId) {
            $emailUser->getEmail()->setRefs($parentMessageId);

            return $emailUser;
        }

        return $emailUser;
    }

    /**
     * Get IP address
     *
     * @return void
     */
    private function getIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * @param string                     $email
     * @param User                     $user
     * @param OrganizationInterface|null $organization
     * @param string                     $originName
     * @param bool                       $enableUseUserEmailOrigin
     *
     * @return \Oro\Bundle\EmailBundle\Entity\EmailOrigin
     */
    public function getEmailOrigin($email,
        User $user,
        OrganizationInterface $organization = null,
        $originName = InternalEmailOrigin::BAP,
        $enableUseUserEmailOrigin = true)
    {
        return $this->extendEmailOriginHelper->getEmailOrigin($email, $user, $organization, $originName, $enableUseUserEmailOrigin);
    }

    /**
     * Extract user from email address
     *
     * @param $str
     *
     * @return mixed
     */
    private function getUserEmailAddress($str)
    {
        preg_match('/<(.*?)>/', $str, $match);
        $records = $match;
        if (count($records) > 0) {
            return $records[1];
        } else {
            return $str;
        }
    }

    /**
     * Get origin's folder
     *
     * @param string $email
     * @param EmailOrigin $origin
     * @return EmailFolder
     */
    protected function getFolder($email, EmailOrigin $origin)
    {
        $folder = $origin->getFolder(FolderType::SENT);
        return $folder;
    }

}
