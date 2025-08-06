<?php

/**
 * Nylas API Client.
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

use Nylas\Client;
use Nylas\Exceptions\NylasException;
use Nylas\Utilities\Options;

/**
 * Nylas API Client.
 *
 * Extends the Nylas Client for custom API functionality.
 *
 * @method NylasFolders\Abs NylasFolders()
 * @method NylasLabels\Abs NylasLabels()
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasApiClient extends Client
{
    /**
     * Constructor for NylasApiClient.
     *
     * @param array $options The Nylas options
     */
    public function __construct(array $options)
    {
        parent::__construct($options);
    }

    /**
     * Call Nylas APIs.
     *
     * @param string $name      The API name
     * @param array  $arguments The API arguments
     *
     * @return object The API class instance
     *
     * @throws NylasException
     */
    public function __call(string $name, array $arguments): object
    {
        if (in_array(ucfirst($name), ['NylasFolders', 'NylasLabels'])) {
            $apiClass = __NAMESPACE__ . '\\' . ucfirst($name) . '\\Abs';
        } else {
            $apiClass = 'Nylas\\' . ucfirst($name) . '\\Abs';
        }
        // check class exists
        if (!class_exists($apiClass)) {
            throw new NylasException("class {$apiClass} not found!");
        }

        return new $apiClass($this->options);
    }
}
