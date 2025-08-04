<?php

/**
 * Nylas Email Body DTO.
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
 * Nylas Email Body DTO.
 *
 * Data Transfer Object for Nylas email body data.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Manager\DTO
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailBody
{
    protected string $content = '';

    protected bool $bodyIsText = false;

    /**
     * Get body content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set body content.
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
     * Indicate whether email body is a text or html.
     *
     * @return bool True if body is text/plain; otherwise, the body content is text/html
     */
    public function getBodyIsText(): bool
    {
        return $this->bodyIsText;
    }

    /**
     * Set body content type.
     *
     * @param bool $bodyIsText True for text/plain, false for text/html
     *
     * @return self
     */
    public function setBodyIsText(bool $bodyIsText): self
    {
        $this->bodyIsText = $bodyIsText;

        return $this;
    }
}
