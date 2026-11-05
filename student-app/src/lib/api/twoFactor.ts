/** Two-step verification for the logged-in student (Backend: TwoFactorController). */
import { request } from "./client";

export type TwoFactorStatus = { available: boolean; required: boolean; enabled: boolean };

export const status = () => request<TwoFactorStatus>("/me/two-factor");
export const setup = () => request<{ secret: string; uri: string }>("/me/two-factor/setup", { method: "POST" });
export const confirm = (code: string) =>
  request<{ recoveryCodes: string[] }>("/me/two-factor/confirm", { method: "POST", body: JSON.stringify({ code }) });
export const disable = (code: string) => request<null>("/me/two-factor/disable", { method: "POST", body: JSON.stringify({ code }) });
