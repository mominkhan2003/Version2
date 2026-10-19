import { useQuery } from "@tanstack/react-query";
import { getMe, type ApiUser } from "./api";

export type Role = "admin" | "coordinator" | "volunteer";

export function useMe() {
  return useQuery({
    queryKey: ["me"],
    queryFn: () => getMe(),
    staleTime: 60_000,
  });
}

export function isStaffUser(u: ApiUser | null): boolean {
  return !!u && (u.isStaff ?? u.roles.includes("admin") || u.roles.includes("coordinator"));
}

export function isAdminUser(u: ApiUser | null): boolean {
  return !!u && u.isAdmin;
}
