"use client";

import { useEffect, useRef, useState } from "react";
import { FileText, Lock, NotebookPen, Pencil, Trash2 } from "lucide-react";
import * as api from "@/lib/api";
import type { StudentNote } from "@/types";
import { useApi } from "@/lib/use-api";
import { cn, timeAgo } from "@/lib/utils";
import { Button } from "@/components/ui/Button";
import { ErrorState } from "@/components/ui/States";

const MAX = 5000;

/** Whole-item notes first, then by page: the same order the server uses. */
const sortNotes = (list: StudentNote[]) => [...list].sort((a, b) => (a.page ?? 0) - (b.page ?? 0) || a.id - b.id);

// An unsent note is kept in the browser, so it is not lost if the student moves to another page by accident.
const draftKey = (contentId: number) => `mmn.note-draft.${contentId}`;
function readDraft(contentId: number): string {
  try {
    return window.localStorage.getItem(draftKey(contentId)) ?? "";
  } catch {
    return "";
  }
}
function writeDraft(contentId: number, text: string) {
  try {
    if (text) window.localStorage.setItem(draftKey(contentId), text);
    else window.localStorage.removeItem(draftKey(contentId));
  } catch {
    /* storage blocked: the draft just is not kept */
  }
}

/**
 * The student's own notes on one piece of content. They are saved on the server, so they are here every time the item is
 * opened, on any device. On a PDF a note can be pinned to the page being read, and a tap on its page chip jumps there.
 */
