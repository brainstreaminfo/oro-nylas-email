<?php


namespace BrainStream\Bundle\NylasBundle\Manager\DTO;

use Oro\Bundle\EmailBundle\Decoder\ContentDecoder;
use Oro\Bundle\EmailBundle\Model\EmailHeader;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;

/**
 * Class Email
 * @package BrainStream\Bundle\NylasBundle\Manager\DTO
 */
class Email extends EmailHeader
{
    /** @var bool */
    const FORMAT_TEXT = false;

    /** @var bool */
    const FORMAT_HTML = true;

    /** @var string */
    const EMAIL_EMPTY_BODY_CONTENT = "\n";

    /**
     * @var array
     */
    private $message = [];

    /** @var bool */
    private $unread;

    /** @var NylasClient */
   // private $client;

    /** @var bool */
    private $hasAttachment;

    /** @var string */
    private $uid;

    /** @var bool */
    private $has_attachments;

    private $body;

    private $attachments;


    /**
     * Email constructor.
     *
     * @param $message
     */
    public function __construct($message)
    {
        $this->message = $message;
        //$this->client = $client;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set item id
     *
     * @param string $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return bool
     */
    public function getUnread()
    {
        return $this->unread;
    }

    /**
     * @param bool $unread
     *
     * @return $this
     */
    public function setUnread($unread)
    {
        $this->unread = $unread;
        return $this;
    }

    /**
     * @return bool
     */
    public function getHasAttachment()
    {
        return $this->hasAttachment;
    }

    /**
     * @param $hasAttachment
     *
     * @return $this
     */
    public function setHasAttachment($hasAttachment)
    {
        $this->hasAttachment = $hasAttachment;

        return $this;
    }

    /**
     * Get email body
     *
     * @return EmailBody
     */
    public function getBody()
    {
        if ($this->body === null) {
            $this->body = new EmailBody();
            $body = $this->message['body'];
            $this->body->setContent(ContentDecoder::decode($body));
            $this->body->setBodyIsText(false);
        }
        return $this->body;
    }

    /**
     * Get email attachments
     *
     * @return EmailAttachment[]
     */
    public function getAttachments()
    {
        if ($this->attachments === null) {
            $this->attachments = array();
            $attachment = $this->message['attachments'];
            if ($this->getBody()->getContent() === self::EMAIL_EMPTY_BODY_CONTENT) {
                $attachments = $attachment === null ? [] : [$attachment];
            } else {
                $attachments = $this->message['attachments'];
            }

            foreach ($attachments as $a) {
                $fileSize = $a['size'];
                $filename = $a['filename'];

                if ($filename !== null) {
                    $contentId = (isset($a['content_id'])) ? str_replace(['<', '>'], '', $a['content_id']) : $a['id'];
                    $attachment = new EmailAttachment();
                    $attachment
                        ->setFileName($filename)
                        ->setFileSize($fileSize)
                        ->setContent($a['id'])
                        ->setContentType($a['content_type'])
                        ->setContentTransferEncoding('base64')
                        ->setContentId($contentId);
                    $attachment->setFileContentId($a['id']);
                    $this->attachments[] = $attachment;
                }
            }
        }

        return $this->attachments;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     *
     * @return $this
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHasAttachments()
    {
        return $this->has_attachments;
    }

    /**
     * @param bool $has_attachments
     */
    public function setHasAttachments($has_attachments)
    {
        $this->has_attachments = $has_attachments;
        return $this;
    }

}
