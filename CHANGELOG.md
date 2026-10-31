# Changelog

## [Unreleased]

### Added
- Groups of people to share with at once - a flatshare's tenants, a couple, heirs:
  `Entity\ShareGroup` (tables `shareGroup`, `shareGroupMember`), `SharedPath::$groups`
  (`sharedPathGroup`), `FileShare::shareWithGroup()`; whoever is in a group shared with a folder is
  among its people (`SharedPath::hasMember()`), added later too. The team's actions `group_create`,
  `group_rename`, `group_member_add`, `group_member_remove`, `group_delete`, `group_unshare`, and
  `share` with `group:<id>`; the page gets `share_groups`.
- Addresses by slugs, `drive.slugs` (false by default): `FileShare::slug()` and `resolve()` (each
  part by its name or else by its slug, so old addresses still lead there), the Twig filter
  `drive_slug` on the links of the bundle's page, an address by names redirected (301) to its
  slugs, the controller's redirects by slugs.
- Tags apart from access, each kept to whom they belong: `drive.team_tags` (`everyone` by default,
  as before; `team` keeps the team's tags to the team - not shown, filtered nor searched by anyone
  else) and `drive.personal_tags` (false by default): `Entity\PersonalTag` (table
  `sharePersonalTag`), `Share\PersonalTags`, the actions `my_tag_add`, `my_tag_remove` and
  `my_tag_color` for whoever sees the folder, the Twig function `drive_my_tag_color()`; their rows
  follow a folder moved and go with one deleted. The page gets `folder_tags` (the team's, when its
  reader may see them), `my_tags`, `my_own_tags`, `personal_tags`, `team_tags_private`; each entry
  `my_tags`, its `tags` (to filter by) being the ones its reader sees. `FileShare::search()` takes
  the tags to find things by (`tagsOf`).
- A folder's words and tags written in place: `ShareController`'s actions `description`, `tag_add`,
  `tag_remove` (who sees it kept) and `tag_color`; a tag's colour, `Share\TagColors` (the one chosen,
  under the setting `app.drive.tag_colors`, otherwise one of eight drawn from its key), and the Twig
  function `drive_tag_color()`. Renaming the folder one is in leads to it under its new name.
- The drive, out of the copies meyers and glitchr each had (« le Partage », « Cloud »), their
  union in one place: `Share\FileShare` (paths, listing, audiences and tags by inheritance, listed,
  each person's own root - `tops()` -, the search through the tree - `search()`, case and accents
  aside -, sharing by name - `shareWith()`, `isSharedWith()` -, workspaces, upload, move, delete,
  zips, download counts), the entities `SharedPath` (with `listed` and `deposit`), `ShareTask` and
  `ShareDownload` under the tables the sites created (`sharedPath`, `sharedPathMember`,
  `shareTask`, `shareDownload`), `Security\Voter\ShareVoter` (`SHARE_VIEW`, `SHARE_MANAGE`,
  `SHARE_WORK`), `Controller\ShareController` at the site's `app.share` path (routes `app_share`,
  `app_share_manage`: previews, downloads, zips, search, a dropped folder kept whole, `X-Robots-Tag:
  noindex` for what is not listed), the commands `drive:import`, `drive:move`, `drive:workspace`,
  `drive:count` (aliases `app:share:*`), the words (`share.*`, French and English).
- Audiences a site extends: `Audience\AudienceInterface` (key, label, icon, whom it lets in),
  autoconfigured; the bundle's readers, users, members and staff; a key nobody answers to any more
  read as the staff's. The column stays a string.
- Whom to share with: `People\PeopleInterface`, the bundle's `AccountPeople` listing every account.
- The Google Drive connection: `Storage\DocumentsStorage` (`drive.storage`, alias
  `documents.storage`), `DriveClient`, `DriveSettings` (the settings sites stored, `app.drive.*`),
  the back office's page `/admin/drive` (`app_admin_drive*`), a site's layout of folders
  (`Tree\TreeInterface`, `Tree\DocumentsTree`).
- Plain, unstyled templates of blocks (`@Drive/share/index.html.twig`,
  `@Drive/admin/drive.html.twig`) for the sites to override.
- Configuration: `drive.user_class`, `staff_role`, `default_audience`, `local_storage`, `zip_name`,
  `listed_root` (glitchr's root: only what the team listed, and the workspaces one is of, to whoever
  is not of the team - and so in a search; the rest by its link).
- Unit tests with their own bootstrap; `docs/`.
