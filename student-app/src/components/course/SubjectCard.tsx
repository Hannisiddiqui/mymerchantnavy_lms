import Link from "next/link";
import { ChevronRight } from "lucide-react";
import type { SubjectSummary } from "@/types";
import { ProgressBar } from "@/components/ui/ProgressBar";

export function SubjectCard({ subject, courseId, index }: { subject: SubjectSummary; courseId: number; index: number }) {
  return (
    <Link
      href={`/my-courses/${courseId}/subjects/${subject.id}`}
      className="group flex items-center gap-4 rounded-2xl border border-line bg-white p-5 transition hover:border-brand-200 hover:shadow-md hover:shadow-brand-600/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
    >
      <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-50 font-display text-lg font-extrabold text-brand-600 dark:bg-brand-950/40 dark:text-brand-400">
        {String(index).padStart(2, "0")}
      </span>
      <div className="min-w-0 flex-1">
        <h3 className="font-bold text-navy-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{subject.title}</h3>
        <p className="mt-0.5 truncate text-sm text-slate-500 dark:text-slate-400">{subject.description}</p>
        <div className="mt-3 flex items-center gap-3">
          <ProgressBar value={subject.progressPercent} className="max-w-48" />
          <span className="shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400">
            {subject.chaptersCount} chapters · {subject.contentsCount} items · {subject.progressPercent}%
          </span>
        </div>
      </div>
      <ChevronRight className="size-5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600 dark:text-slate-500 dark:group-hover:text-brand-400" />
    </Link>
  );
}
