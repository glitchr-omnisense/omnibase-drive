---
title: Testing
order: 70
---

# Testing

The bundle's tests are units - no kernel, no database: `FileShare` on a temporary local storage
(paths, listing, audiences and their inheritance, listed, tags, search, each person's root,
sharing, zips), the audiences, the voter.

```bash
composer install && vendor/bin/phpunit
```

Inside a host's container, with its vendor (`/srv/app/vendor`, or `DRIVE_AUTOLOAD`):

```bash
docker exec -w /srv/omnibase/drive meyers-web-1 php /srv/app/vendor/bin/phpunit --do-not-cache-result
```

The pages are proven by the sites' functional tests (meyers' `tests/Functional/ShareTest.php`).
