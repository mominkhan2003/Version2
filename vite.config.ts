// @lovable.dev/vite-tanstack-config already includes the following — do NOT add them manually
// or the app will break with duplicate plugins:
//   - TanStack devtools (dev-only, first), tanstackStart, viteReact, tailwindcss, tsConfigPaths,
//     VITE_* env injection, @ path alias, React/TanStack dedupe, error logger plugins,
//     and sandbox detection (port/host/strictPort).
//
// Self-hosted single-server deployment (cPanel): the app is built as a pure
// client-side SPA (spa.enabled prerenders the index shell, no Node server at
// runtime). All data flows through /api/*.php served by Apache/PHP.
import { defineConfig } from "@lovable.dev/vite-tanstack-config";

export default defineConfig({
  server: {
    proxy: {
      "/api": {
        target: "http://127.0.0.1:8787",
      },
    },
  },
  tanstackStart: {
    spa: { enabled: true },
  },
});
