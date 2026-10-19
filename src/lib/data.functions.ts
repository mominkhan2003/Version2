import { createServerFn } from "@tanstack/react-start";
import { createClient } from "@supabase/supabase-js";
import type { Database } from "@/integrations/supabase/types";
import { requireSupabaseAuth } from "@/integrations/supabase/auth-middleware";

export const getPublicData = createServerFn({ method: "GET" }).handler(async () => {
  const key = process.env["SUPABASE_PUBLISHABLE_KEY"];
  const url = process.env["SUPABASE_URL"];
  if (!key || !url) throw new Error("Website data is temporarily unavailable.");
  const client = createClient<Database>(url, key, { auth: { persistSession: false, autoRefreshToken: false }, global: { fetch: (input, init) => {
    const headers = new Headers(init?.headers);
    if (key.startsWith("sb_") && headers.get("Authorization") === `Bearer ${key}`) headers.delete("Authorization");
    headers.set("apikey", key);
    return fetch(input, { ...init, headers });
  } } });
  const [settings, events, metrics, counts, finances] = await Promise.all([
    client.from("site_settings").select("*").eq("id", 1).single(),
    client.from("events").select("id,event_date,title,activity_type,meeting_point,location,start_time,end_time,volunteers_needed,extra_info,description,cover_url,status,is_demo").eq("published", true).order("event_date"),
    client.from("impact_metrics").select("*").order("sort"),
    client.rpc("public_event_counts"), client.rpc("public_finances"),
  ]);
  for (const result of [settings, events, metrics, counts, finances]) if (result.error) throw new Error("Website data is temporarily unavailable.");
  return { settings: settings.data, events: events.data ?? [], metrics: metrics.data ?? [], counts: counts.data ?? [], finances: finances.data };
});

export const getTeamData = createServerFn({ method: "GET" }).middleware([requireSupabaseAuth]).handler(async ({ context }) => {
  const { supabase, userId } = context;
  const [profile, roles, events, assignments, profiles] = await Promise.all([
    supabase.from("profiles").select("*").eq("id", userId).single(),
    supabase.from("user_roles").select("role").eq("user_id", userId),
    supabase.from("events").select("*").order("event_date").order("start_time"),
    supabase.from("assignments").select("*"), supabase.from("profiles").select("id,display_name,skills,active"),
  ]);
  for (const result of [profile, roles, events, assignments, profiles]) if (result.error) throw result.error;
  if (!profile.data?.active) throw new Error("This team account is inactive.");
  return { userId, profile: profile.data, roles: (roles.data ?? []).map(r => r.role), events: events.data ?? [], assignments: assignments.data ?? [], profiles: profiles.data ?? [] };
});