"use client";

import { useState } from "react";
import Link from "next/link";
import { ArrowRight, Calendar, CheckCircle, Clock, Layers, Tag } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import type { BatchItem } from "@/types";
import { Badge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

export default function BatchesPage() {
  const [selectedFilter, setSelectedFilter] = useState("All Batches");
  const { data: config } = useApi(() => api.batches.getConfig(), []);
  const { data: batches, loading, error, reload } = useApi(
    () => api.batches.list(selectedFilter === "All Batches" ? undefined : selectedFilter),
    [selectedFilter]
  );

  const showCategories = config?.show_categories ?? true;
  const categoriesList = config?.categories && config.categories.length > 0
    ? config.categories
    : ["All Batches", "IMU-CET", "DNS Sponsorship", "G.P. Rating", "B.Tech Marine"];

  if (loading) return <PageSkeleton />;
  if (error || !batches) return <ErrorState message={error ?? "Could not load batches"} onRetry={reload} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Batches & Programs"
        subtitle="Sellable exam batches and test series for IMU-CET, DNS Sponsorship & Merchant Navy Aspirants."
        crumbs={[
          { label: "Dashboard", href: "/my-courses" },
          { label: "Batches" },
        ]}
        action={
          <div className="flex items-center gap-2">
            <span className="hidden text-xs font-semibold text-slate-500 sm:inline-block">
              {batches.length} batch{batches.length === 1 ? "" : "es"} available
            </span>
          </div>
        }
      />

      {/* Filter Tabs / Categories (Toggled from Admin Panel) */}
      {showCategories && (
        <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
          {categoriesList.map((cat) => {
            const active = selectedFilter === cat;
            return (
              <button
                key={cat}
                type="button"
                onClick={() => setSelectedFilter(cat)}
                className={`shrink-0 rounded-xl px-4 py-2 text-xs font-bold transition-all ${
                  active
                    ? "bg-navy-900 text-white shadow-sm dark:bg-brand-600 dark:text-white ring-2 ring-navy-900/10 dark:ring-brand-500/30"
                    : "bg-white text-slate-700 hover:bg-slate-100 hover:text-navy-900 border border-line dark:bg-slate-900 dark:text-slate-300 dark:border-slate-800 dark:hover:bg-slate-800"
                }`}
              >
                {cat}
              </button>
            );
          })}
        </div>
      )}

      {/* Batches Grid */}
      {batches.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 p-12 text-center bg-white dark:bg-slate-900">
          <Layers className="size-12 text-slate-400 mx-auto mb-3" />
          <h3 className="text-base font-bold text-navy-900 dark:text-white">No batches found for {selectedFilter}</h3>
          <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
            Try choosing a different exam filter or view all available batches above.
          </p>
          <button
            type="button"
            onClick={() => setSelectedFilter("All Batches")}
            className="mt-4 inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400"
          >
            Show All Batches →
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {batches.map((batch) => (
            <BatchCard key={batch.id} batch={batch} />
          ))}
        </div>
      )}
    </div>
  );
}

