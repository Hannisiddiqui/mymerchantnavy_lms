"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import { Circle, CircleCheck, CirclePlay, ChevronDown, Compass, FileText, Target } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { cn } from "@/lib/utils";
import { contentMeta, contentTypes } from "@/components/course/contentTypes";
import { TestCard } from "@/components/test/TestCard";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

/** Subject page: chapters as an accordion, each with its list of content and smooth chapter navigation. */
export default function SubjectPage() {
  const { subjectId } = useParams<{ courseId: string; subjectId: string }>();
  const { data: subject, loading, error, reload } = useApi(() => api.courses.getSubject(Number(subjectId)), [subjectId]);
  const [closed, setClosed] = useState<Set<number>>(new Set());

  const toggle = (id: number) =>
    setClosed((s) => {
      const next = new Set(s);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const scrollToChapter = (chId: number, chNum: number) => {
    // Open chapter if closed
    setClosed((prev) => {
      const next = new Set(prev);
      next.delete(chId);
      return next;
    });

    if (typeof window !== "undefined") {
      window.history.replaceState(null, "", `#chapter-${chNum}`);
      setTimeout(() => {
        const el = document.getElementById(`chapter-${chId}`) || document.getElementById(`chapter-${chNum}`);
        if (el) {
          el.scrollIntoView({ behavior: "smooth", block: "start" });
          el.classList.add("ring-2", "ring-sky-500", "ring-offset-2", "ring-offset-slate-900");
          setTimeout(() => el.classList.remove("ring-2", "ring-sky-500", "ring-offset-2", "ring-offset-slate-900"), 2200);
        }
      }, 60);
    }
  };

  useEffect(() => {
    if (!subject?.chapters || typeof window === "undefined") return;
    const hash = window.location.hash;
    if (hash && hash.startsWith("#chapter-")) {
      const target = hash.replace("#chapter-", "");
      const num = parseInt(target, 10);
      let realChapterNo = 0;
      for (const ch of subject.chapters) {
        if (!ch.test) realChapterNo++;
        if (realChapterNo === num || ch.id === num) {
          scrollToChapter(ch.id, realChapterNo);
          break;
        }
      }
    }
  }, [subject]);

  if (loading) return <PageSkeleton />;
  if (error || !subject) return <ErrorState message={error ?? "Subject not found"} onRetry={reload} />;

  const allContents = subject.chapters.flatMap((c) => c.contents);
  const totalCompleted = allContents.filter((c) => c.completed).length;

  let totalMinutes = 0;
  for (const c of allContents) {
    if (c.type === "video") totalMinutes += Math.round((c.durationSeconds || 0) / 60);
    else if (c.type === "live_class") totalMinutes += c.durationMinutes || 60;
    else if (c.type === "practice_test") totalMinutes += c.test?.durationMinutes || 30;
    else if (c.type === "note") totalMinutes += c.readMinutes || 5;
    else if (c.type === "pdf") totalMinutes += Math.round((c.pageCount || 10) * 1.5);
  }
  const durationHours = Math.floor(totalMinutes / 60);
  const durationMins = totalMinutes % 60;
  const durationStr = durationHours > 0 ? `${durationHours}h ${durationMins}m` : `${durationMins}m`;
  const subjectSubtitle = `Total estimated duration: ${durationStr} • ${totalCompleted} / ${allContents.length} contents completed`;

  let chapterNo = 0;
  let pillNo = 0;

  return (
    <>
      <PageHeader
        title={subject.title.toUpperCase()}
        subtitle={subjectSubtitle}
        crumbs={[
          { label: "My Courses", href: "/my-courses" },
          { label: subject.courseTitle, href: `/my-courses/${subject.courseId}` },
          { label: subject.title },
        ]}
      />

      {/* Quick Chapter Navigation Pills */}
      <div className="mb-5 flex items-center gap-1.5 overflow-x-auto rounded-xl border border-line bg-white p-2.5 shadow-xs transition-colors scrollbar-thin dark:border-slate-800 dark:bg-[#0d1627]">
        <span className="flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-slate-500 shrink-0 mr-1.5 dark:text-slate-400">
          <Compass className="size-3.5 text-brand-600 dark:text-sky-400" /> Jump to:
        </span>
        {subject.chapters.map((ch) => {
          if (ch.test) return null;
          pillNo += 1;
          const currentPill = pillNo;
          return (
            <button
              key={`pill-${ch.id}`}
              type="button"
              onClick={() => scrollToChapter(ch.id, currentPill)}
              className="shrink-0 rounded-lg bg-slate-100 hover:bg-brand-600 hover:text-white px-3 py-1.5 text-xs font-bold text-slate-700 transition border border-slate-200 dark:bg-[#182338] dark:hover:bg-sky-600 dark:hover:text-white dark:text-slate-300 dark:border-slate-700/60"
            >
              Chapter {currentPill}
            </button>
          );
        })}
      </div>

      <div className="space-y-3.5">
        {subject.chapters.map((chapter) => {
          // A mock test placed between chapters: shown as a test card in its position.
          if (chapter.test) return <TestCard key={chapter.id} test={chapter.test} />;
          chapterNo += 1;
          const currentNum = chapterNo;
          const done = chapter.contents.filter((c) => c.completed).length;
          const isOpen = !closed.has(chapter.id);
          return (
            <section
              key={chapter.id}
              id={`chapter-${chapter.id}`}
              className="overflow-hidden rounded-2xl border border-line bg-white transition-all duration-300 shadow-xs scroll-mt-20 dark:border-slate-800/90 dark:bg-[#0d1629]"
            >
              <button
                onClick={() => toggle(chapter.id)}
                className="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition"
                aria-expanded={isOpen}
              >
                <span className="text-xs font-bold uppercase tracking-wider text-brand-600 dark:text-sky-400 shrink-0">Chapter {currentNum}</span>
                <span className="flex-1 font-bold text-navy-900 dark:text-white text-base truncate">{chapter.title}</span>
                <span className="hidden text-xs font-medium text-slate-500 dark:text-slate-400 sm:block shrink-0">
                  {done}/{chapter.contents.length} completed
                </span>
                <ChevronDown className={cn("size-5 text-slate-400 transition shrink-0", isOpen && "rotate-180")} />
              </button>
              {isOpen && (
                <div className="divide-y divide-line border-t border-line dark:divide-slate-800/70 dark:border-slate-800/80">
                  {chapter.contents.map((item) => (
                    <Link
                      key={item.id}
                      href={`/learn/${item.id}`}
                      className="flex items-center gap-3.5 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition group"
                    >
                      <span
                        className={cn(
                          "grid size-9 shrink-0 place-items-center rounded-lg border transition-colors",
                          item.type === "pdf" || item.type === "note"
                            ? "bg-amber-50 text-amber-600 border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/40"
                            : item.type === "practice_test"
                            ? "bg-emerald-50 text-emerald-600 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/40"
                            : "bg-blue-50 text-blue-600 border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800/40"
                        )}
                      >
                        {item.type === "pdf" || item.type === "note" ? (
                          <FileText className="size-4" />
                        ) : item.type === "practice_test" ? (
                          <Target className="size-4" />
                        ) : (
                          <CirclePlay className="size-4" />
                        )}
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block font-semibold text-sm text-slate-800 group-hover:text-brand-600 dark:text-slate-100 dark:group-hover:text-sky-300 transition truncate">
                          {item.title}
                        </span>
                        <span className="mt-0.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                          <span>{contentTypes[item.type]?.label || item.type}</span>
                          <span className="text-slate-300 dark:text-slate-600">•</span>
                          <span>{contentMeta(item)}</span>
                        </span>
                      </span>
                      {item.completed ? (
                        <CircleCheck className="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-label="Completed" />
                      ) : (
                        <Circle className="size-5 shrink-0 text-slate-300 dark:text-slate-600" aria-label="Not completed" />
                      )}
                    </Link>
                  ))}
                </div>
              )}
            </section>
          );
        })}
      </div>
    </>
  );
}

