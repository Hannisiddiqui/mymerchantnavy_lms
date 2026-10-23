"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, ShieldCheck } from "lucide-react";
import * as api from "@/lib/api";
import { LOGIN_METHOD, OTP_RESEND_SECONDS } from "@/lib/config";
import { loginIdentity } from "@/lib/login-session";
import { Button } from "@/components/ui/Button";
import { cn } from "@/lib/utils";

const LENGTH = 6;

export default function VerifyOtpPage() {
  const router = useRouter();
  const byEmail = LOGIN_METHOD === "email";
  const [identity, setIdentity] = useState<string | null>(null);
  const [digits, setDigits] = useState<string[]>(Array(LENGTH).fill(""));
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [resendIn, setResendIn] = useState(OTP_RESEND_SECONDS);
  const inputs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    const stored = loginIdentity.get();
    if (!stored) router.replace("/login");
    else setIdentity(stored);
  }, [router]);

  useEffect(() => {
    if (resendIn <= 0) return;
    const t = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(t);
  }, [resendIn]);

  const code = digits.join("");

  function setDigit(i: number, value: string) {
    const clean = value.replace(/\D/g, "");
    if (clean.length > 1) {
      // pasted full code
      const next = clean.slice(0, LENGTH).split("");
      setDigits([...next, ...Array(LENGTH - next.length).fill("")]);
      inputs.current[Math.min(next.length, LENGTH - 1)]?.focus();
      return;
    }
    const next = [...digits];
    next[i] = clean;
    setDigits(next);
    setError("");
    if (clean && i < LENGTH - 1) inputs.current[i + 1]?.focus();
  }

  async function verify(e?: React.FormEvent) {
    e?.preventDefault();
    if (!identity || code.length !== LENGTH) return;
    setLoading(true);
    try {
      const { needsProfile } = await api.auth.verifyOtp(identity, code);
      loginIdentity.clear();
      router.replace(needsProfile ? "/onboarding" : "/my-courses");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Invalid code");
      setLoading(false);
    }
  }

  async function resend() {
    if (!identity) return;
    await api.auth.sendOtp(identity);
    setResendIn(OTP_RESEND_SECONDS);
    setDigits(Array(LENGTH).fill(""));
    inputs.current[0]?.focus();
  }

  return (
    <div>
      <Link href="/login" className="mb-8 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-600">
        <ArrowLeft className="size-4" /> {byEmail ? "Change email" : "Change number"}
      </Link>
      <div className="mb-6 grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <ShieldCheck className="size-6" />
      </div>
      <h2 className="text-2xl text-navy-900 sm:text-3xl">{byEmail ? "Check your email" : "Enter OTP"}</h2>
      <p className="mt-2 text-slate-500">
        We sent a 6-digit code to <span className="break-all font-semibold text-navy-900">{byEmail ? identity : `+91 ${identity}`}</span>
        {byEmail && <span className="mt-1 block text-xs">It can take a minute to arrive. Check your spam folder if you do not see it.</span>}
      </p>

      <form onSubmit={verify} className="mt-8">
        <div className="flex justify-between gap-2">
          {digits.map((d, i) => (
            <input
              key={i}
              ref={(el) => {
                inputs.current[i] = el;
              }}
              autoFocus={i === 0}
              inputMode="numeric"
              autoComplete={i === 0 ? "one-time-code" : "off"}
              aria-label={`Digit ${i + 1}`}
              value={d}
              onChange={(e) => setDigit(i, e.target.value)}
              onKeyDown={(e) => e.key === "Backspace" && !d && i > 0 && inputs.current[i - 1]?.focus()}
              className={cn(
                "size-12 rounded-xl border text-center text-xl font-bold text-navy-900 outline-none transition sm:size-14",
                error ? "border-red-300 bg-red-50" : "border-line focus:border-brand-500 focus:ring-4 focus:ring-brand-100",
              )}
            />
          ))}
        </div>
        {error && <p className="mt-3 text-sm font-medium text-red-600">{error}</p>}

        <Button type="submit" size="lg" className="mt-6 w-full" loading={loading} disabled={code.length !== LENGTH}>
          Verify & continue
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-slate-500">
        Didn&apos;t get the code?{" "}
        {resendIn > 0 ? (
          <span>Resend in {resendIn}s</span>
        ) : (
          <button onClick={resend} className="font-semibold text-brand-600 hover:underline">
            Resend code
          </button>
        )}
      </p>
    </div>
  );
}