/** PhysicsWallah-Style Individual Batch Card */
function BatchCard({ batch }: { batch: BatchItem }) {
  const discount = batch.discountPercent || (batch.mrpPrice && batch.mrpPrice > batch.price
    ? Math.round(((batch.mrpPrice - batch.price) / batch.mrpPrice) * 100)
    : 0);

  return (
    <div className="group flex flex-col rounded-2xl border border-line bg-white shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
      {/* 16:9 Thumbnail Image Banner */}
      <div className="relative aspect-video w-full overflow-hidden bg-slate-900">
        {batch.imageUrl ? (
          <img
            src={batch.imageUrl}
            alt={batch.title}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-navy-900 via-slate-800 to-brand-900 p-4 text-center">
            <span className="text-sm font-bold text-white tracking-wide">{batch.title}</span>
          </div>
        )}

        {/* Gradient dark scrim for readable badges */}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/30 pointer-events-none" />

        {/* Target Exam Tag */}
        {batch.targetExam && (
          <div className="absolute top-3 left-3">
            <span className="rounded-lg bg-black/70 backdrop-blur-md px-2.5 py-1 text-[11px] font-bold text-amber-300 border border-amber-400/30 shadow-xs">
              {batch.targetExam}
            </span>
          </div>
        )}

        {/* Batch Code Badge */}
        {batch.batchCode && (
          <div className="absolute top-3 right-3">
            <span className="rounded-lg bg-navy-950/80 backdrop-blur-md px-2.5 py-1 text-[10px] font-semibold text-slate-200 border border-white/20">
              {batch.batchCode}
            </span>
          </div>
        )}

        {/* Bottom Banner Title Overlay */}
        <div className="absolute bottom-2.5 left-3 right-3 flex items-center justify-between text-white text-[11px] font-medium drop-shadow-md">
          <span className="flex items-center gap-1 text-slate-200">
            <Calendar className="size-3.5 text-brand-400" />
            {batch.startsAt ? `Starts: ${new Date(batch.startsAt).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}` : "Available Now"}
          </span>
          <span className="flex items-center gap-1 text-amber-300 font-semibold">
            <Clock className="size-3.5" />
            {batch.validityText}
          </span>
        </div>
      </div>

      {/* Card Content Body */}
      <div className="flex flex-1 flex-col p-5">
        <h3 className="text-base font-bold text-navy-900 dark:text-white line-clamp-2 leading-snug group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors min-h-[2.75rem]">
          {batch.title}
        </h3>

        {batch.description && (
          <p className="mt-1.5 text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
            {batch.description}
          </p>
        )}

        {/* Highlights List (PW Style bullets) */}
        {batch.highlights && batch.highlights.length > 0 && (
          <ul className="mt-4 space-y-1.5 border-t border-slate-100 dark:border-slate-800/80 pt-3 text-xs text-slate-600 dark:text-slate-300">
            {batch.highlights.slice(0, 3).map((item, idx) => (
              <li key={idx} className="flex items-start gap-2">
                <CheckCircle className="size-3.5 shrink-0 text-emerald-500 mt-0.5" />
                <span className="line-clamp-1">{item}</span>
              </li>
            ))}
          </ul>
        )}

        {/* Included Bundle Summary */}
        <div className="mt-4 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
          {batch.courses && batch.courses.length > 0 && (
            <span className="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
              📚 {batch.courses.length} Course{batch.courses.length === 1 ? "" : "s"}
            </span>
          )}
          {batch.tests && batch.tests.length > 0 && (
            <span className="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
              📝 {batch.tests.length} Mock Test{batch.tests.length === 1 ? "" : "s"}
            </span>
          )}
        </div>

        {/* Price & Action Row */}
        <div className="mt-auto pt-5 border-t border-line dark:border-slate-800 flex items-center justify-between gap-3">
          <div className="min-w-0">
            <div className="flex items-baseline gap-2">
              <span className="text-xl font-extrabold text-navy-900 dark:text-white">
                ₹{Number(batch.price).toLocaleString("en-IN")}
              </span>
              {batch.mrpPrice && batch.mrpPrice > batch.price && (
                <span className="text-xs text-slate-400 line-through">
                  ₹{Number(batch.mrpPrice).toLocaleString("en-IN")}
                </span>
              )}
            </div>
            {discount > 0 && (
              <span className="inline-block mt-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                {discount}% OFF
              </span>
            )}
          </div>

          <div className="shrink-0">
            {batch.isEnrolled ? (
              <Link
                href="/my-courses"
                className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-xs font-bold shadow-xs transition whitespace-nowrap active:scale-95"
              >
                <span>Enrolled</span>
                <ArrowRight className="size-3.5" />
              </Link>
            ) : (
              <a
                href={batch.purchaseUrl || "https://mymerchantnavy.com/pass/"}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 text-xs font-bold shadow-xs transition whitespace-nowrap active:scale-95"
              >
                <span>Buy Now</span>
                <ArrowRight className="size-3.5" />
              </a>
            )}
          </div>
        </div>
      </div>

      {/* PhysicsWallah Hallmark: Offer Footer Tag */}
      {batch.footerTag && (
        <div className="border-t border-amber-200 bg-amber-50/80 px-4 py-2 text-[11px] font-bold text-amber-800 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-300 flex items-center justify-between">
          <span className="flex items-center gap-1.5 truncate">
            <Tag className="size-3 text-amber-600 dark:text-amber-400 shrink-0" />
            <span className="truncate">{batch.footerTag}</span>
          </span>
          <span className="text-[10px] uppercase font-extrabold tracking-wider text-amber-700 dark:text-amber-400 shrink-0 ml-2">
            Special Offer
          </span>
        </div>
      )}
    </div>
  );
}
