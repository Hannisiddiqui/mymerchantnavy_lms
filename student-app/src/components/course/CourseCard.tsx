import Link from "next/link";
import { ArrowRight, BookOpen, Layers, Target } from "lucide-react";
import type { CourseSummary } from "@/types";
import { cn } from "@/lib/utils";
import { ProgressBar } from "@/components/ui/ProgressBar";

export function CourseCard({ course }: { course: CourseSummary }) {
  return (
    <Link
      href={`/my-courses/${course.id}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-600/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
    >
      <div className={cn("relative h-20 bg-gradient-to-br p-4 sm:h-28 sm:p-5", course.accent)}>
        <svg aria-hidden className="absolute bottom-0 right-0 h-full opacity-20" viewBox="0 0 200 100" fill="none">
          <path d="M0 70 Q50 50 100 70 T200 70 V100 H0Z" fill="white" />
          <path d="M0 82 Q50 66 100 82 T200 82 V100 H0Z" fill="white" />
        </svg>
        <span className="relative inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-white ring-1 ring-white/25 backdrop-blur">
          <BookOpen className="size-3.5" /> Course
        </span>
      </div>
      <div className="flex flex-1 flex-col p-5">
        <h3 className="text-lg font-bold leading-snug text-navy-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{course.title}</h3>
        <p className="mt-1.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{course.description}</p>
        <div className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-500 dark:text-slate-400">
          <span className="flex items-center gap-1.5">
            <Layers className="size-3.5" /> {course.subjectsCount} subjects · {course.chaptersCount} chapters
          </span>
          <span className="flex items-center gap-1.5">
            <Target className="size-3.5" /> {course.practiceTestsCount} practice · {course.mockTestsCount} mock tests
          </span>
        </div>
        <div className="mt-auto pt-5">
          <div className="mb-1.5 flex justify-between text-xs font-semibold">
            <span className="text-slate-500 dark:text-slate-400">Progress</span>
            <span className="text-navy-900 dark:text-white">{course.progressPercent}%</span>
          </div>
          <ProgressBar value={course.progressPercent} />
          {course.expiresAt && (
            <p className={cn("mt-3 text-xs font-semibold", course.isExpired ? "text-red-600" : "text-slate-500")}>
              {course.isExpired
                ? `Expired on ${new Date(course.expiresAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}. Renew to continue.`
                : `Valid until ${new Date(course.expiresAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}`}
            </p>
          )}
          <span className={cn("mt-4 flex items-center gap-1.5 text-sm font-semibold", course.isExpired ? "text-red-600" : "text-brand-600")}>
            {course.isExpired ? "Course expired" : "Continue learning"} <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
          </span>
        </div>
      </div>
    </Link>
  );
}
