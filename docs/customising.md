---
title: The site's own
order: 30
---

# The site's own

## Its look: the templates

The bundle's pages are plain: `@Drive/share/index.html.twig` (the drive) and
`@Drive/admin/drive.html.twig` (the back office's Google Drive page), each made of blocks
(`drive_header`, `drive_toolbar`, `drive_deposit`, `drive_list`, `drive_readme`, `drive_workspace`,
`drive_manage`; `drive_where`, `drive_connect`, `drive_tree`). A site gives them its look:

- by overriding the file: `templates/bundles/DriveBundle/share/index.html.twig`;
- or by extending it (`{% extends '@!Drive/share/index.html.twig' %}`) and redefining blocks.

Its styles and its Stimulus controllers (drag and drop, in-place navigation, list or cards, the
preview window) stay the site's. The variables a page receives are listed at the top of the
bundle's template; `audience`, each entry's `audience` and each of `audiences` is an
`AudienceInterface` (`key`, `label`, `icon`), `row.audience` a key.

## Its words and its name

The words are translation keys of the `messages` domain: `share.*` on the page, `drive.admin.*`
in the back office, French and English. A site rewrites any of them in its own
`translations/messages+intl-icu.<lang>.yaml` - only what it says its own way - or through its back
office's texts when it has some: the drive's name is `share.title` (« Cloud »).

## Its audiences

An audience is a service implementing `Base\Drive\Audience\AudienceInterface`, autoconfigured
(tag `drive.audience`): its key (what the column holds), its label (a translation key), its icon,
and whom it lets in.

```php
final class AssociatesAudience implements AudienceInterface
{
    public function __construct(private readonly Spaces $spaces) {}

    public function key(): string { return 'associates'; }
    public function label(): string { return 'share.audience.associates'; }
    public function icon(): string { return 'fa-solid fa-people-group'; }

    public function lets(?UserInterface $user, string $path, FileShare $share): bool
    {
        return $user instanceof User && [] !== $this->spaces->associateships($user);
    }
}
```

« Pour qui » lists the bundle's readers, users and members, then the site's (by
`#[AsTaggedItem(priority: ...)]`), then staff last.

## Its people

Whom a folder may be shared with: `Base\Drive\People\PeopleInterface` - groups of
`user id => label`, each group's name a key `share.people_group.<group>`. The bundle's lists every
account; a site says who its people are:

```php
#[AsAlias(PeopleInterface::class)]
final class People implements PeopleInterface { /* tenants, associates, contractors, team... */ }
```

## Its folders

`Base\Drive\Tree\TreeInterface`: a layout of folders (path => audience key or null) the back office
creates where the files are (« Créer les dossiers »), or the site's fixtures through
`Tree\DocumentsTree::create()`. Without one, the page offers none.
