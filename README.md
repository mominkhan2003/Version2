# Screenshot Perfect

Implement exactly the screenshot and nothing else

This project was built with [Lovable](https://lovable.dev).

## Build with Lovable

Continue developing this project in the [Lovable editor](https://lovable.dev/projects/c0c27c5f-b86b-46e8-bfff-e9aebfd90858).

- **Ship faster**: describe what you want to build and Lovable handles the code.
- **Stay in sync**: every change made in Lovable is committed straight to this repository.
- **Full ownership**: this code is yours. Push to `main` on GitHub and your changes sync back into Lovable, ready for your next prompt.

## Development

Prefer working locally? You need Node.js and npm — [install with nvm](https://github.com/nvm-sh/nvm#installing-and-updating).

```sh
git clone <this-repository-url>
cd <repository-name>
npm i
npm run dev
```

## Deploy to Namecheap shared hosting

This project is a Vite-built React site with a PHP/MySQL API. Namecheap shared
hosting serves the generated static site and runs the PHP endpoints; Node.js is
needed to build the site, not to keep a server process running. The production
build creates `dist/index.html`, copies the PHP API into `dist/backend/`, and
includes Apache rules for client-side routes and API requests.

The previously tracked local database configuration contained credentials.
Rotate that database user's password in cPanel before using it on the live
site, and update the private `database.php` file with the new password.

1. In Namecheap cPanel, create a MySQL database and user, then grant the user
   all privileges on that database.
2. Create a private directory outside the domain's document root (for example,
   `/home/CPANEL_USER/mcg-private`). Copy
   `backend/config/database.example.php` there as `database.php` and fill in
   the cPanel database values, the site's HTTPS URL, and a writable absolute
   path outside the document root for uploaded media. The backend searches
   `$HOME/mcg-private/database.php` automatically. If PHP does not expose `$HOME`
   on your account, add `SetEnv MCG_CONFIG_DIR /home/CPANEL_USER/mcg-private`
   to `public/.htaccess`, replacing `CPANEL_USER` with your cPanel username.
3. Import `database/schema.sql` and then `database/seed.sql` using cPanel
   phpMyAdmin. The seed file contains the initial site content but no user
   accounts.
4. Install the project dependencies and create the upload-ready build:

   ```sh
   npm install
   npm run build:namecheap
   ```

   The PHP API loads database configuration from the private directory; never
   put database credentials in the frontend.
5. Upload the **contents** of `dist/` (including `.htaccess` and `backend/`) to
   the domain's document root, commonly `public_html/`. Do not upload the
   repository root as the website.
6. In cPanel Terminal, run
   `php backend/tools/check_database.php` from the uploaded document root. It
   checks the private config, database connection, required homepage tables,
   and seed version without printing credentials.
7. Create your first admin with
   `php backend/tools/make_admin.php admin@your-domain.tld admin`. The script
   prints a randomly generated temporary password once; save it securely and
   change it after signing in. Use your own email in place of the example.
   Check that the selected PHP version has PDO MySQL enabled.

To publish later source changes, edit the project, run `npm run build:namecheap`
again, and upload the updated `dist/` contents. Homepage content managed in the
admin area is saved in MySQL and can be changed there without rebuilding the
frontend. The local `backend/config/database.php` is deliberately excluded
from deployment builds; never upload or commit local database credentials.
