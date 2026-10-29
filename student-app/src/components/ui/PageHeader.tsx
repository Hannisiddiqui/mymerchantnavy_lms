import Link from "next/link";
import { ChevronRight } from "lucide-react";

export interface Crumb {
  label: string;
  href?: string;
}

export function PageHeader({ title, subtitle, crumbs, action }: { title: string; subtitle?: string; crumbs?: Crumb[]; action?: React.ReactNode }) {
  return (
    <div className="mb-6">
      {crumbs && crumbs.length > 0 && (
        <nav className="mb-2 flex flex-wrap items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
          {crumbs.map((c, i) => (
            <span key={i} className="flex items-center gap-1">
              {c.href ? (
                <Link href={c.href} className="hover:text-brand-600 dark:hover:text-brand-400">
                  {c.label}
                </Link>
              ) : (
                <span className="text-slate-700 dark:text-slate-200">{c.label}</span>
              )}
              {i < crumbs.length - 1 && <ChevronRight className="size-3.5 text-slate-400 dark:text-slate-500" />}
            </span>
          ))}
        </nav>
      )}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-navy-900 dark:text-white sm:text-2xl">{title}</h1>
          {subtitle && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{subtitle}</p>}
        </div>
        {action}
      </div>
    </div>
  );
}
