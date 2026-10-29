/**
 * App-wide settings. Brand text, sidebar menu and profile options live here
 * so they can be changed without touching any page.
 */
import { BookOpen, ChartNoAxesCombined, ClipboardList, Layers, NotebookPen, Trophy, UserRound, type LucideIcon } from "lucide-react";

export const brand = {
  name: "My Merchant Navy",
  shortName: "MMN",
  tagline: "From Aspirant to Officer.",
  /** The app mark shown beside the name (also the browser-tab icon: src/app/icon.png, favicon.ico). */
  logoSrc: "/brand/logo-mark.png",
  website: "https://mymerchantnavy.com",
  supportEmail: "support@mymerchantnavy.com",
};

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Other URL prefixes that should also highlight this item. */
  alsoActiveOn?: string[];
}

/** Student sidebar. Order here = order on screen. */
export const sidebarNav: NavItem[] = [
  { label: "My Courses", href: "/my-courses", icon: BookOpen, alsoActiveOn: ["/learn"] },
  { label: "Batches", href: "/batches", icon: Layers },
  { label: "Mock Tests", href: "/mock-tests", icon: ClipboardList },
  { label: "Performance", href: "/performance", icon: ChartNoAxesCombined },
  { label: "Results", href: "/results", icon: Trophy },
  { label: "My Notes", href: "/notes", icon: NotebookPen },
  { label: "My Profile", href: "/profile", icon: UserRound },
];

/** Options shown in the profile form. */
export const targetExams = [
  "IMU-CET",
  "Company Sponsorship Exam",
  "DNS (Diploma in Nautical Science)",
  "B.Tech Marine Engineering",
  "GP Rating",
  "ETO (Electro-Technical Officer)",
  "Other",
];

export function attemptYears(from = new Date().getFullYear()): number[] {
  return [from, from + 1, from + 2, from + 3];
}

/**
 * How students log in: "email" (default: a one-time code is emailed) or "phone" (the SMS login, kept but switched off).
 * Set NEXT_PUBLIC_LOGIN_METHOD; it must match LMS_LOGIN_METHOD on the server.
 */
export const LOGIN_METHOD: "email" | "phone" = process.env.NEXT_PUBLIC_LOGIN_METHOD === "phone" ? "phone" : "email";

/** Seconds before the "Resend code" link becomes active. */
export const OTP_RESEND_SECONDS = 30;
