"use client";

import { useEffect, useState, useCallback } from "react";
import { tokenStore } from "@/lib/api/client";
import * as api from "@/lib/api";
import {
  checkAndClaimTabSlot,
  keepTabAlive,
  releaseTabSlot,
  subscribeToTabEvents,
  type TabCheckResult,
} from "@/lib/tab-manager";

export function TabLimitGuard({ children }: { children: React.ReactNode }) {
  const [isBlocked, setIsBlocked] = useState(false);
  const [tabInfo, setTabInfo] = useState<{ maxTabs: number; activeCount: number }>({
    maxTabs: 2,
    activeCount: 1,
  });
  const [checking, setChecking] = useState(false);
  const [policy, setPolicy] = useState<api.auth.SessionPolicy | null>(null);

  // Fetch session limit policy from server
  useEffect(() => {
    let mounted = true;
    api.auth
      .getSessionPolicy()
      .then((p) => {
        if (mounted) setPolicy(p);
      })
      .catch(() => {
        // Fallback default
        if (mounted) {
          setPolicy({
            device_limit_enabled: true,
            student_max_devices: 2,
            tab_limit_enabled: true,
            student_max_tabs: 2,
          });
        }
      });
    return () => {
      mounted = false;
    };
  }, []);

  const evaluateTab = useCallback(() => {
    const token = tokenStore.get();
    // If visitor is not logged in, do not block tab
    if (!token) {
      setIsBlocked(false);
      return;
    }

    const enabled = policy ? policy.tab_limit_enabled : true;
    const maxTabs = policy ? policy.student_max_tabs : 2;

    const result: TabCheckResult = checkAndClaimTabSlot(maxTabs, enabled);
    setTabInfo({ maxTabs: result.maxTabs, activeCount: result.activeCount });
    setIsBlocked(!result.allowed);
  }, [policy]);

  useEffect(() => {
    evaluateTab();

    // Heartbeat every 2.5 seconds to keep slot active
    const heartbeatTimer = setInterval(() => {
      if (!isBlocked && tokenStore.get()) {
        keepTabAlive();
      }
    }, 2500);

    // Release tab when closing/unloading
    const handleUnload = () => {
      releaseTabSlot();
    };
    window.addEventListener("beforeunload", handleUnload);

    // Listen for tab closed / opened events from other tabs
    const unsubscribe = subscribeToTabEvents(() => {
      evaluateTab();
    });

    return () => {
      clearInterval(heartbeatTimer);
      window.removeEventListener("beforeunload", handleUnload);
      unsubscribe();
      releaseTabSlot();
    };
  }, [evaluateTab, isBlocked]);

  const handleManualCheck = () => {
    setChecking(true);
    setTimeout(() => {
      evaluateTab();
      setChecking(false);
    }, 400);
  };

  const handleLogout = async () => {
    releaseTabSlot();
    await api.auth.logout();
    window.location.href = "/login";
  };

  if (isBlocked) {
    return (
      <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/90 backdrop-blur-md p-4">
        <div className="w-full max-w-lg rounded-2xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-100 text-center animate-in fade-in zoom-in-95 duration-200">
          <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-8 ring-amber-50/50">
            <svg
              className="size-8"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth="2"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M4 6a2 2 0 012-2h12a2 2 0 012 2v3H4V6zm0 5h16v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 14v2m0 0h.01"
              />
            </svg>
          </div>

          <div className="mt-5 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
            <span className="size-1.5 rounded-full bg-amber-600 animate-pulse"></span>
            Tab Concurrency Limit Reached
          </div>

          <h2 className="mt-3 text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
            Tab Limit Exceeded
          </h2>

          <p className="mt-2 text-sm text-slate-600 leading-relaxed">
            You already have{" "}
            <span className="font-semibold text-slate-900">
              {tabInfo.maxTabs} active {tabInfo.maxTabs === 1 ? "tab" : "tabs"}
            </span>{" "}
            open for this LMS. Simultaneous browsing is restricted to ensure system stability and test integrity.
          </p>

          <div className="mt-6 rounded-xl bg-slate-50 border border-slate-200 p-4 text-left">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
              How to continue:
            </h4>
            <ul className="mt-2.5 space-y-2 text-xs sm:text-sm text-slate-700">
              <li className="flex items-start gap-2">
                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[11px] font-bold text-slate-700">
                  1
                </span>
                <span>
                  <strong>Close this tab</strong> and continue studying in your existing window.
                </span>
              </li>
              <li className="flex items-start gap-2">
                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[11px] font-bold text-slate-700">
                  2
                </span>
                <span>
                  Or <strong>close your other open tab(s)</strong> and this tab will unlock automatically.
                </span>
              </li>
            </ul>
          </div>

          <div className="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
            <button
              type="button"
              onClick={handleManualCheck}
              disabled={checking}
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 active:scale-[0.98] transition-all disabled:opacity-50"
            >
              {checking ? (
                <>
                  <svg className="size-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  Checking...
                </>
              ) : (
                <>
                  <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Check Availability & Retry
                </>
              )}
            </button>

            <button
              type="button"
              onClick={handleLogout}
              className="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Sign Out
            </button>
          </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
