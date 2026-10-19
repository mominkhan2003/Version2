import { createFileRoute } from "@tanstack/react-router";
import { teamQuery } from "@/lib/queries";
import { TeamShell } from "@/components/team/TeamShell";
import { Planner } from "@/components/team/Planner";
import { pageHead } from "@/lib/seo";
export const Route=createFileRoute("/_authenticated/teams")({validateSearch:(s:Record<string,unknown>)=>({month:typeof s.month==="string"&&/^\d{4}-(0[1-9]|1[0-2])$/.test(s.month)?s.month:new Date().toISOString().slice(0,7),view:typeof s.view==="string"&&["month","week","calendar"].includes(s.view)?s.view:"month",activity:typeof s.activity==="string"?s.activity:"",mine:s.mine===true||s.mine==="true",week:Math.min(5,Math.max(1,Number(s.week)||1)),location:typeof s.location==="string"?s.location:""}),loader:({context})=>context.queryClient.ensureQueryData(teamQuery),head:()=>pageHead("Team-Schichtplan","Unser gemeinsamer Schichtplan mit Terminen, Aufgaben und Verfügbarkeit."),component:Teams,errorComponent:({error})=><div role="alert" className="p-8">{error.message}</div>,notFoundComponent:()=> <p>Kein Plan gefunden.</p>});
function Teams(){const search=Route.useSearch();return <TeamShell><Planner {...search}/></TeamShell>;}
