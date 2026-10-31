# Changelog

## [Unreleased]

### Added
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
- Configuration: `drive.user_class`, `staff_role`, `default_audience`, `local_storage`, `zip_name`.
- Unit tests with their own bootstrap; `docs/`.
