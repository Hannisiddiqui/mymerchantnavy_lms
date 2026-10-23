"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ArrowRight, Mail, Smartphone } from "lucide-react";
import * as api from "@/lib/api";
import { LOGIN_METHOD } from "@/lib/config";
import { loginIdentity } from "@/lib/login-session";
import { Button } from "@/components/ui/Button";

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE = /^[6-9]\d{9}$/;

/**
 * Login. Same form either way; only the field changes: the email address (default, a code is emailed) or, when the
 * SMS login is switched on (NEXT_PUBLIC_LOGIN_METHOD=phone), the mobile number.
 */
export default function LoginPage() {
  const router = useRouter();
  const byEmail = LOGIN_METHOD === "email";
  const [value, setValue] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const valid = byEmail ? EMAIL.test(value.trim()) : PHONE.test(value);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!valid) return setError(byEmail ? "Enter a valid email address" : "Enter a valid 10-digit mobile number");
    setLoading(true);
    try {
      const identifier = byEmail ? value.trim().toLowerCase() : value;
      await api.auth.sendOtp(identifier);
      loginIdentity.set(identifier);
      router.push("/login/verify");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not send the code");
      setLoading(false);
    }
  }

  return (
    <div>
      <div className="mb-6 grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        {byEmail ? <Mail className="size-6" /> : <Smartphone className="size-6" />}
      </div>
      <h2 className="text-2xl text-navy-900 sm:text-3xl">Welcome aboard</h2>
      <p className="mt-2 text-slate-500">
        {byEmail ? "Log in with your email address. We'll send you a one-time password." : "Log in with your mobile number. We'll send you a one-time password."}
      </p>

      <form onSubmit={submit} className="mt-8 space-y-5">
        <label className="block">
          <span className="mb-1.5 block text-sm font-semibold text-navy-900">{byEmail ? "Email address" : "Mobile number"}</span>
          <div className="flex h-12 overflow-hidden rounded-lg border border-line focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
            {!byEmail && <span className="grid place-items-center border-r border-line bg-slate-50 px-3.5 text-sm font-semibold text-slate-600">+91</span>}
            <input
              autoFocus
              type={byEmail ? "email" : "text"}
              inputMode={byEmail ? "email" : "numeric"}
              autoComplete={byEmail ? "email" : "tel-national"}
              autoCapitalize="none"
              spellCheck={false}
              placeholder={byEmail ? "you@example.com" : "98765 43210"}
              maxLength={byEmail ? 190 : 10}
              value={value}
              onChange={(e) => {
                setValue(byEmail ? e.target.value : e.target.value.replace(/\D/g, ""));
                setError("");
              }}
              className="min-w-0 flex-1 px-3.5 text-base text-navy-900 outline-none placeholder:text-slate-400"
            />
          </div>
          {error && <span className="mt-1.5 block text-xs font-medium text-red-600">{error}</span>}
        </label>

        <Button type="submit" size="lg" className="w-full" loading={loading} disabled={!valid}>
          Send OTP <ArrowRight className="size-4" />
        </Button>
      </form>

      <p className="mt-8 text-xs leading-relaxed text-slate-500">
        {byEmail
          ? "Use the email address you registered with. Your courses appear here once our team assigns them to your account."
          : "Use the same mobile number you used while purchasing your course. Your courses appear here once our team assigns them to your account."}
      </p>
    </div>
  );
}
