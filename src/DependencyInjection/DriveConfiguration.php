<?php

namespace Base\Drive\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DriveConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('drive');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('user_class')->defaultValue('App\Entity\User')
                    ->info('The application\'s user entity: the accounts a folder is shared with.')->end()
                ->scalarNode('staff_role')->defaultValue('ROLE_STAFF')
                    ->info('Who manages the drive and sees all of it.')->end()
                ->scalarNode('default_audience')->defaultValue('staff')
                    ->info('Who a path is for when neither it nor any folder above it says: "staff" keeps everything private until a folder is opened, "readers" opens it to everyone.')->end()
                ->scalarNode('local_storage')->defaultValue('local.share')
                    ->info('The flysystem storage the files are on until a Google Drive is connected (and always in the tests).')->end()
                ->booleanNode('listed_root')->defaultFalse()
                    ->info('The root lists, to whoever is not of the team, only what the team listed (and the workspaces they are of); a search finds only that too. The rest opens by its link.')->end()
                ->scalarNode('zip_name')->defaultValue('drive')
                    ->info('The name of a zip of the root, should one be asked for.')->end()
            ->end();

        return $treeBuilder;
    }
}
