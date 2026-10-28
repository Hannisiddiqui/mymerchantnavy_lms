"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { ChevronDown, Compass } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { cn } from "@/lib/utils";
import { ContentRow } from "@/components/course/ContentRow";
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
          el.classList.add("ring-2", "ring-brand-500", "ring-offset-2");
          setTimeout(() => el.classList.remove("ring-2", "ring-brand-500", "ring-offset-2"), 2200);
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
      let matchedIdx = 0;
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

  let chapterNo = 0;
  let pillNo = 0;

  return (
    <>
      <PageHeader
        title={subject.title}
        subtitle={subject.description}
        crumbs={[
          { label: "My Courses", href: "/my-courses" },
          { label: subject.courseTitle, href: `/my-courses/${subject.courseId}` },
          { label: subject.title },
        ]}
      />

      {/* Quick Chapter Navigation Pills */}
      <div className="mb-4 flex items-center gap-1.5 overflow-x-auto rounded-xl border border-line bg-white p-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <span className="flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-slate-500 shrink-0 mr-1.5">
          <Compass className="size-3.5 text-brand-600" /> Jump to:
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
              className="shrink-0 rounded-lg bg-slate-100 hover:bg-brand-600 hover:text-white px-2.5 py-1 text-xs font-bold text-slate-700 transition dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-brand-600 dark:hover:text-white"
            >
              Ch {currentPill}
            </button>
          );
        })}
      </div>

      <div className="space-y-4">
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
              className="overflow-hidden rounded-2xl border border-line bg-white transition-all duration-300 dark:border-slate-800 dark:bg-slate-900 scroll-mt-20"
            >
              <button
                onClick={() => toggle(chapter.id)}
                className="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                aria-expanded={isOpen}
              >
                <span className="text-xs font-bold uppercase tracking-wider text-brand-600">Chapter {currentNum}</span>
                <span className="flex-1 font-bold text-navy-900 dark:text-white">{chapter.title}</span>
                <span className="hidden text-xs font-medium text-slate-500 sm:block">
                  {done}/{chapter.contents.length} completed
                </span>
                <ChevronDown className={cn("size-5 text-slate-400 transition", isOpen && "rotate-180")} />
              </button>
              {isOpen && (
                <div className="divide-y divide-line border-t border-line dark:divide-slate-800 dark:border-slate-800">
                  {chapter.contents.map((item) => (
                    <ContentRow key={item.id} item={item} />
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

