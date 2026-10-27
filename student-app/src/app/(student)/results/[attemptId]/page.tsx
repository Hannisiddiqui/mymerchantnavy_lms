"use client";

import { useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { CalendarDays, CircleCheck, CircleX, Clock, FileQuestion, Medal, Percent, RotateCcw, SkipForward, Target, Timer, Trophy, Users } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { cn, formatClock, formatDate } from "@/lib/utils";
import { outcomeOf, SolutionItem, type SolutionOutcome } from "@/components/result/SolutionItem";
import { StatTile } from "@/components/result/StatTile";
import { Button } from "@/components/ui/Button";
import { Select } from "@/components/ui/Field";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

type Filter = "all" | SolutionOutcome;

/** Result summary on top, detailed solutions below. */
export default function ResultPage() {
  const { attemptId } = useParams<{ attemptId: string }>();
  const router = useRouter();
  const { data: r, loading, error, reload } = useApi(() => api.results.getResult(attemptId), [attemptId]);
  const [filter, setFilter] = useState<Filter>("all");

  if (loading) return <PageSkeleton />;
  if (error || !r) return <ErrorState message={error ?? "Result not found"} onRetry={reload} />;

  const total = r.test.questionsCount;
  const attempted = r.correct + r.incorrect;
  const filtered = r.solutions.map((q, i) => ({ q, no: i + 1 })).filter(({ q }) => filter === "all" || outcomeOf(q) === filter);
  const filters: { key: Filter; label: string; count: number }[] = [
    { key: "all", label: "All", count: total },
    { key: "correct", label: "Correct", count: r.correct },
    { key: "incorrect", label: "Incorrect", count: r.incorrect },
    { key: "skipped", label: "Skipped", count: r.skipped },
  ];

  return (
    <>
      <PageHeader title="Result" crumbs={[{ label: "Results", href: "/results" }, { label: r.testTitle }]} />

      {/* Test header */}
      <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 via-white to-brand-100/60 p-5 ring-1 ring-brand-100 sm:p-7">
        <Trophy aria-hidden className="absolute -bottom-6 right-6 size-40 text-brand-200/40" />
        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 className="text-xl font-extrabold text-navy-900 sm:text-2xl">{r.testTitle}</h2>
            <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
              <span className="flex items-center gap-1.5">
                <FileQuestion className="size-4" /> {total} Questions · {r.totalMarks} Marks
              </span>
              <span className="flex items-center gap-1.5">
                <Clock className="size-4" /> {r.test.durationMinutes} mins
              </span>
              <span className="flex items-center gap-1.5">
                <CalendarDays className="size-4" /> Attempted on {formatDate(r.submittedAt)}
              </span>
            </p>
            <div className="mt-5 flex flex-wrap gap-2">
              <Button variant="outline" href={`/tests/${r.testId}`}>
                <RotateCcw className="size-4" /> Reattempt
              </Button>
              <Button onClick={() => document.getElementById("solutions")?.scrollIntoView({ behavior: "smooth" })}>View Solutions</Button>
            </div>
          </div>
          {r.attempts.length > 1 && (
            <div className="w-full sm:w-44">
              <Select value={r.id} onChange={(e) => router.push(`/results/${e.target.value}`)} aria-label="Choose attempt">
                {r.attempts.map((a) => (
                  <option key={a.id} value={a.id}>
                    Attempt {a.attemptNo}
                  </option>
                ))}
              </Select>
            </div>
          )}
        </div>
      </section>

      {/* Score, rank, percentile */}
      <h2 className="mb-3 mt-8 text-lg font-bold text-navy-900">Result Summary</h2>
      <section className="grid grid-cols-3 gap-2.5 md:gap-4">
        <div className="rounded-2xl border border-line bg-white p-3 sm:p-5">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Score</p>
          <p className="mt-1 font-display text-2xl font-bold text-navy-900 sm:text-4xl">
            {r.score}
            <span className="text-base text-slate-400 sm:text-xl">/{r.totalMarks}</span>
          </p>
        </div>
        <div className="flex items-center gap-4 rounded-2xl border border-line bg-white p-3 sm:p-5">
          <span className="hidden size-12 place-items-center rounded-xl bg-amber-50 text-amber-600 sm:grid">
            <Medal className="size-6" />
          </span>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Rank</p>
            <p className="font-display text-xl font-bold text-navy-900 sm:text-2xl">
              #{r.rank} <span className="text-xs font-semibold text-slate-400 sm:text-sm">of {r.totalCandidates}</span>
            </p>
          </div>
        </div>
        <div className="flex items-center gap-4 rounded-2xl border border-line bg-white p-3 sm:p-5">
          <span className="hidden size-12 place-items-center rounded-xl bg-violet-50 text-violet-600 sm:grid">
            <Users className="size-6" />
          </span>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Percentile</p>
            <p className="font-display text-xl font-bold text-navy-900 sm:text-2xl">{r.percentile.toFixed(2)}</p>
          </div>
        </div>
      </section>
      <p className="mt-2 text-xs text-slate-400">Rank and percentile are based on every student&apos;s first attempt of this test.</p>

      {/* Progress tiles */}
      <section className="mt-6 rounded-2xl bg-brand-50/50 p-4 ring-1 ring-brand-100 sm:p-6">
        <h3 className="mb-4 text-center font-bold text-navy-900">Your Progress</h3>
        <div className="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-3">
          <StatTile icon={CircleCheck} iconClass="text-green-600" label="Correct" value={`${r.correct}/${total}`} percent={(r.correct / total) * 100} barClass="bg-green-500" subLabel="Marks Obtained" subValue={r.marksObtained} subValueClass="text-green-700" />
          <StatTile icon={CircleX} iconClass="text-red-600" label="Incorrect" value={`${r.incorrect}/${total}`} percent={(r.incorrect / total) * 100} barClass="bg-red-500" subLabel="Marks Lost" subValue={r.marksLost} subValueClass="text-red-700" />
          <StatTile icon={SkipForward} iconClass="text-slate-500" label="Skipped" value={`${r.skipped}/${total}`} percent={(r.skipped / total) * 100} barClass="bg-sky-400" subLabel="Marks Skipped" subValue={r.skipped * r.test.marksCorrect} />
          <StatTile icon={Target} iconClass="text-brand-600" label="Accuracy" value={`${r.accuracy}%`} percent={r.accuracy} barClass="bg-brand-600" />
          <StatTile icon={Percent} iconClass="text-violet-600" label="Completed" value={`${Math.round((attempted / total) * 100)}%`} percent={(attempted / total) * 100} barClass="bg-violet-500" />
          <StatTile icon={Timer} iconClass="text-amber-600" label="Time Taken" value={formatClock(r.timeTakenSeconds)} percent={(r.timeTakenSeconds / (r.test.durationMinutes * 60)) * 100} barClass="bg-amber-500" />
        </div>
      </section>

      {/* Detailed solutions */}
      <section id="solutions" className="scroll-mt-20 pt-10">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-lg font-bold text-navy-900">Detailed Solutions</h2>
          <div className="flex gap-1 rounded-xl bg-white p-1 ring-1 ring-line">
            {filters.map((f) => (
              <button
                key={f.key}
                onClick={() => setFilter(f.key)}
                className={cn("rounded-lg px-3 py-1.5 text-sm font-semibold transition", filter === f.key ? "bg-navy-900 text-white" : "text-slate-600 hover:bg-slate-100")}
              >
                {f.label} <span className="opacity-60">{f.count}</span>
              </button>
            ))}
          </div>
        </div>
        <div className="space-y-4">
          {filtered.map(({ q, no }) => (
            <SolutionItem key={`${r.id}-${q.id}`} q={q} number={no} marksCorrect={r.test.marksCorrect} marksWrong={r.test.marksWrong} attemptId={r.id} />
          ))}
          {filtered.length === 0 && <p className="rounded-2xl border border-dashed border-line bg-white p-8 text-center text-sm text-slate-500">No questions in this group.</p>}
        </div>
      </section>
    </>
  );
}
