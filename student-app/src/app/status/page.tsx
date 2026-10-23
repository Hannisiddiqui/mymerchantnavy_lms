"use client";

import { useEffect, useState } from "react";
import { apiBaseUrl } from "@/lib/api/client";

type State = { kind: "checking" } | { kind: "ok"; ms: number } | { kind: "down"; why: string };

/**
 * A page anyone can open (/status) to see whether this site can reach its server, and which server it uses.
 * Handy when a student says "it does not work": ask them to open this page and send a screenshot.
 */
export default function StatusPage() {
  const [api, setApi] = useState("");
  const [origin, setOrigin] = useState("");
  const [online, setOnline] = useState<boolean | null>(null);
  const [state, setState] = useState<State>({ kind: "checking" });

  useEffect(() => {
    // Browser-only facts are read after the page has loaded, so the first render matches the server's.
    setOrigin(window.location.origin);
    setOnline(navigator.onLine);
    let base = "";
    try {
      base = apiBaseUrl();
    } catch (err) {
      setState({ kind: "down", why: err instanceof Error ? err.message : "Unknown address" });
      return;
    }
    setApi(base);
    const started = performance.now();
    // Any HTTP answer (even "not logged in", 401) proves the server is reachable.
    fetch(`${base}/me`, { headers: { Accept: "application/json" } })
      .then(() => setState({ kind: "ok", ms: Math.round(performance.now() - started) }))
      .catch(() => setState({ kind: "down", why: "No answer from the server. Check your internet connection." }));
  }, []);

  return (
    <main className="mx-auto max-w-md px-5 py-16">
      <h1 className="text-2xl text-navy-900">Connection check</h1>
      <p className="mt-2 text-sm text-slate-500">Shows whether this website can reach its server.</p>

      <div className="mt-8 rounded-2xl border border-line bg-white p-5">
        {state.kind === "checking" && <p className="text-slate-600">Checking…</p>}
        {state.kind === "ok" && (
          <p className="font-semibold text-green-700">
            ✓ Connected <span className="font-normal text-slate-500">({state.ms} ms)</span>
          </p>
        )}
        {state.kind === "down" && (
          <>
            <p className="font-semibold text-red-700">✕ Cannot reach the server</p>
            <p className="mt-1 text-sm text-slate-600">{state.why}</p>
          </>
        )}
        <dl className="mt-5 space-y-2 text-xs text-slate-500">
          <div>
            <dt className="font-semibold text-slate-600">Server address</dt>
            <dd className="break-all">{api || "not set"}</dd>
          </div>
          <div>
            <dt className="font-semibold text-slate-600">This website</dt>
            <dd className="break-all">{origin || "…"}</dd>
          </div>
          <div>
            <dt className="font-semibold text-slate-600">Your connection</dt>
            <dd>{online === null ? "…" : online ? "online" : "offline"}</dd>
          </div>
        </dl>
      </div>
    </main>
  );
}
