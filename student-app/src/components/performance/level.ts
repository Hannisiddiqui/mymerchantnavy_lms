/** How a chapter or subject is doing, from its accuracy. Always shown with a label, never colour alone. */
export interface Level {
  key: "new" | "strong" | "steady" | "weak";
  label: string;
  /** Tailwind classes: badge, bar fill and accent text. */
  badge: string;
  bar: string;
  text: string;
}

export function levelOf(accuracy: number | null): Level {
  if (accuracy === null) return { key: "new", label: "Not started", badge: "bg-slate-100 text-slate-600 ring-slate-200", bar: "bg-slate-300", text: "text-slate-500" };
  if (accuracy >= 75) return { key: "strong", label: "Strong", badge: "bg-green-50 text-green-700 ring-green-100", bar: "bg-green-500", text: "text-green-700" };
  if (accuracy >= 50) return { key: "steady", label: "Getting there", badge: "bg-amber-50 text-amber-700 ring-amber-100", bar: "bg-amber-500", text: "text-amber-700" };
  return { key: "weak", label: "Needs work", badge: "bg-red-50 text-red-700 ring-red-100", bar: "bg-red-500", text: "text-red-700" };
}
