"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { Sidebar } from "@/components/layout/Sidebar";
import { Topbar } from "@/components/layout/Topbar";
import { SupportChatbot } from "@/components/support/SupportChatbot";
import { useRequireAuth } from "@/lib/use-require-auth";
import { cn } from "@/lib/utils";

/** Shell for every logged-in page: sidebar + top bar + page content. */
export default function StudentLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const signedIn = useRequireAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  // Lesson pages (/learn/...) give the content the full width: the sidebar collapses automatically.
  // The student can still expand it with the button.
  const inLesson = pathname.startsWith("/learn");
  useEffect(() => {
    setCollapsed(inLesson);
  }, [inLesson]);

  if (!signedIn) return null;

  return (
    <div className="min-h-dvh">
      <Sidebar open={menuOpen} onClose={() => setMenuOpen(false)} collapsed={collapsed} onToggleCollapsed={() => setCollapsed((c) => !c)} />
      <div className={cn("transition-all duration-200", collapsed ? "lg:pl-[4.5rem]" : "lg:pl-64")}>
        <Topbar onMenu={() => setMenuOpen(true)} />
        <main className={cn("mx-auto px-4 py-6 sm:px-6 lg:py-8", inLesson ? "max-w-none" : "max-w-6xl")}>{children}</main>
      </div>
      <SupportChatbot />
    </div>
  );
}
