import { cn } from "@/lib/utils";

/** Correct / wrong / skipped as one segmented bar (2px gaps), with a legend underneath when `legend` is set. */
export function OutcomeBar({ correct, incorrect, skipped, legend = false, className }: { correct: number; incorrect: number; skipped: number; legend?: boolean; className?: string }) {
  const total = correct + incorrect + skipped;
  const parts = [
    { key: "Correct", n: correct, color: "bg-green-500" },
    { key: "Wrong", n: incorrect, color: "bg-red-500" },
    { key: "Skipped", n: skipped, color: "bg-slate-300" },
  ];
  return (
    <div className={className}>
      <div className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label={`${correct} correct, ${incorrect} wrong, ${skipped} skipped`}>
        {total > 0 &&
          parts
            .filter((p) => p.n > 0)
            .map((p) => <div key={p.key} className={cn("h-full first:rounded-l-full last:rounded-r-full", p.color)} style={{ width: `${(p.n / total) * 100}%` }} />)}
      </div>
      {legend && (
        <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
          {parts.map((p) => (
            <li key={p.key} className="flex items-center gap-1.5">
              <span className={cn("size-2 rounded-full", p.color)} /> {p.key} <b className="font-semibold text-navy-900">{p.n}</b>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
