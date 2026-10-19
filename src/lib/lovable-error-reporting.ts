/**
 * Self-hosted build: local console reporting only.
 * (Replaces the Lovable cloud error reporting integration.)
 */
export function reportLovableError(error: unknown, _context: Record<string, unknown> = {}) {
  if (typeof window === "undefined") return;
  console.groupCollapsed("[report] app error");
  console.error(error);
  console.groupEnd();
}
