"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, Lock, Maximize2, Minimize2, NotebookPen, ZoomIn, ZoomOut } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { cn } from "@/lib/utils";

/**
 * Read-only PDF viewer.
 * The browser only ever receives page IMAGES (rendered + watermarked on the server
 * behind short-lived signed URLs), never the PDF file, so there is nothing to download.
 * Right-click, drag, text selection and printing are also disabled here.
 */
export function ProtectedPdfViewer({
  contentId,
  pageCount,
  startPage,
  onPageChange,
  goToRef,
  noteCount,
  onTakeNote,
}: {
  contentId: number;
  pageCount: number;
  /** Shows a Notes button in the toolbar (with this many notes) that opens the notes below the viewer. */
  noteCount?: number;
  onTakeNote?: () => void;
  /** Open at this page (e.g. from a saved note). */
  startPage?: number | null;
  /** Told the page the student is looking at, as they scroll or jump. */
  onPageChange?: (page: number) => void;
  /** Filled with a function that scrolls the viewer to a page (used by the notes list). */
  goToRef?: React.MutableRefObject<((page: number) => void) | null>;
}) {
  const { data: me } = useApi(() => api.profile.getMe(), []);
  const { data: pageUrls, loading } = useApi(() => api.courses.getPdfPageUrls(contentId), [contentId]);
  // null = mock mode (sample pages). An empty list = the server is still preparing the document.
  const preparing = !loading && pageUrls !== null && pageUrls.length === 0;
  const total = pageUrls?.length || pageCount;
  const [page, setPage] = useState(1);
  const [pageText, setPageText] = useState("1");
  const [editingPage, setEditingPage] = useState(false);
  const [zoom, setZoom] = useState(100);
  const scroller = useRef<HTMLDivElement>(null);
  const frame = useRef<HTMLDivElement>(null);
  const [full, setFull] = useState(false);
  /** While the viewer scrolls itself to a page, scroll events must not overwrite the page number. */
  const lockUntil = useRef(0);

  /** Scrolls inside the viewer only (not the whole page) to the top of a page. */
  const goTo = useCallback(
    (target: number, smooth = true) => {
      const box = scroller.current;
      const el = document.getElementById(`pdf-page-${Math.min(Math.max(1, target), total)}`);
      const clamped = Math.min(Math.max(1, target), total);
      setPage(clamped);
      if (!box || !el) return;
      lockUntil.current = Date.now() + (smooth ? 900 : 150);
      box.scrollTo({ top: box.scrollTop + (el.getBoundingClientRect().top - box.getBoundingClientRect().top) - 8, behavior: smooth ? "smooth" : "instant" });
    },
    [total],
  );

  // The page number follows the scrolling: the page under the reading line (a little below the top of the viewer).
  useEffect(() => {
    const box = scroller.current;
    if (!box || preparing) return;
    // A short timer (not requestAnimationFrame, which a background tab can pause for good) limits how often this runs.
    let timer: number | undefined;
    const update = () => {
      timer = undefined;
      if (Date.now() < lockUntil.current) {
        timer = window.setTimeout(update, 120); // still moving to a page: look again once it has arrived
        return;
      }
      const line = box.getBoundingClientRect().top + box.clientHeight * 0.3;
      let current = 1;
      for (let n = 1; n <= total; n++) {
        const el = document.getElementById(`pdf-page-${n}`);
        if (!el) break;
        if (el.getBoundingClientRect().top <= line) current = n;
        else break;
      }
      setPage((p) => (p === current ? p : current));
    };
    const onScroll = () => {
      if (timer === undefined) timer = window.setTimeout(update, 60);
    };
    box.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      box.removeEventListener("scroll", onScroll);
      if (timer !== undefined) window.clearTimeout(timer);
    };
  }, [total, preparing, pageUrls]);

  // Keep the box showing the page number, unless the student is typing in it.
  useEffect(() => {
    if (!editingPage) setPageText(String(page));
  }, [page, editingPage]);

  const reportPage = useRef(onPageChange);
  reportPage.current = onPageChange;
  useEffect(() => {
    reportPage.current?.(page);
  }, [page]);

  useEffect(() => {
    if (goToRef) goToRef.current = (p: number) => goTo(p);
    return () => {
      if (goToRef) goToRef.current = null;
    };
  }, [goToRef, goTo]);

  // Open at a saved page, once the pages are on screen.
  const opened = useRef(false);
  useEffect(() => {
    if (opened.current || !startPage || preparing || !total) return;
    opened.current = true;
    window.setTimeout(() => goTo(startPage, false), 50);
  }, [startPage, preparing, total, goTo]);

  // Changing the zoom changes every page's height: stay on the page being read.
  const lastZoom = useRef(zoom);
  useEffect(() => {
    if (lastZoom.current === zoom) return; // only when the zoom really changed (not on first open)
    lastZoom.current = zoom;
    const t = window.setTimeout(() => goTo(page, false), 50);
    return () => window.clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [zoom]);

  // Full screen: the page fills the whole window (works on every phone). Where the browser allows it
  // we also ask for true full screen, which hides the browser bars. Esc leaves it.
  useEffect(() => {
    if (!full) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setFull(false);
    const onChange = () => !document.fullscreenElement && setFull(false);
    document.addEventListener("keydown", onKey);
    document.addEventListener("fullscreenchange", onChange);
    document.body.style.overflow = "hidden";
    void frame.current?.requestFullscreen?.().catch(() => {});
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("fullscreenchange", onChange);
      document.body.style.overflow = "";
      if (document.fullscreenElement) void document.exitFullscreen().catch(() => {});
    };
  }, [full]);

  const watermark = me ? `${me.name} · ${me.email || me.phone}` : "";

  return (
    <div
      ref={frame}
      className={cn("protected-content overflow-hidden border border-line bg-white", full ? "fixed inset-0 z-50 flex flex-col rounded-none" : "rounded-2xl")}
      onContextMenu={(e) => e.preventDefault()}
    >
      <div className="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
        <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
          <Lock className="size-3.5" /> View only
        </span>
        <div className="mx-auto flex items-center gap-1">
          <ToolButton label="Previous page" onClick={() => goTo(page - 1)} disabled={page === 1}>
            <ChevronLeft className="size-4" />
          </ToolButton>
          <span className="flex min-w-20 items-center justify-center gap-1 text-sm font-semibold text-navy-900">
            <input
              aria-label="Page number"
              inputMode="numeric"
              value={pageText}
              onFocus={(e) => {
                setEditingPage(true);
                e.currentTarget.select();
              }}
              onChange={(e) => setPageText(e.target.value.replace(/\D/g, "").slice(0, 4))}
              onBlur={() => {
                setEditingPage(false);
                const n = Number(pageText);
                if (n >= 1) goTo(n);
                else setPageText(String(page));
              }}
              onKeyDown={(e) => e.key === "Enter" && e.currentTarget.blur()}
              className="h-7 w-11 rounded-md border border-line bg-white text-center text-sm font-semibold text-navy-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span className="text-slate-500">/ {total}</span>
          </span>
          <ToolButton label="Next page" onClick={() => goTo(page + 1)} disabled={page === total}>
            <ChevronRight className="size-4" />
          </ToolButton>
        </div>
        <div className="flex items-center gap-1">
          <ToolButton label="Zoom out" onClick={() => setZoom((z) => Math.max(60, z - 20))}>
            <ZoomOut className="size-4" />
          </ToolButton>
          <span className="w-12 text-center text-xs font-semibold text-slate-600">{zoom}%</span>
          <ToolButton label="Zoom in" onClick={() => setZoom((z) => Math.min(200, z + 20))}>
            <ZoomIn className="size-4" />
          </ToolButton>
          <ToolButton label={full ? "Exit full screen" : "Read in full screen"} onClick={() => setFull((f) => !f)}>
            {full ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
          </ToolButton>
          {onTakeNote && (
            <button
              type="button"
              onClick={() => {
                if (full) setFull(false);
                window.setTimeout(onTakeNote, full ? 300 : 0);
              }}
              className="ml-1 inline-flex h-8 items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 hover:bg-amber-100"
            >
              <NotebookPen className="size-4" /> Notes{noteCount ? ` (${noteCount})` : ""}
            </button>
          )}
        </div>
      </div>

      <div
        ref={scroller}
        className={cn("relative select-none overflow-auto bg-slate-100 p-2 sm:p-3", full ? "min-h-0 flex-1" : "h-[calc(100dvh-11rem)] min-h-[28rem]")}
      >
        {/* Pages fill the available width; zoom scales from there */}
        {preparing && (
          <div className="grid h-full place-items-center text-center">
            <div>
              <Lock className="mx-auto size-8 text-slate-400" />
              <p className="mt-3 font-semibold text-navy-900">This document is being prepared</p>
              <p className="mt-1 text-sm text-slate-500">Please check back in a few minutes.</p>
            </div>
          </div>
        )}
        <div className="mx-auto space-y-3" style={{ width: full ? `min(${zoom}%, ${(zoom / 100) * 60}rem)` : `${zoom}%` }}>
          {Array.from({ length: preparing ? 0 : total }, (_, i) => (
            <div key={i} id={`pdf-page-${i + 1}`} className="relative aspect-[1/1.414] overflow-hidden bg-white shadow-sm" draggable={false}>
              {pageUrls?.[i] ? (
                // The real page images already carry the student's name and "Created by - MyMerchantNavy.com", burned in by the server
                // eslint-disable-next-line @next/next/no-img-element
                <img src={pageUrls[i]} alt={`Page ${i + 1}`} loading="lazy" decoding="async" draggable={false} className="pointer-events-none size-full object-contain" />
              ) : (
                <>
                  <MockPage number={i + 1} />
                  <Watermark text={watermark} />
                </>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function ToolButton({ label, children, ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { label: string }) {
  return (
    <button aria-label={label} className="grid size-8 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-40" {...rest}>
      {children}
    </button>
  );
}

/** Diagonal repeated student name + login (email). The server burns the same watermark into the real page images. */
function Watermark({ text }: { text: string }) {
  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 grid grid-cols-2 place-items-center gap-y-24 overflow-hidden">
      {Array.from({ length: 6 }, (_, i) => (
        <span key={i} className="-rotate-30 whitespace-nowrap text-sm font-semibold text-navy-900/[0.08]">
          {text}
        </span>
      ))}
    </div>
  );
}

/** Placeholder page while using mock data. */
function MockPage({ number }: { number: number }) {
  const widths = ["w-full", "w-11/12", "w-10/12", "w-full", "w-9/12", "w-full", "w-8/12"];
  return (
    <div className="flex h-full flex-col p-[7%]">
      <div className="mb-[6%] flex items-center justify-between border-b border-slate-200 pb-[3%]">
        <div className="h-2.5 w-1/3 rounded bg-navy-900/70" />
        <span className="text-[10px] font-semibold text-slate-400">Page {number}</span>
      </div>
      {[0, 1, 2].map((block) => (
        <div key={block} className="mb-[7%] space-y-2">
          <div className="mb-3 h-2 w-1/4 rounded bg-brand-600/60" />
          {widths.map((w, i) => (
            <div key={i} className={cn("h-1.5 rounded bg-slate-200", w)} />
          ))}
        </div>
      ))}
    </div>
  );
}
