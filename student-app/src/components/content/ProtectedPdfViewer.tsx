"use client";

import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, Lock, Maximize2, Minimize2, ZoomIn, ZoomOut } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { cn } from "@/lib/utils";

/**
 * Read-only PDF viewer.
 * The browser only ever receives page IMAGES (rendered + watermarked on the server
 * behind short-lived signed URLs), never the PDF file, so there is nothing to download.
 * Right-click, drag, text selection and printing are also disabled here.
 */
export function ProtectedPdfViewer({ contentId, pageCount, onReachEnd }: { contentId: number; pageCount: number; onReachEnd?: () => void }) {
  const { data: me } = useApi(() => api.profile.getMe(), []);
  const { data: pageUrls, loading } = useApi(() => api.courses.getPdfPageUrls(contentId), [contentId]);
  // null = mock mode (sample pages). An empty list = the server is still preparing the document.
  const preparing = !loading && pageUrls !== null && pageUrls.length === 0;
  const total = pageUrls?.length || pageCount;
  const [page, setPage] = useState(1);
  const [zoom, setZoom] = useState(100);
  const scroller = useRef<HTMLDivElement>(null);
  const frame = useRef<HTMLDivElement>(null);
  const [full, setFull] = useState(false);

  // Scroll inside the viewer only (not the whole page) when the page number changes.
  useEffect(() => {
    const box = scroller.current;
    const target = document.getElementById(`pdf-page-${page}`);
    if (box && target) box.scrollTo({ top: target.offsetTop - box.offsetTop - 16, behavior: "smooth" });
  }, [page]);

  // Reaching the last page (it scrolls into view, or the student jumps there) counts as having read the document.
  const reachEnd = useRef(onReachEnd);
  reachEnd.current = onReachEnd;
  useEffect(() => {
    const box = scroller.current;
    const last = preparing ? null : document.getElementById(`pdf-page-${total}`);
    if (!box || !last || !reachEnd.current) return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          observer.disconnect();
          reachEnd.current?.();
        }
      },
      { root: box, threshold: 0.3 },
    );
    observer.observe(last);
    return () => observer.disconnect();
  }, [total, preparing, pageUrls]);

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
          <ToolButton label="Previous page" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1}>
            <ChevronLeft className="size-4" />
          </ToolButton>
          <span className="min-w-20 text-center text-sm font-semibold text-navy-900">
            {page} / {total}
          </span>
          <ToolButton label="Next page" onClick={() => setPage((p) => Math.min(total, p + 1))} disabled={page === total}>
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
