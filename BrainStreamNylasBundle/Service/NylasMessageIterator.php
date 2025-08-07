<?php

/**
 * Nylas Message Iterator Service.
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

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
use Oro\Bundle\ImapBundle\Mail\Storage\Message;

/**
 * Nylas Message Iterator Service.
 *
 * Implements Iterator and Countable interfaces for iterating over Nylas messages.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasMessageIterator implements \Iterator, \Countable
{
    private NylasClient $nylasClient;

    private ?array $ids = null;

    private bool $uidMode = false;

    private bool $reverse = false;

    private int $batchSize = 1;

    private ?\Closure $onBatchLoaded = null;

    private array $batch = [];

    private ?int $iterationMin = null;

    private ?int $iterationMax = null;

    private ?int $iterationPos = null;

    private NylasEmailFolder $emailFolder;

    private ?\DateTime $lastSynchronizedAt = null;

    /**
     * Constructor.
     *
     * @param NylasClient $nylasClient The Nylas client service
     * @param NylasEmailFolder $emailFolder The email folder
     * @param int[]|null $ids The message IDs
     * @param bool $uidMode Whether using message UIDs
     */
    public function __construct(
        NylasClient $nylasClient,
        NylasEmailFolder $emailFolder,
        array $ids = null,
        bool $uidMode = false
    ) {
        $this->nylasClient = $nylasClient;
        $this->emailFolder = $emailFolder;
        $this->ids = $ids;
        $this->uidMode = $uidMode;
    }

    /**
     * Sets iteration order. To avoid extra requests to Nylas server
     * the rewind() method should be executed manually on demand.
     *
     * @param bool $reverse Determines the iteration order. By default from newest messages to oldest
     *                      true for from newest messages to oldest
     *                      false for from oldest messages to newest
     *
     * @return void
     */
    public function setIterationOrder(bool $reverse): void
    {
        $this->reverse = $reverse;
    }

    /**
     * Set last email sync timestamp.
     *
     * @param \DateTime|null $lastSynchronizedAt The last synchronized timestamp
     *
     * @return void
     */
    public function setLastSynchronizedAt(?\DateTime $lastSynchronizedAt): void
    {
        $this->lastSynchronizedAt = $lastSynchronizedAt;
    }

    /**
     * Sets batch size.
     *
     * @param int $batchSize Determines how many messages can be loaded at once
     *
     * @return void
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Sets a callback function is called when a batch is loaded.
     *
     * @param \Closure|null $callback The callback function is called when a batch is loaded
     *                                function (Message[] $batch)
     *
     * @return void
     */
    public function setBatchCallback(\Closure $callback = null): void
    {
        $this->onBatchLoaded = $callback;
    }

    /**
     * The number of messages in this iterator.
     *
     * @return int
     */
    public function count(): int
    {
        $this->ensureInitialized();

        return $this->ids === null
            ? $this->iterationMax
            : $this->iterationMax + 1;
    }

    /**
     * Return the current element.
     *
     * @return Message
     */
    public function current(): mixed
    {
        return $this->batch[$this->iterationPos];
    }

    /**
     * Move forward to next element.
     *
     * @return void
     */
    public function next(): void
    {
        $this->increasePosition($this->iterationPos);

        if ($this->isValidPosition($this->iterationPos) && !array_key_exists($this->iterationPos, $this->batch)) {
            // initialize the batch
            $this->batch = [];
            if ($this->batchSize > 1) {
                $pos = $this->iterationPos;

                echo "Start import email from $this->iterationPos .\n";
                try {
                    $messages = $this->nylasClient->nylasClient->Messages->Message->list(
                        $this->nylasClient->nylasClient->Options->getGrantId(),
                        [
                            'in' => $this->emailFolder->getFolderUid(),
                            'limit' => $this->batchSize,
                            'fields' => 'include_headers',
                            'received_after' => $this->lastSynchronizedAt ? $this->lastSynchronizedAt->getTimestamp() : null
                        ]
                    );
                } catch (\Exception $e) {
                    echo "Exception occur ad:" . $e->getMessage();
                }

                foreach ($messages['data'] as $message) {
                    $this->batch[$pos] = $message;
                    $this->increasePosition($pos);
                }
            } else {
                $this->batch[$this->iterationPos] = $this->nylasClient->nylasClient->Messages->Message->find(
                    $this->nylasClient->nylasClient->Options->getGrantId(),
                    (string)$this->getMessageId($this->iterationPos)
                );
            }
            if ($this->onBatchLoaded !== null) {
                call_user_func($this->onBatchLoaded, $this->batch);
            }
        }
    }

    /**
     * Return the key of the current element.
     *
     * @return int|null on success, or null on failure.
     */
    public function key(): mixed
    {
        return $this->iterationPos;
    }

    /**
     * Checks if current position is valid.
     *
     * @return bool Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        $this->ensureInitialized();

        return isset($this->batch[$this->iterationPos]);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->initialize();

        $this->iterationPos = $this->reverse
            ? ($this->iterationMax + 1)
            : ($this->iterationMin - 1);

        $this->batch = [];

        $this->next();
    }

    /**
     * Makes sure the Iterator is ready to work.
     *
     * @return void
     */
    protected function ensureInitialized(): void
    {
        if ($this->iterationMin === null || $this->iterationMax === null) {
            $this->initialize();
        }
    }

    /**
     * Prepares the Iterator to work.
     *
     * @return void
     */
    protected function initialize(): void
    {
        if ($this->ids === null) {
            $this->iterationMin = 1;
            $messageCounter = $this->nylasClient->nylasClient->Messages->Message->list(
                $this->nylasClient->nylasClient->Options->getGrantId(),
                [
                    'in' => $this->emailFolder->getFolderUid(),
                    'received_after' => $this->lastSynchronizedAt ? $this->lastSynchronizedAt->getTimestamp() : null,
                    'fields' => 'include_headers'
                ]
            );
            $this->iterationMax = count($messageCounter['data']);
        } else {
            $this->iterationMin = 0;
            $this->iterationMax = count($this->ids) - 1;
        }
    }

    /**
     * Get a message id by its position in the Iterator.
     *
     * @param int $pos The position
     *
     * @return string
     */
    protected function getMessageId(int $pos): string
    {
        return $this->ids === null
            ? (string)$pos
            : (string)$this->ids[$pos];
    }

    /**
     * Move the given position of the Iterator to the next element.
     *
     * @param int $pos The position to increase
     *
     * @return void
     */
    protected function increasePosition(int &$pos): void
    {
        if ($this->reverse) {
            --$pos;
        } else {
            ++$pos;
        }
    }

    /**
     * Checks if the given position is valid.
     *
     * @param int $pos The position to check
     *
     * @return bool
     */
    protected function isValidPosition(int $pos): bool
    {
        return
            $pos !== null
            && $pos >= $this->iterationMin
            && $pos <= $this->iterationMax;
    }
}
