<?php

/**
 * Nylas Email DTO.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager\DTO
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Manager\DTO;

use Oro\Bundle\EmailBundle\Decoder\ContentDecoder;
use Oro\Bundle\EmailBundle\Model\EmailHeader;

/**
 * Nylas Email DTO.
 *
 * Data Transfer Object for Nylas email data.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager\DTO
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class Email extends EmailHeader
{
    public const FORMAT_TEXT = false;

    public const FORMAT_HTML = true;

    public const EMAIL_EMPTY_BODY_CONTENT = "\n";

    private array $message = [];

    private ?string $id = null;

    private bool $unread = false;

    private bool $hasAttachment = false;

    private string $uid = '';

    private ?EmailBody $body = null;

    private ?array $attachments = null;

    /**
     * Constructor for Email DTO.
     *
     * @param array $message The email message data
     */
    public function __construct(array $message)
    {
        $this->message = $message;
    }

    /**
     * Get id.
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set item id.
     *
     * @param string $id The id
     *
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get unread status.
     *
     * @return bool|null
     */
    public function getUnread(): ?bool
    {
        return $this->unread;
    }

    /**
     * Set unread status.
     *
     * @param bool $unread The unread status
     *
     * @return self
     */
    public function setUnread(bool $unread): self
    {
        $this->unread = $unread;
        return $this;
    }

    /**
     * Get has attachment status.
     *
     * @return bool|null
     */
    public function getHasAttachment(): ?bool
    {
        return $this->hasAttachment;
    }

    /**
     * Set has attachment status.
     *
     * @param bool $hasAttachment The has attachment status
     *
     * @return self
     */
    public function setHasAttachment(bool $hasAttachment): self
    {
        $this->hasAttachment = $hasAttachment;

        return $this;
    }

    /**
     * Get email body.
     *
     * @return EmailBody
     */
    public function getBody(): EmailBody
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
     * Get email attachments.
     *
     * @return EmailAttachment[]
     */
    public function getAttachments(): array
    {
        if ($this->attachments === null) {
            $this->attachments = [];
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
     * Get uid.
     *
     * @return string|null
     */
    public function getUid(): ?string
    {
        return $this->uid;
    }

    /**
     * Set uid.
     *
     * @param string $uid The uid
     *
     * @return self
     */
    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    /**
     * Check if has attachments.
     *
     * @return bool
     */
    public function isHasAttachments(): bool
    {
        return $this->hasAttachment;
    }

    /**
     * Set has attachments.
     *
     * @param bool $hasAttachments The has attachments status
     *
     * @return self
     */
    public function setHasAttachments(bool $hasAttachments): self
    {
        $this->hasAttachment = $hasAttachments;
        return $this;
    }
}
