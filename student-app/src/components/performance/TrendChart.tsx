"use client";

import { useRef, useState } from "react";
import type { PerformanceTrendPoint } from "@/types";
import { formatDate } from "@/lib/utils";

const W = 640;
const H = 230;
const PAD = { top: 16, right: 18, bottom: 28, left: 38 };

const shortDate = (iso: string) => new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "short" });

/**
 * Score of the latest attempts (as % of the test's marks), oldest to newest. Plain SVG, scales to any width.
 * Hover or touch shows the attempt; the newest is shown by default. A hidden table carries the same numbers.
 */
export function TrendChart({ points }: { points: PerformanceTrendPoint[] }) {
  const box = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState<number | null>(null);

  const innerW = W - PAD.left - PAD.right;
  const innerH = H - PAD.top - PAD.bottom;
  const x = (i: number) => PAD.left + (points.length === 1 ? innerW / 2 : (i / (points.length - 1)) * innerW);
  const y = (v: number) => PAD.top + innerH - (Math.min(100, Math.max(0, v)) / 100) * innerH;
  const line = points.map((p, i) => `${i ? "L" : "M"}${x(i).toFixed(1)},${y(p.scorePercent).toFixed(1)}`).join(" ");
  const area = points.length > 1 ? `${line} L${x(points.length - 1)},${y(0)} L${x(0)},${y(0)} Z` : "";

  function pick(clientX: number) {
    const rect = box.current?.getBoundingClientRect();
    if (!rect || points.length === 0) return;
    const px = ((clientX - rect.left) / rect.width) * W;
    let best = 0;
    points.forEach((_, i) => {
      if (Math.abs(x(i) - px) < Math.abs(x(best) - px)) best = i;
    });
    setActive(best);
  }

  const shown = active ?? points.length - 1;
  const p = points[shown];

  return (
    <div>
      {/* The attempt under the finger or pointer (the newest by default). Fixed place, so it never covers the line. */}
      {p && (
        <div className="mb-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-line">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-navy-900">{p.testTitle}</p>
            <p className="text-xs text-slate-500">
              {formatDate(p.submittedAt)} · {p.testKind === "mock" ? "Mock test" : "Practice test"}
            </p>
          </div>
          <p className="text-sm text-slate-500">
            <b className="font-display text-lg font-bold text-brand-700">{p.scorePercent}%</b> score · <b className="font-semibold text-navy-900">{p.accuracy}%</b> accuracy
          </p>
        </div>
      )}
      <div ref={box} className="relative touch-pan-y select-none" onPointerMove={(e) => pick(e.clientX)} onPointerDown={(e) => pick(e.clientX)} onPointerLeave={() => setActive(null)}>
        <svg viewBox={`0 0 ${W} ${H}`} className="block h-auto w-full" role="img" aria-label="Score trend over your latest attempts">
          {[0, 25, 50, 75, 100].map((t) => (
            <g key={t}>
              <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} className="stroke-slate-200" strokeWidth={1} />
              <text x={PAD.left - 8} y={y(t) + 4} textAnchor="end" className="fill-slate-500" fontSize={11}>
                {t}%
              </text>
            </g>
          ))}
          {area && <path d={area} className="fill-brand-600/10" />}
          {points.length > 1 && <path d={line} fill="none" className="stroke-brand-600" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />}
          {active !== null && <line x1={x(active)} x2={x(active)} y1={PAD.top} y2={y(0)} className="stroke-slate-300" strokeWidth={1} />}
          {points.map((pt, i) => (
            <circle
              key={pt.attemptId}
              cx={x(i)}
              cy={y(pt.scorePercent)}
              r={i === shown ? 5.5 : points.length > 12 ? 3 : 4.5}
              className="fill-brand-600 stroke-white"
              strokeWidth={2}
            />
          ))}
          {points.length > 0 && (
            <text x={x(0)} y={H - 8} textAnchor={points.length === 1 ? "middle" : "start"} className="fill-slate-500" fontSize={11}>
              {shortDate(points[0].submittedAt)}
            </text>
          )}
          {points.length > 1 && (
            <text x={x(points.length - 1)} y={H - 8} textAnchor="end" className="fill-slate-500" fontSize={11}>
              {shortDate(points[points.length - 1].submittedAt)}
            </text>
          )}
        </svg>

      </div>

      <div className="sr-only">
      <table>
        <caption>Score of your latest attempts</caption>
        <thead>
          <tr>
            <th>Date</th>
            <th>Test</th>
            <th>Score</th>
            <th>Accuracy</th>
          </tr>
        </thead>
        <tbody>
          {points.map((pt) => (
            <tr key={pt.attemptId}>
              <td>{formatDate(pt.submittedAt)}</td>
              <td>{pt.testTitle}</td>
              <td>{pt.scorePercent}%</td>
              <td>{pt.accuracy}%</td>
            </tr>
          ))}
        </tbody>
      </table>
      </div>
    </div>
  );
}
