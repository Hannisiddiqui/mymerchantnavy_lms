"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { ArrowRight, BookOpenCheck, ChartNoAxesCombined, Clock, Flame, ListChecks, Target, TrendingUp } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import type { ChapterPerformance, PerformanceReport, SubjectPerformance } from "@/types";
import { levelOf } from "@/components/performance/level";
import { OutcomeBar } from "@/components/performance/OutcomeBar";
import { Ring } from "@/components/performance/Ring";
import { SubjectCard } from "@/components/performance/SubjectCard";
import { TrendChart } from "@/components/performance/TrendChart";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

type Ranked = ChapterPerformance & { subject: string };

/** Subject-wise and chapter-wise performance, built from every test the student has submitted. */
export default function PerformancePage() {
  const { data, loading, error, reload } = useApi(() => api.performance.getPerformance(), []);

  return (
    <>
      <PageHeader title="Performance" subtitle="How you are doing in each subject and chapter" />
      {loading ? <PageSkeleton /> : error || !data ? <ErrorState message={error ?? "Could not load your performance"} onRetry={reload} /> : <Dashboard report={data} />}
    </>
  );
}

function Dashboard({ report }: { report: PerformanceReport }) {
  const { overview: o, trend, subjects } = report;
  const firstStarted = subjects.find((s) => s.chaptersStarted > 0)?.id ?? subjects[0]?.id ?? null;
  const [openId, setOpenId] = useState<number | null>(firstStarted);

  // Chapters with enough to say something: weakest first, strongest first
  const { weakest, strongest } = useMemo(() => {
    const ranked: Ranked[] = subjects.flatMap((s) => s.chapters.filter((c) => c.accuracy !== null && c.correct + c.incorrect >= 5).map((c) => ({ ...c, subject: s.title })));
    return {
      weakest: [...ranked].filter((c) => (c.accuracy ?? 100) < 75).sort((a, b) => (a.accuracy ?? 0) - (b.accuracy ?? 0)).slice(0, 3),
      strongest: [...ranked].filter((c) => (c.accuracy ?? 0) >= 75).sort((a, b) => (b.accuracy ?? 0) - (a.accuracy ?? 0)).slice(0, 3),
    };
  }, [subjects]);

  // Nothing attempted yet: still show every subject and chapter, each with a Start button for its practice test.
  if (o.attempts === 0) {
    return (
      <div className="space-y-6">
        <div className="flex flex-col items-start gap-3 rounded-2xl bg-gradient-to-br from-brand-50 via-white to-brand-100/60 p-5 ring-1 ring-brand-100 sm:flex-row sm:items-center sm:p-6">
          <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-white text-brand-600 shadow-sm ring-1 ring-brand-100">
            <ChartNoAxesCombined className="size-6" />
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="text-lg font-bold text-navy-900">Your performance will show up here</h2>
            <p className="mt-1 text-sm text-slate-600">Every practice test in your chapters and every mock test you take is tracked, subject by subject and chapter by chapter. Pick a chapter below to begin.</p>
          </div>
          <Button variant="outline" href="/mock-tests">
            Mock Tests
          </Button>
        </div>
        <SubjectList subjects={subjects} openId={openId} setOpenId={setOpenId} />
      </div>
    );
  }

  const level = levelOf(o.accuracy);
  const questions = o.correct + o.incorrect + o.skipped;

  return (
    <div className="space-y-6 sm:space-y-8">
      {/* Overview */}
      <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 via-white to-brand-100/60 p-4 ring-1 ring-brand-100 sm:p-7">
        <div className="relative flex flex-col gap-5 2xl:flex-row 2xl:items-center 2xl:gap-8">
          <div className="flex items-center gap-4 sm:gap-6">
            <Ring value={o.accuracy} size={120} stroke={12} ringClass="text-brand-600">
              <div>
                <p className="font-display text-3xl font-bold leading-none text-navy-900">{o.accuracy === null ? "—" : `${o.accuracy}%`}</p>
                <p className="mt-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Accuracy</p>
              </div>
            </Ring>
            <div className="min-w-0">
              <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Overall</p>
              <p className="mt-1 font-display text-xl font-bold text-navy-900 sm:text-2xl">{level.label}</p>
              <p className="mt-1 text-sm text-slate-600">
                {questions} questions answered across {o.attempts} attempt{o.attempts > 1 ? "s" : ""}
              </p>
            </div>
          </div>

          <div className="grid flex-1 grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            <Tile icon={TrendingUp} tone="text-brand-600 bg-brand-50" label="Average score" value={o.avgScorePercent === null ? "—" : `${o.avgScorePercent}%`} sub={o.bestScorePercent === null ? undefined : `Best ${o.bestScorePercent}%`} />
            <Tile icon={ListChecks} tone="text-violet-600 bg-violet-50" label="Tests taken" value={String(o.testsTaken)} sub={`${o.attempts} attempts`} />
            <Tile icon={BookOpenCheck} tone="text-green-600 bg-green-50" label="Chapters practised" value={`${o.chaptersStarted}/${o.chaptersTotal}`} sub={o.chaptersTotal ? `${Math.round((o.chaptersStarted / o.chaptersTotal) * 100)}% covered` : undefined} />
            <Tile icon={Clock} tone="text-amber-600 bg-amber-50" label="Time spent" value={compactDuration(o.timeSpentSeconds)} sub="on tests" />
          </div>
        </div>
        <OutcomeBar correct={o.correct} incorrect={o.incorrect} skipped={o.skipped} legend className="relative mt-5" />
      </section>

      {/* Trend */}
      <section className="rounded-2xl border border-line bg-white p-4 sm:p-6">
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-1">
          <h2 className="text-base font-bold text-navy-900 sm:text-lg">Score trend</h2>
          <p className="text-xs text-slate-500">Your last {trend.length} attempt{trend.length > 1 ? "s" : ""}, as % of the test&apos;s marks</p>
        </div>
        <TrendChart points={trend} />
      </section>

      {/* Focus / strengths */}
      {(weakest.length > 0 || strongest.length > 0) && (
        <section className="grid gap-4 md:grid-cols-2">
          <Highlights title="Focus on these" icon={Target} tone="text-red-600 bg-red-50" empty="No weak chapters. Great work!" items={weakest} />
          <Highlights title="Your strengths" icon={Flame} tone="text-green-600 bg-green-50" empty="Keep practising: strengths show up at 75% accuracy." items={strongest} />
        </section>
      )}

      <SubjectList subjects={subjects} openId={openId} setOpenId={setOpenId} />
    </div>
  );
}

