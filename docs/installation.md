---
title: Installation
order: 10
---

# Installation

## The bundle

```bash
composer require omnibase/drive
```

```php
// config/bundles.php
return [
    // ...
    Base\BaseBundle::class => ['all' => true],
    Base\Drive\DriveBundle::class => ['all' => true],
];
```

`DriveBundle` aliases `Base\Drive\Entity\*` to `App\Entity\Drive\*` and the repositories to
`App\Repository\Drive\*` unless the application declares its own classes there; its `templates/`
is `@Drive`, its `translations/` add the `share.*` and `drive.admin.*` keys to the `messages`
domain.

## The routes

```yaml
# config/routes.yaml
drive_controller:
    resource: "@DriveBundle/src/Controller"
    type: attribute
    prefix: /
```

The page's path is the site's: the key `app.share` of `config/routes_paths.yaml` (omnibase's
localized route paths), which the bundle's controller requires. Its routes are named `app_share`
(GET: a folder, a file, `?preview`, `?download`, `?zip`, `?q`) and `app_share_manage` (POST: the
team's actions and a workspace's). The back office's Google Drive page is `/admin/drive`
(`app_admin_drive`, with omnibase/admin and google/apiclient).

```yaml
# config/routes_paths.yaml
app.share:
    fr: /cloud/{path}
    en: /cloud/{path}
```

## The storage

The files are on a flysystem storage of the site's, `local.share` by default
(`drive.local_storage`), never under `public/`: they are read through the controller, which asks
the voter. Give the tests a folder of their own:

```yaml
flysystem:
    storages:
        local.share:
            local:
                directory: '%kernel.project_dir%/var/storage/share'

when@test:
    flysystem:
        storages:
            local.share:
                local:
                    directory: '%kernel.project_dir%/var/storage/share-test'
```

The service `drive.storage` (alias `documents.storage`) is that storage, or the site's Google
Drive folder once connected ([Google Drive](google-drive.md)).

## The tables

`sharedPath` (what the team says of a path), `sharedPathMember` (its people), `shareTask` (a
workspace's tasks), `shareDownload` (download counts). The names are fixed (`#[ORM\Table]`): a site
that had its own copy of these entities keeps its tables and their rows. The people are accounts
of `App\Entity\User`, as in the core's own entities. Generate the application's migration:

```bash
bin/console make:migration
bin/console doctrine:migrations:migrate
```
