"use client";
import { cleanHtml } from "@/lib/sanitize";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import {
  ArrowRight,
  ChevronDown,
  ChevronUp,
  HelpCircle,
  Info,
  AlertTriangle,
  CheckCircle2,
  Sparkles,
  Layers,
  ExternalLink,
  Download,
  Clock,
  Check,
  ShoppingBag,
} from "lucide-react";
import * as api from "@/lib/api";
import { apiBaseUrl } from "@/lib/api/client";
import { useApi } from "@/lib/use-api";
import type { CustomPageBlock, CustomPageDetail } from "@/lib/api/customPages";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

export default function CustomDynamicPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const { data: page, loading, error, reload } = useApi(() => api.customPages.get(slug), [slug]);

  if (loading) return <PageSkeleton />;
  if (error || !page) return <ErrorState message={error ?? "Could not load page"} onRetry={reload} />;

  const hasBlocks = Array.isArray(page.blocks) && page.blocks.length > 0;
  const widthClass = { narrow: "max-w-3xl", wide: "max-w-7xl", full: "max-w-none" }[page.width ?? "normal"] ?? "max-w-5xl";
  const cover = safeHref(page.cover_image_url);

  return (
    <div className={`mx-auto space-y-8 ${widthClass}`} style={themeVars(page.theme)}>
      {page.show_header !== false && <PageHeader
        title={page.title}
        subtitle={page.meta_description ?? undefined}
        crumbs={[
          { label: "Dashboard", href: "/my-courses" },
          { label: "Resources", href: "/my-courses" },
          { label: page.title },
        ]}
        action={
          page.badge_text ? (
            <span className="rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
              {page.badge_text}
            </span>
          ) : undefined
        }
      />}

      {cover && (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={cover} alt="" className="h-auto max-h-80 w-full rounded-3xl border border-line object-cover shadow-sm dark:border-slate-800" />
      )}

      {/* Render Visual Blocks if Defined */}
      {hasBlocks && (
        <div className="space-y-8">
          {page.blocks!.map((block, idx) => (
            <RenderBlock key={`${block.type}-${idx}`} block={block} />
          ))}
        </div>
      )}

      {/* Render Fallback Rich Text Content */}
      {page.content && (
        <div className="rounded-2xl border border-line bg-card p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900 shadow-sm">
          <div
            className="prose prose-slate max-w-none dark:prose-invert prose-headings:font-bold prose-a:text-brand-600 dark:prose-a:text-brand-400"
            dangerouslySetInnerHTML={{ __html: cleanHtml(page.content) }}
          />
        </div>
      )}

      {/* Empty State if neither blocks nor content */}
      {!hasBlocks && !page.content && (
        <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 p-12 text-center bg-card dark:bg-slate-900">
          <Layers className="size-12 text-slate-400 mx-auto mb-3" />
          <h3 className="text-base font-bold text-navy-900 dark:text-white">Page content under construction</h3>
          <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
            The administrator has published this page title but has not added content blocks yet.
          </p>
        </div>
      )}
    </div>
  );
}

/** Block Renderer */
function RenderBlock({ block }: { block: CustomPageBlock }) {
  switch (block.type) {
    case "hero":
      return <HeroBlock data={block.data} />;
    case "rich_text":
      return <RichTextBlock data={block.data} />;
    case "cards_grid":
      return <CardsGridBlock data={block.data} />;
    case "faq":
      return <FaqBlock data={block.data} />;
    case "cta_box":
      return <CtaBoxBlock data={block.data} />;
    case "alert_notice":
      return <AlertNoticeBlock data={block.data} />;
    case "media_image":
      return <MediaImageBlock data={block.data} />;
    case "split_content":
      return <SplitContentBlock data={block.data} />;
    case "stats_numbers":
      return <StatsNumbersBlock data={block.data} />;
    case "testimonials":
      return <TestimonialsBlock data={block.data} />;
    case "video_embed":
      return <VideoEmbedBlock data={block.data} />;
    case "section_heading":
      return <SectionHeadingBlock data={block.data} />;
    case "text_columns":
      return <TextColumnsBlock data={block.data} />;
    case "button_row":
      return <ButtonRowBlock data={block.data} />;
    case "image_gallery":
      return <ImageGalleryBlock data={block.data} />;
    case "timeline":
      return <TimelineBlock data={block.data} />;
    case "data_table":
      return <DataTableBlock data={block.data} />;
    case "downloads":
      return <DownloadsBlock data={block.data} />;
    case "batches_showcase":
      return <BatchesShowcaseBlock data={block.data} />;
    case "countdown":
      return <CountdownBlock data={block.data} />;
    case "checklist":
      return <ChecklistBlock data={block.data} />;
    case "spacer":
      return <SpacerBlock data={block.data} />;
    case "custom_html":
      return <CustomHtmlBlock data={block.data} />;
    default:
      return null;
  }
}

