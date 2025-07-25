<?php

namespace BrainStream\Bundle\NylasBundle\Service;

use Nylas\Client;
use Nylas\Exceptions\NylasException;
use Nylas\Utilities\Options;

/**
 * ----------------------------------------------------------------------------------
 * Nylas Client
 * @method NylasFolders\Abs NylasFolders()
 * @method NylasLabels\Abs NylasLabels()
 * ----------------------------------------------------------------------------------
 *
 * @author lanlin
 * @change 2018/11/26
 */
class NylasApiClient extends Client
{
    /**
     * @var Options
     */
    private $options;

    public function __construct(array $options)
    {
        parent::__construct($options);
    }

    /**
     * call nylas apis
     *
     * @param string $name
     * @param array $arguments
     * @return object
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array(ucfirst($name), ['NylasFolders', 'NylasLabels'])) {
            $apiClass = __NAMESPACE__ . '\\' . ucfirst($name) . '\\Abs';
        } else {
            $apiClass = 'Nylas\\' . ucfirst($name) . '\\Abs';
        }
        // check class exists
        if (!class_exists($apiClass))
        {
            throw new NylasException("class {$apiClass} not found!");
        }

        return new $apiClass($this->options);
    }
}