export function NotesPanel({
  contentId,
  isPdf,
  currentPage,
  totalPages,
  onGoToPage,
  onCount,
}: {
  contentId: number;
  isPdf: boolean;
  /** The PDF page being read right now. */
  currentPage?: number;
  totalPages?: number;
  onGoToPage?: (page: number) => void;
  onCount?: (count: number) => void;
}) {
  const { data, loading, error, reload, setData } = useApi(() => api.notes.getContentNotes(contentId), [contentId]);
  const notes = data ?? [];
  const [draft, setDraft] = useState(() => readDraft(contentId));
  const [attach, setAttach] = useState(true);
  const [saving, setSaving] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    if (data) onCount?.(data.length);
  }, [data, onCount]);

  const pinnedPage = isPdf && attach && currentPage ? currentPage : null;
  const canSave = draft.trim().length > 0 && !saving;

  function change(text: string) {
    setDraft(text);
    writeDraft(contentId, text);
    setProblem(null);
  }

  async function save() {
    if (!canSave) return;
    setSaving(true);
    setProblem(null);
    try {
      const note = await api.notes.addNote(contentId, { body: draft, page: pinnedPage });
      setData((list) => sortNotes([...(list ?? []), note]));
      change("");
    } catch (e) {
      setProblem(e instanceof Error ? e.message : "Could not save the note. Please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section id="my-notes" className="scroll-mt-20 rounded-2xl border border-line bg-white">
      <header className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line px-4 py-3 sm:px-5">
        <span className="grid size-9 place-items-center rounded-xl bg-amber-50 text-amber-600">
          <NotebookPen className="size-4.5" />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="font-bold leading-tight text-navy-900">
            My notes <span className="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{notes.length}</span>
          </h2>
          <p className="flex items-center gap-1 text-xs text-slate-500">
            <Lock className="size-3" /> Only you can see these. They are here whenever you open this again.
          </p>
        </div>
      </header>

      <div className="space-y-4 p-4 sm:p-5">
        {/* Composer */}
        <div className="rounded-xl bg-slate-50 p-3 ring-1 ring-line focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-200">
          <textarea
            id="note-composer"
            value={draft}
            onChange={(e) => change(e.target.value)}
            onKeyDown={(e) => {
              if ((e.ctrlKey || e.metaKey) && e.key === "Enter") void save();
            }}
            maxLength={MAX}
            rows={3}
            placeholder={isPdf ? "Write a note: a formula, a doubt, something to revise…" : "Write a note on this lesson…"}
            className="block w-full resize-y bg-transparent text-[15px] leading-relaxed text-navy-900 outline-none placeholder:text-slate-400"
          />
          <div className="mt-2 flex flex-wrap items-center gap-2">
            {isPdf && currentPage ? (
              <button
                type="button"
                onClick={() => setAttach((a) => !a)}
                aria-pressed={attach}
                className={cn(
                  "inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset transition",
                  attach ? "bg-amber-50 text-amber-800 ring-amber-200" : "bg-white text-slate-500 ring-line",
                )}
              >
                <FileText className="size-3.5" /> {attach ? `Pinned to page ${currentPage}` : "Not pinned to a page"}
              </button>
            ) : null}
            {draft.length > MAX * 0.8 && (
              <span className={cn("text-xs", draft.length >= MAX ? "font-semibold text-red-600" : "text-slate-500")}>
                {draft.length}/{MAX}
              </span>
            )}
            <Button size="sm" className="ml-auto" onClick={save} disabled={!canSave} loading={saving}>
              Save note
            </Button>
          </div>
          {problem && <p className="mt-2 text-sm font-medium text-red-600">{problem}</p>}
        </div>

        {/* Saved notes */}
        {loading && !data ? (
          <div className="space-y-2" aria-busy>
            <div className="h-16 animate-pulse rounded-xl bg-slate-100" />
            <div className="h-16 animate-pulse rounded-xl bg-slate-100" />
          </div>
        ) : error ? (
          <ErrorState message={error} onRetry={reload} />
        ) : notes.length === 0 ? (
          <p className="rounded-xl border border-dashed border-line px-4 py-6 text-center text-sm text-slate-500">
            No notes yet. Jot down anything worth remembering: it is saved to this {isPdf ? "document" : "lesson"} and waiting for you next time.
          </p>
        ) : (
          <ul className="space-y-2.5">
            {notes.map((n) => (
              <NoteItem
                key={n.id}
                note={n}
                isPdf={isPdf}
                totalPages={totalPages}
                onGoToPage={onGoToPage}
                onChanged={(saved) => setData((list) => sortNotes((list ?? []).map((x) => (x.id === saved.id ? saved : x))))}
                onDeleted={(id) => setData((list) => (list ?? []).filter((x) => x.id !== id))}
              />
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}

function NoteItem({
  note,
  isPdf,
  totalPages,
  onGoToPage,
  onChanged,
  onDeleted,
}: {
  note: StudentNote;
  isPdf: boolean;
  totalPages?: number;
  onGoToPage?: (page: number) => void;
  onChanged: (note: StudentNote) => void;
  onDeleted: (id: number) => void;
}) {
  const [editing, setEditing] = useState(false);
  const [text, setText] = useState(note.body);
  const [pageText, setPageText] = useState(note.page ? String(note.page) : "");
  const [busy, setBusy] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const field = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (editing) field.current?.focus();
  }, [editing]);

  const edited = new Date(note.updatedAt).getTime() - new Date(note.createdAt).getTime() > 5000;

  async function save() {
    if (!text.trim()) return setProblem("A note cannot be empty. Delete it instead.");
    const page = isPdf && pageText ? Math.min(Number(pageText), totalPages || 2000) : null;
    setBusy(true);
    setProblem(null);
    try {
      onChanged(await api.notes.updateNote(note.id, { body: text, page }));
      setEditing(false);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : "Could not save. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    setBusy(true);
    try {
      await api.notes.deleteNote(note.id);
      onDeleted(note.id);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : "Could not delete. Please try again.");
      setBusy(false);
    }
  }

  return (
    <li className="rounded-xl border border-line p-3.5 sm:p-4">
      <div className="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1">
        {note.page ? (
          <button
            type="button"
            onClick={() => onGoToPage?.(note.page!)}
            title="Go to this page"
            className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 transition hover:bg-amber-100"
          >
            <FileText className="size-3.5" /> Page {note.page}
          </button>
        ) : isPdf ? (
          <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Whole document</span>
        ) : null}
        <span className="text-xs text-slate-400">
          {edited ? "Edited" : "Saved"} {timeAgo(note.updatedAt)}
        </span>
        {!editing && (
          <span className="ml-auto flex items-center gap-1">
            {confirmDelete ? (
              <>
                <span className="text-xs font-semibold text-red-700">Delete this note?</span>
                <Button size="sm" variant="danger" onClick={remove} loading={busy}>
                  Delete
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setConfirmDelete(false)} disabled={busy}>
                  Keep
                </Button>
              </>
            ) : (
              <>
                <button aria-label="Edit note" title="Edit" onClick={() => setEditing(true)} className="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-navy-900">
                  <Pencil className="size-4" />
                </button>
                <button aria-label="Delete note" title="Delete" onClick={() => setConfirmDelete(true)} className="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600">
                  <Trash2 className="size-4" />
                </button>
              </>
            )}
          </span>
        )}
      </div>

      {editing ? (
        <div>
          <textarea
            ref={field}
            value={text}
            onChange={(e) => setText(e.target.value)}
            maxLength={MAX}
            rows={4}
            className="block w-full resize-y rounded-lg border border-line bg-white px-3 py-2 text-[15px] leading-relaxed text-navy-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
          />
          <div className="mt-2 flex flex-wrap items-center gap-2">
            {isPdf && (
              <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                Page
                <input
                  inputMode="numeric"
                  value={pageText}
                  onChange={(e) => setPageText(e.target.value.replace(/\D/g, "").slice(0, 4))}
                  placeholder="all"
                  aria-label="Page of this note (leave empty for the whole document)"
                  className="h-8 w-16 rounded-lg border border-line bg-white px-2 text-center text-sm text-navy-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                />
              </label>
            )}
            <span className="ml-auto flex gap-2">
              <Button
                size="sm"
                variant="ghost"
                onClick={() => {
                  setEditing(false);
                  setText(note.body);
                  setPageText(note.page ? String(note.page) : "");
                  setProblem(null);
                }}
                disabled={busy}
              >
                Cancel
              </Button>
              <Button size="sm" onClick={save} loading={busy}>
                Save
              </Button>
            </span>
          </div>
        </div>
      ) : (
        <p className="whitespace-pre-wrap break-words text-[15px] leading-relaxed text-slate-700">{note.body}</p>
      )}
      {problem && <p className="mt-2 text-sm font-medium text-red-600">{problem}</p>}
    </li>
  );
}
