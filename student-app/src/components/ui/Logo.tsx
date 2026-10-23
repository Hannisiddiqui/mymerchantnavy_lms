import { brand } from "@/lib/config";
import { cn } from "@/lib/utils";

/** The app mark (public/brand/logo-mark.png) beside the name. `tone="light"` is for dark backgrounds (sidebar, login panel). */
export function Logo({ tone = "dark", className }: { tone?: "dark" | "light"; className?: string }) {
  return (
    <span className={cn("flex items-center gap-2.5", className)}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={brand.logoSrc} alt="" width={36} height={36} className={cn("size-9 rounded-xl", tone === "light" && "ring-1 ring-white/20")} />
      <span className={cn("font-display text-[15px] font-extrabold leading-tight", tone === "light" ? "text-white" : "text-navy-900")}>
        My Merchant
        <br />
        <span className={tone === "light" ? "text-brand-200" : "text-brand-600"}>Navy</span>
      </span>
    </span>
  );
}
