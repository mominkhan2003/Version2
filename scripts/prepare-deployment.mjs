import { access, copyFile, cp, mkdir, rm, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const target = process.argv[2];
if (target !== "namecheap" && target !== "local") {
  throw new Error("Usage: node scripts/prepare-deployment.mjs <namecheap|local>");
}

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const publicBuild = path.join(projectRoot, ".output", "public");
const outputDir = path.join(projectRoot, target === "namecheap" ? "dist-namecheap" : "dist-local");
const shellFile = path.join(publicBuild, "_shell.html");

try {
  await access(shellFile);
} catch {
  throw new Error("Vite did not generate .output/public/_shell.html; deployment was not prepared.");
}

await rm(outputDir, { recursive: true, force: true });
await cp(publicBuild, outputDir, { recursive: true });
await copyFile(shellFile, path.join(outputDir, "index.html"));

for (const generatedFile of ["_shell.html", "_headers"]) {
  await rm(path.join(outputDir, generatedFile), { force: true });
}

const backendDist = path.join(outputDir, "backend");
await mkdir(backendDist, { recursive: true });
for (const directory of ["api", "src", "tools"]) {
  await cp(path.join(projectRoot, "backend", directory), path.join(backendDist, directory), {
    recursive: true,
  });
}

const databaseDist = path.join(outputDir, "database");
await mkdir(databaseDist, { recursive: true });
await copyFile(
  path.join(projectRoot, "database", "schema.sql"),
  path.join(databaseDist, "schema.sql"),
);
await copyFile(
  path.join(projectRoot, "backend", "config", "database.example.php"),
  path.join(outputDir, "database.example.php"),
);

const instructions =
  target === "namecheap"
    ? `NAMECHEAP SHARED HOSTING

Upload the CONTENTS of this folder (index.html, assets/, backend/, .htaccess,
database/, and this file) to the domain document root, commonly public_html/.
The ZIP archive is also rooted at these files; it does not add a dist folder.

DATABASE CONFIGURATION
1. In cPanel, create a MySQL database and user, and grant all privileges.
   cPanel may prefix both names with your account name.
2. Create $HOME/mcg-private outside the document root. Copy
   database.example.php to $HOME/mcg-private/database.php and enter the exact
   cPanel host, database, user, password, port, public HTTPS URL, and a
   writable storage path outside the web root. Never put real credentials in
   this package or in a web-accessible directory.
3. Only for a new, empty database, import database/schema.sql followed by
   database/seed.sql from your local repository. Both files are fresh-install
   only; seed.sql contains non-idempotent inserts and imported planner data.
   Never re-import them into production. Back up production data and use
   reviewed migrations for upgrades. Never upload seed.sql into the document
   root.
4. Set cPanel PHP to 8.1 or newer and enable PDO MySQL (pdo_mysql).
5. In cPanel Terminal from the document root run:
   php backend/tools/check_database.php
6. Create an administrator using your own email:
   php backend/tools/make_admin.php admin@your-domain.tld admin
   Save the generated temporary password securely and change it after login.

If the preflight reports a connection failure, verify cPanel's exact hostname,
port, prefixed database/user names and privileges. For further PHP failures,
read cPanel's PHP error log. Never share passwords when asking for help.
`
    : `LOCAL PHP PREVIEW

Requirements: Node.js, npm, PHP 8.1+ with PDO MySQL, and a configured private
database. This package is separate from the Namecheap ZIP.

From the project root, run the PHP API in one terminal:
  npm run dev:api

Run the frontend in a second terminal:
  npm run dev

For this prebuilt static package instead, run from the project root:
  php -S 127.0.0.1:8080 -t dist-local dist-local/backend/api/server.php
Then open http://127.0.0.1:8080.

For repository development, copy backend/config/database.example.php to
backend/config/database.php. For this extracted package, create backend/config/
and copy the top-level database.example.php there as database.php. Alternatively
set MCG_CONFIG_DIR to a private directory containing database.php. Never
commit real credentials.
`;

await writeFile(
  path.join(outputDir, target === "namecheap" ? "README-DEPLOY.txt" : "README-LOCAL.txt"),
  instructions,
  "utf8",
);

console.log(`Prepared ${target} deployment in ${path.basename(outputDir)}/.`);