/** 1. Hero Block */
function HeroBlock({ data }: { data: Record<string, any> }) {
  const bgTheme = data.bg_theme || "navy";

  const themeClasses =
    bgTheme === "royal"
      ? "bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-800 text-white"
      : bgTheme === "emerald"
      ? "bg-gradient-to-r from-emerald-800 to-teal-900 text-white"
      : bgTheme === "amber"
      ? "bg-gradient-to-r from-amber-700 via-orange-800 to-amber-900 text-white"
      : "bg-navy-900 text-white dark:bg-slate-900/90 border border-slate-800";

  return (
    <div className={`relative overflow-hidden rounded-3xl p-8 sm:p-10 shadow-lg ${themeClasses}`}>
      <div className="relative z-10 max-w-3xl space-y-4">
        {data.badge && (
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold tracking-wider uppercase text-white backdrop-blur-sm border border-white/15">
            <Sparkles className="size-3.5 text-amber-300" />
            {data.badge}
          </span>
        )}

        <h1 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
          {data.title}
        </h1>

        {data.subtitle && (
          <p className="text-sm sm:text-base text-slate-200/90 leading-relaxed font-normal">
            {data.subtitle}
          </p>
        )}

        {data.cta_text && data.cta_url && (
          <div className="pt-2">
            <Link
              href={data.cta_url}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-2.5 text-xs font-bold text-navy-900 shadow hover:bg-slate-100 transition whitespace-nowrap active:scale-95"
            >
              <span>{data.cta_text}</span>
              <ArrowRight className="size-4 shrink-0" />
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}

/** 2. Rich Text Section */
function RichTextBlock({ data }: { data: Record<string, any> }) {
  return (
    <div className="rounded-2xl border border-line bg-card p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900 shadow-sm space-y-4">
      {data.heading && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white border-b border-line pb-3 dark:border-slate-800">
          {data.heading}
        </h2>
      )}
      <div
        className="prose prose-slate max-w-none dark:prose-invert prose-headings:font-bold prose-a:text-brand-600 dark:prose-a:text-brand-400"
        dangerouslySetInnerHTML={{ __html: cleanHtml(data.body) }}
      />
    </div>
  );
}

/** 3. Feature Cards Grid */
function CardsGridBlock({ data }: { data: Record<string, any> }) {
  const cols = data.columns === "2" ? "md:grid-cols-2" : data.columns === "4" ? "md:grid-cols-2 lg:grid-cols-4" : "md:grid-cols-3";
  const cards = Array.isArray(data.cards) ? data.cards : [];

  return (
    <div className="space-y-4">
      {data.section_title && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white">{data.section_title}</h2>
      )}
      <div className={`grid grid-cols-1 gap-4 ${cols}`}>
        {cards.map((card: any, idx: number) => {
          const Content = (
            <div className="flex h-full flex-col justify-between rounded-2xl border border-line bg-card p-5 dark:border-slate-800 dark:bg-slate-900 shadow-sm transition hover:shadow-md">
              <div>
                <div className="flex items-center justify-between gap-2 mb-2">
                  <h3 className="text-sm font-bold text-navy-900 dark:text-white">{card.title}</h3>
                  {card.badge && (
                    <span className="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                      {card.badge}
                    </span>
                  )}
                </div>
                {card.description && (
                  <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {card.description}
                  </p>
                )}
              </div>
              {card.link_url && (
                <div className="mt-4 pt-3 border-t border-line dark:border-slate-800 flex items-center justify-between text-xs font-semibold text-brand-600 dark:text-brand-400">
                  <span>Explore</span>
                  <ExternalLink className="size-3.5" />
                </div>
              )}
            </div>
          );

          return card.link_url ? (
            <Link key={idx} href={card.link_url}>
              {Content}
            </Link>
          ) : (
            <div key={idx}>{Content}</div>
          );
        })}
      </div>
    </div>
  );
}

/** 4. FAQ / Accordion Block */
function FaqBlock({ data }: { data: Record<string, any> }) {
  const items = Array.isArray(data.items) ? data.items : [];
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <div className="space-y-4">
      {data.section_title && (
        <div className="flex items-center gap-2">
          <HelpCircle className="size-5 text-brand-500" />
          <h2 className="text-lg font-bold text-navy-900 dark:text-white">{data.section_title}</h2>
        </div>
      )}
      <div className="space-y-3">
        {items.map((item: any, idx: number) => {
          const isOpen = openIndex === idx;
          return (
            <div
              key={idx}
              className="rounded-2xl border border-line bg-card overflow-hidden dark:border-slate-800 dark:bg-slate-900 shadow-sm"
            >
              <button
                type="button"
                onClick={() => setOpenIndex(isOpen ? null : idx)}
                className="w-full flex items-center justify-between gap-4 p-5 text-left text-sm font-bold text-navy-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800/50 transition"
              >
                <span>{item.question}</span>
                {isOpen ? <ChevronUp className="size-4 shrink-0 text-slate-400" /> : <ChevronDown className="size-4 shrink-0 text-slate-400" />}
              </button>
              {isOpen && (
                <div className="px-5 pb-5 pt-1 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed border-t border-line dark:border-slate-800">
                  {item.answer}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/** 5. Call-to-Action Box */
function CtaBoxBlock({ data }: { data: Record<string, any> }) {
  return (
    <div className="rounded-3xl border border-brand-200 bg-brand-50/50 p-8 text-center dark:border-brand-900/40 dark:bg-brand-950/20 shadow-sm">
      <div className="mx-auto max-w-xl space-y-3">
        <h2 className="text-xl font-bold text-navy-900 dark:text-white">{data.headline}</h2>
        {data.description && (
          <p className="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
            {data.description}
          </p>
        )}
        {data.button_label && data.button_url && (
          <div className="pt-2">
            <Link
              href={data.button_url}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-brand-700 transition whitespace-nowrap active:scale-95"
            >
              <span>{data.button_label}</span>
              <ArrowRight className="size-4 shrink-0" />
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}

/** 6. Alert / Notice Block */
function AlertNoticeBlock({ data }: { data: Record<string, any> }) {
  const type = data.type || "info";

  const config = {
    info: {
      border: "border-blue-200 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-950/20 text-blue-900 dark:text-blue-200",
      icon: <Info className="size-5 text-blue-600 dark:text-blue-400 shrink-0" />,
    },
    success: {
      border: "border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-200",
      icon: <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />,
    },
    warning: {
      border: "border-amber-200 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/20 text-amber-900 dark:text-amber-200",
      icon: <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400 shrink-0" />,
    },
    danger: {
      border: "border-rose-200 dark:border-rose-900/40 bg-rose-50/60 dark:bg-rose-950/20 text-rose-900 dark:text-rose-200",
      icon: <AlertTriangle className="size-5 text-rose-600 dark:text-rose-400 shrink-0" />,
    },
  }[type as "info" | "success" | "warning" | "danger"] || {
    border: "border-blue-200 dark:border-blue-900/40 bg-blue-50/60 dark:bg-blue-950/20 text-blue-900 dark:text-blue-200",
    icon: <Info className="size-5 text-blue-600 shrink-0" />,
  };

  return (
    <div className={`flex items-start gap-3 rounded-2xl border p-4 sm:p-5 ${config.border}`}>
      {config.icon}
      <div className="space-y-1">
        <h4 className="text-sm font-bold">{data.title}</h4>
        <p className="text-xs leading-relaxed opacity-90">{data.message}</p>
      </div>
    </div>
  );
}

/** 7. Media / Image Block (Upload from Computer or URL) */
function MediaImageBlock({ data }: { data: Record<string, any> }) {
  const src = storageUrl(data.image_file) ?? safeHref(data.image_url);
  if (!src) return null;

  const align = data.alignment || "center";
  const alignClass =
    align === "left"
      ? "mr-auto max-w-xl"
      : align === "right"
      ? "ml-auto max-w-xl"
      : align === "full"
      ? "w-full"
      : "mx-auto max-w-3xl";

  return (
    <figure className={`space-y-2 ${alignClass}`}>
      <div className="overflow-hidden rounded-2xl border border-line bg-card shadow-sm dark:border-slate-800">
        <img
          src={src}
          alt={data.caption || "Page media"}
          className="h-auto w-full object-cover transition duration-300 hover:scale-[1.01]"
          loading="lazy"
        />
      </div>
      {data.caption && (
        <figcaption className="text-center text-xs text-slate-500 dark:text-slate-400 italic">
          {data.caption}
        </figcaption>
      )}
    </figure>
  );
}

/** 8. Split Content Layout (Image + Text) */
function SplitContentBlock({ data }: { data: Record<string, any> }) {
  const src = data.image_file ? `https://api.mymerchantnavy.com/storage/${data.image_file}` : data.image_url;
  const isRight = data.image_position === "right";

  return (
    <div className={`grid grid-cols-1 items-center gap-8 lg:grid-cols-2 ${isRight ? "lg:flex-row-reverse" : ""}`}>
      {src && (
        <div className={`overflow-hidden rounded-2xl border border-line bg-card shadow-md dark:border-slate-800 ${isRight ? "lg:order-2" : "lg:order-1"}`}>
          <img src={src} alt={data.heading || "Section image"} className="h-full w-full object-cover max-h-96" loading="lazy" />
        </div>
      )}
      <div className={`space-y-4 ${isRight ? "lg:order-1" : "lg:order-2"}`}>
        <h2 className="text-xl sm:text-2xl font-extrabold text-navy-900 dark:text-white leading-snug">
          {data.heading}
        </h2>
        {data.body && (
          <div
            className="prose prose-slate max-w-none text-xs sm:text-sm dark:prose-invert"
            dangerouslySetInnerHTML={{ __html: cleanHtml(data.body) }}
          />
        )}
        {data.button_text && data.button_url && (
          <div className="pt-2">
            <Link
              href={data.button_url}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-brand-700 transition whitespace-nowrap active:scale-95"
            >
              <span>{data.button_text}</span>
              <ArrowRight className="size-4 shrink-0" />
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}

/** 9. Numbers & Statistics Counters */
function StatsNumbersBlock({ data }: { data: Record<string, any> }) {
  const stats = Array.isArray(data.stats) ? data.stats : [];

  return (
    <div className="space-y-4">
      {data.section_title && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white text-center">{data.section_title}</h2>
      )}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {stats.map((s: any, idx: number) => (
          <div key={idx} className="rounded-2xl border border-line bg-card p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="text-3xl sm:text-4xl font-extrabold text-brand-600 dark:text-brand-400 tracking-tight">
              {s.value}
            </div>
            <div className="mt-2 text-sm font-bold text-navy-900 dark:text-white">{s.label}</div>
            {s.description && (
              <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">{s.description}</div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

/** 10. Testimonials / Student Reviews */
function TestimonialsBlock({ data }: { data: Record<string, any> }) {
  const reviews = Array.isArray(data.reviews) ? data.reviews : [];

  return (
    <div className="space-y-4">
      {data.section_title && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white">{data.section_title}</h2>
      )}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {reviews.map((r: any, idx: number) => (
          <div key={idx} className="flex flex-col justify-between rounded-2xl border border-line bg-card p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p className="text-xs sm:text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">
              "{r.feedback}"
            </p>
            <div className="mt-4 pt-3 border-t border-line dark:border-slate-800 flex items-center gap-3">
              {r.avatar_url ? (
                <img src={r.avatar_url} alt={r.name} className="size-9 rounded-full object-cover" />
              ) : (
                <div className="flex size-9 items-center justify-center rounded-full bg-brand-100 text-brand-800 font-bold text-xs dark:bg-brand-950 dark:text-brand-300">
                  {r.name?.charAt(0) || "S"}
                </div>
              )}
              <div>
                <div className="text-xs font-bold text-navy-900 dark:text-white">{r.name}</div>
                {r.rank && <div className="text-[11px] text-brand-600 dark:text-brand-400 font-medium">{r.rank}</div>}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/** 11. Video Embed Block */
function VideoEmbedBlock({ data }: { data: Record<string, any> }) {
  if (!data.video_url) return null;

  // YouTube / Vimeo links become players; a direct .mp4 plays in a video tag; anything else is not shown.
  const raw = String(data.video_url).trim();
  let embedUrl: string | null = null;
  let mp4: string | undefined;
  try {
    const u = new URL(raw);
    if (u.protocol === "https:") {
      if (/(^|\.)youtube\.com$/.test(u.hostname) && u.searchParams.get("v")) embedUrl = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(u.searchParams.get("v")!)}`;
      else if (/(^|\.)youtube\.com$/.test(u.hostname) && u.pathname.startsWith("/embed/")) embedUrl = `https://www.youtube-nocookie.com${u.pathname}`;
      else if (u.hostname === "youtu.be") embedUrl = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(u.pathname.slice(1))}`;
      else if (u.hostname === "player.vimeo.com") embedUrl = u.toString();
      else if (u.hostname === "vimeo.com" && /^\/\d+/.test(u.pathname)) embedUrl = `https://player.vimeo.com/video/${u.pathname.slice(1).split("/")[0]}`;
      else if (/\.mp4($|\?)/i.test(u.pathname)) mp4 = u.toString();
    }
  } catch {
    /* not a web address */
  }
  if (!embedUrl && !mp4) return null;

  return (
    <div className="space-y-3 mx-auto max-w-4xl">
      {data.title && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white">{data.title}</h2>
      )}
      <div className="relative aspect-video w-full overflow-hidden rounded-2xl border border-line bg-black shadow-md dark:border-slate-800">
        {mp4 ? (
          <video src={mp4} controls className="absolute inset-0 h-full w-full" />
        ) : (
          <iframe
            src={embedUrl!}
            title={data.title || "Video presentation"}
            className="absolute inset-0 h-full w-full border-0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            referrerPolicy="strict-origin-when-cross-origin"
            allowFullScreen
          />
        )}
      </div>
      {data.caption && (
        <p className="text-xs text-slate-500 dark:text-slate-400 text-center">{data.caption}</p>
      )}
    </div>
  );
}



// ---------------------------------------------------------------------------------------------------- helpers

/** Only http(s), mailto, tel and site-relative links: a stored "javascript:" address can never run. */
function safeHref(url?: string | null): string | undefined {
  if (!url) return undefined;
  const u = url.trim();
  if (/^(https?:|mailto:|tel:)/i.test(u) || (u.startsWith("/") && !u.startsWith("//")) || u.startsWith("#")) return u;
  return undefined;
}

/** Files uploaded in the admin panel live on the API host under /storage. */
function storageUrl(path?: string | null): string | undefined {
  if (!path) return undefined;
  return `${apiBaseUrl().replace(/\/api\/v1\/?$/, "")}/storage/${String(path).replace(/^\/+/, "")}`;
}

const THEME_PALETTES: Record<string, Record<string, string>> = {
  navy: { "50": "#eef2f9", "100": "#d9e2f1", "200": "#b3c4e3", "500": "#1a3a6b", "600": "#112a52", "700": "#0b1e3d" },
  emerald: { "50": "#ecfdf5", "100": "#d1fae5", "200": "#a7f3d0", "500": "#10b981", "600": "#059669", "700": "#047857" },
  violet: { "50": "#f5f3ff", "100": "#ede9fe", "200": "#ddd6fe", "500": "#8b5cf6", "600": "#7c3aed", "700": "#6d28d9" },
  amber: { "50": "#fffbeb", "100": "#fef3c7", "200": "#fde68a", "500": "#f59e0b", "600": "#d97706", "700": "#b45309" },
  rose: { "50": "#fff1f2", "100": "#ffe4e6", "200": "#fecdd3", "500": "#f43f5e", "600": "#e11d48", "700": "#be123c" },
};

/** The page's colour theme: it re-colours every "brand" accent inside the page. */
function themeVars(theme?: string | null): React.CSSProperties | undefined {
  const p = theme ? THEME_PALETTES[theme] : undefined;
  if (!p) return undefined;
  return Object.fromEntries(Object.entries(p).map(([k, v]) => [`--color-brand-${k}`, v])) as React.CSSProperties;
}

const card = "rounded-2xl border border-line bg-card p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6";
const titleCls = "text-lg font-bold text-navy-900 dark:text-white";

// ---------------------------------------------------------------------------------------------------- new blocks

function SectionHeadingBlock({ data }: { data: Record<string, any> }) {
  const size = data.size === "xl" ? "text-3xl sm:text-4xl" : data.size === "md" ? "text-lg sm:text-xl" : "text-2xl sm:text-3xl";
  return (
    <div className={data.align === "center" ? "text-center" : ""}>
      <h2 className={`font-extrabold tracking-tight text-navy-900 dark:text-white ${size}`}>{data.title}</h2>
      {data.subtitle && <p className="mt-2 text-sm text-slate-500 dark:text-slate-400 sm:text-base">{data.subtitle}</p>}
    </div>
  );
}

function TextColumnsBlock({ data }: { data: Record<string, any> }) {
  const items = Array.isArray(data.items) ? data.items : [];
  const cols = String(data.columns) === "3" ? "md:grid-cols-3" : "md:grid-cols-2";
  return (
    <div className={`grid grid-cols-1 gap-4 ${cols}`}>
      {items.map((it: any, i: number) => (
        <div key={i} className={card}>
          {it.title && <h3 className="mb-2 text-base font-bold text-navy-900 dark:text-white">{it.title}</h3>}
          <div className="prose prose-slate max-w-none text-sm dark:prose-invert" dangerouslySetInnerHTML={{ __html: cleanHtml(it.body) }} />
        </div>
      ))}
    </div>
  );
}

function ButtonRowBlock({ data }: { data: Record<string, any> }) {
  const buttons = Array.isArray(data.buttons) ? data.buttons : [];
  const justify = data.align === "center" ? "justify-center" : data.align === "right" ? "justify-end" : "justify-start";
  const styles: Record<string, string> = {
    primary: "bg-brand-600 text-white hover:bg-brand-700 shadow-sm",
    outline: "border border-brand-600 text-brand-700 hover:bg-brand-50 dark:text-brand-200 dark:hover:bg-slate-800",
    dark: "bg-navy-900 text-white hover:bg-navy-800 dark:bg-white dark:text-navy-900",
  };
  return (
    <div className={`flex flex-wrap gap-3 ${justify}`}>
      {buttons.map((b: any, i: number) => {
        const href = safeHref(b.url);
        if (!href || !b.label) return null;
        return (
          <a key={i} href={href} target={b.new_tab ? "_blank" : undefined} rel="noopener noreferrer"
            className={`inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold transition active:scale-95 ${styles[b.style] ?? styles.primary}`}>
            {b.label} <ArrowRight className="size-4" />
          </a>
        );
      })}
    </div>
  );
}

function ImageGalleryBlock({ data }: { data: Record<string, any> }) {
  const images = (Array.isArray(data.images) ? data.images : []).map((im: any) => ({ src: storageUrl(im.image_file) ?? safeHref(im.image_url), caption: im.caption })).filter((im: any) => im.src);
  const cols = String(data.columns) === "2" ? "sm:grid-cols-2" : String(data.columns) === "4" ? "sm:grid-cols-2 lg:grid-cols-4" : "sm:grid-cols-2 lg:grid-cols-3";
  if (!images.length) return null;
  return (
    <div className="space-y-3">
      {data.section_title && <h2 className={titleCls}>{data.section_title}</h2>}
      <div className={`grid grid-cols-1 gap-3 ${cols}`}>
        {images.map((im: any, i: number) => (
          <figure key={i} className="overflow-hidden rounded-2xl border border-line bg-card shadow-sm dark:border-slate-800">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={im.src} alt={im.caption || "Gallery photo"} loading="lazy" className="aspect-[4/3] w-full object-cover" />
            {im.caption && <figcaption className="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{im.caption}</figcaption>}
          </figure>
        ))}
      </div>
    </div>
  );
}

function TimelineBlock({ data }: { data: Record<string, any> }) {
  const steps = Array.isArray(data.steps) ? data.steps : [];
  return (
    <div className={card}>
      {data.section_title && <h2 className={`${titleCls} mb-4`}>{data.section_title}</h2>}
      <ol className="relative space-y-6 border-l-2 border-brand-100 pl-6 dark:border-slate-700">
        {steps.map((st: any, i: number) => (
          <li key={i} className="relative">
            <span className="absolute -left-[2.2rem] top-0.5 grid size-6 place-items-center rounded-full bg-brand-600 text-[11px] font-bold text-white ring-4 ring-white dark:ring-slate-900">{i + 1}</span>
            {st.label && <p className="text-xs font-bold uppercase tracking-wider text-brand-600">{st.label}</p>}
            <h3 className="text-sm font-bold text-navy-900 dark:text-white">{st.title}</h3>
            {st.description && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{st.description}</p>}
          </li>
        ))}
      </ol>
    </div>
  );
}

function DataTableBlock({ data }: { data: Record<string, any> }) {
  const cols: string[] = Array.isArray(data.columns) ? data.columns : [];
  const rows: any[] = Array.isArray(data.rows) ? data.rows : [];
  return (
    <div className="space-y-3">
      {data.section_title && <h2 className={titleCls}>{data.section_title}</h2>}
      <div className="overflow-x-auto rounded-2xl border border-line bg-card shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table className="w-full min-w-[32rem] text-left text-sm">
          <thead className="bg-brand-50 text-xs font-bold uppercase tracking-wider text-brand-700 dark:bg-slate-800 dark:text-brand-200">
            <tr>{cols.map((c, i) => <th key={i} className="px-4 py-3">{c}</th>)}</tr>
          </thead>
          <tbody className="divide-y divide-line dark:divide-slate-800">
            {rows.map((r, i) => (
              <tr key={i}>{cols.map((_, j) => <td key={j} className="px-4 py-3 text-slate-700 dark:text-slate-300">{Array.isArray(r.cells) ? r.cells[j] ?? "" : ""}</td>)}</tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function DownloadsBlock({ data }: { data: Record<string, any> }) {
  const files = Array.isArray(data.files) ? data.files : [];
  return (
    <div className={card}>
      {data.section_title && <h2 className={`${titleCls} mb-3`}>{data.section_title}</h2>}
      <ul className="divide-y divide-line dark:divide-slate-800">
        {files.map((f: any, i: number) => {
          const href = safeHref(f.file_url);
          if (!href) return null;
          return (
            <li key={i}>
              <a href={href} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 py-3 transition hover:text-brand-700">
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-slate-800"><Download className="size-4" /></span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold text-navy-900 dark:text-white">{f.label}</span>
                  {f.note && <span className="block text-xs text-slate-500">{f.note}</span>}
                </span>
                <ExternalLink className="size-4 shrink-0 text-slate-400" />
              </a>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

function BatchesShowcaseBlock({ data }: { data: Record<string, any> }) {
  const batches: any[] = Array.isArray(data.batches) ? data.batches : [];
  if (!batches.length) return null;
  return (
    <div className="space-y-3">
      {data.section_title && <h2 className={titleCls}>{data.section_title}</h2>}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {batches.map((b) => {
          const href = safeHref(b.purchase_url) ?? `/batches/${b.slug}`;
          const img = safeHref(b.image_url);
          return (
            <div key={b.id} className="flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-sm dark:border-slate-800 dark:bg-slate-900">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {img && <img src={img} alt="" loading="lazy" className="aspect-[16/9] w-full object-cover" />}
              <div className="flex flex-1 flex-col p-4">
                <h3 className="text-sm font-bold text-navy-900 dark:text-white">{b.title}</h3>
                {b.validity_text && <p className="mt-1 text-xs text-slate-500">{b.validity_text}</p>}
                {Array.isArray(b.highlights) && (
                  <ul className="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                    {b.highlights.slice(0, 4).map((h: string, i: number) => <li key={i} className="flex gap-1.5"><Check className="mt-0.5 size-3.5 shrink-0 text-brand-600" />{h}</li>)}
                  </ul>
                )}
                <div className="mt-auto flex items-end justify-between gap-2 pt-4">
                  <div>
                    <span className="text-lg font-extrabold text-navy-900 dark:text-white">₹{Number(b.price).toLocaleString("en-IN")}</span>
                    {b.mrp_price && Number(b.mrp_price) > Number(b.price) && <span className="ml-2 text-xs text-slate-400 line-through">₹{Number(b.mrp_price).toLocaleString("en-IN")}</span>}
                  </div>
                  <a href={href} className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-brand-700">
                    <ShoppingBag className="size-3.5" /> Enroll
                  </a>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function CountdownBlock({ data }: { data: Record<string, any> }) {
  const target = data.target ? new Date(data.target).getTime() : NaN;
  const [now, setNow] = useState<number | null>(null);
  useEffect(() => {
    setNow(Date.now());
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);
  if (Number.isNaN(target) || now === null) return null;
  const left = Math.max(0, target - now);
  const parts = [
    ["Days", Math.floor(left / 86400000)],
    ["Hours", Math.floor(left / 3600000) % 24],
    ["Minutes", Math.floor(left / 60000) % 60],
    ["Seconds", Math.floor(left / 1000) % 60],
  ] as const;
  return (
    <div className="rounded-3xl bg-gradient-to-r from-navy-900 to-brand-700 p-6 text-center text-white shadow-lg sm:p-8">
      <p className="mb-1 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest text-white/70"><Clock className="size-4" /> {data.title}</p>
      {left === 0 ? (
        <p className="mt-2 text-xl font-bold">{data.after_text || "This event has started."}</p>
      ) : (
        <div className="mt-3 grid grid-cols-4 gap-2 sm:gap-4">
          {parts.map(([label, n]) => (
            <div key={label} className="rounded-xl bg-white/10 py-3">
              <div className="text-2xl font-extrabold tabular-nums sm:text-4xl">{String(n).padStart(2, "0")}</div>
              <div className="text-[10px] font-semibold uppercase tracking-wider text-white/70">{label}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function ChecklistBlock({ data }: { data: Record<string, any> }) {
  const items: string[] = Array.isArray(data.items) ? data.items : [];
  const style = data.style || "check";
  return (
    <div className={card}>
      {data.section_title && <h2 className={`${titleCls} mb-3`}>{data.section_title}</h2>}
      <ul className="space-y-2.5">
        {items.map((it, i) => (
          <li key={i} className="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
            {style === "check" ? (
              <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-brand-600 text-white"><Check className="size-3" /></span>
            ) : style === "numbered" ? (
              <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{i + 1}</span>
            ) : (
              <span className="mt-2 size-1.5 shrink-0 rounded-full bg-brand-600" />
            )}
            <span>{it}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function SpacerBlock({ data }: { data: Record<string, any> }) {
  const h = data.height === "lg" ? "h-12" : data.height === "sm" ? "h-2" : "h-6";
  return <div className={`flex items-center ${h}`}>{data.line !== false && <hr className="w-full border-line dark:border-slate-800" />}</div>;
}

function CustomHtmlBlock({ data }: { data: Record<string, any> }) {
  return <div className="prose prose-slate max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: cleanHtml(data.body) }} />;
}
