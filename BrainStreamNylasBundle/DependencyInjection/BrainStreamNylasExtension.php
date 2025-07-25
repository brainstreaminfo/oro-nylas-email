<?php

namespace BrainStream\Bundle\NylasBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class BrainStreamNylasExtension extends Extension //implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        //file_put_contents(__DIR__ . '/debugbst.log', "BrainStreamNylasExtension loaded\n", FILE_APPEND);
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        if (isset($config['settings'])) {
            $container->prependExtensionConfig($this->getAlias(), ['settings' => $config['settings']]);
        }
    }

    /*public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'form_themes' => [
                '@BrainStreamNylas/Form/fields.html.twig'
            ]
        ]);
        // Optional: Prepend configuration for other extensions (e.g., routing, twig)
    }*/

    /**
     * ref:set alias
     * @return string
     */
    #[\Override]
    public function getAlias(): string
    {
        return 'brainstream_nylas';
    }
}
