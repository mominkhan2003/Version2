import { useEffect, useState } from "react";
import { Link } from "@tanstack/react-router";
import { Instagram, Menu, X } from "lucide-react";
import { useLang } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Logo, Star } from "@/components/brand";
import { DonateDialog, useSettings } from "./DonateDialog";

export function LangSwitch({ className = "" }: { className?: string }) {
  const { lang, setLang } = useLang();
  return (
    <div className={`inline-flex rounded-full border-2 border-ink bg-card p-0.5 text-xs font-bold ${className}`} role="group" aria-label="Sprache / Language">
      {(["de", "en"] as const).map((l) => (
        <button key={l} onClick={() => setLang(l)} aria-pressed={lang === l}
          className={`min-h-8 min-w-10 rounded-full px-2 uppercase transition ${lang === l ? "bg-ink text-ink-foreground" : ""}`}>
          {l}
        </button>
      ))}
    </div>
  );
}

export function SiteHeader() {
  const { t } = useLang();
  const { data: s } = useSettings();
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 20);
    on();
    window.addEventListener("scroll", on, { passive: true });
    return () => window.removeEventListener("scroll", on);
  }, []);
  const links = [
    ["#about", t("nav_about")], ["#kitchen", t("nav_kitchen")], ["#impact", t("nav_impact")],
    ["#events", t("nav_events")], ["#instagram", t("nav_instagram")],
  ] as const;
  const insta = `https://instagram.com/${s?.instagram_handle ?? "mensacomunistagraz"}`;
  return (
    <header className={`fixed inset-x-0 top-0 z-50 transition-all ${scrolled ? "bg-background/90 shadow-soft backdrop-blur" : ""}`}>
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 md:h-20 md:px-8">
        <Link to="/" className="text-lg md:text-xl" aria-label="Start"><Logo /></Link>
        <nav className="hidden items-center gap-6 text-sm font-semibold lg:flex">
          {links.map(([h, l]) => (
            <a key={h} href={h} className="relative after:absolute after:-bottom-1 after:left-0 after:h-0.5 after:w-0 after:bg-primary after:transition-all hover:after:w-full">{l}</a>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          <LangSwitch className="hidden sm:inline-flex" />
          <DonateDialog><Button variant="poster" size="sm" className="h-10 px-4">{t("donate_now")}</Button></DonateDialog>
          <button className="inline-flex h-11 w-11 items-center justify-center rounded-full border-2 border-ink bg-card lg:hidden" onClick={() => setOpen(!open)} aria-label="Menu" aria-expanded={open}>
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>
      {open && (
        <div className="border-t-2 border-ink bg-ink px-6 pb-8 pt-4 text-ink-foreground lg:hidden">
          <nav className="flex flex-col">
            {links.map(([h, l]) => (
              <a key={h} href={h} onClick={() => setOpen(false)} className="border-b border-sidebar-border py-4 font-display text-2xl uppercase">{l}</a>
            ))}
            <a href={insta} target="_blank" rel="noreferrer" className="flex items-center gap-2 py-4"><Instagram className="h-5 w-5" /> @{s?.instagram_handle ?? "mensacomunistagraz"}</a>
            <a href="/auth" className="py-2 text-sm underline">{t("team_login")}</a>
          </nav>
          <LangSwitch className="mt-4 text-foreground" />
        </div>
      )}
    </header>
  );
}

export function SiteFooter() {
  const { t } = useLang();
  const { data: s } = useSettings();
  const handle = s?.instagram_handle ?? "mensacomunistagraz";
  return (
    <footer className="grain overflow-hidden bg-ink text-ink-foreground">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-16 md:grid-cols-3 md:px-8">
        <div>
          <div className="text-3xl"><Logo invert /></div>
          <p className="mt-4 font-hand text-2xl text-lavender">{t("footer_tag")}</p>
        </div>
        <div className="space-y-2 text-sm">
          <h3 className="mb-3 text-lg uppercase text-primary">{t("contact")}</h3>
          <p>{s?.contact_email ? <a className="underline" href={`mailto:${s.contact_email}`}>{s.contact_email}</a> : <span className="opacity-60">E-Mail: {t("not_configured")}</span>}</p>
          <p>{s?.contact_address ?? <span className="opacity-60">{t("not_configured")}</span>}</p>
          <a href={`https://instagram.com/${handle}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 underline"><Instagram className="h-4 w-4" /> @{handle}</a>
        </div>
        <div className="flex flex-col items-start gap-3 text-sm">
          <DonateDialog><Button variant="poster">{t("donate_now")}</Button></DonateDialog>
          <a href="/privacy" className="underline">{t("privacy")}</a>
          <a href="/imprint" className="underline">{t("imprint")}</a>
          <a href="/auth" className="underline">{t("team_login")}</a>
        </div>
      </div>
      <div className="flex items-center justify-center gap-2 border-t border-sidebar-border py-4 text-xs opacity-70">
        <Star className="h-3 w-3 text-primary" /> © {new Date().getFullYear()} Mensa Comunista Graz
      </div>
    </footer>
  );
}
