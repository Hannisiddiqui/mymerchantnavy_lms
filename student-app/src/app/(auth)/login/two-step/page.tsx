"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, KeyRound } from "lucide-react";
import * as api from "@/lib/api";
import { Button } from "@/components/ui/Button";

/** Second step of a login for students who turned on two-step verification. */
export default function TwoStepPage() {
  const router = useRouter();
  const [challenge, setChallenge] = useState<{ challenge: string; redirect: string | null } | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const c = api.auth.twoFactorChallenge.get();
    if (!c) router.replace("/login");
    else setChallenge(c);
  }, [router]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!challenge || !code.trim()) return;
    setLoading(true);
    setError("");
    try {
      const { needsProfile } = await api.auth.completeTwoFactor(challenge.challenge, code);
      const r = challenge.redirect;
      const target = r && /^\/[\w/-]*$/.test(r) && !r.includes("//") ? r : "/my-courses";
      router.replace(needsProfile ? "/onboarding" : target);
    } catch (err) {
      setError(err instanceof Error ? err.message : "That code is not right.");
      setLoading(false);
    }
  }

  return (
    <div>
      <Link href="/login" onClick={() => api.auth.twoFactorChallenge.clear()} className="mb-8 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-600">
        <ArrowLeft className="size-4" /> Back to login
      </Link>
      <div className="mb-6 grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <KeyRound className="size-6" />
      </div>
      <h2 className="text-2xl text-navy-900 sm:text-3xl">Two-step verification</h2>
      <p className="mt-2 text-slate-500">Open your authenticator app and enter the 6-digit code for My Merchant Navy.</p>

      <form onSubmit={submit} className="mt-8">
        <input
          autoFocus
          inputMode="text"
          autoComplete="one-time-code"
          aria-label="Authenticator code or recovery code"
          value={code}
          onChange={(e) => {
            setCode(e.target.value);
            setError("");
          }}
          placeholder="123 456"
          maxLength={14}
          className="w-full rounded-xl border border-line px-4 py-3.5 text-center text-xl font-bold tracking-widest text-navy-900 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
        />
        {error && <p className="mt-3 text-sm font-medium text-red-600">{error}</p>}
        <Button type="submit" size="lg" className="mt-6 w-full" loading={loading} disabled={!code.trim()}>
          Verify & continue
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-slate-500">Lost your phone? Type one of your recovery codes instead.</p>
    </div>
  );
}
