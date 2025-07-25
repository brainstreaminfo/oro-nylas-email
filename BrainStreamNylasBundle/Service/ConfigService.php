<?php

namespace BrainStream\Bundle\NylasBundle\Service;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

class ConfigService
{
    private ConfigManager $configManager;

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
        //dump($this->configManager);
        //dump((string) $this->configManager->get('nylas.client_id', false, false, false));
        //dump((string) $this->configManager->get('nylas.client_id'));
        //dump((string) $this->configManager->get('nylas.settings.client_id', false, false, false));
        //dump((string) $this->configManager->get('nylas.settings.client_id'));

    }

    public function getClientId(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.client_id', false, false, false) ?? null;
    }

    public function getClientSecret(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.client_secret', false, false, false) ?? null;
    }

    public function getRegion(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.region', false, false, false) ?? null;
    }

    public function getApiUrl(): ?string
    {
        return (string) $this->configManager->get('brainstream_nylas.api_url', false, false, false)?? null;
    }
}
