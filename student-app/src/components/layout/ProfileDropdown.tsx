"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Award,
  BookOpen,
  Check,
  ChevronDown,
  FileText,
  Headphones,
  Laptop,
  Layers,
  LogOut,
  Moon,
  Settings,
  Shield,
  Sun,
  User,
} from "lucide-react";
import * as api from "@/lib/api";
import { initials } from "@/lib/utils";
import { useTheme, type Theme } from "@/components/theme/ThemeProvider";

interface ProfileDropdownProps {
  user: {
    name?: string;
    nickname?: string;
    email?: string;
    phone?: string;
    targetExam?: string;
    attemptYear?: number;
  } | null;
}

export function ProfileDropdown({ user }: ProfileDropdownProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const { theme, setTheme } = useTheme();

  // Close dropdown on click outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        setIsOpen(false);
      }
    }

    if (isOpen) {
      document.addEventListener("mousedown", handleClickOutside);
      document.addEventListener("keydown", handleKeyDown);
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen]);

  async function handleLogout() {
    setIsOpen(false);
    await api.auth.logout();
    router.replace("/login");
  }

  const themeOptions: { value: Theme; label: string; icon: typeof Sun }[] = [
    { value: "light", label: "Light", icon: Sun },
    { value: "dark", label: "Dark", icon: Moon },
    { value: "system", label: "Desktop", icon: Laptop },
  ];

  const displayName = user?.name || user?.nickname || "Student";
  const userInitials = user?.name ? initials(user.name) : "S";

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        aria-expanded={isOpen}
        aria-haspopup="true"
        className="group flex items-center gap-2.5 rounded-xl p-1.5 transition-all hover:bg-slate-100 dark:hover:bg-slate-800/80 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20"
      >
        <div className="relative">
          <span className="grid size-9 place-items-center rounded-full bg-gradient-to-br from-brand-600 via-brand-700 to-navy-900 text-sm font-bold text-white shadow-xs ring-2 ring-transparent transition group-hover:ring-brand-400/40">
            {userInitials}
          </span>
          <span className="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-slate-900" />
        </div>

        <div className="hidden text-left leading-tight md:block">
          <span className="block text-sm font-semibold text-navy-900 transition-colors group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-400">
            {displayName}
          </span>
          <span className="block text-xs text-slate-500 dark:text-slate-400">
            {user?.targetExam || "Student Portal"}
          </span>
        </div>

        <ChevronDown
          className={`size-4 text-slate-400 transition-transform duration-200 group-hover:text-slate-600 dark:text-slate-400 dark:group-hover:text-slate-200 ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      {/* Modern Popover Dropdown */}
      {isOpen && (
        <div
          className="absolute right-0 top-full mt-2.5 w-76 sm:w-80 rounded-2xl border border-slate-200/90 bg-white p-2 shadow-2xl shadow-slate-900/15 backdrop-blur-xl transition-all dark:border-slate-800 dark:bg-slate-900/95 dark:shadow-black/50 z-50 animate-in fade-in slide-in-from-top-2 duration-150"
          role="menu"
        >
          {/* Student Profile Summary Header */}
          <div className="rounded-xl bg-slate-50/80 p-3.5 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 mb-2">
            <div className="flex items-center gap-3">
              <span className="grid size-11 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-navy-900 text-base font-bold text-white shadow-sm shrink-0">
                {userInitials}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold text-navy-950 dark:text-white">
                  {displayName}
                </p>
                {user?.email && (
                  <p className="truncate text-xs text-slate-500 dark:text-slate-400" title={user.email}>
                    {user.email}
                  </p>
                )}
                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                  {user?.targetExam ? (
                    <span className="inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-950/60 dark:text-brand-300">
                      🎯 {user.targetExam}
                    </span>
                  ) : null}
                  {user?.attemptYear ? (
                    <span className="inline-flex items-center rounded-md bg-slate-200/70 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                      Target {user.attemptYear}
                    </span>
                  ) : null}
                </div>
              </div>
            </div>
          </div>

          {/* Navigation Links */}
          <div className="space-y-0.5">
            <Link
              href="/profile"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <User className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">My Profile</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Personal details & target exam</p>
              </div>
            </Link>

            <Link
              href="/profile"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                <Settings className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">Account & Security</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Logged-in devices & sessions</p>
              </div>
            </Link>

            <Link
              href="/my-courses"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                <BookOpen className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">My Courses</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Active enrollments & lectures</p>
              </div>
            </Link>

            <Link
              href="/batches"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                <Layers className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">Batches & Programs</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Explore new target batches</p>
              </div>
            </Link>

            <Link
              href="/results"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400">
                <Award className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">Test Results & Analytics</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Scores, rank & solutions</p>
              </div>
            </Link>

            <Link
              href="/notes"
              onClick={() => setIsOpen(false)}
              className="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
              role="menuitem"
            >
              <span className="grid size-7 place-items-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400">
                <FileText className="size-4" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold">My Notes & Bookmarks</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Saved formulas & doubts</p>
              </div>
            </Link>
          </div>

          {/* Theme Mode Switcher (Modern Segmented Bar) */}
          <div className="mt-2.5 rounded-xl border border-slate-200/80 bg-slate-50/70 p-2.5 dark:border-slate-800 dark:bg-slate-800/40">
            <div className="mb-2 flex items-center justify-between px-1">
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Display Theme
              </span>
              <span className="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-semibold capitalize text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                {theme === "system" ? "Desktop (Auto)" : theme}
              </span>
            </div>

            <div className="grid grid-cols-3 gap-1 rounded-lg bg-slate-200/60 p-1 dark:bg-slate-800/80" role="radiogroup">
              {themeOptions.map(({ value, label, icon: Icon }) => {
                const isActive = theme === value;
                return (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setTheme(value)}
                    role="radio"
                    aria-checked={isActive}
                    title={`${label} Mode${value === "system" ? " (Automatically matches your device/OS theme)" : ""}`}
                    className={`flex items-center justify-center gap-1.5 rounded-md py-1.5 text-xs font-semibold transition-all ${
                      isActive
                        ? "bg-white text-navy-950 shadow-xs dark:bg-slate-700 dark:text-white"
                        : "text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white"
                    }`}
                  >
                    <Icon className="size-3.5" />
                    <span>{label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Divider */}
          <div className="my-2 h-px bg-slate-100 dark:bg-slate-800" />

          {/* Logout Action */}
          <button
            type="button"
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"
            role="menuitem"
          >
            <span className="grid size-7 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
              <LogOut className="size-4" />
            </span>
            <div className="text-left">
              <p className="text-xs font-semibold">Sign Out</p>
              <p className="text-[11px] text-rose-500/70 dark:text-rose-400/60">Log out of this browser</p>
            </div>
          </button>
        </div>
      )}
    </div>
  );
}

