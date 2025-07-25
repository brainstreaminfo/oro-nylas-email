<?php

namespace BrainStream\Bundle\NylasBundle;

use BrainStream\Bundle\NylasBundle\DependencyInjection\BrainStreamNylasExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BrainStreamNylasBundle extends Bundle
{
    public function __construct()
    {
        //file_put_contents(__DIR__ . '/bundle_debug.log', "BrainStreamNylasBundle instantiated\n", FILE_APPEND);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new BrainStreamNylasExtension();
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }

   /*
    public function getConfigurationDir(): string
    {
        return __DIR__ . '/Resources/config';
    }


    public function getRoutingConfigurationFiles($environment): array
    {
        return [
            'oro/routing.yml'
        ];
    }
   */
}
