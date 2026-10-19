/**
 * Data loaders — now thin wrappers over the PHP API client.
 * Same function names as the old Supabase server functions so route loaders stay untouched.
 */
import { apiGet, apiPost } from "./api";
import type { PublicData, TeamData } from "./types";

export async function getPublicData(lang: "de" | "en" = "de"): Promise<PublicData> {
  return apiGet<PublicData>(`/api/public_data.php?lang=${lang}`);
}

export async function getTeamData(): Promise<TeamData> {
  return apiGet<TeamData>("/api/team_data.php");
}
