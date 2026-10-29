"use client";

import { useEffect, useState } from "react";
import { Laptop, Moon, Sun } from "lucide-react";
import { useTheme, type Theme } from "./ThemeProvider";

export function ThemeToggle({ className = "" }: { className?: string }) {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) {
    return (
      <div className={`flex items-center rounded-xl bg-slate-100 p-1 dark:bg-slate-800 ${className}`}>
        <span className="grid size-7 place-items-center rounded-lg text-slate-400">
          <Sun className="size-3.5" />
        </span>
      </div>
    );
  }

  const options: { value: Theme; label: string; icon: typeof Sun }[] = [
    { value: "light", label: "Light", icon: Sun },
    { value: "dark", label: "Dark", icon: Moon },
    { value: "system", label: "Desktop", icon: Laptop },
  ];

  return (
    <div
      className={`inline-flex items-center rounded-xl border border-line bg-slate-100/80 p-0.5 shadow-2xs dark:border-slate-800 dark:bg-slate-800/80 ${className}`}
      role="radiogroup"
      aria-label="Theme mode"
    >
      {options.map(({ value, label, icon: Icon }) => {
        const active = theme === value;
        return (
          <button
            key={value}
            type="button"
            onClick={() => setTheme(value)}
            title={`${label} Mode${value === "system" ? " (Matches your device desktop theme)" : ""}`}
            className={`flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold transition-all ${
              active
                ? "bg-white text-navy-900 shadow-xs dark:bg-slate-700 dark:text-white"
                : "text-slate-500 hover:text-navy-900 dark:text-slate-400 dark:hover:text-white"
            }`}
            aria-checked={active}
            role="radio"
          >
            <Icon className="size-3.5" />
            <span className="hidden sm:inline-block">{label}</span>
          </button>
        );
      })}
    </div>
  );
}
