import { cn } from "@/lib/utils";

export function Star({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={cn("fill-current", className)} aria-hidden>
      <path d="M12 1.5l3.1 6.9 7.4.7-5.6 5 1.7 7.4L12 17.6l-6.6 3.9 1.7-7.4-5.6-5 7.4-.7z" />
    </svg>
  );
}

export function Sandwich({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 120 60" className={cn("stroke-current fill-none", className)} strokeWidth="3" strokeLinecap="round" aria-hidden>
      <path d="M8 30c0-14 22-24 52-24s52 10 52 24" />
      <path d="M8 30h104" />
      <path d="M10 36c8 4 12-4 20 0s12-4 20 0 12-4 20 0 12-4 20 0 12-4 20 0" />
      <path d="M12 44c0 6 20 10 48 10s48-4 48-10" />
      <path d="M30 16l6 4M52 12l6 4M74 13l6 4M92 18l5 4" />
    </svg>
  );
}

/** Temporary text brand mark – replace with the official logo file when available. */
export function Logo({ className, invert }: { className?: string; invert?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-1 leading-none", className)} aria-label="Mensa Comunista Graz">
      <Star className="h-4 w-4 text-primary -mt-3" />
      <span className="flex flex-col font-display text-[0.95em] uppercase tracking-tight">
        <span className={invert ? "text-ink-foreground" : "text-foreground"}>Mensa</span>
        <span className="text-primary -mt-0.5">Comunista</span>
      </span>
      <Star className="h-3 w-3 text-primary mt-3" />
    </span>
  );
}
