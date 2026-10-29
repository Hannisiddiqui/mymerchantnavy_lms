import { Clock, FileQuestion, RotateCcw, Trophy } from "lucide-react";
import type { TestSummary } from "@/types";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { cn } from "@/lib/utils";

/** A test row/card with Start / Reattempt / View result. Used in course curriculum and Mock Tests. */
export function TestCard({ test, showCourse, className }: { test: TestSummary; showCourse?: boolean; className?: string }) {
  const attempted = test.attemptsCount > 0;
  return (
    <div className={cn("flex flex-col gap-4 rounded-2xl border border-line bg-white p-5 transition-colors sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900", className)}>
      <div className={cn("grid size-12 shrink-0 place-items-center rounded-xl", test.kind === "mock" ? "bg-navy-900 text-white dark:bg-slate-800 dark:text-brand-300" : "bg-green-50 text-green-600 dark:bg-emerald-950/40 dark:text-emerald-400")}>
        <Trophy className="size-6" />
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={test.kind === "mock" ? "navy" : "green"}>{test.kind === "mock" ? "Mock test" : "Practice test"}</Badge>
          {attempted && <Badge tone="blue">Best: {test.bestScore}/{test.totalMarks}</Badge>}
        </div>
        <h3 className="mt-1.5 font-bold text-navy-900 dark:text-white">{test.title}</h3>
        <p className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-500 dark:text-slate-400">
          <span className="flex items-center gap-1">
            <FileQuestion className="size-3.5" /> {test.questionsCount} questions · {test.totalMarks} marks
          </span>
          <span className="flex items-center gap-1">
            <Clock className="size-3.5" /> {test.durationMinutes} min
          </span>
          {showCourse && <span>{test.courseTitle}</span>}
        </p>
      </div>
      <div className="flex gap-2 sm:shrink-0">
        {attempted && test.lastAttemptId && (
          <Button variant="navy" href={`/results/${test.lastAttemptId}`}>
            View result
          </Button>
        )}
        <Button href={`/tests/${test.id}`} variant={attempted ? "outline" : "primary"}>
          {attempted ? (
            <>
              <RotateCcw className="size-4" /> Reattempt
            </>
          ) : (
            "Start test"
          )}
        </Button>
      </div>
    </div>
  );
}
