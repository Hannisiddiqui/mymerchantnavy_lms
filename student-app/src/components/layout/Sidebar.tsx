"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { LifeBuoy, LogOut, PanelLeftClose, PanelLeftOpen, X } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { brand, sidebarNav } from "@/lib/config";
import { cn } from "@/lib/utils";
import { Logo } from "@/components/ui/Logo";

/**
 * Left navigation. Menu items come from `sidebarNav` in lib/config.ts.
 * On desktop it can be collapsed to icons only (`collapsed`); on phones it is a slide-in drawer (`open`).
 */
export function Sidebar({
  open,
  onClose,
  collapsed,
  onToggleCollapsed,
}: {
  open: boolean;
  onClose: () => void;
  collapsed: boolean;
  onToggleCollapsed: () => void;
}) {
  const pathname = usePathname();
  const router = useRouter();
  const { data: visibility } = useApi(() => api.pageVisibility.get(), []);

  async function logout() {
    await api.auth.logout();
    router.replace("/login");
  }

  // Text that disappears when collapsed (desktop only)
  const label = collapsed ? "lg:hidden" : "";

  const visibleNav = sidebarNav.filter(({ href }) => {
    if (!visibility) return true;
    if (href === "/my-courses" && visibility.my_courses === false) return false;
    if (href === "/batches" && visibility.batches === false) return false;
    if (href === "/mock-tests" && visibility.mock_tests === false) return false;
    if (href === "/performance" && visibility.performance === false) return false;
    if (href === "/results" && visibility.results === false) return false;
    if (href === "/notes" && visibility.notes === false) return false;
    if (href === "/profile" && visibility.profile === false) return false;
    return true;
  });

  return (
    <>
      <div className={cn("fixed inset-0 z-30 bg-navy-950/50 lg:hidden", open ? "block" : "hidden")} onClick={onClose} />

      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-navy-900 text-slate-300 transition-all duration-200 lg:translate-x-0",
          collapsed ? "lg:w-[4.5rem]" : "lg:w-64",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className={cn("flex h-16 items-center justify-between px-5", collapsed && "lg:justify-center lg:px-0")}>
          <Link href="/my-courses" onClick={onClose} className={label}>
            <Logo tone="light" className="h-9" />
          </Link>
          <button
            onClick={onToggleCollapsed}
            className="hidden rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:block"
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            {collapsed ? <PanelLeftOpen className="size-5" /> : <PanelLeftClose className="size-5" />}
          </button>
          <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Close menu">
            <X className="size-5" />
          </button>
        </div>

        <p className={cn("mt-4 px-6 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500", label)}>Learn</p>
        <nav className="mt-2 flex flex-col gap-1 px-3">
          {visibleNav.map(({ label: text, href, icon: Icon, alsoActiveOn = [] }) => {
            const active = [href, ...alsoActiveOn].some((p) => pathname === p || pathname.startsWith(`${p}/`));
            return (
              <Link
                key={href}
                href={href}
                onClick={onClose}
                title={collapsed ? text : undefined}
                className={cn(
                  "group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition",
                  collapsed && "lg:justify-center",
                  active ? "bg-white/10 text-white" : "hover:bg-white/5 hover:text-white",
                )}
              >
                {active && <span className="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-500" />}
                <Icon className={cn("size-5 shrink-0", active ? "text-brand-200" : "text-slate-400 group-hover:text-slate-200")} />
                <span className={label}>{text}</span>
              </Link>
            );
          })}
        </nav>

        <div className="mt-auto space-y-3 p-4">
          <div className={cn("rounded-xl bg-white/5 p-4 ring-1 ring-white/10", label)}>
            <LifeBuoy className="size-5 text-brand-200" />
            <p className="mt-2 text-sm font-semibold text-white">Need help?</p>
            <a href={`mailto:${brand.supportEmail}`} className="mt-0.5 block truncate text-xs text-slate-400 hover:text-white">
              {brand.supportEmail}
            </a>
          </div>
          <button
            onClick={logout}
            title={collapsed ? "Log out" : undefined}
            className={cn("flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-white/5 hover:text-white", collapsed && "lg:justify-center")}
          >
            <LogOut className="size-5 shrink-0 text-slate-400" /> <span className={label}>Log out</span>
          </button>
        </div>
      </aside>
    </>
  );
}
