"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ShieldCheck } from "lucide-react";
import * as api from "@/lib/api";
import { TwoFactorCard } from "@/components/profile/TwoFactorCard";
import { useRequireAuth } from "@/lib/use-require-auth";

/** Shown to a student who has logged in but has not yet set up the (compulsory) two-step verification. */
export default function SecuritySetupPage() {
  const router = useRouter();
  const signedIn = useRequireAuth();
  const [checked, setChecked] = useState(false);

  useEffect(() => {
    if (!signedIn) return;
    api.twoFactor
      .status()
      .then((s) => {
        if (!s.required || s.enabled) router.replace("/my-courses");
        else setChecked(true);
      })
      .catch(() => setChecked(true));
  }, [signedIn, router]);

  if (!signedIn || !checked) return null;

  return (
    <div>
      <div className="mb-6 grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <ShieldCheck className="size-6" />
      </div>
      <h2 className="text-2xl text-navy-900 sm:text-3xl">Secure your account</h2>
      <p className="mt-2 mb-6 text-slate-500">
        To keep your course and results safe, two-step verification is compulsory. It takes a minute: install an authenticator app (Google Authenticator, Authy or Microsoft Authenticator), scan the picture, and type the 6-digit code.
      </p>
      <TwoFactorCard onEnabled={() => router.replace("/my-courses")} />
    </div>
  );
}
