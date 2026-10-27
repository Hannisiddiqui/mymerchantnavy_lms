import { BookOpen, Flag, Megaphone, Radio, Trophy } from "lucide-react";
import type { NotificationType } from "@/types";
import { cn } from "@/lib/utils";

const styles: Record<NotificationType, { icon: React.ElementType; className: string }> = {
  course_assigned: { icon: BookOpen, className: "bg-brand-50 text-brand-600" },
  live_class: { icon: Radio, className: "bg-red-50 text-red-600" },
  result: { icon: Trophy, className: "bg-amber-50 text-amber-600" },
  announcement: { icon: Megaphone, className: "bg-violet-50 text-violet-600" },
  report: { icon: Flag, className: "bg-green-50 text-green-600" },
};

export function NotificationIcon({ type, className }: { type: NotificationType; className?: string }) {
  const { icon: Icon, className: tone } = styles[type];
  return (
    <span className={cn("grid size-9 shrink-0 place-items-center rounded-xl", tone, className)}>
      <Icon className="size-4.5" />
    </span>
  );
}
