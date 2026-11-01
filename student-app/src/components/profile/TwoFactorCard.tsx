"use client";

import { useEffect, useState } from "react";
import QRCode from "qrcode";
import { ShieldCheck, ShieldOff } from "lucide-react";
import * as api from "@/lib/api";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";

type Step = "idle" | "scan" | "codes" | "off";

/** Student switch for two-step verification (authenticator app). Lives on My Profile. */
export function TwoFactorCard() {
  const [status, setStatus] = useState<api.twoFactor.TwoFactorStatus | null>(null);
  const [step, setStep] = useState<Step>("idle");
  const [secret, setSecret] = useState("");
  const [qr, setQr] = useState("");
  const [code, setCode] = useState("");
  const [codes, setCodes] = useState<string[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.twoFactor.status().then(setStatus).catch(() => setStatus(null));
  }, []);

  if (!status || !status.available) return null;

  async function start() {
    setBusy(true);
    setError("");
    try {
      const res = await api.twoFactor.setup();
      setSecret(res.secret);
      setQr(await QRCode.toDataURL(res.uri, { margin: 1, width: 192 }));
      setCode("");
      setStep("scan");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not start the set-up.");
    }
    setBusy(false);
  }

  async function confirm() {
    setBusy(true);
    setError("");
    try {
      const res = await api.twoFactor.confirm(code);
      setCodes(res.recoveryCodes);
      setStatus({ available: true, enabled: true });
      setStep("codes");
    } catch (e) {
      setError(e instanceof Error ? e.message : "That code is not right.");
    }
    setBusy(false);
  }

  async function turnOff() {
    setBusy(true);
    setError("");
    try {
      await api.twoFactor.disable(code);
      setStatus({ available: true, enabled: false });
      setStep("idle");
      setCode("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "That code is not right.");
    }
    setBusy(false);
  }

  const input = (
    <input
      value={code}
      onChange={(e) => {
        setCode(e.target.value);
        setError("");
      }}
      inputMode="text"
      maxLength={14}
      placeholder="6-digit code"
      aria-label="Authenticator code"
      className="w-full max-w-[14rem] rounded-xl border border-line px-4 py-2.5 text-center text-lg font-bold tracking-widest text-navy-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
    />
  );

  return (
    <Card>
      <CardHeader title="Two-step verification" action={<Badge tone={status.enabled ? "green" : "slate"}>{status.enabled ? "On" : "Off"}</Badge>} />
      <div className="space-y-4 p-5 sm:p-6">
        {step === "idle" && (
          <>
            <p className="flex items-start gap-2 text-sm text-slate-600">
              {status.enabled ? <ShieldCheck className="mt-0.5 size-4 shrink-0 text-green-600" /> : <ShieldOff className="mt-0.5 size-4 shrink-0 text-slate-400" />}
              {status.enabled
                ? "Your account asks for a code from your authenticator app every time you log in."
                : "Add a second lock: after the emailed code you also type a 6-digit code from an authenticator app (Google Authenticator, Authy, Microsoft Authenticator)."}
            </p>
            {error && <p className="text-sm font-medium text-red-600">{error}</p>}
            {status.enabled ? (
              <Button variant="outline" onClick={() => { setStep("off"); setCode(""); setError(""); }}>Turn off</Button>
            ) : (
              <Button onClick={start} loading={busy}>Set up</Button>
            )}
          </>
        )}

        {step === "scan" && (
          <>
            <p className="text-sm text-slate-600">1. Scan this picture with your authenticator app, or type the key by hand.</p>
            <div className="flex flex-wrap items-center gap-4">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {qr && <img src={qr} alt="Scan with your authenticator app" width={192} height={192} className="rounded-xl border border-line" />}
              <code className="break-all rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-navy-900">{secret}</code>
            </div>
            <p className="text-sm text-slate-600">2. Enter the 6-digit code the app shows.</p>
            {input}
            {error && <p className="text-sm font-medium text-red-600">{error}</p>}
            <div className="flex gap-2">
              <Button onClick={confirm} loading={busy} disabled={code.replace(/\s/g, "").length < 6}>Turn on</Button>
              <Button variant="outline" onClick={() => setStep("idle")}>Cancel</Button>
            </div>
          </>
        )}

        {step === "codes" && (
          <>
            <p className="rounded-lg bg-amber-50 px-3.5 py-2.5 text-sm font-medium text-amber-800">Save these recovery codes now. Each works once if you lose your phone. They are not shown again.</p>
            <ul className="grid grid-cols-2 gap-2 font-mono text-sm text-navy-900">
              {codes.map((c) => (
                <li key={c} className="rounded-lg bg-slate-100 px-3 py-2">{c}</li>
              ))}
            </ul>
            <Button onClick={() => { setStep("idle"); setCode(""); }}>I have saved them</Button>
          </>
        )}

        {step === "off" && (
          <>
            <p className="text-sm text-slate-600">Enter a current code from your app (or a recovery code) to turn two-step verification off.</p>
            {input}
            {error && <p className="text-sm font-medium text-red-600">{error}</p>}
            <div className="flex gap-2">
              <Button variant="danger" onClick={turnOff} loading={busy} disabled={!code.trim()}>Turn off</Button>
              <Button variant="outline" onClick={() => setStep("idle")}>Cancel</Button>
            </div>
          </>
        )}
      </div>
    </Card>
  );
}
