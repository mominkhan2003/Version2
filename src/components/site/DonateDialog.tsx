import { useState, type ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { z } from "zod";
import { CheckCircle2, Landmark, Loader2, Send } from "lucide-react";
import { toast } from "sonner";
import { supabase } from "@/integrations/supabase/client";
import { useLang } from "@/lib/i18n";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Star } from "@/components/brand";

export function useSettings() {
  return useQuery({
    queryKey: ["site_settings"],
    queryFn: async () => {
      const { data, error } = await supabase.from("site_settings").select("*").eq("id", 1).single();
      if (error) throw error;
      return data;
    },
  });
}

const types = ["money", "food", "ingredients", "equipment", "other"] as const;
const typeLabels: Record<(typeof types)[number], [string, string]> = {
  money: ["Geld", "Money"],
  food: ["Lebensmittel", "Food"],
  ingredients: ["Zutaten", "Ingredients"],
  equipment: ["Ausstattung", "Equipment"],
  other: ["Sonstiges", "Other"],
};

const schema = z.object({
  full_name: z.string().trim().min(2).max(120),
  email: z.string().trim().email().max(200),
  phone: z.string().trim().max(40).optional(),
  amount: z.number().min(0).max(1_000_000).nullable(),
  donation_type: z.enum(types),
  contact_method: z.enum(["email", "phone", "whatsapp"]),
  message: z.string().trim().max(2000).optional(),
  consent: z.literal(true),
});

