---
title: Google Drive
order: 50
---

# Google Drive

With `google/apiclient` and `masbug/flysystem-google-drive-ext` installed, the files may live in a
folder of the site's Google Drive, connected from the back office (`/admin/drive`, omnibase/admin,
`ROLE_ADMIN`):

1. Google Cloud Console: enable the Google Drive API, create an OAuth client "Web application",
   its redirect URI the page's `app_admin_drive_callback`, and publish the consent screen (Google
   drops the token after seven days otherwise).
2. `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env.local` or the secrets vault.
3. « Connecter Google Drive », then pick or create the folder.

`Storage\DriveSettings` keeps the refresh token in omnibase's settings, sealed with a key derived
from the app secret, with the folder and the account (`app.drive.*`: the names sites stored them
under before the bundle). `Storage\DocumentsStorage` answers the Drive folder once connected, the
site's storage otherwise - and always in the tests; Google unreachable, it falls back and logs why.
Nothing moves by itself between the two.
