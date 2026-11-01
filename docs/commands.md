---
title: Commands
order: 60
---

# Commands

Each keeps the name sites knew it by as an alias. With the retention lock on (`drive.retention`),
nothing kept is moved nor replaced: `drive:move` fails, `drive:import` leaves a file of another size
as it is, says so and ends in failure.

| Command | Alias | Does |
|---|---|---|
| `drive:import <directory> [target]` | `app:share:import` | a folder of files into the drive as it is; a file already there with the same size is left |
| `drive:move <destination> <paths>... [--drop-tags]` | `app:share:move` | files and folders into another folder, what was said of them and their downloads following |
| `drive:workspace <name> [--audience=members] [--description=] [--tag=]...` | `app:share:workspace` | a workspace at the root, created or a folder made one |
| `drive:count <path> <count> [--add]` | `app:share:count` | a path's downloads set from statistics kept elsewhere |
