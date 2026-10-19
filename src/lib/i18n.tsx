import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

export type Lang = "de" | "en";

const dict = {
  nav_about: ["Über uns", "About"],
  nav_kitchen: ["Volksküche", "Community Kitchen"],
  nav_impact: ["Wirkung", "Impact"],
  nav_events: ["Termine", "Events"],
  nav_instagram: ["Instagram", "Instagram"],
  donate_now: ["Jetzt spenden", "Donate now"],
  get_involved: ["Mitmachen", "Get involved"],
  team_login: ["Team-Login", "Team login"],
  hero_kicker: ["Gratis essen. Gemeinsam kochen.", "Free food. Cooked together."],
  hero_title_1: ["Essen für", "Food for"],
  hero_title_2: ["alle.", "everyone."],
  hero_sub: [
    "Mensa Comunista Graz kocht jede Woche frische Mahlzeiten und teilt sie kostenlos aus – organisiert von Freiwilligen, getragen von Solidarität.",
    "Mensa Comunista Graz cooks fresh meals every week and hands them out for free – organized by volunteers, powered by solidarity.",
  ],
  rotating: [["Solidarisch", "Kostenlos", "Selbstorganisiert", "Für alle"], ["Solidarity", "Free", "Self-organized", "For everyone"]],
  kitchen_title: ["So läuft die Mensa", "How the Mensa works"],
  kitchen_sub: [
    "Wir kaufen gemeinsam ein, kochen im Volkshaus Graz und geben das Essen an der Uni am Sonnenfelsplatz aus. Jede:r kann mitmachen – ohne Vorkenntnisse.",
    "We shop together, cook at Volkshaus Graz and serve the food at the university on Sonnenfelsplatz. Anyone can join – no experience needed.",
  ],
  step_shop: ["Einkaufen", "Shopping"],
  step_shop_d: ["Am Vortag besorgen wir Zutaten.", "The day before, we buy ingredients."],
  step_cook: ["Kochen", "Cooking"],
  step_cook_d: ["Ab 8 Uhr im Volkshaus, Lagergasse 98a.", "From 8 am at Volkshaus, Lagergasse 98a."],
  step_serve: ["Ausgeben", "Serving"],
  step_serve_d: ["Mittags an der Uni am Sonnenfelsplatz.", "At lunchtime at Uni Sonnenfelsplatz."],
  step_clean: ["Abwaschen", "Cleaning up"],
  step_clean_d: ["Danach wird gemeinsam aufgeräumt.", "Afterwards we clean up together."],
  events_title: ["Nächste Termine", "Upcoming dates"],
  events_empty: ["Gerade keine veröffentlichten Termine.", "No published dates right now."],
  meeting_point: ["Treffpunkt", "Meeting point"],
  volunteers: ["Freiwillige", "Volunteers"],
  spots_open: ["Plätze frei", "spots open"],
  full: ["Voll besetzt", "Fully staffed"],
  stories_title: ["Geschichten aus der Mensa", "Stories from the Mensa"],
  stories_sub: ["Tippe auf einen Ast, um die Geschichte zu öffnen.", "Tap a branch to open its story."],
  impact_title: ["Unsere Wirkung", "Our impact"],
  impact_sample: ["Beispielwerte – werden vom Team gepflegt", "Sample values – maintained by the team"],
  impact_shifts: ["Geplante Schichten", "Planned shifts"],
  impact_people: ["Eingetragene Einsätze", "Volunteer sign-ups"],
  insta_title: ["Auf Instagram", "On Instagram"],
  insta_fallback: [
    "Live-Einbettung noch nicht eingerichtet. Folge uns direkt auf Instagram für aktuelle Posts.",
    "Live embedding not configured yet. Follow us directly on Instagram for the latest posts.",
  ],
  insta_follow: ["Auf Instagram folgen", "Follow on Instagram"],
  transparency_title: ["Spenden-Transparenz", "Donation transparency"],
  transparency_sub: [
    "Nur bestätigte Eingänge und erfasste Ausgaben – ohne persönliche Daten.",
    "Only confirmed receipts and recorded expenses – no personal data.",
  ],
  received: ["Bestätigt erhalten", "Confirmed received"],
  spent: ["Ausgegeben", "Spent"],
  available: ["Verfügbar", "Available"],
  no_expenses: ["Noch keine Ausgaben erfasst.", "No expenses recorded yet."],
  involved_title: ["Mach mit!", "Join us!"],
  involved_sub: [
    "Komm einfach vorbei oder schreib uns. Wir suchen Menschen für:",
    "Just drop by or write to us. We're looking for people for:",
  ],
  roles: [
    ["Kochen", "Ausgabe", "Abwasch", "Einkaufen", "Fotografie", "Video & Schnitt", "Events"],
    ["Cooking", "Serving", "Dishwashing", "Shopping", "Photography", "Video & editing", "Events"],
  ],
  footer_tag: ["Solidarität geht durch den Magen.", "Solidarity goes through the stomach."],
  privacy: ["Datenschutz", "Privacy"],
  imprint: ["Impressum", "Imprint"],
  contact: ["Kontakt", "Contact"],
  not_configured: ["Noch nicht hinterlegt", "Not configured yet"],
} as const;

type Key = keyof typeof dict;

const Ctx = createContext<{ lang: Lang; setLang: (l: Lang) => void }>({ lang: "de", setLang: () => {} });

export function LangProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>("de");
  useEffect(() => {
    const s = localStorage.getItem("mcg-lang");
    if (s === "en" || s === "de") setLangState(s);
  }, []);
  useEffect(() => {
    document.documentElement.lang = lang;
  }, [lang]);
  const setLang = (l: Lang) => {
    localStorage.setItem("mcg-lang", l);
    setLangState(l);
  };
  return <Ctx.Provider value={{ lang, setLang }}>{children}</Ctx.Provider>;
}

export function useLang() {
  const { lang, setLang } = useContext(Ctx);
  const i = lang === "de" ? 0 : 1;
  function t<K extends Key>(k: K): (typeof dict)[K][0] {
    return dict[k][i] as (typeof dict)[K][0];
  }
  return { lang, setLang, t, pick: (de: string, en: string) => (lang === "de" ? de : en) };
}
