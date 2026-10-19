import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export type Role = "admin" | "coordinator" | "volunteer";

export function useMe() {
  return useQuery({
    queryKey: ["me"],
    queryFn: async () => {
      const { data } = await supabase.auth.getUser();
      const user = data.user;
      if (!user) return null;
      const [{ data: roles }, { data: profile }] = await Promise.all([
        supabase.from("user_roles").select("role").eq("user_id", user.id),
        supabase.from("profiles").select("*").eq("id", user.id).maybeSingle(),
      ]);
      const r = (roles ?? []).map((x) => x.role as Role);
      return {
        user,
        profile,
        roles: r,
        isAdmin: r.includes("admin"),
        isStaff: r.includes("admin") || r.includes("coordinator"),
        name: profile?.display_name ?? user.email ?? "",
      };
    },
  });
}

export async function logAudit(action: string, entity: string, details?: unknown) {
  const { data } = await supabase.auth.getUser();
  if (!data.user) return;
  await supabase.from("audit_logs").insert({ action, entity, details: (details ?? null) as never, actor: data.user.id });
}
