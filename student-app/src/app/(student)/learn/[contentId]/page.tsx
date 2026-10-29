"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { ArrowLeft, ArrowRight, ChevronDown, ChevronRight, Circle, CircleCheck, CirclePlay, Clock, FileQuestion, FileText, Target, Trophy } from "lucide-react";
import * as api from "@/lib/api";
import type { ContentItem } from "@/types";
import { useApi } from "@/lib/use-api";
import { ContentRow } from "@/components/course/ContentRow";
import { contentMeta, contentTypes } from "@/components/course/contentTypes";
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

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div className="min-w-0 space-y-4">
          <div id="reader" className="scroll-mt-20">
            <ContentBody
              item={content}
              pdf={{ startPage, onPageChange: setPdfPage, goToRef: pdfGoTo, noteCount, onTakeNote: takeNote }}
            />
          </div>

          <div className="flex flex-wrap items-center gap-2 pt-1">
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
                <Button href={`/learn/${next.id}`} onClick={onDone} className="bg-blue-600 hover:bg-blue-700 text-white">
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
          <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {/* Slate Header Banner matching Screenshot 2 */}
            <div className="bg-[#64748b] px-4 py-3 text-white">
              <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-200">{subject.title}</p>
              <div className="flex items-center justify-between mt-0.5">
                <p className="font-bold text-sm text-white">Chapter Navigation</p>
                <span className="text-xs text-slate-200 font-medium">
                  {all.filter((c) => c.completed).length}/{all.length} completed
                </span>
              </div>
            </div>

            {/* Chapter Accordion List */}
            <div className="max-h-[72vh] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80">
              {subject.chapters.map((ch) => {
                if (ch.test) {
                  return (
                    <Link
                      key={ch.id}
                      href={`/tests/${ch.test.id}`}
                      className="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60"
                    >
                      <Trophy className="size-4 text-brand-600 shrink-0" />
                      <span className="truncate">{ch.test.title}</span>
                      <span className="ml-auto text-[11px] font-medium text-slate-400 shrink-0">Mock test</span>
                    </Link>
                  );
                }

                const isCurrentChapter = ch.id === content.chapterId;
                const isOpen = openChapters[ch.id] ?? isCurrentChapter;
                const completedInChapter = ch.contents.filter((c) => c.completed).length;
                const num = ++chapterNo;

                return (
                  <div key={ch.id} id={`nav-chapter-${ch.id}`} className="transition-colors">
                    <button
                      type="button"
                      onClick={() => toggleChapter(ch.id)}
                      className={`flex w-full items-center justify-between px-3.5 py-2.5 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/50 ${
                        isOpen ? "bg-slate-50/50 dark:bg-slate-800/20" : ""
                      }`}
                    >
                      <span className="flex items-center gap-2.5 min-w-0 pr-2">
                        <span
                          className={`inline-flex items-center justify-center size-5 rounded-full text-[10px] font-bold shrink-0 ${
                            isOpen
                              ? "bg-blue-600 text-white"
                              : "bg-slate-600 text-white dark:bg-slate-700 dark:text-slate-200"
                          }`}
                        >
                          {num}
                        </span>
                        <span
                          className={`text-xs truncate ${
                            isOpen
                              ? "font-bold text-blue-600 dark:text-blue-400"
                              : "font-semibold text-slate-600 dark:text-slate-300"
                          }`}
                        >
                          {ch.title}
                        </span>
                      </span>
                      <span className="flex items-center gap-1.5 shrink-0 text-slate-400 text-xs">
                        <span className={`text-[11px] ${isOpen ? "text-blue-600 dark:text-blue-400 font-semibold" : "text-slate-500"}`}>
                          {completedInChapter}/{ch.contents.length}
                        </span>
                        {isOpen ? (
                          <ChevronDown className={`size-3.5 ${isOpen ? "text-blue-600 dark:text-blue-400" : "text-slate-400"}`} />
                        ) : (
                          <ChevronRight className="size-3.5 text-slate-400" />
                        )}
                      </span>
                    </button>

                    {isOpen && (
                      <div className="bg-slate-50/60 dark:bg-slate-900/40 p-1.5 space-y-1">
                        {ch.contents.map((c) => {
                          const isActive = c.id === content.id;
                          return (
                            <Link
                              key={c.id}
                              href={`/learn/${c.id}`}
                              className={`flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition text-left group ${
                                isActive
                                  ? "bg-blue-50/90 text-blue-900 border border-blue-200/60 dark:bg-blue-950/40 dark:border-blue-900/60 dark:text-blue-200 font-medium"
                                  : "hover:bg-white text-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                              }`}
                            >
                              {/* Icon */}
                              <span
                                className={`grid size-7 place-items-center rounded-lg shrink-0 ${
                                  c.type === "pdf" || c.type === "note"
                                    ? "bg-orange-50 text-orange-500 dark:bg-orange-950/40 dark:text-orange-400"
                                    : c.type === "practice_test"
                                    ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    : "bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                                }`}
                              >
                                {c.type === "pdf" || c.type === "note" ? (
                                  <FileText className="size-3.5" />
                                ) : c.type === "practice_test" ? (
                                  <Target className="size-3.5" />
                                ) : (
                                  <CirclePlay className="size-3.5" />
                                )}
                              </span>

                              {/* Title & subtitle */}
                              <span className="min-w-0 flex-1">
                                <span
                                  className={`block text-xs truncate ${
                                    isActive ? "font-bold text-blue-700 dark:text-blue-300" : "font-medium text-slate-800 dark:text-slate-200"
                                  }`}
                                >
                                  {c.title}
                                </span>
                                <span className="text-[11px] text-slate-500 dark:text-slate-400">
                                  {c.type === "pdf"
                                    ? `${c.pageCount} pages`
                                    : c.type === "practice_test"
                                    ? `${c.test.questionsCount} questions`
                                    : contentMeta(c)}
                                </span>
                              </span>

                              {/* Checkbox indicator */}
                              {c.completed ? (
                                <CircleCheck className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                              ) : (
                                <Circle className="size-4 shrink-0 text-slate-300 dark:text-slate-600" />
                              )}
                            </Link>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
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
