/**
 * Which backend the student site talks to.
 *
 * The decision is made from the ADDRESS THE PAGE IS OPENED ON, not only from a build-time setting, so a missing or wrong
 * NEXT_PUBLIC_API_URL can never send students to "localhost" (their own computer: the browser would show a "wants to
 * access other apps and services on this device" prompt and every request would fail):
 *
 *   - a page on a real site (lms.example.com) never uses a local address: a configured localhost is ignored, and with no
 *     setting at all the backend is the matching api.example.com;
 *   - a page on your own computer (localhost) uses the configured address, or the local backend.
 */
const LOCAL = /^(localhost|127\.\d+\.\d+\.\d+|0\.0\.0\.0|\[::1\]|::1|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|.+\.local)$/i;

export const isLocalHost = (hostname: string) => LOCAL.test(hostname);

export const LOCAL_API = "http://localhost:8000/api/v1";

export function resolveApiBase(configured: string | undefined, page: { hostname: string; protocol: string } | null): string {
  const onLocalPage = !page || isLocalHost(page.hostname);

  let configuredIsLocal = false;
  try {
    configuredIsLocal = !!configured && isLocalHost(new URL(configured).hostname);
  } catch {
    configuredIsLocal = false; // not a valid address: treated as not configured below
  }

  if (configured && (onLocalPage || !configuredIsLocal)) {
    try {
      new URL(configured);
      return configured;
    } catch {
      // fall through to the automatic address
    }
  }

  if (!onLocalPage && page.hostname.startsWith("lms.")) {
    return `${page.protocol}//api.${page.hostname.slice(4)}/api/v1`;
  }
  if (onLocalPage) return LOCAL_API;

  throw new Error("This site does not know where its server is. Please contact support.");
}
