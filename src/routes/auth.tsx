import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { login, signup, requestPasswordReset, ApiError } from "@/lib/api";
import { useLang } from "@/lib/i18n";
import { pageHead } from "@/lib/seo";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo, Star } from "@/components/brand";
import { LangSwitch } from "@/components/site/SiteChrome";
import photo from "@/assets/sharing-meal.jpg";
export const Route = createFileRoute("/auth")({ head: () => pageHead("Team-Login", "Melde dich bei der Mensa Comunista Graz an und hilf bei unseren gemeinsamen Schichten."), component: Auth });
function Auth() {
 const { pick } = useLang(); const navigate = useNavigate(); const qc = useQueryClient();
 const [mode,setMode] = useState<"login"|"signup"|"forgot">("login"); const [email,setEmail] = useState(""); const [password,setPassword] = useState(""); const [name,setName] = useState(""); const [busy,setBusy] = useState(false); const [message,setMessage] = useState("");
 async function submit(e: React.FormEvent) { e.preventDefault(); setBusy(true); setMessage("");
   try {
     if (mode === "forgot") { await requestPasswordReset(email); setMessage(pick("Prüfe deine E-Mails für den Link.","Check your email for the link.")); }
     else if (mode === "signup") { await signup(email, name, password); qc.clear(); await navigate({to:"/teams"}); }
     else { await login(email, password); qc.clear(); await navigate({to:"/teams"}); }
   } catch (err) { setMessage(err instanceof ApiError ? err.message : pick("Anmeldung fehlgeschlagen. Bitte prüfe E-Mail und Passwort.","Sign-in failed. Check your email and password.")); }
   setBusy(false);
 }
 return <main className="grid min-h-screen lg:grid-cols-2"><div className="relative hidden bg-ink lg:block"><img src={photo} alt={pick("Unsere Gemeinschaft", "Our community")} className="absolute inset-0 h-full w-full object-cover" /><div className="hero-shade absolute inset-0" /><div className="absolute bottom-16 left-12 right-12 text-ink-foreground"><Star className="mb-6 h-16 w-16 text-primary" /><h1 className="text-5xl uppercase">{pick("Gemeinsam", "Together")}<br />{pick("mehr bewegen.", "we do more.")}</h1><p className="mt-5 font-hand text-3xl">Mensa Comunista Graz</p></div></div><div className="flex flex-col px-6 py-8"><div className="flex items-center justify-between"><Link to="/"><Logo className="text-xl" /></Link><LangSwitch /></div><div className="m-auto w-full max-w-sm py-14"><p className="section-kicker text-primary">{pick("Willkommen im Team", "Welcome to the team")}</p><h2 className="mt-4 text-3xl uppercase">{mode === "signup" ? pick("Mitmachen", "Join us") : mode === "forgot" ? pick("Passwort vergessen?","Forgot password?") : pick("Schön, dass du da bist.","Good to have you here.")}</h2><form onSubmit={submit} className="mt-7 space-y-4">{mode === "signup" && <label className="block text-sm">{pick("Dein Name","Your name")}<Input className="mt-2" value={name} onChange={e=>setName(e.target.value)} required /></label>}<label className="block text-sm">E-Mail<Input className="mt-2" type="email" value={email} onChange={e=>setEmail(e.target.value)} required autoComplete="email" /></label>{mode !== "forgot" && <label className="block text-sm">{pick("Passwort","Password")}<Input className="mt-2" type="password" value={password} onChange={e=>setPassword(e.target.value)} required minLength={mode === "signup" ? 8 : undefined} autoComplete={mode === "signup" ? "new-password" : "current-password"} /></label>}{message && <p role="status" className="rounded-md bg-muted p-3 text-sm">{message}</p>}<Button type="submit" variant="poster" className="w-full" disabled={busy}>{busy ? pick("Bitte warten…","Please wait…") : mode === "signup" ? pick("Konto erstellen","Create account") : mode === "forgot" ? pick("Link senden","Send reset link") : pick("Anmelden","Sign in")}</Button></form><div className="mt-5 flex flex-col items-center gap-1"><Button variant="link" onClick={()=>{setMode(mode === "signup" ? "login" : "signup");setMessage("");}}>{mode === "signup" ? pick("Schon dabei? Anmelden","Already a member? Sign in") : pick("Neu hier? Konto erstellen","New here? Create account")}</Button><Button variant="link" onClick={()=>{setMode(mode === "forgot" ? "login" : "forgot");setMessage("");}}>{mode === "forgot" ? pick("Zurück zur Anmeldung","Back to sign in") : pick("Passwort vergessen?","Forgot password?")}</Button></div></div></div></main>;
}
