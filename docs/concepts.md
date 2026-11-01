---
title: Concepts
order: 20
---

# Concepts

## Paths

A path is relative to the drive's root, `/`-separated, without slashes at its ends; `""` is the
root. `FileShare::normalize()` refuses `..`, `.` and any part beginning with a dot: hidden files
(`.git`, `.htaccess`) are never listed, served, searched nor zipped. `FileShare::name()` makes a
typed or uploaded name fit (its last part, without what a path cannot hold).

## What the team says of a path

`Entity\SharedPath`, one row per path the team said something of: its audience (a key), a few
words, its tags, whether it is listed, whether it is a workspace (its people, its tasks), whether
its people may bring files (`deposit`). A path without a row is as its nearest folder with one; the
root as `drive.default_audience` says. Tags add up: a path has its own and all its folders'.

## Audiences

Who a path is for, by key, stored as a string (`sharedPath.audience`, 16 characters). The bundle's:

| Key | Lets in |
|---|---|
| `readers` | everyone, signed in or not |
| `users` | the signed-in accounts |
| `members` | the people of the workspace the path is in |
| `staff` | nobody else (the staff see everything anyway) |

A site adds its own ([customising](customising.md)). A key no audience answers to any more is read
as `staff`.

## Who sees what

`Security\Voter\ShareVoter`:

- `SHARE_MANAGE`: the staff (`drive.staff_role`), who also see everything;
- `SHARE_VIEW` (the path as subject): the people the path is shared with - the members of a
  workspace in it or above it -, whatever its audience; otherwise its audience's `lets()`;
- `SHARE_WORK` (the path as subject): a workspace's people - its tasks, and the files they bring
  when the team allowed it;
- `SHARE_ALTER` (the path as subject): delete, rename or move it - the staff, and nobody where
  the retention lock keeps it;
- `SHARE_RETAIN` (the folder as subject): lift its retention lock or put it back - the superadmin
  (`drive.retention_role`), with `drive.retention` on.

Someone signed in whom the root is not for has a root of their own: the highest folders they see
(`FileShare::tops()`), and no crumb of a folder above them.

## Sharing by name

« Partager » (the team): a folder shared with an account becomes a workspace if it was not one,
keeps its audience, and is theirs to see from their own access, read only unless its people may
bring files (`FileShare::shareWith()`, `isSharedWith()`).

## Each person's space, and sharing on upload

A person's space is the folder their role gives them - a tenant's, an owner's, a partner's. Where
it is, a bundle says (`Space\SpaceProviderInterface`, tagged `drive.space`: the first provider to
answer wins - omnibase/estate's tenants, owners and co-owners); nobody answering, it is
`drive.spaces_root/<their name>`, the team's and theirs. `Space\Spaces::of()` makes it when
missing, reuses it otherwise, and shares it with them, read only.

Files the team adds - the upload form, a drop on the page, on a folder's line or on the empty
folder's line - may be shared at once with named people and groups (`share_with[]`: user ids, or
`group:<id>`): besides the folder they were added to, a copy goes into each person's space (a
group: each of its people's), beside what is there, never over it, and none where the file already
is in their space (`Spaces::deliver()`). Read only for them, unless the uploader ticks
`share_deposit`: they may then bring files into their space too (a right given, never taken back
by sharing again). Only the team shares on upload; a workspace's people only bring files.

## The retention lock

With `drive.retention` on, every folder and what it holds is kept: nothing deleted, overwritten,
renamed nor moved, whoever asks - the page, the commands (`drive:move` refuses, `drive:import`
replaces nothing and says so) and the bundles filing into the drive all go through `FileShare`,
which throws `Share\LockedException`; `ShareVoter` answers `SHARE_ALTER` no, and the pages offer
no delete, rename nor move on what is kept. Adding files stays open.

The superadmin lifts it per folder - until it is put back, for a while (a number of hours or
days), or until a date (that day included) - and puts it back, from the folder's access dialog
(`retention_lift`, `retention_lock`), where the log is listed. Each lift and each lock is a line of
`shareRetention` (`Entity\RetentionEvent`: who, when, which folder, until when, and why - a reason
is required), only ever added to. A folder is as the nearest of itself and its folders whose
latest line says something: a lock, or a lift still running. A lift whose time is up says nothing
any more: the lock is back by itself, read so when asked, with no task to run. A folder goes
(deleted, moved) only with all it holds: a lock put back on a folder inside a lifted one keeps
both. A lift is on a path: a lifted folder renamed or moved is kept again at its new place.

## Listed or not

A folder listed is shown on the root and open to search engines; not listed (the default), its
pages and files answer `X-Robots-Tag: noindex`. With `drive.listed_root`, the root and the search show
whoever is not of the team only what is listed (and the workspaces they are of): the rest opens by
its link only.

## Downloads

A file served counts once (not a `Range` request, nor a `?preview`); a folder's zip counts for the
folder. A folder's total is everything under it; the root's total is the team's to see.
