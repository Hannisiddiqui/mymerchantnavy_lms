"use client";

import { Menu } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { NotificationBell } from "./NotificationBell";
import { ProfileDropdown } from "./ProfileDropdown";

export function Topbar({ onMenu }: { onMenu: () => void }) {
  const { data: me } = useApi(() => api.profile.getMe(), []);

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur transition-colors dark:border-slate-800 dark:bg-slate-900/90 sm:px-6">
      <button
        onClick={onMenu}
        className="grid size-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
        aria-label="Open menu"
      >
        <Menu className="size-5" />
      </button>

      <p className="hidden text-sm text-slate-500 dark:text-slate-400 sm:block">
        Welcome back{me ? "," : ""}{" "}
        <span className="font-semibold text-navy-900 dark:text-white">
          {me?.nickname || me?.name || "Aspirant"}
        </span>{" "}
        👋
      </p>

      {Boolean(me?.isPreview || (me as any)?.is_preview || me?.email?.includes("@preview.") || me?.name?.startsWith("Preview:")) && (
        <a
          href="https://api.mymerchantnavy.com/admin/courses"
          className="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/70"
          title="Click to return to the admin dashboard"
        >
          <span className="size-2 rounded-full bg-amber-500 animate-pulse" />
          <span>Student Preview</span>
          <span className="hidden text-[10px] text-amber-600 dark:text-amber-400 sm:inline">· Return to Admin ↗</span>
        </a>
      )}

      <div className="ml-auto flex items-center gap-2 sm:gap-3">
        <NotificationBell />
        <ProfileDropdown user={me ?? null} />
      </div>
    </header>
  );
}
