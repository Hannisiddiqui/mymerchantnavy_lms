"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import Link from "next/link";
import { Lock, ShieldAlert, ArrowRight, MessageCircle } from "lucide-react";
import * as pageVisibilityApi from "@/lib/api/pageVisibility";
import type { PageVisibilitySettings } from "@/lib/api/pageVisibility";

const ROUTE_VISIBILITY_MAP: Record<string, keyof PageVisibilitySettings> = {
  "/batches": "batches",
  "/my-courses": "my_courses",
  "/mock-tests": "mock_tests",
  "/performance": "performance",
  "/results": "results",
  "/notes": "notes",
  "/profile": "profile",
};

export function PageVisibilityGuard({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [visibility, setVisibility] = useState<PageVisibilitySettings | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;
    pageVisibilityApi
      .get()
      .then((data) => {
        if (mounted) {
          setVisibility(data);
          setLoading(false);
        }
      })
      .catch(() => {
        if (mounted) {
          setVisibility(pageVisibilityApi.defaultPageVisibility);
          setLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, [pathname]);

  if (loading || !visibility) {
    return <>{children}</>;
  }

  // Determine if current pathname matches a disabled module
  let matchedKey: keyof PageVisibilitySettings | null = null;
  for (const [routePrefix, key] of Object.entries(ROUTE_VISIBILITY_MAP)) {
    if (pathname === routePrefix || pathname.startsWith(`${routePrefix}/`)) {
      matchedKey = key;
      break;
    }
  }

  const isBlocked = matchedKey ? visibility[matchedKey] === false : false;

  if (isBlocked) {
    const formattedName = matchedKey ? matchedKey.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()) : "This section";

    // Find the first enabled route to recommend
    const firstEnabledRoute =
      visibility.my_courses ? "/my-courses" :
      visibility.batches ? "/batches" :
      visibility.mock_tests ? "/mock-tests" :
      "/my-courses";

    return (
      <div className="flex min-h-[60vh] items-center justify-center py-12 px-4">
        <div className="w-full max-w-lg rounded-2xl border border-line bg-card p-8 text-center shadow-lg dark:border-slate-800 dark:bg-slate-900">
          <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-8 ring-amber-500/5">
            <Lock className="size-7" />
          </div>

          <span className="inline-block rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:bg-amber-950/60 dark:text-amber-400">
            Access Restricted
          </span>

          <h2 className="mt-3 text-xl font-extrabold text-navy-900 dark:text-white">
            {formattedName} is Currently Unavailable
          </h2>

          <p className="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            Access to this page has been temporarily turned off by the academic administration. 
            Direct URL navigation is blocked while maintenance or updates are in progress.
          </p>

          <div className="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
            <Link
              href={firstEnabledRoute}
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none"
            >
              Return to Active LMS
              <ArrowRight className="size-4" />
            </Link>

            <button
              type="button"
              onClick={() => {
                const chatBtn = document.querySelector<HTMLButtonElement>('[data-support-trigger="true"]');
                if (chatBtn) chatBtn.click();
              }}
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-line px-5 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              <MessageCircle className="size-4 text-brand-500" />
              Contact Support
            </button>
          </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

