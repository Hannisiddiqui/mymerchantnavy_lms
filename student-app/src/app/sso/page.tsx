"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as api from "@/lib/api";

/**
 * Where the WordPress site sends a student after payment: https://lms.<domain>/sso#t=<one-time token>.
 * The token is in the part after "#", so it never reaches any server log. It signs the student in, then they land on
 * their courses (or on the short profile form the very first time).
 */
export default function SsoPage() {
  const router = useRouter();
  const started = useRef(false); // the token works once: never send it twice
  const [error, setError] = useState("");

  useEffect(() => {
    if (started.current) return;
    started.current = true;
    const token = new URLSearchParams(window.location.hash.replace(/^#/, "")).get("t");
    window.history.replaceState(null, "", "/sso");
    if (!token) {
      router.replace("/login");
      return;
    }
    api.auth
      .ssoLogin(token)
      .then(({ needsProfile, redirect }) => {
        // the page to open (e.g. the course just bought); only ever a path on this site
        const target = redirect && /^\/[\w/-]*$/.test(redirect) && !redirect.includes("//") ? redirect : "/my-courses";
        router.replace(needsProfile ? "/onboarding" : target);
      })
      .catch((err) => setError(err instanceof Error ? err.message : "Could not sign you in."));
  }, [router]);

  return (
    <main className="mx-auto grid min-h-dvh max-w-md place-items-center px-5 text-center">
      {error ? (
        <div>
          <p className="font-semibold text-red-700">{error}</p>
          <Link href="/login" className="mt-4 inline-block font-semibold text-brand-600 underline">
            Log in with your email
          </Link>
        </div>
      ) : (
        <p className="text-slate-600">Signing you in…</p>
      )}
    </main>
  );
}
