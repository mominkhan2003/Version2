import { queryOptions } from "@tanstack/react-query";
import { getPublicData, getTeamData } from "./data.functions";
export const publicQuery = queryOptions({ queryKey: ["public"], queryFn: () => getPublicData(), staleTime: 30_000 });
export const teamQuery = queryOptions({ queryKey: ["team"], queryFn: () => getTeamData(), staleTime: 10_000 });