/**
 * Login with email + a one-time code that is emailed (default), or mobile number + SMS OTP when LOGIN_METHOD is "phone".
 * Backend: app/Http/Controllers/Api/V1/AuthController.php
 */
import { LOGIN_METHOD } from "../config";
import { USE_MOCK, mockDelay, request, tokenStore } from "./client";
import { readState, writeState } from "../mock/store";

/** The body field the server expects: { email } or { phone }. */
const identity = (value: string) => (LOGIN_METHOD === "phone" ? { phone: value } : { email: value.trim().toLowerCase() });

export async function sendOtp(identifier: string): Promise<void> {
  if (USE_MOCK) return mockDelay(500);
  await request("/auth/otp/send", { method: "POST", body: JSON.stringify(identity(identifier)) });
}

/** After the emailed code, a student with two-step verification must still enter the code of their authenticator app. */
export type LoginResult = { needsProfile: boolean; requiresTwoFactor?: false } | { requiresTwoFactor: true; challenge: string };

const CHALLENGE_KEY = "mmn.2fa";
export const twoFactorChallenge = {
  set: (challenge: string, redirect: string | null = null) => window.sessionStorage.setItem(CHALLENGE_KEY, JSON.stringify({ challenge, redirect })),
  get: (): { challenge: string; redirect: string | null } | null => {
    try {
      return JSON.parse(window.sessionStorage.getItem(CHALLENGE_KEY) ?? "null");
    } catch {
      return null;
    }
  },
  clear: () => window.sessionStorage.removeItem(CHALLENGE_KEY),
};

/** Returns whether the student still has to complete their profile (or that a two-step code is needed). */
export async function verifyOtp(identifier: string, code: string): Promise<LoginResult> {
  if (USE_MOCK) {
    await mockDelay(500);
    if (!/^\d{6}$/.test(code)) throw new Error("Enter the 6-digit code we sent you");
    const { student } = readState();
    const who = identity(identifier);
    const same = student && (LOGIN_METHOD === "phone" ? student.phone === identifier : student.email === who.email);
    if (!same) {
      writeState((s) => ({
        ...s,
        student: { id: 1, phone: "", email: "", emailVerified: true, name: "", nickname: "", targetExam: "", attemptYear: 0, ...who },
      }));
      return { needsProfile: true };
    }
    return { needsProfile: !student.name };
  }
  const data = await request<{ token?: string; needsProfile?: boolean; requiresTwoFactor?: boolean; challenge?: string }>("/auth/otp/verify", {
    method: "POST",
    body: JSON.stringify({ ...identity(identifier), code, device_name: navigator.userAgent }),
  });
  if (data.requiresTwoFactor && data.challenge) return { requiresTwoFactor: true, challenge: data.challenge };
  tokenStore.set(data.token as string);
  return { needsProfile: Boolean(data.needsProfile) };
}

/** The second step of a login with two-step verification: the 6-digit app code, or a recovery code. */
export async function completeTwoFactor(challenge: string, code: string): Promise<{ needsProfile: boolean }> {
  const data = await request<{ token: string; needsProfile: boolean }>("/auth/2fa/challenge", {
    method: "POST",
    body: JSON.stringify({ challenge, code: code.trim() }),
  });
  tokenStore.set(data.token);
  twoFactorChallenge.clear();
  return { needsProfile: data.needsProfile };
}

/** The "go to my course" link from the WordPress site: a one-time token signs the student in without a code. */
export async function ssoLogin(token: string): Promise<{ needsProfile: boolean; redirect: string | null; requiresTwoFactor?: boolean }> {
  if (USE_MOCK) throw new Error("Sign-in links need the real backend.");
  const data = await request<{ token?: string; needsProfile?: boolean; redirect: string | null; requiresTwoFactor?: boolean; challenge?: string }>("/auth/sso", {
    method: "POST",
    body: JSON.stringify({ token, device_name: navigator.userAgent }),
  });
  if (data.requiresTwoFactor && data.challenge) {
    twoFactorChallenge.set(data.challenge, data.redirect);
    return { needsProfile: false, redirect: data.redirect, requiresTwoFactor: true };
  }
  tokenStore.set(data.token as string);
  return { needsProfile: Boolean(data.needsProfile), redirect: data.redirect };
}

export async function logout(): Promise<void> {
  if (!USE_MOCK) await request("/auth/logout", { method: "POST" }).catch(() => undefined);
  tokenStore.clear();
}