/** Every subject with its chapters. Each chapter's own practice test and any mock test questions count towards it. */
function SubjectList({ subjects, openId, setOpenId }: { subjects: SubjectPerformance[]; openId: number | null; setOpenId: (id: number | null) => void }) {
  return (
    <section>
      <div className="mb-3 flex items-end justify-between gap-3">
        <h2 className="text-lg font-bold text-navy-900">Subject-wise performance</h2>
        <button className="text-sm font-semibold text-brand-600 hover:underline" onClick={() => setOpenId(openId === null ? (subjects[0]?.id ?? null) : null)}>
          {openId === null ? "Open first" : "Collapse all"}
        </button>
      </div>
      <div className="space-y-3">
        {subjects.map((s) => (
          <SubjectCard key={s.id} subject={s} open={openId === s.id} onToggle={() => setOpenId(openId === s.id ? null : s.id)} />
        ))}
      </div>
      <p className="mt-3 text-xs text-slate-400">
        Includes the practice test in each chapter and your mock tests. Accuracy = right answers ÷ questions you answered, over all your attempts. Skipped questions are not counted against you.
      </p>
    </section>
  );
}

/** 41200 → "11h 27m" (fits a small tile) */
function compactDuration(totalSeconds: number) {
  const m = Math.round(totalSeconds / 60);
  if (m < 60) return `${m}m`;
  return m % 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${Math.floor(m / 60)}h`;
}

function Tile({ icon: Icon, tone, label, value, sub }: { icon: React.ElementType; tone: string; label: string; value: string; sub?: string }) {
  return (
    <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-line sm:p-4">
      <span className={`grid size-8 place-items-center rounded-lg ${tone}`}>
        <Icon className="size-4.5" />
      </span>
      <p className="mt-2.5 text-xs font-semibold text-slate-500">{label}</p>
      <p className="font-display text-xl font-bold text-navy-900 sm:text-2xl">{value}</p>
      {sub && <p className="text-[11px] text-slate-500 sm:text-xs">{sub}</p>}
    </div>
  );
}

function Highlights({ title, icon: Icon, tone, empty, items }: { title: string; icon: React.ElementType; tone: string; empty: string; items: Ranked[] }) {
  return (
    <div className="rounded-2xl border border-line bg-white p-4 sm:p-5">
      <h2 className="flex items-center gap-2 text-base font-bold text-navy-900">
        <span className={`grid size-8 place-items-center rounded-lg ${tone}`}>
          <Icon className="size-4.5" />
        </span>
        {title}
      </h2>
      {items.length === 0 ? (
        <p className="mt-3 text-sm text-slate-500">{empty}</p>
      ) : (
        <ul className="mt-3 divide-y divide-line">
          {items.map((c) => {
            const level = levelOf(c.accuracy);
            return (
              <li key={c.id}>
                <Link href={c.testId ? `/tests/${c.testId}` : "/my-courses"} className="group flex items-center gap-3 py-2.5">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-navy-900">{c.title}</p>
                    <p className="text-xs text-slate-500">{c.subject}</p>
                  </div>
                  <span className={`text-sm font-bold ${level.text}`}>{c.accuracy}%</span>
                  <ArrowRight className="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
