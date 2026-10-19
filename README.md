# Screenshot Perfect

Implement exactly the screenshot and nothing else

This project was built with [Lovable](https://lovable.dev).

## Build with Lovable

Continue developing this project in the [Lovable editor](https://lovable.dev/projects/c0c27c5f-b86b-46e8-bfff-e9aebfd90858).

- **Ship faster**: describe what you want to build and Lovable handles the code.
- **Stay in sync**: every change made in Lovable is committed straight to this repository.
- **Full ownership**: this code is yours. Push to `main` on GitHub and your changes sync back into Lovable, ready for your next prompt.

## Development

Local development requires Node.js, npm, and PHP 8.1+ with PDO MySQL. Use a
private MySQL config in `backend/config/database.php` (ignored by Git), or set
`MCG_CONFIG_DIR` to a private directory containing `database.php`. Start the
PHP API and Vite frontend in separate terminals:

```sh
git clone <this-repository-url>
cd <repository-name>
npm i
npm run dev:api
```

```sh
npm run dev
```

The Vite server proxies `/api/*` to the PHP server on port 8787.

To build two separate preview packages:

```sh
npm run build:all
```

This creates `dist-namecheap/` and `dist-namecheap.zip` for cPanel, plus
`dist-local/` and `dist-local.zip` for local PHP preview. `dist.zip` is a
backwards-compatible copy of the Namecheap ZIP. Each ZIP has `index.html` at
its root. The local prebuilt package runs with:

```sh
php -S 127.0.0.1:8080 -t dist-local dist-local/backend/api/server.php
```

Then open <http://127.0.0.1:8080>.

To build either package separately, run `npm run build:namecheap` or
`npm run build:local`.

## Deploy to Namecheap shared hosting

Namecheap shared hosting serves the generated static frontend and runs the PHP
API; Node.js is only needed to build the site. Build the upload package with:

```sh
npm run build:namecheap
```

Upload the **contents of `dist-namecheap.zip`** to the domain's document root,
commonly `public_html/`. The archive contains `index.html`, assets, `.htaccess`,
the PHP API, a safe database config template, database schema, and these
deployment instructions at its root. Never upload the repository root.

### cPanel setup

1. In cPanel, create a MySQL database and user, and grant that user all
   privileges on the database. Use the exact host, port, and account-prefixed
   database/user names shown by cPanel.
2. Create `$HOME/mcg-private` **outside** the document root. Copy
  `database.example.php` from the package to
  `$HOME/mcg-private/database.php`, then fill in the database details, HTTPS
  site URL, and a writable media directory outside the web root. The backend
  checks the configured `MCG_CONFIG_DIR`, PHP's `$HOME`, and a private folder
  adjacent to the document root. Never put real credentials in the ZIP or
  under `public_html`.
3. In phpMyAdmin, import `database/schema.sql`. Then import `database/seed.sql`
  from your local project checkout; do **not** upload the seed file, which
  contains imported planner data.
4. Select PHP 8.1 or newer in cPanel and verify the `pdo_mysql` extension is
  enabled.
5. In cPanel Terminal, from the document root, run:

   ```sh
   php backend/tools/check_database.php
   ```

   This reports whether PHP has PDO MySQL, whether the database connection
   succeeds, and whether the homepage schema and seed rows are present. If it
   reports a MySQL connection failure, check cPanel's exact host/port, prefixed
   DB/user names, password, and grants. For other failures, inspect the cPanel
   PHP error log; the browser intentionally receives only a generic error.
6. Create the first administrator using your own email:

  ```sh
  php backend/tools/make_admin.php admin@your-domain.tld admin
  ```

  A new account receives a randomly generated temporary password printed once
  in the terminal. Save it securely and change it after signing in.

### Credential handling

The previous tracked database config contained credentials. That file has been
removed from the Git index and added to `.gitignore`, but prior published Git
history may still contain the old value. Rotate that database user's password
in cPanel and update the private `database.php`; do not reuse the old password.

Changes to public content managed by the admin interface are stored in MySQL
and do not require a frontend rebuild. For code changes, rebuild and upload the
contents of the new Namecheap ZIP.
