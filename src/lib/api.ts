/**
 * Typed PHP API client — replaces every Supabase/Lovable backend touchpoint.
 * Backend session cookies + CSRF token are handled transparently.
 */

export interface ApiUser {
  userId: string;
  email: string;
  roles: string[];
  isStaff: boolean;
  isAdmin: boolean;
  mustChangePassword?: boolean;
  profile: { display_name: string; phone: string | null; skills: string[] } | null;
}

export class ApiError extends Error {
  status: number;
  fields: Record<string, string>;
  constructor(message: string, status: number, fields?: Record<string, string>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.fields = fields ?? {};
  }
}

let csrfMemo: Promise<string> | null = null;

export async function fetchCsrf(): Promise<string> {
  if (!csrfMemo) {
    csrfMemo = (async () => {
      const res = await fetch("/api/csrf.php", { credentials: "same-origin" });
      if (!res.ok) throw new ApiError("Security bootstrap failed.", res.status);
      const body = (await res.json()) as { token?: string };
      if (!body.token) throw new ApiError("Security bootstrap failed.", res.status);
      return body.token;
    })();
    csrfMemo.catch(() => { csrfMemo = null; });
  }
  return csrfMemo;
}

async function handle(res: Response): Promise<never> {
  let message = "Request failed. Please try again later.";
  let fields: Record<string, string> = {};
  try {
    const body = (await res.json()) as { error?: string; errors?: Record<string, string> | unknown };
    if (typeof body.error === "string") message = translate(body.error);
    if (body.errors && typeof body.errors === "object") {
      fields = body.errors as Record<string, string>;
    }
  } catch { /* non-JSON error body */ }
  throw new ApiError(message, res.status, fields);
}

/** Backend messages are English; German UI users get the German wording. */
function translate(msg: string): string {
  let lang = "en";
  try {
    lang = localStorage.getItem("mcg-lang") ?? "en";
  } catch {
    // storage unavailable — keep English
  }
  if (lang === "en") return msg;
  const map: Record<string, string> = {
    "Unauthorized. Please sign in.": "Nicht angemeldet. Bitte melde dich an.",
    "Session expired. Please sign in again.": "Sitzung abgelaufen. Bitte erneut anmelden.",
    "Forbidden. Admin role required.": "Zugang verweigert. Admin-Berechtigung nötig.",
    "Forbidden. Staff role required.": "Zugang verweigert. Teamleitung nötig.",
    "CSRF token invalid. Reload and try again.": "Sicherheitstoken ungültig. Seite neu laden.",
    "Sign-in failed. Check your e-mail and password.": "Anmeldung fehlgeschlagen. E-Mail und Passwort prüfen.",
    "Too many attempts. Please wait a moment.": "Zu viele Versuche. Bitte kurz warten.",
    "This account is inactive. Ask an administrator.": "Dieses Konto ist inaktiv. Frage eine:n Admin.",
    "Please enter e-mail and password.": "Bitte E-Mail und Passwort eingeben.",
    "Internal error. Please try again later.": "Interner Fehler. Bitte später erneut versuchen.",
    "Invalid JSON body.": "Ungültige Anfrage.",
    "Invalid reset link.": "Ungültiger Reset-Link.",
  };
  return map[msg] ?? msg;
}

export async function apiGet<T>(path: string): Promise<T> {
  const res = await fetch(path, { credentials: "same-origin" });
  if (!res.ok) await handle(res);
  return (await res.json()) as T;
}

export async function apiPost<T>(path: string, body: unknown, method: "POST" | "PATCH" = "POST"): Promise<T> {
  const token = await fetchCsrf();
  const res = await fetch(path, {
    method,
    credentials: "same-origin",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": token },
    body: JSON.stringify(body ?? {}),
  });
  if (!res.ok) await handle(res);
  return (await res.json()) as T;
}

// ---------------------------------------------------------------- auth flow
interface AuthEnvelope { ok: boolean; user: ApiUser | null; message?: string }

export async function getMe(): Promise<ApiUser | null> {
  try {
    const body = await apiGet<AuthEnvelope>("/api/auth.php");
    return body.user;
  } catch {
    return null;
  }
}

export async function login(email: string, password: string): Promise<{ ok: true }> {
  return apiPost("/api/auth.php", { action: "login", email, password });
}

export async function signup(email: string, name: string, password: string): Promise<{ ok: true }> {
  return apiPost("/api/auth.php", { action: "signup", email, name, password });
}

export async function logout(): Promise<void> {
  await apiPost("/api/auth.php", { action: "logout" }).catch(() => null);
}

export async function requestPasswordReset(email: string): Promise<void> {
  await apiPost("/api/auth.php", { action: "reset_request", email });
}

export async function confirmPasswordReset(token: string, password: string): Promise<void> {
  await apiPost("/api/auth.php", { action: "reset_confirm", token, password });
}

export async function changePassword(current: string, password: string): Promise<void> {
  await apiPost("/api/account.php", { action: "change_password", current, password });
}

export async function updateProfile(displayName: string, phone: string, skills: string): Promise<void> {
  await apiPost("/api/account.php", { action: "update_profile", display_name: displayName, phone, skills });
}

// ---------------------------------------------------------------- transport for binary uploads
export async function uploadFile(path: string, file: File, fields: Record<string, string> = {}, onProgress?: (pct: number) => void): Promise<Record<string, unknown>> {
  const token = await fetchCsrf();
  const fd = new FormData();
  for (const [k, v] of Object.entries(fields)) fd.append(k, v);
  fd.append("file", file);
  return new Promise<Record<string, unknown>>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", path);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-CSRF-Token", token);
    if (onProgress) xhr.upload.addEventListener("progress", (e) => onProgress(Math.round((e.loaded / Math.max(1, e.total)) * 100)));
    xhr.addEventListener("load", () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          resolve(JSON.parse(xhr.responseText) as Record<string, unknown>);
        } catch {
          reject(new ApiError("Invalid server answer.", xhr.status));
        }
      } else {
        let msg = "Upload failed.";
        try {
          msg = (JSON.parse(xhr.responseText) as { error?: string }).error ?? msg;
        } catch { /* ignore */ }
        reject(new ApiError(msg, xhr.status));
      }
    });
    xhr.addEventListener("error", () => reject(new ApiError("Upload failed.", 0)));
    xhr.send(fd);
  });
}
