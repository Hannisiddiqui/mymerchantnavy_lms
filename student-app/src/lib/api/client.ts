/**
 * Low-level HTTP client for the Laravel API (backend/routes/api.php).
 *
 * Demo data (no backend needed) is OPT-IN: only when NEXT_PUBLIC_USE_MOCK is exactly "true". A live site that is missing
 * the setting therefore shows real data, never made-up demo data.
 */
import { resolveApiBase } from "./base-url";

/** Where the backend is. See base-url.ts: decided from the page's address, so a live site can never call "localhost". */
export function apiBaseUrl(): string {
  return resolveApiBase(process.env.NEXT_PUBLIC_API_URL, typeof window === "undefined" ? null : window.location);
}

export const USE_MOCK = process.env.NEXT_PUBLIC_USE_MOCK === "true";

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
  let res: Response;
  try {
    res = await fetch(`${apiBaseUrl()}${path}`, {
      ...init,
      headers: {
        Accept: "application/json",
        ...(isUpload ? {} : { "Content-Type": "application/json" }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init.headers,
      },
    });
  } catch (err) {
    // No answer at all (offline, server down, blocked): say it in plain words instead of the browser's "Failed to fetch".
    if (err instanceof Error && err.message.startsWith("This site does not know")) throw err;
    throw new ApiError("We can't reach the server right now. Please check your internet connection and try again in a moment.", 0);
  }

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
