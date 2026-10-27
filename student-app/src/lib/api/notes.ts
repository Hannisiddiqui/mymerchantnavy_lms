/** The student's own notes on what they read or watch. Backend: app/Http/Controllers/Api/V1/NoteController.php */
import type { NoteWithContext, StudentNote } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";

export interface NoteInput {
  body: string;
  /** PDF page; null or left out = the whole item. */
  page?: number | null;
}

// ---- demo mode: notes live in this browser only ----
const MOCK_KEY = "mmn.mock.notes";
const readMock = (): StudentNote[] => {
  try {
    return JSON.parse(window.localStorage.getItem(MOCK_KEY) ?? "[]");
  } catch {
    return [];
  }
};
const writeMock = (notes: StudentNote[]) => {
  try {
    window.localStorage.setItem(MOCK_KEY, JSON.stringify(notes));
  } catch {
    /* storage blocked: notes just are not kept in demo mode */
  }
};

const byPage = (a: StudentNote, b: StudentNote) => (a.page ?? 0) - (b.page ?? 0) || a.id - b.id;

/** Whole-item notes first, then by page. */
export async function getContentNotes(contentId: number): Promise<StudentNote[]> {
  if (USE_MOCK) {
    await mockDelay(120);
    return readMock().filter((n) => n.contentId === contentId).sort(byPage);
  }
  return request<StudentNote[]>(`/contents/${contentId}/notes`);
}

export async function addNote(contentId: number, input: NoteInput): Promise<StudentNote> {
  if (USE_MOCK) {
    const now = new Date().toISOString();
    const note: StudentNote = { id: Date.now(), contentId, page: input.page ?? null, body: input.body.trim(), createdAt: now, updatedAt: now };
    writeMock([...readMock(), note]);
    return note;
  }
  return request<StudentNote>(`/contents/${contentId}/notes`, { method: "POST", body: JSON.stringify(input) });
}

export async function updateNote(id: number, input: NoteInput): Promise<StudentNote> {
  if (USE_MOCK) {
    let updated!: StudentNote;
    writeMock(readMock().map((n) => (n.id === id ? (updated = { ...n, body: input.body.trim(), page: input.page ?? null, updatedAt: new Date().toISOString() }) : n)));
    return updated;
  }
  return request<StudentNote>(`/notes/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deleteNote(id: number): Promise<void> {
  if (USE_MOCK) {
    writeMock(readMock().filter((n) => n.id !== id));
    return;
  }
  await request(`/notes/${id}`, { method: "DELETE" });
}

/** Every note, newest first, with where it belongs (for the My Notes page). */
export async function getAllNotes(): Promise<NoteWithContext[]> {
  if (USE_MOCK) {
    await mockDelay(150);
    return [];
  }
  return request<NoteWithContext[]>("/notes");
}
