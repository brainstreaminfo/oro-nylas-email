<?php

/**
 * Replace Embedded Attachments Listener.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\EventListener;

use Oro\Bundle\EmailBundle\Entity\EmailAttachment;
use Oro\Bundle\EmailBundle\Event\EmailBodyLoaded;

/**
 * Replace Embedded Attachments Listener.
 *
 * Listener for replacing embedded attachments in email bodies.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class ReplaceEmbeddedAttachmentsListener
{
    /**
     * Replace embedded attachments in email body.
     *
     * @param EmailBodyLoaded $event The email body loaded event
     *
     * @return void
     */
    public function replace(EmailBodyLoaded $event): void
    {
        $emailBody = $event->getEmail()->getEmailBody();
        if ($emailBody !== null) {
            $content = $emailBody->getBodyContent();
            $attachments = $emailBody->getAttachments();
            $replacements = [];
            if (!$emailBody->getBodyIsText()) {
                foreach ($attachments as $attachment) {
                    $contentId = $attachment->getEmbeddedContentId();
                    if ($contentId !== null && $this->supportsAttachment($attachment) && $attachment->getFileName() !== $attachment->getContent()->getContent()) {
                        $replacement = sprintf(
                            'data:%s;base64,%s',
                            $attachment->getContentType(),
                            $attachment->getContent()->getContent()
                        );
                        $replacements['cid:' . $contentId] = $replacement;
                    }
                }
                $emailBody->setBodyContent(strtr($content, $replacements));
            }
        }
    }

    /**
     * Check if attachment is supported for replacement.
     *
     * @param EmailAttachment $attachment The email attachment
     *
     * @return bool
     */
    protected function supportsAttachment(EmailAttachment $attachment): bool
    {
        return $attachment->getContent()->getContentTransferEncoding() === 'base64'
            && strpos($attachment->getContentType(), 'image/') === 0;
    }
}
