import { Check, Lightbulb, X } from "lucide-react";
import type { SolutionQuestion } from "@/types";
import { cn } from "@/lib/utils";
import { MathText } from "@/components/math/MathText";
import { Badge } from "@/components/ui/Badge";
import { ReportQuestion } from "@/components/result/ReportQuestion";

export type SolutionOutcome = "correct" | "incorrect" | "skipped";

export function outcomeOf(q: SolutionQuestion): SolutionOutcome {
  if (q.selectedOptionId == null) return "skipped";
  return q.selectedOptionId === q.correctOptionId ? "correct" : "incorrect";
}

/** One question in the detailed solutions: your answer, the correct answer and the explanation. */
export function SolutionItem({ q, number, marksCorrect, marksWrong, attemptId }: { q: SolutionQuestion; number: number; marksCorrect: number; marksWrong: number; attemptId: string }) {
  const outcome = outcomeOf(q);
  return (
    <article className="rounded-2xl border border-line bg-white p-5 sm:p-6" id={`q-${number}`}>
      <header className="flex flex-wrap items-center gap-2">
        <span className="grid size-8 place-items-center rounded-lg bg-navy-900 text-sm font-bold text-white">{number}</span>
        {outcome === "correct" && <Badge tone="green">Correct · +{marksCorrect}</Badge>}
        {outcome === "incorrect" && <Badge tone="red">Incorrect · −{marksWrong}</Badge>}
        {outcome === "skipped" && <Badge tone="slate">Skipped · 0</Badge>}
      </header>

      <MathText html={q.body} className="mt-4 text-[15px] leading-relaxed text-navy-900" />

      <ul className="mt-4 space-y-2">
        {q.options.map((opt) => {
          const isCorrect = opt.id === q.correctOptionId;
          const isChosen = opt.id === q.selectedOptionId;
          return (
            <li
              key={opt.id}
              className={cn(
                "flex items-center gap-3 rounded-xl border px-4 py-3 text-[15px]",
                isCorrect ? "border-green-300 bg-green-50" : isChosen ? "border-red-300 bg-red-50" : "border-line",
              )}
            >
              <span className="w-4 text-sm font-semibold text-slate-500">{opt.label}</span>
              <MathText as="span" html={opt.body} className="flex-1 text-navy-900 [&_p]:m-0" />
              {isChosen && <span className={cn("text-xs font-semibold", isCorrect ? "text-green-700" : "text-red-700")}>Your answer</span>}
              {isCorrect ? (
                <Check className="size-5 shrink-0 text-green-600" aria-label="Correct answer" />
              ) : isChosen ? (
                <X className="size-5 shrink-0 text-red-600" aria-label="Wrong answer" />
              ) : null}
            </li>
          );
        })}
      </ul>

      <div className="mt-4 rounded-xl bg-brand-50/70 p-4">
        <p className="mb-1 flex items-center gap-1.5 text-sm font-bold text-brand-700">
          <Lightbulb className="size-4" /> Solution
        </p>
        <MathText html={q.explanation} className="text-[15px] leading-relaxed text-slate-700" />
      </div>

      <ReportQuestion attemptId={attemptId} questionId={q.id} number={number} report={q.report} />
    </article>
  );
}
