---
title: Testing
order: 70
---

# Testing

The bundle's tests are units - no kernel, no database: `FileShare` on a temporary local storage
(paths, listing, audiences and their inheritance, listed, tags, search, each person's root,
sharing, zips), the audiences, the voter; `Space\SpacesTest` (an upload shared with a person and a
group: their spaces made, then reused, read only unless they may bring files); `Share\RetentionTest`
(a lock refusing delete, overwrite, rename and move, the team included; a lift by the superadmin
with its log, refused to anyone else; a lift for a while expiring by itself, on a `MockClock`; a
lock put back inside a lifted folder).

```bash
composer install && vendor/bin/phpunit
```

Inside a host's container, with its vendor (`/srv/app/vendor`, or `DRIVE_AUTOLOAD`):

```bash
docker exec -w /srv/omnibase/drive meyers-web-1 php /srv/app/vendor/bin/phpunit --do-not-cache-result
```

The pages are proven by the sites' functional tests (meyers' `tests/Functional/ShareTest.php`).
