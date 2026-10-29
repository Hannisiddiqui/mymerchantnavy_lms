"use client";

import { use, useState } from "react";
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
} from "lucide-react";
import * as api from "@/lib/api";
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

  return (
    <div className="space-y-8">
      <PageHeader
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
      />

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
            dangerouslySetInnerHTML={{ __html: page.content }}
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
        dangerouslySetInnerHTML={{ __html: data.body || "" }}
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
  const src = data.image_file ? `https://api.mymerchantnavy.com/storage/${data.image_file}` : data.image_url;
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
            dangerouslySetInnerHTML={{ __html: data.body }}
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

  // Convert YouTube watch URL to embed URL if needed
  let embedUrl = data.video_url;
  if (embedUrl.includes("youtube.com/watch?v=")) {
    embedUrl = embedUrl.replace("watch?v=", "embed/");
  } else if (embedUrl.includes("youtu.be/")) {
    embedUrl = embedUrl.replace("youtu.be/", "www.youtube.com/embed/");
  }

  return (
    <div className="space-y-3 mx-auto max-w-4xl">
      {data.title && (
        <h2 className="text-lg font-bold text-navy-900 dark:text-white">{data.title}</h2>
      )}
      <div className="relative aspect-video w-full overflow-hidden rounded-2xl border border-line bg-black shadow-md dark:border-slate-800">
        <iframe
          src={embedUrl}
          title={data.title || "Video presentation"}
          className="absolute inset-0 h-full w-full border-0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
        />
      </div>
      {data.caption && (
        <p className="text-xs text-slate-500 dark:text-slate-400 text-center">{data.caption}</p>
      )}
    </div>
  );
}

