<?php

/**
 * Config Service.
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

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

/**
 * Config Service.
 *
 * Service for managing Nylas configuration settings.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class ConfigService
{
    private ConfigManager $configManager;

    /**
     * Constructor.
     *
     * @param ConfigManager $configManager The config manager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * Get client ID.
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.client_id', false, false, false) ?? null;
    }

    /**
     * Get client secret.
     *
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.client_secret', false, false, false) ?? null;
    }

    /**
     * Get region.
     *
     * @return string|null
     */
    public function getRegion(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.region', false, false, false) ?? null;
    }

    /**
     * Get API URL.
     *
     * @return string|null
     */
    public function getApiUrl(): ?string
    {
        $region = $this->getRegion();

        if ($region === 'EU') {
            $apiUrl = 'https://api.eu.nylas.com';
        } else {
            // Default to US
            $apiUrl = 'https://api.us.nylas.com';
        }

        // Quick test
        //echo "Region: " . $region . " | API URL: " . $apiUrl; exit;

        return $apiUrl;
    }
}
