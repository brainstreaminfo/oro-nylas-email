<?php


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

//#ref:adbrain missing dependancies in orm crm 6
//use Oro\Bundle\EmailBundle\Mailer\DirectMailer;
//use Oro\Bundle\EmailBundle\Mailer\Processor;
//use Oro\Bundle\SecurityBundle\Encoder\Mcrypt;
//use Oro\Bundle\SecurityBundle\SecurityFacade;

/**
 * Class NylasEmailProcessor
 * @package BrainStream\Bundle\NylasBundle\Mailer
 */
class NylasEmailProcessor extends EmailModelSender
{
    /** @var NylasClient $nylasClient */
    protected $nylasClient;

    //private $securityFacade;

    /** @var TranslatorInterface */
    private $translator;

    /** @var EmailOriginHelper */
    private $extendEmailOriginHelper;

    //ref:adbrain
    private $eventDispatcher;
    private $emailEntityBuilder;

    private $emailActivityManager;

    private $entityManager;

    private $emailAddressHelper;

    private $logger;


    /**
     * @param MailerInterface $mailer
     * @param EmbeddedImagesInEmailModelHandler $embeddedImagesHandler
     * @param EmailFactory $emailFactory
     * @param EmailUserFromEmailModelBuilder $emailUserBuilder
     * @param EventDispatcherInterface $eventDispatcher
     * @param EntityListener $entityListener
     * @param EmailAddressHelper $emailAddressHelper
     * @param EmailEntityBuilder $emailEntityBuilder
     * @param EmailActivityManager $emailActivityManager
     * @param EmailOriginHelper $emailOriginHelper
     * @param NylasClient $nylasClient
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
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
    }

    /** ref:adbrain method name changed to process() -> send()
     *
     * @param EmailModel $model
     * @param null       $origin
     * @param bool       $persist
     *
     * @return EmailUser
     * @throws \Exception
     */
    public function send(EmailModel $model, $origin = null, $persist = true): EmailUser
    {
        //$this->send1($model, $origin);
       // $this->assertModel($model);
        $messageDate     = new \DateTime('now', new \DateTimeZone('UTC'));
        $parentMessageId = $this->getParentMessageId($model);
        $fromEmailAddress = $this->getUserEmailAddress($model->getFrom());

        if ($origin instanceof NylasEmailOrigin && $origin->getAccountId() != null) {
            $fromEmailAddress = $origin->getMailboxName();
            $this->nylasClient->setEmailOrigin($origin);
            $model->setFrom($origin->getOwner()->getFullName() . '<' . $fromEmailAddress . '>');
            $message           = $this->prepareMessage($model, $parentMessageId, $messageDate);
            // Verify the origin account
            $accountStatus = $this->verifyAccountStatus();
            // Send an email only if the account is in running state
            if(isset($accountStatus['grant_status']) && $accountStatus['grant_status'] == 'valid') {
                $sentMessageDetail = $this->processSend($message, $origin);
            } else {
                // throw an exception if the account is not in running state
                throw new \Exception('Access token are invalid. please try re-connecting your e-mail account');
            }
        } else {
            throw new \Exception($this->translator->trans("automation.origin.config.message"));
        }

        $emailUser = $this->prepareEmailUser($model, $origin, $sentMessageDetail, $messageDate, $parentMessageId);
        $emailUser->setOrigin($origin);

        if ($persist) {
            // persist the email and all related entities such as folders, email addresses etc.//ref:adbrain get entity manager way changed
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
        //ref:adbrain
        //$this->eventDispatcher->dispatch(EmailBodyAdded::NAME, $event);
        $this->eventDispatcher->dispatch($event);

        return $emailUser;
    }

    //ref:adbrain added to support existing code, taken from processor class of old oro
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
                'http_version' => '1.1'
            ]);
            $response = $client->request('POST', "https://api.us.nylas.com/v3/grants/$grantId/messages/send", $defaultOptions);
            $responseData = $response->toArray();
        } catch (\Exception $e) {
            /*ob_start();
            dump($e);
            $dumpOutput = ob_get_clean();
            file_put_contents(
                '/home/brainstream/workspace/orocrm6_default/var/logs/nylas_error1.log',
                "Error: " . $dumpOutput . "\n",
                FILE_APPEND
            );
            throw $e;*/
            $this->logger->error('Failed to send email via Nylas', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'grant_id' => $grantId,
            ]);
            //$this->logger->error('Nylas API request failed', ['error' => $e->getMessage(), 'grant_id' => $grantId]);
        }
        return $responseData;
    }

    /**
     * @param EmailModel $model
     * @param string     $parentMessageId
     * @param \DateTime  $messageDate
     *
     * @return array
     */
    protected function prepareMessage(EmailModel $model, $parentMessageId, $messageDate): array
    {
        /** @var array $message */
        $message = [];

        if ($parentMessageId) {
            $message['reply_to_message_id'] = $parentMessageId;
        }
        $addresses           = $this->getAddresses($model->getFrom());
        $message['from']     = $addresses;
        $message['reply_to'] = $addresses;
        $message['to']       = $this->getAddresses($model->getTo());
        $message['cc']       = $this->getAddresses($model->getCc());
        $message['bcc']      = $this->getAddresses($model->getBcc());

        // Ensure the body is UTF-8 encoded
        $body = mb_convert_encoding($model->getBody(), 'UTF-8', 'auto');
        if ($body === false) {
            throw new \Exception('Failed to encode email body to UTF-8');
        }
        $message['body'] = $body;
        //$message['body']     = $model->getBody();
        //$message['subject']  = $model->getSubject();
        $message['subject'] = mb_convert_encoding($model->getSubject(), 'UTF-8', 'auto');
        $message['attachments'] = $this->addEmailAttachments($model);
        $response     = $this->processEmbeddedImages($message, $model);
        $message['body'] = $response[0];
        if (!empty($response[1])) {
            $inlineAttachments = $response[1];
            $message['attachments'] = array_merge($message['attachments'], $inlineAttachments);
        }

        return $message;
    }


    /**
     * Converts emails addresses to a form acceptable to \Swift_Mime_Message class
     *
     * @param string|string[] $addresses Examples of correct email addresses: john@example.com, <john@example.com>,
     *                                   John Smith <john@example.com> or "John Smith" <john@example.com>
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
            //return [];
            return [$message['body'], []];
        }
        $fileArray = [];
        //$message['file_ids'] = [];
        //ref:adbrain mime guess way changed
        //$guesser = ExtensionGuesser::getInstance();
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

        // Base64-encode the content of inline attachments
       /* foreach ($fileArray as &$attachment) {
            if (isset($attachment['content'])) {
                $attachment['content'] = base64_encode($attachment['content']);
            }
        }*/
        //dump($body);
        //dump($fileArray);

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
            //$fileAttachmentArray[$attachment->getFileName()] = $attachment;
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
//                    $emailUser->setOwner(null);
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

        // ref:adbrain Enable or disable email tracking for any email
        /*$emailTracking = ($model->hasEnableTracking())?1:0;
        $emailUser->getEmail()->setEnableTracking($emailTracking);*/

        $emailUser->setSeen(true);
        $emailUser->getEmail()->setUid($messageId);
        //$emailUser->getEmail()->setEmailIp($this->getIpAddr());//ref:adbrain ip not found
        if (isset($message['data']['attachments']) && count($message['data']['attachments']) > 0) {
            $emailUser->getEmail()->setHasAttachments(1);
        }

        if ($parentMessageId) {
            $emailUser->getEmail()->setRefs($parentMessageId);

            return $emailUser;
        }

        return $emailUser;
    }

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

        //ref:adbrain refactored below code as Processor($this->emailOriginHelper) class not found in oro crm 6
        //In case when 'useremailorigin' origin doesn't have folder, get folder from internal origin
        /*
        if (!$folder && $origin instanceof UserEmailOrigin) {
            $origin = $this->emailOriginHelper->getEmailOrigin($email, $origin->getOwner(), null, InternalEmailOrigin::BAP, false);
            return $origin->getFolder(FolderType::SENT);
        }*/

        return $folder;
    }

}
