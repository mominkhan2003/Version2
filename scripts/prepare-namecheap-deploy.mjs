import { access, copyFile, cp, mkdir, rm } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const publicBuild = path.join(projectRoot, ".output", "public");
const dist = path.join(projectRoot, "dist");
const shellFile = path.join(publicBuild, "_shell.html");

try {
  await access(shellFile);
} catch {
  throw new Error("Vite did not generate .output/public/_shell.html; Namecheap deployment was not prepared.");
}

await rm(dist, { recursive: true, force: true });
await cp(publicBuild, dist, { recursive: true });
await copyFile(shellFile, path.join(dist, "index.html"));

const backendDist = path.join(dist, "backend");
await mkdir(backendDist, { recursive: true });

for (const directory of ["api", "src", "tools", "config"]) {
  await cp(
    path.join(projectRoot, "backend", directory),
    path.join(backendDist, directory),
    {
      recursive: true,
      filter: (source) => path.basename(source) !== "database.php",
    },
  );
}

console.log("Namecheap deployment prepared in dist/ (frontend index.html and PHP backend).");
