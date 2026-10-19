import { createFileRoute, redirect, Outlet } from "@tanstack/react-router";
import { getMe } from "@/lib/api";
import { storedLang, publicQuery } from "@/lib/queries";

export const Route = createFileRoute("/_authenticated")({
  ssr: false,
  beforeLoad: async ({ context }) => {
    const user = await getMe();
    if (!user) throw redirect({ to: "/auth" });
    // warm the team bundle alongside the guard
    await context.queryClient.ensureQueryData(publicQuery(storedLang()));
  },
  component: Outlet,
});
