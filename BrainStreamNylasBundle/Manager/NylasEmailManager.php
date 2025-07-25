<?php

namespace BrainStream\Bundle\NylasBundle\Manager;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailAddress;
use BrainStream\Bundle\NylasBundle\Manager\DTO\Email;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;
use BrainStream\Bundle\NylasBundle\Service\NylasEmailIterator;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Model\EmailHeader;
use Oro\Bundle\ImapBundle\Util\DateTimeParser;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;


/**
 * Class ImapEmailManager
 *
 * @package Oro\Bundle\ImapBundle\Manager
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class NylasEmailManager
{
    /**
     * According to RFC 2822
     */
    const SUBJECT_MAX_LENGTH = 998;

    private $currentFolder;

    /** @var NylasClient */
    private $client;

    /** @var EmailEntityBuilder */
    private $emailEntityBuilder;

    /** @var EntityManager */
    private $entityManager;

    /**
     * @param NylasClient $connector
     */
    public function __construct(NylasClient $client, EmailEntityBuilder $entityBuilder, EntityManager $entityManager)
    {
        $this->client             = $client;
        $this->emailEntityBuilder = $entityBuilder;
        $this->entityManager      = $entityManager;
    }

    /**
     * Set selected folder
     *
     * @param string $folder
     */
    public function selectFolder($folder)
    {
        $this->currentFolder = $folder;
    }

    /**
     * Retrieve email by its UID
     *
     * @param string $nylasEmailId
     *
     * @return EmailHeader|null
     * @throws \RuntimeException When message can't be parsed correctly
     */
    public function findEmail($nylasEmailId)
    {
        try {
            $msg = $this->client->nylasClient->Messages()->Message()->getMessage($nylasEmailId);
            return $this->convertToEmail($msg[$nylasEmailId], false);
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * Creates Email DTO for the given email message
     *
     * @param array $nylasEmailContent
     *
     * @parma bool $flag
     *
     * @parma bool $flag
     *
     * @return Email|null
     *
     * @throws \RuntimeException if the given message cannot be converted to {@see Email} object
     */
    public function convertToEmail(array $nylasEmailContent, $flag = true)
    {
        $messageId = $this->client->getMessageId($nylasEmailContent, 'Message-Id', $nylasEmailContent['id']);

        if ((count($nylasEmailContent['from']) == 0 || $messageId == null)  && $flag) {
            return null;
        }
        /** @var Email $email */
        $email = new Email($nylasEmailContent, $this->client);
        try {
            $email
                ->setId($nylasEmailContent['id'])
                ->setUnread(($nylasEmailContent['unread']) ? false : true)
                ->setUid($nylasEmailContent['id'])
                ->setMessageId($messageId)
                ->setSubject(
                    $this->getString($nylasEmailContent, 'subject', self::SUBJECT_MAX_LENGTH)
                )
                ->setFrom($this->emailEntityBuilder->address($this->getFromString($nylasEmailContent, 'email'))->getEmail())
                ->setSentAt($this->getDateTime($nylasEmailContent, 'date'))
//                ->setReceivedAt($this->getReceivedAt($nylasEmailContent))
                ->setReceivedAt($this->getDateTime($nylasEmailContent, 'date'))
                ->setInternalDate(new \DateTime())
                ->setImportance($this->getImportance($nylasEmailContent))
                //->setRefs($this->getReferences($nylasEmailContent, 'References')) //ref:adbrain commented as not found
                //->setXMessageId($this->getReferences($nylasEmailContent, 'In-Reply-To'))
                ->setXThreadId($this->getString($nylasEmailContent, 'thread_id'))
                ->setMultiMessageId($this->getMultiMessageId($nylasEmailContent, 'id'))
                ->setAcceptLanguageHeader($this->getAcceptLanguageHeader($nylasEmailContent));

            foreach ($this->getRecipients($nylasEmailContent, 'to') as $val) {
                $email->addToRecipient($val);
            }
            foreach ($this->getRecipients($nylasEmailContent, 'cc') as $val) {
                $email->addCcRecipient($val);
            }
            foreach ($this->getRecipients($nylasEmailContent, 'bcc') as $val) {
                $email->addBccRecipient($val);
            }

            $emailHasAttachments = false;
            if (count($nylasEmailContent['attachments']) > 0) {
                $emailHasAttachments = true;
            }
            $email->setHasAttachment($emailHasAttachments);

            return $email;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot parse email message. Subject: %s. Error: %s',
                    $nylasEmailContent['subject'],
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Returns Accept-Language header from headers.
     *
     * @param array $nylasEmailContent
     *
     * @return string
     */
    protected function getAcceptLanguageHeader(array $nylasEmailContent)
    {
        $header = isset($nylasEmailContent['Accept-Language']) ?? false;

        if ($header === false) {
            return '';
        } elseif (!$header instanceof \ArrayIterator) {
            $header = new \ArrayIterator([$header]);
        }

        $items = [];
        $header->rewind();
        while ($header->valid()) {
            $items[] = AcceptHeaderItem::fromString($header->current());
            $header->next();
        }

        $acceptHeader = new AcceptHeader($items);

        return (string)$acceptHeader;
    }

    /**
     * Gets a string representation of an email header
     *
     * @param array  $nylasEmailContent
     * @param string $name
     * @param int    $lengthLimit if more than 0 returns part of header specified length
     *
     * @return string
     *
     * @throws \RuntimeException if a value of the requested header cannot be converted to a string
     */
    protected function getString(array $nylasEmailContent, $name, $lengthLimit = 0)
    {
        $header = $nylasEmailContent[$name];
        if ($header === false) {
            return '';
        } elseif ($header instanceof \ArrayIterator) {
            $values = [];
            $header->rewind();
            while ($header->valid()) {
                $values[] = sprintf('"%s"', $header->current());
                $header->next();
            }
            throw new \RuntimeException(
                sprintf(
                    'It is expected that the header "%s" has a string value, '
                    . 'but several values are returned. Values: %s.',
                    $name,
                    implode(', ', $values)
                )
            );
        }

        $headerValue = $header;
        if ($lengthLimit > 0 && $lengthLimit < mb_strlen($headerValue)) {
            $headerValue = mb_strcut($headerValue, 0, $lengthLimit);
        }

        return addslashes($headerValue);
    }

    /**
     * Gets a from string representation of an email header
     *
     * @param array  $nylasEmailContent
     * @param string $name
     * @param int    $lengthLimit if more than 0 returns part of header specified length
     *
     * @return string
     *
     * @throws \RuntimeException if a value of the requested header cannot be converted to a string
     */
    protected function getFromString(array $nylasEmailContent, $name)
    {
        $header = $nylasEmailContent['from'][0][$name];
        if ($header === false) {
            return '';
        } elseif ($header instanceof \ArrayIterator) {
            $values = [];
            $header->rewind();
            while ($header->valid()) {
                $values[] = sprintf('"%s"', $header->current());
                $header->next();
            }
            throw new \RuntimeException(
                sprintf(
                    'It is expected that the header "%s" has a string value, '
                    . 'but several values are returned. Values: %s.',
                    $name,
                    implode(', ', $values)
                )
            );
        }

        $emailAddress = $this->entityManager
            ->getRepository(NylasEmailAddress::class)
            ->createQueryBuilder('nylas_email_address')
            ->select('nylas_email_address.email')
            ->where('nylas_email_address.email = :email')
            ->setParameter('email', $header)
            ->getQuery()
            ->getOneOrNullResult();

        if ($emailAddress) {
            return $emailAddress['email'];
        } else {
            return $header;
        }
    }

    /**
     * @param array   $nylasEmailContent
     * @param         $name
     *
     * @return array|null
     */
    protected function getMultiMessageId(array $nylasEmailContent, $name)
    {
        if (!isset($nylasEmailContent[$name])) {
            return null;
        }
        $header = $nylasEmailContent[$name];
        $values = [];
        if (!$header instanceof \ArrayIterator) {
            $header = new \ArrayIterator([$header]);
        }

        $header->rewind();
        while ($header->valid()) {
            $values[] = $header->current();
            $header->next();
        }

        return $values;
    }

    /**
     * Gets a email references header
     *
     * @param array  $nylasEmailContent
     * @param string $name
     *
     * @return string|null
     */
    protected function getReferences(array $nylasEmailContent, $name)
    {
        $values = [];
        $header = $nylasEmailContent['headers'][$name];
        if ($header === false) {
            return null;
        } elseif (is_array($header)) {
            foreach ($header as $attr) {
                $values[] = sprintf('"%s"', $attr);
            }
        } else {
            $values[] = $header;
        }

        return implode(' ', $values);
    }

    /**
     * Gets an email header as DateTime type
     *
     * @param array  $email
     * @param string $name
     *
     * @return \DateTime
     * @throws \Exception if header contain incorrect DateTime string
     */
    protected function getDateTime(array $email, $name)
    {
        $val     = $email[$name];
        $newDate = new \DateTime();
        $newDate->setTimestamp($val);
        return $newDate;
    }

    /**
     * Gets DateTime when an email is received
     *
     * @param array $email
     *
     * @return \DateTime
     * @throws \Exception if Received header contain incorrect DateTime string
     */
    protected function getReceivedAt(array $email)
    {
        $val = $email['Received'];
        $str = '';
        /*if ($val instanceof HeaderInterface) {
            $str = $val();
        } else*/
        if ($val instanceof \ArrayIterator) {
            $val->rewind();
            $str = $val->current();
        }

        $delim = strrpos($str, ';');
        if ($delim !== false) {
            $str = trim(preg_replace('@[\r\n]+@', '', substr($str, $delim + 1)));

            return $this->convertToDateTime($str);
        }

        return new \DateTime('0001-01-01', new \DateTimeZone('UTC'));
    }

    /**
     * Get an email recipients
     *
     * @param array  $nylasEmailContent
     * @param string $name
     *
     * @return string[]
     */
    protected function getRecipients(array $nylasEmailContent, $name)
    {
        if (!isset($nylasEmailContent[$name])) {
            return [];
        }
        $result = array();
        $val    = $nylasEmailContent[$name];
        foreach ($val as $addr) {
            $email    = str_replace('"', "", json_encode($addr['email']));
            $result[] = str_replace("'", "", $email);
        }

        return $result;
    }

    /**
     * Gets an email importance
     *
     * @param array $nylasEmailContent
     *
     * @return integer
     */
    protected function getImportance(array $nylasEmailContent)
    {
        $importance = isset($nylasEmailContent['importance']) ?? null;
        switch (strtolower($importance)) {
            case 'high':
                return 1;
            case 'low':
                return -1;
            default:
                return 0;
        }
    }

    /**
     * Convert a string to DateTime
     *
     * @param string $value
     *
     * @return \DateTime
     *
     * @throws \Exception
     */
    protected function convertToDateTime($value)
    {
        return DateTimeParser::parse($value);
    }

    protected static function removeEmoji($text)
    {
        $clean_text = '';

        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clean_text     = preg_replace($regexEmoticons, '', $text);

        // Match Miscellaneous Symbols and Pictographs
        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clean_text   = preg_replace($regexSymbols, '', $clean_text);

        // Match Transport And Map Symbols
        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clean_text     = preg_replace($regexTransport, '', $clean_text);

        // Match Miscellaneous Symbols
        $regexMisc  = '/[\x{2600}-\x{26FF}]/u';
        $clean_text = preg_replace($regexMisc, '', $clean_text);

        // Match Dingbats
        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $clean_text    = preg_replace($regexDingbats, '', $clean_text);

        return $clean_text;
    }
}
