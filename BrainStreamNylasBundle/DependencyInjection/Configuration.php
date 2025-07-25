<?php

namespace BrainStream\Bundle\NylasBundle\DependencyInjection;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_NODE = 'nylas';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);
        $rootNode = $treeBuilder->getRootNode();

        SettingsBuilder::append($rootNode, [
            'client_id' => ['value' => 'client-id', 'scope' => 'app', 'type' => 'scalar'],
            'client_secret' => ['value' => 'client-secret(api key)', 'scope' => 'app', 'type' => 'scalar'],
            'region' => ['value' => 'us', 'scope' => 'app', 'type' => 'scalar'],
            'api_url' => ['value' => 'https://api.us.nylas.com/', 'scope' => 'app', 'type' => 'scalar'],
        ]);

        return $treeBuilder;
    }
}
