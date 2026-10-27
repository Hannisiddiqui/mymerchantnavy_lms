import { cn } from "@/lib/utils";

/** Circular gauge for a 0-100 value (accuracy). `value` null draws an empty ring with a dash. */
export function Ring({
  value,
  size = 96,
  stroke = 10,
  className,
  ringClass = "text-brand-600",
  children,
}: {
  value: number | null;
  size?: number;
  stroke?: number;
  className?: string;
  ringClass?: string;
  children?: React.ReactNode;
}) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const pct = Math.min(100, Math.max(0, value ?? 0));
  return (
    <div className={cn("relative grid shrink-0 place-items-center", className)} style={{ width: size, height: size }} role="img" aria-label={value === null ? "No data yet" : `${value}%`}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90" aria-hidden>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="currentColor" strokeWidth={stroke} className="text-slate-100" />
        {value !== null && pct > 0 && (
          <circle
            cx={size / 2}
            cy={size / 2}
            r={r}
            fill="none"
            stroke="currentColor"
            strokeWidth={stroke}
            strokeLinecap="round"
            strokeDasharray={`${(pct / 100) * c} ${c}`}
            className={cn("transition-all duration-700", ringClass)}
          />
        )}
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        {children ?? <span className="font-display text-lg font-bold text-navy-900">{value === null ? "—" : `${value}%`}</span>}
      </div>
    </div>
  );
}
