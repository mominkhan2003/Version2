import { queryOptions } from "@tanstack/react-query";
import { getPublicData, getTeamData } from "./data.functions";
import type { Lang } from "./i18n";

export function storedLang(): Lang {
  try {
    const s = localStorage.getItem("mcg-lang");
    if (s === "en" || s === "de") return s;
  } catch { /* SSR/no storage */ }
  return "de";
}

export const publicQuery = (lang: Lang = "de") =>
  queryOptions({ queryKey: ["public", lang], queryFn: () => getPublicData(lang), staleTime: 30_000 });

export const teamQuery = queryOptions({ queryKey: ["team"], queryFn: () => getTeamData(), staleTime: 10_000 });