export function DonateDialog({ children }: { children: ReactNode }) {
  const { pick } = useLang();
  const { data: s } = useSettings();
  const [open, setOpen] = useState(false);
  const [done, setDone] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [f, setF] = useState({
    full_name: "", email: "", phone: "", amount: "", donation_type: "money" as (typeof types)[number],
    contact_method: "email" as "email" | "phone" | "whatsapp", message: "", consent: false,
  });
  const bankReady = s?.bank_enabled && s.bank_iban && s.bank_holder;
  const requestOn = s ? s.request_enabled : true;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const parsed = schema.safeParse({
      ...f,
      phone: f.phone || undefined,
      message: f.message || undefined,
      amount: f.amount === "" ? null : Number(f.amount.replace(",", ".")),
    });
    if (!parsed.success) {
      const errs: Record<string, string> = {};
      for (const i of parsed.error.issues) {
        const k = String(i.path[0]);
        errs[k] =
          k === "email" ? pick("Bitte gültige E-Mail angeben.", "Please enter a valid email.")
          : k === "full_name" ? pick("Bitte deinen Namen angeben.", "Please enter your name.")
          : k === "consent" ? pick("Bitte Datenschutz zustimmen.", "Please accept the privacy notice.")
          : k === "amount" ? pick("Ungültiger Betrag.", "Invalid amount.")
          : pick("Ungültige Eingabe.", "Invalid input.");
      }
      setErrors(errs);
      return;
    }
    setErrors({});
    setBusy(true);
    const { error } = await supabase.from("donation_requests").insert({ ...parsed.data, phone: parsed.data.phone ?? null, message: parsed.data.message ?? null });
    setBusy(false);
    if (error) {
      toast.error(pick("Senden fehlgeschlagen. Bitte später erneut versuchen.", "Sending failed. Please try again later."));
      return;
    }
    setDone(true);
  }

  return (
    <Dialog open={open} onOpenChange={(o) => { setOpen(o); if (!o) setDone(false); }}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="max-h-[92vh] overflow-y-auto border-2 border-ink sm:max-w-xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 font-display text-2xl uppercase">
            <Star className="h-5 w-5 text-primary" /> {pick("Spenden", "Donate")}
          </DialogTitle>
          <DialogDescription>
            {pick("Jede Unterstützung hilft, kostenlos für alle zu kochen.", "Every bit of support helps us cook for free for everyone.")}
          </DialogDescription>
        </DialogHeader>

        {bankReady && (
          <div className="rounded-xl border-2 border-ink bg-lavender p-4 text-sm">
            <div className="mb-2 flex items-center gap-2 font-display uppercase"><Landmark className="h-4 w-4" /> {pick("Direkt überweisen", "Direct bank transfer")}</div>
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 break-all">
              <dt className="font-semibold">{pick("Inhaber:in", "Holder")}</dt><dd>{s!.bank_holder}</dd>
              <dt className="font-semibold">IBAN</dt><dd className="font-mono">{s!.bank_iban}</dd>
              {s!.bank_bic && (<><dt className="font-semibold">BIC</dt><dd className="font-mono">{s!.bank_bic}</dd></>)}
              {s!.bank_name && (<><dt className="font-semibold">Bank</dt><dd>{s!.bank_name}</dd></>)}
              {s!.bank_reference && (<><dt className="font-semibold">{pick("Verwendungszweck", "Reference")}</dt><dd>{s!.bank_reference}</dd></>)}
            </dl>
            {s!.bank_instructions && <p className="mt-2">{s!.bank_instructions}</p>}
          </div>
        )}

        {!requestOn && !bankReady && (
          <p className="rounded-lg bg-muted p-4 text-sm">{pick("Spenden sind gerade nicht möglich. Bitte kontaktiere uns direkt.", "Donations are currently unavailable. Please contact us directly.")}</p>
        )}

        {requestOn && (done ? (
          <div className="flex flex-col items-center gap-3 py-8 text-center">
            <CheckCircle2 className="h-14 w-14 text-success" />
            <h3 className="text-xl">{pick("Danke! Anfrage ist angekommen.", "Thank you! Request received.")}</h3>
            <p className="max-w-sm text-sm text-muted-foreground">
              {pick("Wir melden uns bald mit den nächsten Schritten. Deine Spende gilt erst nach Eingang als erhalten.", "We'll get back to you soon with next steps. Your donation counts as received only once it arrives.")}
            </p>
            <Button variant="posterLight" onClick={() => setOpen(false)}>{pick("Schließen", "Close")}</Button>
          </div>
        ) : (
          <form onSubmit={submit} className="grid gap-4" noValidate>
            {bankReady && <p className="text-sm font-semibold">{pick("…oder sende uns eine Anfrage, wir melden uns:", "…or send a request and we'll contact you:")}</p>}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={pick("Name*", "Full name*")} error={errors["full_name"]}>
                <Input value={f.full_name} onChange={(e) => setF({ ...f, full_name: e.target.value })} autoComplete="name" />
              </Field>
              <Field label={pick("E-Mail*", "Email*")} error={errors["email"]}>
                <Input type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} autoComplete="email" />
              </Field>
              <Field label={pick("Telefon (optional)", "Phone (optional)")}>
                <Input value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} autoComplete="tel" />
              </Field>
              <Field label={pick("Betrag in € (optional)", "Amount in € (optional)")} error={errors["amount"]}>
                <Input inputMode="decimal" value={f.amount} onChange={(e) => setF({ ...f, amount: e.target.value })} />
              </Field>
            </div>
            <Field label={pick("Art der Spende", "Donation type")}>
              <div className="flex flex-wrap gap-2">
                {types.map((t) => (
                  <button type="button" key={t} onClick={() => setF({ ...f, donation_type: t })}
                    className={`min-h-10 rounded-full border-2 border-ink px-4 text-sm font-semibold transition ${f.donation_type === t ? "bg-primary text-primary-foreground" : "bg-card"}`}>
                    {pick(...typeLabels[t])}
                  </button>
                ))}
              </div>
            </Field>
            <Field label={pick("Bevorzugter Kontakt", "Preferred contact")}>
              <div className="flex flex-wrap gap-2">
                {(["email", "phone", "whatsapp"] as const).map((c) => (
                  <button type="button" key={c} onClick={() => setF({ ...f, contact_method: c })}
                    className={`min-h-10 rounded-full border-2 border-ink px-4 text-sm font-semibold ${f.contact_method === c ? "bg-ink text-ink-foreground" : "bg-card"}`}>
                    {c === "email" ? "E-Mail" : c === "phone" ? pick("Telefon", "Phone") : "WhatsApp"}
                  </button>
                ))}
              </div>
            </Field>
            <Field label={pick("Nachricht / Details", "Message / details")}>
              <Textarea rows={3} value={f.message} onChange={(e) => setF({ ...f, message: e.target.value })} maxLength={2000} />
            </Field>
            <label className="flex items-start gap-3 text-sm">
              <Checkbox checked={f.consent} onCheckedChange={(v) => setF({ ...f, consent: v === true })} className="mt-0.5" />
              <span>
                {pick("Ich stimme zu, dass meine Angaben zur Bearbeitung der Spende gespeichert werden. Sie werden nicht veröffentlicht.", "I agree my details are stored to process this donation. They will never be published.")}{" "}
                <a href="/privacy" className="underline">{pick("Datenschutz", "Privacy")}</a>
                {errors["consent"] && <span className="block text-destructive">{errors["consent"]}</span>}
              </span>
            </label>
            <Button type="submit" variant="poster" size="lg" disabled={busy}>
              {busy ? <Loader2 className="animate-spin" /> : <Send />} {pick("Anfrage senden", "Send request")}
            </Button>
          </form>
        ))}
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, error, children }: { label: string; error?: string | undefined; children: ReactNode }) {
  return (
    <div className="grid gap-1.5">
      <Label className="font-semibold">{label}</Label>
      {children}
      {error && <span className="text-sm text-destructive">{error}</span>}
    </div>
  );
}
