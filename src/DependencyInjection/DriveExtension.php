<?php

namespace Base\Drive\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class DriveExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): DriveConfiguration
    {
        return new DriveConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $configuration = new DriveConfiguration();
        $config = (new Processor())->processConfiguration($configuration, $configs);

        // drive.user_class, drive.staff_role, drive.default_audience, drive.local_storage...
        $this->setConfiguration($container, $config, $configuration->getConfigTreeBuilder()->buildTree()->getName());

        // The files' storage until a Google Drive is connected: the site's flysystem storage.
        $container->getDefinition(\Base\Drive\Storage\DocumentsStorage::class)
            ->setArgument('$local', new Reference($config['local_storage']));
    }
}
