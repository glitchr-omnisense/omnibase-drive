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
    team_tags:        everyone
    personal_tags:    false
    slugs:            false
    zip_name:         drive
```

| Option | Default | Read by |
|---|---|---|
| `user_class` | `App\Entity\User` | the accounts a folder is shared with (`ShareController`, `People\AccountPeople`) |
| `staff_role` | `ROLE_STAFF` | `ShareVoter`: who manages the drive and sees all of it |
| `default_audience` | `staff` | `FileShare::audience()`: who a path is for when neither it nor a folder above it says - `staff` keeps everything private until a folder is opened, `readers` opens the drive to everyone |
| `local_storage` | `local.share` | `Storage\DocumentsStorage`: the flysystem storage the files are on until a Google Drive is connected, and always in the tests |
| `listed_root` | `false` | `ShareController`: the root lists, to whoever is not of the team, only what the team listed and the workspaces they are of, and a search finds only that; the rest opens by its link (a studio's public drive) |
| `team_tags` | `everyone` | `ShareController`: who sees the team's tags on folders and files, and filters or finds things by them - `everyone` who sees the folder (a public drive's categories), or `team`: the team's own way of sorting, which nobody else knows of |
| `personal_tags` | `false` | `Share\PersonalTags`: whoever is signed in puts tags of their own on the folders they see (`my_tag_add`, `my_tag_remove`, `my_tag_color`), nobody else seeing them, the team neither; they need the `sharePersonalTag` table (a migration in the site) |
| `slugs` | `false` | `ShareController` and `\|drive_slug`: addresses by slugs, `/cloud/assemblees-generales/2026/proces-verbal.pdf`, a file keeping its extension; an address by names still leads there (`FileShare::resolve()`), redirected (301) to its slugs. A site's template writes its links `path('app_share', {path: entry.path\|drive_slug})` |
| `zip_name` | `drive` | the name of a zip of the root |

Each option is also a container parameter (`%drive.default_audience%`...).

## Services a site may replace

| Interface | The bundle's | A site's own |
|---|---|---|
| `Base\Drive\People\PeopleInterface` | `AccountPeople`: every account, one group | `#[AsAlias(PeopleInterface::class)]` on its class |
| `Base\Drive\Tree\TreeInterface` | none: the back office offers no layout | `#[AsAlias(TreeInterface::class)]` on its class |
| `Base\Drive\Audience\AudienceInterface` | readers, users, members, staff | any number, autoconfigured (`drive.audience`) |
