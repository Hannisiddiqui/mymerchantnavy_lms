"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { ArrowRight, FileText, NotebookPen, Search } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { timeAgo } from "@/lib/utils";
import type { NoteWithContext } from "@/types";
import { ContentIcon } from "@/components/course/contentTypes";
import { Select } from "@/components/ui/Field";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState, ErrorState, PageSkeleton } from "@/components/ui/States";

/** Every note the student has written, grouped by the PDF or lesson it belongs to. Notes are edited where they were written. */
export default function MyNotesPage() {
  const { data, loading, error, reload } = useApi(() => api.notes.getAllNotes(), []);
  const [query, setQuery] = useState("");
  const [subject, setSubject] = useState("all");

  const subjects = useMemo(() => [...new Map((data ?? []).map((n) => [n.subjectId, n.subjectTitle])).entries()], [data]);

  const groups = useMemo(() => {
    const q = query.trim().toLowerCase();
    const shown = (data ?? []).filter(
      (n) =>
        (subject === "all" || String(n.subjectId) === subject) &&
        (!q || [n.body, n.contentTitle, n.chapterTitle].some((t) => t.toLowerCase().includes(q))),
    );
    const byContent = new Map<number, NoteWithContext[]>();
    for (const n of shown) byContent.set(n.contentId, [...(byContent.get(n.contentId) ?? []), n]);
    // a group's notes in reading order; groups with the most recently touched note first
    return [...byContent.values()]
      .map((notes) => ({ notes: [...notes].sort((a, b) => (a.page ?? 0) - (b.page ?? 0) || a.id - b.id), latest: Math.max(...notes.map((n) => new Date(n.updatedAt).getTime())) }))
      .sort((a, b) => b.latest - a.latest);
  }, [data, query, subject]);

  return (
    <>
      <PageHeader title="My Notes" subtitle="Everything you have written while studying, in one place" />

      {loading ? (
        <PageSkeleton />
      ) : error ? (
        <ErrorState message={error} onRetry={reload} />
      ) : !data?.length ? (
        <EmptyState
          icon={NotebookPen}
          title="No notes yet"
          body="Open a PDF or a lesson and write a note: a formula, a doubt, something to revise. It will be saved here and next to the page it belongs to."
          action={<Button href="/my-courses">Go to My Courses</Button>}
        />
      ) : (
        <>
          <div className="mb-5 flex flex-col gap-2.5 sm:flex-row sm:items-center">
            <label className="relative block flex-1">
              <span className="sr-only">Search your notes</span>
              <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
              <input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search your notes…"
                className="h-11 w-full rounded-lg border border-line bg-white pl-10 pr-3.5 text-sm text-navy-900 outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
              />
            </label>
            {subjects.length > 1 && (
              <div className="sm:w-56">
                <Select value={subject} onChange={(e) => setSubject(e.target.value)} aria-label="Filter by subject">
                  <option value="all">All subjects</option>
                  {subjects.map(([id, title]) => (
                    <option key={id} value={id}>
                      {title}
                    </option>
                  ))}
                </Select>
              </div>
            )}
          </div>
          <p className="mb-3 text-xs font-medium text-slate-500">
            {groups.reduce((n, g) => n + g.notes.length, 0)} note{groups.reduce((n, g) => n + g.notes.length, 0) === 1 ? "" : "s"} in {groups.length} {groups.length === 1 ? "item" : "items"}
          </p>

          {groups.length === 0 ? (
            <p className="rounded-2xl border border-dashed border-line bg-white p-8 text-center text-sm text-slate-500">No notes match your search.</p>
          ) : (
            <div className="space-y-4">
              {groups.map(({ notes }) => {
                const first = notes[0];
                return (
                  <section key={first.contentId} className="overflow-hidden rounded-2xl border border-line bg-white">
                    <header className="flex items-center gap-3 border-b border-line bg-slate-50/70 px-4 py-3 sm:px-5">
                      <ContentIcon type={first.contentType} />
                      <div className="min-w-0 flex-1">
                        <h2 className="truncate font-bold text-navy-900">{first.contentTitle}</h2>
                        <p className="truncate text-xs text-slate-500">
                          {first.courseTitle} · {first.subjectTitle} · {first.chapterTitle}
                        </p>
                      </div>
                      <Button size="sm" variant="outline" href={`/learn/${first.contentId}`}>
                        Open <ArrowRight className="size-3.5" />
                      </Button>
                    </header>
                    <ul className="divide-y divide-line">
                      {notes.map((n) => (
                        <li key={n.id} className="px-4 py-3.5 sm:px-5">
                          <div className="mb-1.5 flex flex-wrap items-center gap-2">
                            {n.page ? (
                              <Link
                                href={`/learn/${n.contentId}?page=${n.page}`}
                                className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 hover:bg-amber-100"
                              >
                                <FileText className="size-3.5" /> Page {n.page}
                              </Link>
                            ) : null}
                            <span className="text-xs text-slate-400">{timeAgo(n.updatedAt)}</span>
                          </div>
                          <p className="whitespace-pre-wrap break-words text-[15px] leading-relaxed text-slate-700">{n.body}</p>
                        </li>
                      ))}
                    </ul>
                  </section>
                );
              })}
            </div>
          )}
        </>
      )}
    </>
  );
}
