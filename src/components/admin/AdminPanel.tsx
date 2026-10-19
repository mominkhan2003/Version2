import { useMemo, useState } from "react";
import { Link } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Plus, Trash2, Upload, X } from "lucide-react";
import { apiGet, apiPost, uploadFile, ApiError } from "@/lib/api";
import { useLang } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import type { MediaAsset } from "@/lib/types";

/* ------------------------------------------------------------------ queries */
export interface AdminHomepageSection {
  id: number; slug: string; kind: string; position: number; is_visible: boolean;
  texts: Record<string, Record<string, string>>;
  lists: Record<string, Array<{ lang: string; position: number; value: string }>>;
  images: Array<{ role: string; position: number; media_id: string; thumb_url: string; url: string }>;
  links: Record<string, { id: number; section_id: number; link_key: string; href: string; labels: Record<string, string> }>;
}
export interface AdminBundle {
  ok: boolean;
  sections: Array<AdminHomepageSection> | Record<string, AdminHomepageSection>;
  stories: Array<{ id: string; position: number; is_visible: boolean; media_id: string | null; thumb_url: string | null; texts: Record<string, { title: string; body: string }> }>;
  metrics: Array<{ id: number; metric_key: string; value: number; position: number; is_sample: boolean; is_visible: boolean; labels: Record<string, string> }>;
  settings: Record<string, unknown>;
  bank_inst: Record<string, unknown>;
  pages: Record<string, Record<string, { title: string; body: string }>>;
  instagram: Record<string, unknown> | null;
  tiles: Array<{ position: number; post_url: string | null; alt: string | null }>;
}
const q = (key: readonly unknown[], url: string) => ({
  queryKey: key,
  queryFn: () => apiGet<Record<string, unknown>>(url),
  staleTime: 5_000,
});

/* ---------------------------------------------------------------- helpers */
function Err({ e }: { e: unknown }) {
  return <p className="rounded-md bg-destructive/10 p-3 text-sm">{e instanceof ApiError ? e.message : String(e)}</p>;
}
function LangRow({ label, value, onDone }: { label: string; value: string; onDone: (v: string) => void }) {
  const [v, setV] = useState(value);
  return (
    <label className="grid text-sm">
      <span className="mb-1 font-semibold">{label}</span>
      <textarea className="workspace-input min-h-20" value={v} onChange={(e) => setV(e.target.value)}
        onBlur={() => { if (v !== value) onDone(v); }} />
    </label>
  );
}
function BoolToggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return <label className="flex items-center gap-2 text-sm">{label ?? ""} <Checkbox checked={checked} onCheckedChange={(v) => onChange(v === true)} /></label>;
}

