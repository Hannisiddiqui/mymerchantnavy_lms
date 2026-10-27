"use client";

import Link from "next/link";
import { ChevronDown, History, PlayCircle, RotateCcw } from "lucide-react";
import type { ChapterPerformance, SubjectPerformance } from "@/types";
import { cn, timeAgo } from "@/lib/utils";
import { levelOf } from "./level";
import { OutcomeBar } from "./OutcomeBar";
import { Ring } from "./Ring";

const ringColor = { new: "text-slate-300", strong: "text-green-500", steady: "text-amber-500", weak: "text-red-500" } as const;

/** One subject: accuracy ring and totals on top; open it to see every chapter. */
export function SubjectCard({ subject, open, onToggle }: { subject: SubjectPerformance; open: boolean; onToggle: () => void }) {
  const level = levelOf(subject.accuracy);
  const answered = subject.correct + subject.incorrect + subject.skipped;

  return (
    <article className="overflow-hidden rounded-2xl border border-line bg-white">
      <button onClick={onToggle} aria-expanded={open} className="flex w-full items-center gap-4 p-4 text-left transition hover:bg-slate-50 sm:gap-5 sm:p-5">
        <Ring value={subject.accuracy} size={72} stroke={8} ringClass={ringColor[level.key]} />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <h3 className="truncate text-base font-bold text-navy-900 sm:text-lg">{subject.title}</h3>
            <span className={cn("inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset", level.badge)}>{level.label}</span>
          </div>
          <p className="mt-0.5 text-xs text-slate-500 sm:text-sm">
            {subject.chaptersStarted} of {subject.chaptersTotal} chapters practised
            {answered > 0 && <> · {answered} questions</>}
          </p>
          <OutcomeBar correct={subject.correct} incorrect={subject.incorrect} skipped={subject.skipped} className="mt-3" />
        </div>
        <ChevronDown className={cn("size-5 shrink-0 text-slate-400 transition-transform", open && "rotate-180")} />
      </button>

      {open && (
        <div className="border-t border-line bg-slate-50/60">
          <ul className="divide-y divide-line">
            {subject.chapters.map((c) => (
              <ChapterRow key={c.id} chapter={c} />
            ))}
            {subject.chapters.length === 0 && <li className="p-5 text-sm text-slate-500">No chapters in this subject yet.</li>}
          </ul>
        </div>
      )}
    </article>
  );
}

function ChapterRow({ chapter: c }: { chapter: ChapterPerformance }) {
  const level = levelOf(c.accuracy);
  const answered = c.correct + c.incorrect + c.skipped;

  return (
    <li className="px-4 py-3.5 sm:px-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
        <div className="min-w-0 sm:w-56 sm:shrink-0">
          <p className="truncate text-sm font-semibold text-navy-900">{c.title}</p>
          <p className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
            <span className={cn("inline-flex items-center rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset", level.badge)}>{level.label}</span>
            {c.attempts > 0 && (
              <span>
                {c.attempts} attempt{c.attempts > 1 ? "s" : ""}
              </span>
            )}
          </p>
        </div>

        <div className="min-w-0 flex-1">
          {answered > 0 ? (
            <>
              <div className="mb-1.5 flex items-baseline justify-between text-xs">
                <span className="text-slate-500">
                  <b className="font-semibold text-navy-900">{c.correct}</b> right · <b className="font-semibold text-navy-900">{c.incorrect}</b> wrong · {c.skipped} skipped
                </span>
                <span className={cn("text-sm font-bold", level.text)}>{c.accuracy === null ? "—" : `${c.accuracy}%`}</span>
              </div>
              <OutcomeBar correct={c.correct} incorrect={c.incorrect} skipped={c.skipped} />
              {c.lastScorePercent !== null && (
                <p className="mt-1.5 flex flex-wrap items-center gap-x-3 text-xs text-slate-500">
                  <span>
                    Last score <b className="font-semibold text-navy-900">{c.lastScorePercent}%</b>
                  </span>
                  {c.bestScorePercent !== null && (
                    <span>
                      Best <b className="font-semibold text-navy-900">{c.bestScorePercent}%</b>
                    </span>
                  )}
                  {c.lastAttemptAt && <span>{timeAgo(c.lastAttemptAt)}</span>}
                </p>
              )}
            </>
          ) : (
            <p className="text-sm text-slate-500">No questions attempted yet.</p>
          )}
        </div>

        <div className="flex shrink-0 gap-2">
          {c.lastAttemptId && (
            <Link href={`/results/${c.lastAttemptId}`} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-white px-3 text-sm font-semibold text-navy-900 hover:border-brand-200 hover:bg-brand-50">
              <History className="size-4" /> Result
            </Link>
          )}
          {c.testId && (
            <Link href={`/tests/${c.testId}`} className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-brand-600 px-3 text-sm font-semibold text-white hover:bg-brand-700">
              {c.attempts > 0 ? <RotateCcw className="size-4" /> : <PlayCircle className="size-4" />} {c.attempts > 0 ? "Practise" : "Start"}
            </Link>
          )}
        </div>
      </div>
    </li>
  );
}
