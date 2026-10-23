import { CircleCheck } from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { brand } from "@/lib/config";

/** Split screen for login, OTP and first-time profile setup. */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
      <aside className="relative hidden overflow-hidden bg-gradient-to-br from-navy-950 via-navy-900 to-navy-700 p-12 text-white lg:flex lg:flex-col">
        <div aria-hidden className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-brand-600/30 blur-3xl" />
        <div aria-hidden className="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-sky-400/10 blur-3xl" />
        <Logo tone="light" />
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/brand/hero.jpg" alt="A merchant navy officer looking out at a cargo ship: a global career awaits you" className="relative mx-auto my-8 max-h-[40vh] w-full max-w-lg self-center rounded-3xl object-cover shadow-2xl ring-1 ring-white/15" />
        <div className="relative mt-auto max-w-md">
          <p className="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-brand-200">Student Dashboard</p>
          <h1 className="text-3xl leading-tight sm:text-4xl">{brand.tagline}</h1>
          <p className="mt-4 text-base text-slate-300">Your lectures, notes, practice tests and mock exams — all in one place.</p>
          <ul className="mt-8 space-y-3 text-sm text-slate-200">
            {["Chapterwise Basic Material", "Multiple Practice Tests for Each Chapter", "Practice Ladder for Each Chapter"].map((t) => (
              <li key={t} className="flex items-center gap-2.5">
                <CircleCheck className="size-5 text-brand-200" /> {t}
              </li>
            ))}
          </ul>
        </div>
        <p className="relative mt-12 text-xs text-slate-400">© {new Date().getFullYear()} {brand.name}</p>
      </aside>

      <main className="flex flex-col bg-white px-5 py-8 sm:px-10">
        <div className="lg:hidden">
          <Logo />
        </div>
        <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">{children}</div>
      </main>
    </div>
  );
}