/* ============================================================ HOME TABS */
function SectionCard({ s, save }: { s: AdminHomepageSection; save: (body: Record<string, unknown>) => Promise<void> }) {
  const [open, setOpen] = useState(s.slug === "hero");
  const { pick } = useLang();
  return (
    <div className="rounded-lg border border-border bg-card">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
        <Button variant="ghost" className="flex items-center gap-3 px-2" onClick={() => setOpen(!open)}>
          <span className="font-display uppercase">{s.slug}</span>
          <span className="text-xs text-muted-foreground">{s.kind} · #{s.position}</span>
        </Button>
        <div className="flex items-center gap-4 text-sm">
          <BoolToggle checked={s.is_visible} label={pick("sichtbar", "visible")} onChange={(v) => void save({ action: "section_save", id: s.id, is_visible: v })} />
          <label className="flex items-center gap-1">{pick("Reihenfolge", "Order")}
            <input type="number" className="w-16 rounded border border-input bg-background px-2 py-1" defaultValue={s.position} min={0}
              onBlur={(e) => { const n = Number(e.target.value); if (Number.isFinite(n) && n !== s.position) void save({ action: "section_save", id: s.id, position: n }); }} /></label>
        </div>
      </header>
      {open && (
        <div className="grid gap-6 px-4 py-5">
          {Object.keys(s.texts).sort().map((fk) => (
            <div key={fk} className="grid gap-3 rounded border border-border p-3">
              <p className="text-xs font-bold uppercase text-muted-foreground">{fk}</p>
              {["de", "en"].map((l) => (
                <LangRow key={l} label={l.toUpperCase()} value={s.texts[fk]?.[l] ?? ""} onDone={(v) => void save({ action: "text_save", section_id: s.id, field_key: fk, lang: l, value: v })} />
              ))}
            </div>
          ))}
          {Object.entries(s.lists).map(([gk, items]) => (
            <div key={gk} className="grid gap-3 rounded border border-border p-3">
              <p className="text-xs font-bold uppercase text-muted-foreground">{pick("Liste", "List")}: {gk}</p>
              {["de", "en"].map((l) => (
                <label key={l} className="grid text-sm">
                  <span className="mb-1 font-semibold">{l.toUpperCase()}</span>
                  <textarea className="workspace-input" defaultValue={(items.filter(i => i.lang === l).sort((a, b) => a.position - b.position).map(i => i.value)).join(", ")}
                    onBlur={(e) => { if (e.target.value !== items.filter(i => i.lang === l).map(i => i.value).join(", ")) void save({ action: "list_save", section_id: s.id, group_key: gk, lang: l, items: e.target.value.split(",").map(x => x.trim()).filter(Boolean) }); }} />
                </label>
              ))}
            </div>
          ))}
          {Object.entries(s.links).map(([lk, link]) => (
            <div key={lk} className="grid gap-3 rounded border border-border p-3">
              <p className="text-xs font-bold uppercase text-muted-foreground">{pick("Link", "Link")}: {lk}</p>
              <label className="grid text-sm"><span className="mb-1 font-semibold">href</span>
                <input className="workspace-input" defaultValue={link.href} key={`${lk}-h`} onBlur={(e) => { if (e.target.value !== link.href) void save({ action: "link_save", section_id: s.id, link_key: lk, href: e.target.value, labels: link.labels }); }} /></label>
              {["de", "en"].map((l) => (
                <label key={l} className="grid text-sm"><span className="mb-1 font-semibold">label {l}</span>
                  <input className="workspace-input" defaultValue={link.labels?.[l] ?? ""} key={`${lk}-${l}`} onBlur={(e) => { if (e.target.value !== (link.labels?.[l] ?? "")) void save({ action: "link_save", section_id: s.id, link_key: lk, href: link.href, labels: { ...link.labels, [l]: e.target.value } }); }} /></label>
              ))}
            </div>
          ))}
          {s.images.length > 0 && (
            <div className="rounded border border-border p-3 text-sm">
              <p className="mb-2 text-xs font-bold uppercase text-muted-foreground">{pick("Bilder", "Images")}</p>
              <div className="flex flex-wrap gap-3">
                {s.images.map((img) => <img key={`${img.role}-${img.position}`} src={img.thumb_url} alt={img.role} className="h-20 rounded border" />)}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function HomeTab({ data, qc }: { data: AdminBundle; qc: ReturnType<typeof useQueryClient> }) {
  const { pick } = useLang();
  const save = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/homepage_admin.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "homepage"] });
      await qc.invalidateQueries({ queryKey: ["public"] });
      toast.success(pick("Gespeichert", "Saved"));
    } catch (e) {
      toast.error(pick("Speichern fehlgeschlagen", "Save failed"));
      void e;
    }
  };
  const sections = useMemo(() => {
    const arr = Object.prototype.toString.call(data.sections) === "[object Array]"
      ? (data.sections as AdminHomepageSection[])
      : Object.values(data.sections as Record<string, AdminHomepageSection>);
    return arr;
  }, [data.sections]);
  if (!sections.length) return <p>{pick("Keine Bereiche vorhanden.", "No sections found.")}</p>;
  return (
    <div className="grid gap-4 xl:grid-cols-2">
      {sections.map((s) => <SectionCard key={s.id} s={s} save={save} />)}
    </div>
  );
}

/* ============================================================ STORIES */
function StoriesTab({ data, qc }: { data: AdminBundle; qc: ReturnType<typeof useQueryClient> }) {
  const { pick } = useLang();
  const media = useQuery(q(["admin", "media", 1], "/api/media.php?page=1&per_page=48"));
  const assets = (media.data?.assets ?? []) as Array<{ id: string; thumb_url: string; alt: string }>;
  const save = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/homepage_admin.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "homepage"] });
      await qc.invalidateQueries({ queryKey: ["public"] });
      toast.success(pick("Gespeichert", "Saved"));
    } catch { toast.error(pick("Speichern fehlgeschlagen", "Save failed")); }
  };
  return (
    <div className="grid gap-4 xl:grid-cols-3">
      {data.stories.map((st) => (
        <div key={st.id} className="rounded-lg border border-border bg-card p-4">
          {st.thumb_url && <img src={st.thumb_url} alt="" className="mb-3 h-28 w-full rounded object-cover" />}
          <div className="mb-2 flex items-center gap-3 text-sm">
            <BoolToggle checked={st.is_visible} label={pick("sichtbar", "visible")} onChange={(v) => void save({ action: "story_save", id: st.id, is_visible: v, position: st.position, media_id: st.media_id, texts: st.texts })} />
            <select className="workspace-input !w-auto" value={st.media_id ?? ""} onChange={(e) => void save({ action: "story_save", id: st.id, is_visible: st.is_visible, position: st.position, media_id: e.target.value || null, texts: st.texts })}>
              <option value="">{pick("Kein Bild", "No image")}</option>
              {assets.map(a => <option key={a.id} value={a.id}>#{a.id.slice(0, 8)} {a.alt}</option>)}
            </select>
            <Button variant="ghost" size="icon" aria-label={pick("Löschen", "Delete")} onClick={async () => { if (window.confirm(pick("Geschichte löschen?", "Delete story?"))) await save({ action: "story_delete", id: st.id }); }}><Trash2 className="h-4 w-4" /></Button>
          </div>
          {["de", "en"].map((l) => (
            <div key={l} className="mb-3">
              <label className="grid text-sm"><span className="mb-1 font-semibold">{pick("Titel", "Title")} {l.toUpperCase()}</span>
                <input className="workspace-input" defaultValue={st.texts[l]?.title ?? ""} key={`t-${l}-${st.title}`} onBlur={(e) => { if (e.target.value !== (st.texts[l]?.title ?? "")) void save({ action: "story_save", id: st.id, is_visible: st.is_visible, position: st.position, media_id: st.media_id, texts: { ...st.texts, [l]: { title: e.target.value, body: st.texts[l]?.body ?? "" } } }); }} /></label>
              <label className="mt-2 grid text-sm"><span className="mb-1 font-semibold">{pick("Text", "Body")} {l.toUpperCase()}</span>
                <textarea className="workspace-input min-h-16" defaultValue={st.texts[l]?.body ?? ""} key={`b-${l}-${st.body}`} onBlur={(e) => { if (e.target.value !== (st.texts[l]?.body ?? "")) void save({ action: "story_save", id: st.id, is_visible: st.is_visible, position: st.position, media_id: st.media_id, texts: { ...st.texts, [l]: { title: st.texts[l]?.title ?? "", body: e.target.value } } }); }} /></label>
            </div>
          ))}
        </div>
      ))}
      <Button variant="outline" className="justify-self-start" onClick={() => void save({ action: "story_save", is_visible: true, position: data.stories.length, texts: { de: { title: "Neue Geschichte", body: "" }, en: { title: "New story", body: "" } } })}>
        <Plus />{pick("Neue Geschichte", "New story")}
      </Button>
    </div>
  );
}

/* ============================================================ METRICS + SETTINGS */
function SettingsTab({ data, qc }: { data: AdminBundle; qc: ReturnType<typeof useQueryClient> }) {
  const { pick } = useLang();
  const save = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/homepage_admin.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "homepage"] });
      await qc.invalidateQueries({ queryKey: ["public"] });
      toast.success(pick("Gespeichert", "Saved"));
    } catch { toast.error(pick("Speichern fehlgeschlagen", "Save failed")); }
  };
  const s = data.settings as Record<string, string | number | boolean | null>;
  const text = (k: string) => String(s[k] ?? "");
  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <div className="rounded-lg border border-border bg-card p-4">
        <h3 className="font-display uppercase">{pick("Wirkungszahlen", "Impact metrics")}</h3>
        <div className="mt-4 grid gap-4">
          {data.metrics.map(m => (
            <div key={m.id} className="grid gap-2 rounded border border-border p-3 text-sm">
              <div className="flex items-center justify-between gap-3">
                <strong className="uppercase">{m.metric_key}</strong>
                <BoolToggle checked={m.is_sample} label={pick("Beispielwert", "sample")} onChange={(v) => void save({ action: "metric_save", id: m.id, is_sample: v })} />
                <BoolToggle checked={m.is_visible} label={pick("sichtbar", "visible")} onChange={(v) => void save({ action: "metric_save", id: m.id, is_visible: v })} />
              </div>
              <label className="grid">{pick("Wert", "Value")}
                <input className="workspace-input" defaultValue={m.value} onBlur={(e) => { const n = Number(e.target.value); if (Number.isFinite(n) && n !== m.value) void save({ action: "metric_save", id: m.id, value: n }); }} /></label>
              {["de", "en"].map(l => (
                <label key={l} className="grid">label {l.toUpperCase()}
                  <input className="workspace-input" defaultValue={m.labels[l] ?? ""} onBlur={(e) => { if (e.target.value !== (m.labels[l] ?? "")) void save({ action: "metric_save", id: m.id, labels: { [l]: e.target.value } }); }} /></label>
              ))}
            </div>
          ))}
        </div>
      </div>
      <div className="rounded-lg border border-border bg-card p-4">
        <h3 className="font-display uppercase">{pick("Website-Einstellungen", "Site settings")}</h3>
        <div className="mt-4 grid gap-3 text-sm">
          <BoolToggle checked={!!s.request_enabled} label={pick("Spenden-Anfragen aktiv", "Donation requests enabled")} onChange={(v) => void save({ action: "settings_save", request_enabled: v })} />
          <BoolToggle checked={!!s.bank_enabled} label={pick("Bankdaten anzeigen", "Show bank details")} onChange={(v) => void save({ action: "settings_save", bank_enabled: v })} />
          {["bank_holder", "bank_iban", "bank_bic", "bank_name", "bank_reference", "office_email", "contact_address"].map(k => (
            <label key={k} className="grid">{k}
              <input className="workspace-input" defaultValue={text(k)} onBlur={(e) => { if (e.target.value !== text(k)) void save({ action: "settings_save", [k]: e.target.value }); }} /></label>
          ))}
          {["de", "en"].map(l => (
            <label key={l} className="grid">{pick("Bank-Hinweis", "Bank note")} {l.toUpperCase()}
              <textarea className="workspace-input" defaultValue={String(data.bank_inst[l] ?? "")} onBlur={(e) => { if (e.target.value !== String(data.bank_inst[l] ?? "")) void save({ action: "settings_save", bank_instructions: { [l]: e.target.value } }); }} /></label>
          ))}
          <label className="grid">{pick("Instagram-Handle", "Instagram handle")}
            <input className="workspace-input" defaultValue={String(data.instagram?.handle ?? "")} onBlur={(e) => { if (e.target.value !== String(data.instagram?.handle ?? "")) void save({ action: "settings_save", instagram: { handle: e.target.value } }); }} /></label>
          {["privacy", "imprint"].map(p => (
            <div key={p} className="rounded border border-border p-3">
              <p className="text-xs font-bold uppercase text-muted-foreground">/{p}</p>
              {["de", "en"].map(l => (
                <div key={l} className="mt-2 grid gap-2">
                  <input className="workspace-input" defaultValue={data.pages[p]?.[l]?.title ?? ""} placeholder={`title ${l}`} onBlur={(e) => { if (e.target.value !== (data.pages[p]?.[l]?.title ?? "")) void save({ action: "settings_save", pages: { [p]: { [l]: { title: e.target.value, body: data.pages[p]?.[l]?.body ?? "" } } } }); }} />
                  <textarea className="workspace-input" defaultValue={data.pages[p]?.[l]?.body ?? ""} placeholder={`body ${l}`} onBlur={(e) => { if (e.target.value !== (data.pages[p]?.[l]?.body ?? "")) void save({ action: "settings_save", pages: { [p]: { [l]: { title: data.pages[p]?.[l]?.title ?? "", body: e.target.value } } } }); }} />
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

/* ============================================================ EVENTS */
function EventsTab() {
  const qc = useQueryClient();
  const ev = useQuery(q(["admin", "events"], "/api/events.php"));
  type Ev = { id: string; event_date: string; translations: Record<string, { title: string }> | null; title?: string; is_published?: boolean; needs_review?: boolean; status: string };
  const events = (ev.data?.events ?? []) as Array<Record<string, unknown>>;
  const rows = useMemo(() => events.filter(e => String(e.event_date ?? "") >= "2026-01-01").slice(-60).reverse(), [ev.data]);
  const act = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/events.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "events"] });
      await qc.invalidateQueries({ queryKey: ["public"] });
      toast.success("OK");
    } catch (e) { toast.error(e instanceof ApiError ? e.message : "Fehler"); }
  };
  return (
    <div>
      <p className="mb-3 text-sm text-muted-foreground">Neue Schichten und Detailpflege laufen über den <Link to="/teams" className="underline">{({ } as never) && ""}Schichtplan</Link>. Hier: Veröffentlichung und Prüffälle.</p>
      <div className="overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full text-sm">
          <thead><tr className="border-b border-border text-left text-xs uppercase text-muted-foreground"><th className="p-3">Datum</th><th className="p-3">Titel</th><th className="p-3">Status</th><th className="p-3">{({ } as never) && ""}Aktionen</th></tr></thead>
          <tbody>
            {rows.map(e => {
              const id = String(e.id);
              const pub = Boolean(e.is_published);
              return (
                <tr key={id} className="border-b border-border/60">
                  <td className="p-3 whitespace-nowrap">{String(e.event_date)}</td>
                  <td className="p-3">{ev.data && String((e.translations as Record<string, unknown> | null) && (e.translations as Record<string, unknown>).de ? (e.translations as Record<string, unknown>).de.title : e.title)}</td>
                  <td className="p-3">{pub ? "✓公开" || (({ } as never) && "") : "—"}{e.needs_review ? " ⚠" : ""}</td>
                  <td className="flex gap-1 p-2">
                    <Button size="sm" variant={pub ? "secondary" : "outline"} onClick={() => void act({ action: pub ? "unpublish" : "publish", id })}>{pub ? "Verbergen" : "Veröffentlichen"}</Button>
                    {e.needs_review ? <Button size="sm" variant="ghost" onClick={() => void act({ action: "review_clear", id })}>Prüfflag löschen</Button> : null}
                    <Button size="sm" variant="ghost" className="text-destructive" onClick={async () => { if (window.confirm("Schicht löschen?")) await act({ action: "delete", id }); }}><Trash2 className="h-4 w-4" /></Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/* ============================================================ DONATIONS */
const DON_STATUSES = ["new", "contacted", "confirmed", "received", "closed"] as const;
function DonationsTab() {
  const { pick } = useLang();
  const qc = useQueryClient();
  const [status, setStatus] = useState("");
  const don = useQuery(q(["admin", "donations", status], `/api/donations.php?page=1${status ? `&status=${status}` : ""}`));
  const rep = useQuery(q(["admin", "report"], "/api/donations.php?action=report&from=2000-01-01"));
  const rows = (don.data?.requests ?? []) as Array<Record<string, unknown>>;
  const act = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/donations.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "donations"] });
      await qc.invalidateQueries({ queryKey: ["admin", "report"] });
      await qc.invalidateQueries({ queryKey: ["public"] });
      toast.success("OK");
    } catch (e) { toast.error(e instanceof ApiError ? e.message : "Fehler"); }
  };
  return (
    <div className="grid gap-5">
      <div className="grid gap-2 rounded-lg border border-border bg-card p-4 text-sm sm:grid-cols-3">
        <p>Bestätigt erhalten: <strong className="font-display text-xl" suppressHydrationWarning>{String(rep.data?.received ?? "…")} €</strong></p>
        <p>Ausgegeben: <strong className="font-display text-xl">{String(rep.data?.spent ?? "…")} €</strong></p>
        <p>Verfügbar: <strong className="font-display text-xl">{String(rep.data?.available ?? "…")} €</strong></p>
      </div>
      <div className="flex items-center gap-3">
        <select className="workspace-input !w-auto" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">{({ }) as never && ""}Alle Status</option>
          {DON_STATUSES.map(st => <option key={st} value={st}>{st}</option>)}
        </select>
        <span className="text-sm text-muted-foreground">{String(don.data?.total ?? "")} {pick("Anfragen", "requests")}</span>
      </div>
      <div className="overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full text-sm">
          <thead><tr className="border-b border-border text-left text-xs uppercase text-muted-foreground"><th className="p-3">Erhalten</th><th className="p-3">Name</th><th className="p-3">Kontakt</th><th className="p-3">Betrag</th><th className="p-3">Art</th><th className="p-3">Nachricht</th><th className="p-3">Status</th><th /></tr></thead>
          <tbody>
            {rows.map(r => {
              const id = String(r.id);
              return (
                <tr key={id} className="border-b border-border/60 align-top">
                  <td className="p-3 whitespace-nowrap">{String(r.created_at).slice(0, 10)}</td>
                  <td className="p-3">{String(r.full_name)}</td>
                  <td className="p-3">{String(r.email)}{r.phone ? <> · {String(r.phone)}</> : null}</td>
                  <td className="p-3">{r.amount ? `${String(r.amount)} €` : "—"}</td>
                  <td className="p-3">{String(r.type)}</td>
                  <td className="max-w-60 p-3 text-xs text-muted-foreground">{String(r.message ?? "")}</td>
                  <td className="p-3">
                    <select className="workspace-input !w-auto" value={String(r.status)} onChange={e => void act({ action: "set_status", id, status: e.target.value })}>
                      {DON_STATUSES.map(st => <option key={st} value={st}>{st}</option>)}
                    </select>
                  </td>
                  <td className="p-2"><Button size="icon" variant="ghost" className="text-destructive" aria-label="Löschen" onClick={async () => { if (window.confirm("Anfrage löschen?")) await act({ action: "delete", id }); }}><Trash2 className="h-4 w-4" /></Button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/* ============================================================ FINANCES */
function FinancesTab() {
  const qc = useQueryClient();
  const exp = useQuery(q(["admin", "expenses", 1], "/api/expenses.php?page=1"));
  const [f, setF] = useState({ spent_on: "", category_id: "", amount: "", supplier: "", description: "" });
  const cats = (exp.data?.categories ?? []) as Array<{ id: number; slug: string; label_de: string }>;
  const create = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiPost("/api/expenses.php", { action: "create", spent_on: f.spent_on || undefined, category_id: Number(f.category_id), amount: Number(f.amount.replace(",", ".")), supplier: f.supplier || null, description: f.description || null });
      setF({ spent_on: "", category_id: "", amount: "", supplier: "", description: "" });
      await qc.invalidateQueries({ queryKey: ["admin", "expenses"] });
      await qc.invalidateQueries({ queryKey: ["admin", "report"] });
      toast.success("Gespeichert");
    } catch (er) { toast.error(er instanceof ApiError ? er.message : "Fehler"); }
  };
  return (
    <div className="grid gap-6 xl:grid-cols-[380px_1fr]">
      <form onSubmit={create} className="grid gap-3 rounded-lg border border-border bg-card p-4 text-sm">
        <h3 className="font-display uppercase">{({ }) as never && ""}Ausgabe erfassen</h3>
        <label className="grid">Datum<input type="date" className="workspace-input" value={f.spent_on} onChange={x => setF({ ...f, spent_on: x.target.value })} /></label>
        <label className="grid">Kategorie<select required className="workspace-input" value={f.category_id} onChange={x => setF({ ...f, category_id: x.target.value })}><option value="">—</option>{cats.map(c => <option key={c.id} value={c.id}>{c.label_de}</option>)}</select></label>
        <label className="grid">Betrag (€)<input required inputMode="decimal" className="workspace-input" value={f.amount} onChange={x => setF({ ...f, amount: x.target.value })} /></label>
        <label className="grid">Anbieter<input className="workspace-input" value={f.supplier} onChange={x => setF({ ...f, supplier: x.target.value })} /></label>
        <label className="grid">Beschreibung<input className="workspace-input" value={f.description} onChange={x => setF({ ...f, description: x.target.value })} /></label>
        <Button type="submit">{({ }) as never && ""}Speichern</Button>
      </form>
      <div className="overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full text-sm">
          <thead><tr className="border-b border-border text-left text-xs uppercase text-muted-foreground"><th className="p-3">Datum</th><th className="p-3">Kategorie</th><th className="p-3">Betrag</th><th className="p-3">Anbieter</th><th className="p-3">Beschreibung</th><th /></tr></thead>
          <tbody>{((exp.data?.expenses ?? []) as Array<Record<string, unknown>>).map(r => {
            const id = String(r.id);
            return (
              <tr key={id} className="border-b border-border/60">
                <td className="p-3 whitespace-nowrap">{String(r.spent_on)}</td>
                <td className="p-3">{String(r.category)}</td>
                <td className="p-3">{String(r.amount)} €</td>
                <td className="p-3">{String(r.supplier ?? "")}</td>
                <td className="p-3">{String(r.description ?? "")}</td>
                <td className="p-2"><Button size="icon" variant="ghost" className="text-destructive" aria-label="Löschen" onClick={async () => { if (window.confirm("Ausgabe löschen?")) { await apiPost("/api/expenses.php", { action: "delete", id }); await qc.invalidateQueries({ queryKey: ["admin", "expenses"] }); } }}><Trash2 className="h-4 w-4" /></Button></td>
              </tr>
            );
          })}</tbody>
        </table>
      </div>
    </div>
  );
}

/* ============================================================ MEDIA */
function MediaTab() {
  const { pick } = useLang();
  const qc = useQueryClient();
  const media = useQuery(q(["admin", "media", 1], "/api/media.php?page=1&per_page=48"));
  const assets = (media.data?.assets ?? []) as Array<MediaAsset & { thumb_url: string }>;
  const refresh = async () => {
    await qc.invalidateQueries({ queryKey: ["admin", "media"] });
    await qc.invalidateQueries({ queryKey: ["public"] });
  };
  return (
    <div className="grid gap-5">
      <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-card p-3 text-sm">
        <Upload className="h-4 w-4" />{pick("Foto hochladen", "Upload image")}
        <input type="file" accept="image/*" className="hidden" onChange={async (e) => {
          const file = e.target.files?.[0];
          if (!file) return;
          try {
            await uploadFile("/api/media.php", file, { action: "upload" });
            await refresh();
            toast.success(pick("Hochgeladen", "Uploaded"));
          } catch (er) { toast.error(er instanceof ApiError ? er.message : "Upload fehlgeschlagen"); }
        }} />
      </label>
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        {assets.map(a => (
          <figure key={a.id} className="overflow-hidden rounded-lg border border-border bg-card">
            <img src={a.thumb_url} alt={a.alt ?? ""} className="aspect-square w-full object-cover" />
            <figcaption className="flex items-center justify-between gap-2 p-2 text-xs">
              <span className="truncate">{a.alt || a.id.slice(0, 8)}</span>
              <span className="flex gap-1">
                <label className="cursor-pointer" title="Ersetzen">
                  <Upload className="h-3.5 w-3.5" />
                  <input type="file" className="hidden" accept="image/*" onChange={async (e) => {
                    const file = e.target.files?.[0];
                    if (!file) return;
                    try { await uploadFile("/api/media.php", file, { action: "replace", id: a.id }); await refresh(); toast.success(pick("Ersetzt", "Replaced")); } catch { toast.error("Fehler"); }
                  }} /></label>
                <button className="text-destructive" aria-label={pick("Löschen", "Delete")} onClick={async () => { if (window.confirm(pick("Bild löschen?", "Delete image?"))) { try { await apiPost("/api/media.php", { action: "delete", id: a.id }); await refresh(); } catch { toast.error("Fehler"); } } }}><X className="h-3.5 w-3.5" /></button>
              </span>
            </figcaption>
          </figure>
        ))}
      </div>
    </div>
  );
}

/* ============================================================ USERS */
function UsersTab({ meId }: { meId: string }) {
  const qc = useQueryClient();
  const users = useQuery(q(["admin", "users"], "/api/users_admin.php"));
  const rows = (users.data?.users ?? []) as Array<{ id: string; email: string; display_name: string; is_active: boolean; roles: string[]; last_login: string | null }>;
  const act = async (body: Record<string, unknown>) => {
    try {
      await apiPost("/api/users_admin.php", body);
      await qc.invalidateQueries({ queryKey: ["admin", "users"] });
      toast.success("OK");
    } catch (e) { toast.error(e instanceof ApiError ? e.message : "Fehler"); }
  };
  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase text-muted-foreground"><th className="p-3">E-Mail</th><th className="p-3">Name</th><th className="p-3">Letzter Login</th><th className="p-3">Rollen</th><th className="p-3">Aktiv</th></tr></thead>
        <tbody>
          {rows.map(u => (
            <tr key={u.id} className="border-b border-border/60">
              <td className="p-3">{u.email}</td>
              <td className="p-3">{u.display_name || "—"}</td>
              <td className="p-3 whitespace-nowrap">{u.last_login?.slice(0, 10) ?? "—"}</td>
              <td className="p-2">
                {(["admin", "coordinator", "volunteer"] as const).map(r => (
                  <label key={r} className="mr-3 inline-flex items-center gap-1 text-xs">
                    <Checkbox checked={u.roles.includes(r)} disabled={u.id === meId}
                      onCheckedChange={(v) => void act({ action: "set_roles", user_id: u.id, roles: v === true ? [...u.roles, r] : u.roles.filter(x => x !== r) })} />
                    {r}
                  </label>
                ))}
              </td>
              <td className="p-2">
                <Checkbox checked={u.is_active} disabled={u.id === meId} onCheckedChange={(v) => void act({ action: "set_active", user_id: u.id, is_active: v === true })} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ============================================================ AUDIT + NOTIFICATIONS */
function AuditTab() {
  const audit = useQuery(q(["admin", "audit"], "/api/audit.php?page=1"));
  const rows = (audit.data?.entries ?? []) as Array<Record<string, unknown>>;
  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full text-sm">
        <thead><tr className="border-b border-border text-left text-xs uppercase text-muted-foreground"><th className="p-3">Zeit</th><th className="p-3">Aktion</th><th className="p-3">Objekt</th><th className="p-3">Details</th></tr></thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i} className="border-b border-border/60">
              <td className="p-3 whitespace-nowrap">{String(r.created_at ?? "").slice(0, 19)}</td>
              <td className="p-3 font-semibold">{String(r.action ?? "")}</td>
              <td className="p-3">{String(r.entity ?? "")} {String(r.entity_id ?? "").slice(0, 8)}</td>
              <td className="max-w-96 truncate p-3 text-xs text-muted-foreground">{JSON.stringify(r.details ?? {})}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
function NotificationsTab({ unread, items }: { unread: number; items: Array<Record<string, unknown>> }) {
  const qc = useQueryClient();
  const mark = async () => {
    try { await apiPost("/api/notifications.php", { action: "mark_all_read" }); await qc.invalidateQueries({ queryKey: ["admin", "notifications"] }); } catch { toast.error("Fehler"); }
  };
  return (
    <div className="grid gap-4">
      <div className="flex items-center gap-3">
        <span className="text-sm">{unread} {unread === 1 ? "ungelesen" : "ungelesene"}</span>
        {unread > 0 && <Button size="sm" variant="outline" onClick={() => void mark()}>Alle als gelesen</Button>}
      </div>
      <ul className="rounded-lg border border-border bg-card text-sm">
        {items.map((n, i) => (
          <li key={i} className={`flex justify-between gap-3 border-b border-border/60 p-3 ${n.read ? "opacity-60" : ""}`}>
            <span><strong>{String(n.kind)}</strong> — {String(n.title)}</span>
            <span className="whitespace-nowrap text-xs text-muted-foreground">{String(n.created_at ?? "").slice(0, 19)}</span>
          </li>
        ))}
        {!items.length && <li className="p-4 text-muted-foreground">Keine Benachrichtigungen.</li>}
      </ul>
    </div>
  );
}

/* ============================================================ SHELL */
const TABS = [
  ["home", "Startseite"], ["stories", "Geschichten"], ["events", "Termine"],
  ["donations", "Spenden"], ["finances", "Finanzen"], ["media", "Medien"],
  ["users", "Benutzer"], ["log", "Protokoll"], ["notify", "Mitteilungen"],
] as const;

export function AdminPanel({ meId }: { meId: string }) {
  const { pick } = useLang();
  const qc = useQueryClient();
  const [tab, setTab] = useState<(typeof TABS)[number][0]>("home");
  const home = useQuery(q(["admin", "homepage"], "/api/homepage_admin.php"));
  const notif = useQuery({
    queryKey: ["admin", "notifications"],
    queryFn: () => apiGet<{ unread: number; items: Array<Record<string, unknown>> }>("/api/notifications.php"),
    staleTime: 5_000,
  });

  return (
    <div>
      <div className="mb-6 flex flex-wrap gap-2">
        {TABS.map(([id, label]) => (
          <Button key={id} size="sm" variant={tab === id ? "secondary" : "outline"} onClick={() => setTab(id)}>
            {pick(label as string, label as string) || ""}{id === "notify" && (notif.data?.unread ?? 0) > 0 ? ` (${notif.data?.unread})` : ""}
          </Button>
        ))}
      </div>
      {home.error && <Err e={home.error} />}
      {tab === "home" && home.data && <HomeTab data={home.data as unknown as AdminBundle} qc={qc} />}
      {tab === "stories" && home.data && <StoriesTab data={home.data as unknown as AdminBundle} qc={qc} />}
      {tab === "events" && <EventsTab />}
      {tab === "donations" && <DonationsTab />}
      {tab === "finances" && <FinancesTab />}
      {tab === "media" && <MediaTab />}
      {tab === "users" && <UsersTab meId={meId} />}
      {tab === "log" && <AuditTab />}
      {tab === "notify" && <NotificationsTab unread={notif.data?.unread ?? 0} items={notif.data?.items ?? []} />}
    </div>
  );
}
