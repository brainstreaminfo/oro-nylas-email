<?php

/**
 * Nylas Email Attachment DTO.
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

/**
 * Nylas Email Attachment DTO.
 *
 * Data Transfer Object for Nylas email attachment data.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager\DTO
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailAttachment
{
    protected string $fileName = '';

    protected int $fileSize = 0;

    protected string $contentType = '';

    protected string $contentTransferEncoding = '';

    protected string $content = '';

    protected ?string $contentId = null;

    protected ?string $fileContentId = null;

    /**
     * Get attachment file name.
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Set attachment file name.
     *
     * @param string $fileName The file name
     *
     * @return self
     */
    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * Get content type. It may be any MIME type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Set content type.
     *
     * @param string $contentType Any MIME type
     *
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get encoding type of attachment content.
     *
     * @return string
     */
    public function getContentTransferEncoding(): string
    {
        return $this->contentTransferEncoding;
    }

    /**
     * Set encoding type of attachment content.
     *
     * @param string $contentTransferEncoding The content transfer encoding
     *
     * @return self
     */
    public function setContentTransferEncoding(string $contentTransferEncoding): self
    {
        $this->contentTransferEncoding = $contentTransferEncoding;

        return $this;
    }

    /**
     * Get content of email attachment.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set content of email attachment.
     *
     * @param string $content The content
     *
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content ID.
     *
     * @return string|null
     */
    public function getContentId(): ?string
    {
        return $this->contentId;
    }

    /**
     * Set content ID.
     *
     * @param string|null $contentId The content ID
     *
     * @return self
     */
    public function setContentId(?string $contentId): self
    {
        $this->contentId = $contentId;
        return $this;
    }

    /**
     * Get file content ID.
     *
     * @return string|null
     */
    public function getFileContentId(): ?string
    {
        return $this->fileContentId;
    }

    /**
     * Set file content ID.
     *
     * @param string|null $fileContentId The file content ID
     *
     * @return self
     */
    public function setFileContentId(?string $fileContentId): self
    {
        $this->fileContentId = $fileContentId;
        return $this;
    }

    /**
     * Get attachment file size in bytes.
     *
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Set attachment file size in bytes.
     *
     * @param int $fileSize The file size in bytes
     *
     * @return self
     */
    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }
}
