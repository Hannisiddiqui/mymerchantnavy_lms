"use client";

import Link from "next/link";
import { Menu } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { initials } from "@/lib/utils";
import { NotificationBell } from "./NotificationBell";
import { ThemeToggle } from "@/components/theme/ThemeToggle";

export function Topbar({ onMenu }: { onMenu: () => void }) {
  const { data: me } = useApi(() => api.profile.getMe(), []);

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur transition-colors dark:border-slate-800 dark:bg-slate-900/90 sm:px-6">
      <button onClick={onMenu} className="grid size-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden" aria-label="Open menu">
        <Menu className="size-5" />
      </button>
      <p className="hidden text-sm text-slate-500 dark:text-slate-400 sm:block">
        Welcome back{me ? "," : ""} <span className="font-semibold text-navy-900 dark:text-white">{me?.nickname}</span> 👋
      </p>
      <div className="ml-auto flex items-center gap-2.5">
        <ThemeToggle />
        <NotificationBell />
        <Link href="/profile" className="flex items-center gap-2.5 rounded-xl py-1 pl-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
          <span className="grid size-9 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-navy-800 text-sm font-bold text-white shadow-xs">
            {me ? initials(me.name) : ""}
          </span>
          <span className="hidden text-left leading-tight md:block">
            <span className="block text-sm font-semibold text-navy-900 dark:text-white">{me?.name}</span>
            <span className="block text-xs text-slate-500 dark:text-slate-400">{me?.targetExam}</span>
          </span>
        </Link>
      </div>
    </header>
  );
}
