/**
 * Low-level HTTP client for the Laravel API (backend/routes/api.php).
 *
 * While NEXT_PUBLIC_USE_MOCK is not "false", every function in lib/api
 * returns mock data instead of calling the server. Each function shows
 * the real endpoint it will call, so switching over is one flag.
 */

/**
 * Where the backend is. NEXT_PUBLIC_API_URL is read when the site is BUILT, so if it was missing from the build the site
 * would silently talk to localhost (the visitor's own computer). To make that impossible on the live site, a student site
 * on lms.<domain> falls back to api.<domain>; only a local copy (localhost) falls back to the local backend.
 */
export function apiBaseUrl(): string {
  const configured = process.env.NEXT_PUBLIC_API_URL;
  if (configured) return configured;
  if (typeof window !== "undefined" && window.location.hostname.startsWith("lms.")) {
    return `${window.location.protocol}//api.${window.location.hostname.slice(4)}/api/v1`;
  }
  return "http://localhost:8000/api/v1";
}
export const USE_MOCK = process.env.NEXT_PUBLIC_USE_MOCK !== "false";

const TOKEN_KEY = "mmn.token";

export const tokenStore = {
  get: () => (typeof window === "undefined" ? null : window.localStorage.getItem(TOKEN_KEY)),
  set: (token: string) => window.localStorage.setItem(TOKEN_KEY, token),
  clear: () => window.localStorage.removeItem(TOKEN_KEY),
};

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
  }
}

/** Calls the API and returns the `data` field of the JSON response. */
export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = tokenStore.get();
  const isUpload = typeof FormData !== "undefined" && init.body instanceof FormData; // browser sets the multipart header itself
  const res = await fetch(`${apiBaseUrl()}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(isUpload ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  });

  // Token expired / device logged out → back to login (not for the login screens themselves).
  if (res.status === 401 && typeof window !== "undefined" && !path.startsWith("/auth/")) {
    tokenStore.clear();
    window.location.href = "/login";
  }

  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(body.message ?? "Something went wrong", res.status, body.errors);
  return body.data as T;
}

/** Small delay so loading states are visible while using mock data. */
export const mockDelay = (ms = 250) => new Promise<void>((r) => setTimeout(r, ms));
