<?php

namespace BrainStream\Bundle\NylasBundle\Service;

//use Leme\Bundle\EmailBundle\Entity\EmailFolder;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailFolder;
//use BrainStream\Bundle\NylasBundle\Manager\EmailFolder;
use Oro\Bundle\ImapBundle\Mail\Storage\Message;

class NylasMessageIterator implements \Iterator, \Countable
{
    /** @var NylasClient */
    private $nylasClient;

    /** @var int[]|null */
    private $ids;

    /** @var bool using message uids */
    private $uidMode;

    /** @var bool */
    private $reverse = false;

    /** @var int */
    private $batchSize = 1;

    /** @var \Closure|null */
    private $onBatchLoaded;

    /** @var Message[] an array is indexed by the Iterator keys */
    private $batch = [];

    /** @var int|null */
    private $iterationMin;

    /** @var int|null */
    private $iterationMax;

    /** @var int|null */
    private $iterationPos;

    /** @var NylasEmailFolder $emailFolder */
    private $emailFolder;

    /** @var \DateTime */
    private $lastSynchronizedAt;

    /**
     * Constructor
     *
     * @param NylasClient      $nylasClient
     * @param NylasEmailFolder      $emailFolder
     * @param int[]|null       $ids
     * @param bool             $uidMode
     */
    public function __construct(NylasClient $nylasClient, NylasEmailFolder $emailFolder, array $ids = null, $uidMode = false)
    {
        $this->nylasClient = $nylasClient;
        $this->emailFolder = $emailFolder;
        $this->ids         = $ids;
        $this->uidMode     = $uidMode;
    }

    /**
     * Sets iteration order. To avoid extra requests to Nylas server
     * the rewind() method should be executed manually on demand.
     *
     * @param bool $reverse Determines the iteration order. By default from newest messages to oldest
     *                      true for from newest messages to oldest
     *                      false for from oldest messages to newest
     */
    public function setIterationOrder($reverse)
    {
        $this->reverse = $reverse;
    }

    /**
     * Set lasy email sync timestamp
     *
     * @param $lastSynchronizedAt
     */
    public function setLastSynchronizedAt($lastSynchronizedAt)
    {
        $this->lastSynchronizedAt = $lastSynchronizedAt;
    }

    /**
     * Sets batch size
     *
     * @param int $batchSize Determines how many messages can be loaded at once
     */
    public function setBatchSize($batchSize)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Sets a callback function is called when a batch is loaded
     *
     * @param \Closure|null $callback The callback function is called when a batch is loaded
     *                                function (Message[] $batch)
     */
    public function setBatchCallback(\Closure $callback = null)
    {
        $this->onBatchLoaded = $callback;
    }

    /**
     * The number of messages in this iterator
     *
     * @return int
     */
    public function count()
    {
        $this->ensureInitialized();

        return $this->ids === null
            ? $this->iterationMax
            : $this->iterationMax + 1;
    }

    /**
     * Return the current element
     *
     * @return Message
     */
    public function current()
    {
        return $this->batch[$this->iterationPos];
    }

    /**
     * Move forward to next element
     */
    public function next()
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
                            //'offset' => ($this->iterationPos - 1),
                            'fields' => 'include_headers',
                            'received_after' => $this->lastSynchronizedAt
                        ]
                    );
                } catch (\Exception $e) {
                    echo "Exception occur ad:".$e->getMessage();
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
     * Return the key of the current element
     *
     * @return int on success, or null on failure.
     */
    public function key()
    {
        return $this->iterationPos;
    }

    /**
     * Checks if current position is valid
     *
     * @return boolean Returns true on success or false on failure.
     */
    public function valid()
    {
        $this->ensureInitialized();

        return isset($this->batch[$this->iterationPos]);
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        $this->initialize();

        $this->iterationPos = $this->reverse
            ? ($this->iterationMax + 1)
            : ($this->iterationMin - 1);

        $this->batch = [];

        $this->next();
    }

    /**
     * Makes sure the Iterator is ready to work
     */
    protected function ensureInitialized()
    {
        if ($this->iterationMin === null || $this->iterationMax === null) {
            $this->initialize();
        }
    }

    /**
     * Prepares the Iterator to work
     */
    protected function initialize()
    {
        if ($this->ids === null) {
            $this->iterationMin = 1;
            $messageCounter = $this->nylasClient->nylasClient->Messages->Message->list(
                $this->nylasClient->nylasClient->Options->getGrantId(),
                [
                    'in'   => $this->emailFolder->getFolderUid(),
                    'received_after' => $this->lastSynchronizedAt,
                    'fields'         => 'include_headers'
                ]
            );
            $this->iterationMax = count($messageCounter['data']);
        } else {
            $this->iterationMin = 0;
            $this->iterationMax = count($this->ids) - 1;
        }
    }

    /**
     * Get a message id by its position in the Iterator
     *
     * @param int $pos
     *
     * @return string
     */
    protected function getMessageId($pos)
    {
        return $this->ids === null
            ? $pos
            : $this->ids[$pos];
    }

    /**
     * Move the given position of the Iterator to the next element
     *
     * @param int $pos
     */
    protected function increasePosition(&$pos)
    {
        if ($this->reverse) {
            --$pos;
        } else {
            ++$pos;
        }
    }

    /**
     * Checks if the given position is valid
     *
     * @param int $pos
     *
     * @return boolean
     */
    protected function isValidPosition($pos)
    {
        return
            $pos !== null
            && $pos >= $this->iterationMin
            && $pos <= $this->iterationMax;
    }
}
