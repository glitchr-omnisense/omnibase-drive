---
title: Configuration reference
order: 40
---

# Configuration reference

## `drive:`

```yaml
drive:
    user_class:       App\Entity\User
    staff_role:       ROLE_STAFF
    default_audience: staff
    local_storage:    local.share
    listed_root:      false
    zip_name:         drive
```

| Option | Default | Read by |
|---|---|---|
| `user_class` | `App\Entity\User` | the accounts a folder is shared with (`ShareController`, `People\AccountPeople`) |
| `staff_role` | `ROLE_STAFF` | `ShareVoter`: who manages the drive and sees all of it |
| `default_audience` | `staff` | `FileShare::audience()`: who a path is for when neither it nor a folder above it says - `staff` keeps everything private until a folder is opened, `readers` opens the drive to everyone |
| `local_storage` | `local.share` | `Storage\DocumentsStorage`: the flysystem storage the files are on until a Google Drive is connected, and always in the tests |
| `listed_root` | `false` | `ShareController`: the root lists, to whoever is not of the team, only what the team listed and the workspaces they are of, and a search finds only that; the rest opens by its link (a studio's public drive) |
| `zip_name` | `drive` | the name of a zip of the root |

Each option is also a container parameter (`%drive.default_audience%`...).

## Services a site may replace

| Interface | The bundle's | A site's own |
|---|---|---|
| `Base\Drive\People\PeopleInterface` | `AccountPeople`: every account, one group | `#[AsAlias(PeopleInterface::class)]` on its class |
| `Base\Drive\Tree\TreeInterface` | none: the back office offers no layout | `#[AsAlias(TreeInterface::class)]` on its class |
| `Base\Drive\Audience\AudienceInterface` | readers, users, members, staff | any number, autoconfigured (`drive.audience`) |
