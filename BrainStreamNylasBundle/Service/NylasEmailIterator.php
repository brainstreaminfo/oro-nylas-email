<?php

/**
 * Nylas Email Iterator Service.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Service;

use BrainStream\Bundle\NylasBundle\Manager\DTO\Email;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;

/**
 * Nylas Email Iterator Service.
 *
 * Implements Iterator and Countable interfaces for iterating over Nylas emails.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailIterator implements \Iterator, \Countable
{
    private NylasMessageIterator $iterator;

    private NylasEmailManager $manager;

    private ?array $batch = null;

    private \Closure $onBatchLoaded;

    private ?\Closure $onConvertError = null;

    private ?int $iterationPos = 0;

    /**
     * Constructor.
     *
     * @param NylasMessageIterator $iterator The message iterator
     * @param NylasEmailManager    $manager  The email manager
     */
    public function __construct(NylasMessageIterator $iterator, NylasEmailManager $manager)
    {
        $this->iterator = $iterator;
        $this->manager = $manager;

        $this->onBatchLoaded = function ($batch) {
            $this->handleBatchLoaded($batch);
        };
        $this->setBatchCallback();
    }

    /**
     * Sets iteration order.
     *
     * @param bool $reverse Determines the iteration order. By default from newest emails to oldest
     *                      true for from newest emails to oldest
     *                      false for from oldest emails to newest
     *
     * @return void
     */
    public function setIterationOrder(bool $reverse): void
    {
        $this->iterator->setIterationOrder($reverse);
    }

    /**
     * Set last email sync timestamp.
     *
     * @param mixed $lastSynchronizedAt The last synchronized timestamp
     *
     * @return void
     */
    public function setLastSynchronizedAt($lastSynchronizedAt): void
    {
        $this->iterator->setLastSynchronizedAt($lastSynchronizedAt);
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
        $this->iterator->setBatchSize($batchSize);
    }

    /**
     * Sets a callback function is called when a batch is loaded.
     *
     * @param \Closure|null $callback The callback function is called when a batch is loaded
     *                                function (Email[] $batch)
     *
     * @return void
     */
    public function setBatchCallback(\Closure $callback = null): void
    {
        if ($callback === null) {
            // restore default callback
            $this->iterator->setBatchCallback($this->onBatchLoaded);
        } else {
            $iteratorCallback = function ($batch) use ($callback) {
                call_user_func($this->onBatchLoaded, $batch);
                call_user_func($callback, $this->batch);
            };
            $this->iterator->setBatchCallback($iteratorCallback);
        }
    }

    /**
     * Sets a callback function that will handle message convert error. If this callback set then iterator will work
     * in fail safe mode invalid messages will just skipped.
     *
     * @param \Closure|null $callback The callback function.
     *                                function (\Exception)
     *
     * @return void
     */
    public function setConvertErrorCallback(\Closure $callback = null): void
    {
        $this->onConvertError = $callback;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function count(): int
    {
        return $this->iterator->count();
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->batch[$this->iterationPos];
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function next(): void
    {
        $this->iterationPos++;

        // call the underlying iterator to make sure a batch is loaded
        // actually $this->batch is initialized at this moment
        $this->iterator->next();

        // skip invalid messages (they are not added to $this->batch)
        while (!isset($this->batch[$this->iterationPos]) && $this->iterator->valid()) {
            $this->iterationPos++;
            $this->iterator->next();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->iterationPos;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->batch[$this->iterationPos]);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->iterationPos = 0;
        $this->batch = [];

        $this->iterator->rewind();
    }

    /**
     * Handle batch loaded event.
     *
     * @param array $batch The batch of messages
     *
     * @throws \Exception
     *
     * @return void
     */
    protected function handleBatchLoaded(array $batch): void
    {
        $this->batch = [];

        $counter = 0;
        foreach ($batch as $key => $val) {
            if (!$val) {
                echo("\n handleBatchLoaded:" . $key . "=>" . $val);
                echo("\n ERRO:" . count($this->batch) . "\n\n\n\n");
                continue;
            }

            try {
                $email = $this->manager->convertToEmail($val);
            } catch (\Exception $e) {
                if (null !== $this->onConvertError) {
                    echo(" ------------------------------------------ catch:");
                    call_user_func($this->onConvertError, $e);
                    $email = null;
                } else {
                    echo(" ----------------------------------------- throw:");
                    throw $e;
                }
            }

            // do not add invalid messages to $this->batch
            if ($email !== null) {
                $this->batch[$this->iterationPos + $counter] = $email;
            }
            $counter++;
        }
    }
}
