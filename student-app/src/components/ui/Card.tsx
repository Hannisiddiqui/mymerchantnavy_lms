import { cn } from "@/lib/utils";

export function Card({ className, ...rest }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("rounded-2xl border border-line bg-white dark:border-slate-800 dark:bg-slate-900", className)} {...rest} />;
}

export function CardHeader({ title, action, className }: { title: React.ReactNode; action?: React.ReactNode; className?: string }) {
  return (
    <div className={cn("flex items-center justify-between gap-3 border-b border-line px-5 py-4 dark:border-slate-800", className)}>
      <h2 className="text-base font-bold text-navy-900 dark:text-white">{title}</h2>
      {action}
    </div>
  );
}
