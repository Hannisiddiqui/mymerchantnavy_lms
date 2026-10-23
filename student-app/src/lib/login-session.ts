/** Remembers the email (or phone number) between the login and code screens (kept out of the URL). */
const KEY = "mmn.loginIdentity";

export const loginIdentity = {
  get: () => (typeof window === "undefined" ? null : window.sessionStorage.getItem(KEY)),
  set: (identity: string) => window.sessionStorage.setItem(KEY, identity),
  clear: () => window.sessionStorage.removeItem(KEY),
};
