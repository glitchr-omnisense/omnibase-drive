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
  when the team allowed it.

Someone signed in whom the root is not for has a root of their own: the highest folders they see
(`FileShare::tops()`), and no crumb of a folder above them.

## Sharing by name

« Partager » (the team): a folder shared with an account becomes a workspace if it was not one,
keeps its audience, and is theirs to see from their own access, read only unless its people may
bring files (`FileShare::shareWith()`, `isSharedWith()`).

## Listed or not

A folder listed is shown on the root and open to search engines; not listed (the default), its
pages and files answer `X-Robots-Tag: noindex`. With `drive.listed_root`, the root and the search show
whoever is not of the team only what is listed (and the workspaces they are of): the rest opens by
its link only.

## Downloads

A file served counts once (not a `Range` request, nor a `?preview`); a folder's zip counts for the
folder. A folder's total is everything under it; the root's total is the team's to see.
