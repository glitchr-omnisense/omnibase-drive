<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Base\Drive\People\AccountPeople;
use Base\Drive\People\PeopleInterface;
use Base\Drive\Storage\DocumentsStorage;
use League\Flysystem\FilesystemOperator;

/*
 * Plain autowiring over src/, as omnibase/estate does. Entities and models are not services; the
 * Google Drive connection (Storage\DriveClient and the back office's page) is loaded only when
 * google/apiclient is there, the back office's page only when omnibase/admin is too.
 */
return function (ContainerConfigurator $configurator) {

    $src = dirname(__DIR__).'/src';
    $google = class_exists('Google\\Client') && class_exists('Masbug\\Flysystem\\GoogleDriveAdapter');

    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->public(false);

    $services->load('Base\\Drive\\', $src.'/')
        ->exclude([
            $src.'/DependencyInjection/',
            $src.'/Entity/',
            $src.'/Model/',
            $src.'/Controller/',
            $src.'/DriveBundle.php',
        ]);

    $services->load('Base\\Drive\\Controller\\', $src.'/Controller/')
        ->exclude([$src.'/Controller/Admin/'])
        ->tag('controller.service_arguments');

    if ($google && class_exists('Base\\Admin\\AdminBundle')) {
        $services->load('Base\\Drive\\Controller\\Admin\\', $src.'/Controller/Admin/')
            ->tag('controller.service_arguments');
    }

    // Where the files are: the site's Google Drive folder once connected, its own storage otherwise.
    $services->set('drive.storage', FilesystemOperator::class)
        ->factory([service(DocumentsStorage::class), 'create']);
    // The name the sites knew it by.
    $services->alias('documents.storage', 'drive.storage');

    // Whom a folder may be shared with: every account, unless the site says otherwise (#[AsAlias]).
    $services->alias(PeopleInterface::class, AccountPeople::class);
};
