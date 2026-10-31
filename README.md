# omnibase/drive

The drive application for the sites built on [omnisense/omnibase](https://github.com/glitchr-omnisense/omnibase):
a site's own cloud of folders and files - who each folder is for, whom it is shared with by name,
workspaces with their people and tasks, a search through the tree, each person's own root,
previews, zips and download counts - on the site's storage, or on its Google Drive once connected
from the back office. Not to be confused with [omnisense/omnidrive](https://github.com/glitchr-omnisense/omnidrive),
the storage access: this bundle is the application a site's people use.

The backend is the bundle's; the look is each site's. The bundle ships plain, unstyled templates
made of blocks, which a site overrides with its own (`templates/bundles/DriveBundle/`), keeping its
own styles and Stimulus controllers.

## Requirements

- PHP 8.2+, `doctrine/orm` 2.15 or 3, `doctrine/doctrine-bundle`
- `omnisense/omnibase` 3.x or 4.0, `league/flysystem` 3 with `league/flysystem-bundle`
- Symfony 7 or 8 (`config`, `console`, `dependency-injection`, `http-kernel`, `mime`, `security-bundle`, `string`)
- Suggested: `google/apiclient` and `masbug/flysystem-google-drive-ext` (the files on the site's
  Google Drive), `omnibase/admin` (the back office's Google Drive page), `ext-zip`, `ext-intl`

## Installation

```bash
composer require omnibase/drive
```

```php
// config/bundles.php
Base\Drive\DriveBundle::class => ['all' => true],
```

```yaml
# config/routes.yaml
drive_controller:
    resource: "@DriveBundle/src/Controller"
    type: attribute
    prefix: /
```

```yaml
# config/routes_paths.yaml - where the drive is (omnibase's localized paths)
app.share:
    fr: /cloud/{path}
    en: /cloud/{path}
```

```yaml
# config/packages/flysystem.yaml - its files, never public
flysystem:
    storages:
        local.share:
            local:
                directory: '%kernel.project_dir%/var/storage/share'
```

Its tables are `sharedPath`, `sharedPathMember`, `shareTask` and `shareDownload`: generate the
application's migration (`make:migration`).

## Documentation

[docs/index.md](docs/index.md): installation, configuration, audiences, people, the site's look,
Google Drive, commands, testing.

## License

MIT
