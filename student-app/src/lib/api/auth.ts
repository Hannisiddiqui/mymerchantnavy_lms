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

/** Returns whether the student still has to complete their profile. */
export async function verifyOtp(identifier: string, code: string): Promise<{ needsProfile: boolean }> {
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
  const data = await request<{ token: string; needsProfile: boolean }>("/auth/otp/verify", {
    method: "POST",
    body: JSON.stringify({ ...identity(identifier), code, device_name: navigator.userAgent }),
  });
  tokenStore.set(data.token);
  return { needsProfile: data.needsProfile };
}

/** The "go to my course" link from the WordPress site: a one-time token signs the student in without a code. */
export async function ssoLogin(token: string): Promise<{ needsProfile: boolean }> {
  if (USE_MOCK) throw new Error("Sign-in links need the real backend.");
  const data = await request<{ token: string; needsProfile: boolean }>("/auth/sso", {
    method: "POST",
    body: JSON.stringify({ token, device_name: navigator.userAgent }),
  });
  tokenStore.set(data.token);
  return { needsProfile: data.needsProfile };
}

export async function logout(): Promise<void> {
  if (!USE_MOCK) await request("/auth/logout", { method: "POST" }).catch(() => undefined);
  tokenStore.clear();
}
