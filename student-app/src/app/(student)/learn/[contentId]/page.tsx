"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { ArrowLeft, ArrowRight, ChevronDown, ChevronRight, CircleCheck, Clock, FileQuestion, Trophy } from "lucide-react";
import * as api from "@/lib/api";
import type { ContentItem } from "@/types";
import { useApi } from "@/lib/use-api";
import { ContentRow } from "@/components/course/ContentRow";
import { contentTypes } from "@/components/course/contentTypes";
import { AssignmentView } from "@/components/content/AssignmentView";
import { LiveClassView } from "@/components/content/LiveClassView";
import { ProtectedPdfViewer } from "@/components/content/ProtectedPdfViewer";
import { VideoPlayer } from "@/components/content/VideoPlayer";
import { MathText } from "@/components/math/MathText";
import { NotesPanel } from "@/components/notes/NotesPanel";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

/** Content player: the item on the left, the subject's chapter list on the right (Udemy style). */
export default function LearnPage() {
  const { contentId } = useParams<{ contentId: string }>();
  const id = Number(contentId);
  const { data, loading, error, reload, setData } = useApi(() => api.courses.getContent(id), [id]);

  // PDF reading state shared with the notes: the page being read, a way to scroll the viewer, and where to open
  // (a saved note links here as /learn/12?page=7).
  const [pdfPage, setPdfPage] = useState(1);
  const [noteCount, setNoteCount] = useState(0);
  const [startPage, setStartPage] = useState<number | null>(null);
  const pdfGoTo = useRef<((page: number) => void) | null>(null);
  useEffect(() => {
    const p = Number(new URLSearchParams(window.location.search).get("page"));
    setStartPage(p > 0 ? p : null);
    setPdfPage(1);
    setNoteCount(0);
  }, [id]);

  // Chapter accordion expansion state: auto-expand current chapter by default
  const [openChapters, setOpenChapters] = useState<Record<number, boolean>>({});

  useEffect(() => {
    if (data?.content?.chapterId) {
      setOpenChapters((prev) => ({
        ...prev,
        [data.content.chapterId]: true,
      }));

      if (typeof document !== "undefined") {
        setTimeout(() => {
          document.getElementById(`nav-chapter-${data.content.chapterId}`)?.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }, 250);
      }
    }
  }, [data?.content?.chapterId]);

  function toggleChapter(chId: number) {
    setOpenChapters((prev) => {
      const willOpen = !prev[chId];
      if (willOpen && typeof document !== "undefined") {
        setTimeout(() => {
          document.getElementById(`nav-chapter-${chId}`)?.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }, 60);
      }
      return {
        ...prev,
        [chId]: willOpen,
      };
    });
  }

  if (loading) return <PageSkeleton />;
  if (error || !data) return <ErrorState message={error ?? "Content not found"} onRetry={reload} />;

  const { content, subject } = data;
  const all = subject.chapters.flatMap((ch) => ch.contents);
  const index = all.findIndex((c) => c.id === content.id);
  const prev = all[index - 1];
  const next = all[index + 1];
  const chapter = subject.chapters.find((ch) => ch.id === content.chapterId)!;
  let chapterNo = 0;

  // A document (PDF or written note) is marked as read ONLY when the student presses "Mark as read", even if they
  // scrolled to the very end. Watching a video or a recorded class still counts when they move on to the next item.
  // A test or an assignment counts only when it is actually taken or handed in (the server records that).
  const isDocument = content.type === "pdf" || content.type === "note";
  const autoCompletes = !content.completed && ["video", "live_class"].includes(content.type);
  const onDone = autoCompletes ? () => void markComplete() : undefined;
  // Notes can be written on anything the student reads or watches (not on tests or assignments)
  const canTakeNotes = ["pdf", "note", "video", "live_class"].includes(content.type);

  function goToPage(page: number) {
    document.getElementById("reader")?.scrollIntoView({ behavior: "smooth", block: "start" });
    pdfGoTo.current?.(page);
  }
  function takeNote() {
    document.getElementById("my-notes")?.scrollIntoView({ behavior: "smooth", block: "start" });
    window.setTimeout(() => document.getElementById("note-composer")?.focus({ preventScroll: true }), 350);
  }

  async function markComplete() {
    await api.courses.markContentComplete(content.id);
    setData((d) =>
      d && {
        content: { ...d.content, completed: true },
        subject: {
          ...d.subject,
          chapters: d.subject.chapters.map((ch) => ({
            ...ch,
            contents: ch.contents.map((c) => (c.id === content.id ? { ...c, completed: true } : c)),
          })),
        },
      },
    );
  }

  return (
    <>
      <PageHeader
        title={content.title}
        crumbs={[
          { label: subject.courseTitle, href: `/my-courses/${subject.courseId}` },
          { label: subject.title, href: `/my-courses/${subject.courseId}/subjects/${subject.id}` },
          { label: chapter.title },
        ]}
        action={<Badge tone="blue">{contentTypes[content.type].label}</Badge>}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-4">
          <div id="reader" className="scroll-mt-20">
            <ContentBody
              item={content}
              pdf={{ startPage, onPageChange: setPdfPage, goToRef: pdfGoTo, noteCount, onTakeNote: takeNote }}
            />
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {content.completed ? (
              <span className="flex items-center gap-1.5 text-sm font-semibold text-green-700">
                <CircleCheck className="size-5" /> {isDocument ? "Marked as read" : "Completed"}
              </span>
            ) : content.type !== "practice_test" ? (
              <Button variant={isDocument ? "primary" : "outline"} onClick={markComplete}>
                <CircleCheck className="size-4" /> {isDocument ? "Mark as read" : "Mark as complete"}
              </Button>
            ) : null}
            <div className="ml-auto flex gap-2">
              {prev && (
                <Button variant="ghost" href={`/learn/${prev.id}`}>
                  <ArrowLeft className="size-4" /> Previous
                </Button>
              )}
              {next && (
                <Button href={`/learn/${next.id}`} onClick={onDone}>
                  Next <ArrowRight className="size-4" />
                </Button>
              )}
            </div>
          </div>

          {canTakeNotes && (
            <NotesPanel
              key={content.id}
              contentId={content.id}
              isPdf={content.type === "pdf"}
              currentPage={content.type === "pdf" ? pdfPage : undefined}
              totalPages={content.type === "pdf" ? content.pageCount : undefined}
              onGoToPage={goToPage}
              onCount={setNoteCount}
            />
          )}
        </div>

        <aside className="lg:sticky lg:top-20 lg:self-start">
          <Card className="overflow-hidden shadow-sm border border-line">
            <div className="border-b border-line bg-slate-50/50 px-4 py-3 dark:bg-slate-900/50">
              <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{subject.title}</p>
              <div className="flex items-center justify-between mt-0.5">
                <p className="font-bold text-navy-900 dark:text-white">Chapter Navigation</p>
                <span className="text-xs font-medium text-slate-500">
                  {all.filter((c) => c.completed).length}/{all.length} completed
                </span>
              </div>
            </div>
            <div className="max-h-[70vh] overflow-y-auto p-2 space-y-1.5 divide-y divide-slate-100 dark:divide-slate-800">
              {subject.chapters.map((ch) => {
                if (ch.test) {
                  return (
                    <Link
                      key={ch.id}
                      href={`/tests/${ch.test.id}`}
                      className="flex items-center gap-2 rounded-lg bg-navy-900/5 px-3 py-2.5 text-sm font-bold text-navy-900 hover:bg-navy-900/10 dark:bg-navy-900/20 dark:text-white"
                    >
                      <Trophy className="size-4 text-brand-600 shrink-0" />
                      <span className="truncate">{ch.test.title}</span>
                      <span className="ml-auto text-xs font-medium text-slate-500 shrink-0">Mock test</span>
                    </Link>
                  );
                }

                const isCurrentChapter = ch.id === content.chapterId;
                const isOpen = openChapters[ch.id] ?? isCurrentChapter;
                const completedInChapter = ch.contents.filter((c) => c.completed).length;
                const num = ++chapterNo;

                return (
                  <div
                    key={ch.id}
                    id={`nav-chapter-${ch.id}`}
                    className={`pt-1.5 rounded-lg transition-colors scroll-mt-6 ${isCurrentChapter ? "bg-brand-50/30 dark:bg-brand-950/10" : ""}`}
                  >
                    <button
                      type="button"
                      onClick={() => toggleChapter(ch.id)}
                      className="flex w-full items-center justify-between px-2.5 py-1.5 text-left text-xs font-bold text-slate-700 hover:text-navy-900 dark:text-slate-300 dark:hover:text-white"
                    >
                      <span className="flex items-center gap-1.5 truncate">
                        <span className={`inline-flex items-center justify-center size-5 rounded-full text-[10px] font-semibold shrink-0 ${isCurrentChapter ? "bg-brand-600 text-white" : "bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200"}`}>
                          {num}
                        </span>
                        <span className="truncate">{ch.title}</span>
                      </span>
                      <span className="flex items-center gap-1.5 shrink-0 ml-2">
                        <span className="text-[11px] font-normal text-slate-500">
                          {completedInChapter}/{ch.contents.length}
                        </span>
                        {isOpen ? (
                          <ChevronDown className="size-3.5 text-slate-400" />
                        ) : (
                          <ChevronRight className="size-3.5 text-slate-400" />
                        )}
                      </span>
                    </button>

                    {isOpen && (
                      <div className="mt-1 space-y-0.5 pb-1 pl-1 pr-0.5">
                        {ch.contents.map((c) => (
                          <ContentRow
                            key={c.id}
                            item={c}
                            active={c.id === content.id}
                            compact
                          />
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </Card>
        </aside>
      </div>
    </>
  );
}

/** Picks the viewer for each content type. Add a case here for a new type. */
function ContentBody({
  item,
  pdf,
}: {
  item: ContentItem;
  pdf: { startPage: number | null; onPageChange: (page: number) => void; goToRef: React.MutableRefObject<((page: number) => void) | null>; noteCount: number; onTakeNote: () => void };
}) {
  switch (item.type) {
    case "video":
      return <VideoPlayer title={item.title} embedUrl={item.embedUrl} durationSeconds={item.durationSeconds} />;
    case "live_class":
      return <LiveClassView item={item} />;
    case "pdf":
      return (
        <ProtectedPdfViewer
          key={item.id}
          contentId={item.id}
          pageCount={item.pageCount}
          startPage={pdf.startPage}
          onPageChange={pdf.onPageChange}
          goToRef={pdf.goToRef}
          noteCount={pdf.noteCount}
          onTakeNote={pdf.onTakeNote}
        />
      );
    case "note":
      return (
        <Card className="p-6 sm:p-8">
          <MathText html={item.body} className="text-[15px] leading-relaxed text-slate-700" />
        </Card>
      );
    case "assignment":
      return <AssignmentView item={item} />;
    case "practice_test":
      return (
        <Card className="flex flex-col items-center p-8 text-center sm:p-12">
          <span className="grid size-16 place-items-center rounded-2xl bg-green-50 text-green-600">
            <FileQuestion className="size-8" />
          </span>
          <h2 className="mt-4 text-xl font-bold text-navy-900">{item.test.title}</h2>
          <p className="mt-2 flex flex-wrap justify-center gap-x-4 text-sm text-slate-500">
            <span>{item.test.questionsCount} questions</span>
            <span>{item.test.totalMarks} marks</span>
            <span className="flex items-center gap-1">
              <Clock className="size-4" /> {item.test.durationMinutes} min
            </span>
            <span>
              +{item.test.marksCorrect} / −{item.test.marksWrong}
            </span>
          </p>
          <div className="mt-6 flex gap-2">
            {item.test.lastAttemptId && (
              <Button variant="outline" href={`/results/${item.test.lastAttemptId}`}>
                Last result
              </Button>
            )}
            <Button href={`/tests/${item.test.id}`}>{item.test.attemptsCount ? "Reattempt" : "Start test"}</Button>
          </div>
          {item.test.attemptsCount > 0 && <p className="mt-3 text-xs text-slate-500">Attempted {item.test.attemptsCount} time(s) · Best {item.test.bestScore}/{item.test.totalMarks}</p>}
        </Card>
      );
  }
}
