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
                ->enumNode('team_tags')->values(['everyone', 'team'])->defaultValue('everyone')
                    ->info('Who sees the team\'s tags on folders and files (and filters or searches by them): "everyone" who sees the folder, or "team" - the team\'s own way of sorting, which nobody else knows of.')->end()
                ->booleanNode('personal_tags')->defaultFalse()
                    ->info('Whoever is signed in puts tags of their own on the folders they see, to sort them for themselves: nobody else sees them, the team neither (the sharePersonalTag table).')->end()
                ->booleanNode('slugs')->defaultFalse()
                    ->info('Addresses by slugs: /cloud/assemblees-generales/2026 rather than /cloud/Assembl%C3%A9es%20g%C3%A9n%C3%A9rales/2026. An address by names still leads there, redirected to its slugs.')->end()
                ->scalarNode('spaces_root')->defaultValue('Spaces')
                    ->info('The folder each person\'s space is made in when no bundle says where it is (Space\\SpaceProviderInterface): <spaces_root>/<their name>. A file shared on upload with someone goes there.')->end()
                ->booleanNode('retention')->defaultFalse()
                    ->info('The retention lock: nothing in the drive deleted, overwritten, renamed nor moved, by anyone, the team included - until the superadmin lifts it for a folder (until put back, or for a while), each lift and lock logged with its reason (the shareRetention table).')->end()
                ->scalarNode('retention_role')->defaultValue('ROLE_SUPERADMIN')
                    ->info('Who lifts the retention lock and puts it back: the superadmin.')->end()
                ->scalarNode('zip_name')->defaultValue('drive')
                    ->info('The name of a zip of the root, should one be asked for.')->end()
            ->end();

        return $treeBuilder;
    }
}
