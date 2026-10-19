import { copyFile, rm } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const target = process.argv[2];
if (target !== "namecheap" && target !== "local") {
  throw new Error("Usage: node scripts/package-deployment.mjs <namecheap|local>");
}

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const outputName = target === "namecheap" ? "dist-namecheap" : "dist-local";
const archiveName = target === "namecheap" ? "dist-namecheap.zip" : "dist-local.zip";
const source = path.join(projectRoot, outputName);
const archive = path.join(projectRoot, archiveName);
await rm(archive, { force: true });
const quote = (value) => `'${value.replaceAll("'", "''")}'`;
const ps = [
  "$ErrorActionPreference = 'Stop'",
  "Add-Type -AssemblyName System.IO.Compression.FileSystem",
  `$source = (Resolve-Path -LiteralPath ${quote(source)}).Path`,
  `$archive = ${quote(archive)}`,
  "if (-not (Test-Path -LiteralPath (Join-Path $source 'index.html'))) { throw 'Package source is missing index.html.' }",
  "if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive -Force }",
  "[System.IO.Compression.ZipFile]::CreateFromDirectory($source, $archive, [System.IO.Compression.CompressionLevel]::Optimal, $false)",
  "$zip = [System.IO.Compression.ZipFile]::OpenRead($archive)",
  "try {",
  "  $names = @($zip.Entries | ForEach-Object { $_.FullName.Replace('\\', '/') })",
  "  $required = @('index.html', '.htaccess', 'backend/api/public_data.php', 'backend/api/server.php', 'backend/src/Database.php', 'backend/tools/check_database.php', 'database/schema.sql', 'database.example.php')",
  '  foreach ($entry in $required) { if ($names -notcontains $entry) { throw "Archive is missing $entry." } }',
  "  if ($names -contains 'dist-namecheap/index.html' -or $names -contains 'dist-local/index.html') { throw 'Archive must not contain a nested build directory.' }",
  "  $blocked = @($names | Where-Object { $_ -match '^backend/config/' })",
  "  if ($blocked.Count -gt 0) { throw 'A backend config directory must not be packaged.' }",
  "} finally { $zip.Dispose() }",
].join("; ");

let result;
if (process.platform === "win32") {
  result = spawnSync(
    "powershell.exe",
    ["-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-Command", ps],
    { cwd: projectRoot, stdio: "inherit" },
  );
} else {
  result = spawnSync("zip", ["-qr", archive, "."], { cwd: source, stdio: "inherit" });
}

if (result.error) throw result.error;
if (result.status !== 0) {
  throw new Error(`Failed to create ${archiveName} (exit ${result.status ?? "unknown"}).`);
}

if (process.platform !== "win32") {
  const listing = spawnSync("unzip", ["-Z1", archive], { encoding: "utf8" });
  if (listing.error) throw listing.error;
  if (listing.status !== 0) {
    throw new Error(`Could not inspect ${archiveName} (exit ${listing.status ?? "unknown"}).`);
  }
  const names = new Set(listing.stdout.split(/\r?\n/).map((name) => name.replaceAll("\\", "/")));
  const required = [
    "index.html",
    ".htaccess",
    "backend/api/public_data.php",
    "backend/api/server.php",
    "backend/src/Database.php",
    "backend/tools/check_database.php",
    "database/schema.sql",
    "database.example.php",
  ];
  for (const name of required) {
    if (!names.has(name)) throw new Error(`Archive is missing ${name}.`);
  }
  if ([...names].some((name) => name.startsWith("backend/config/"))) {
    throw new Error("A backend config directory must not be packaged.");
  }
}

if (target === "namecheap") {
  await copyFile(archive, path.join(projectRoot, "dist.zip"));
}

console.log(`Created ${archiveName}; archive root contains index.html.`);
