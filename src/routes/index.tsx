import { createFileRoute } from "@tanstack/react-router";
import { HomePage } from "@/components/site/HomePage";
import { publicQuery } from "@/lib/queries";
import { pageHead } from "@/lib/seo";
export const Route = createFileRoute("/")({
  head: () => pageHead("Gratis Essen für alle", "Mensa Comunista Graz – kostenlose Mahlzeiten, gemeinsam kochen und Solidarität leben. Termine, Mitmachen und Spenden."),
  loader: ({ context }) => context.queryClient.ensureQueryData(publicQuery),
  component: HomePage,
  errorComponent: () => <div className="grid min-h-screen place-content-center gap-4 px-6 text-center"><h1>Mensa Comunista Graz</h1><p>Die Website ist gerade nicht erreichbar. Bitte versuche es später erneut.</p></div>,
  notFoundComponent: () => <p>Seite nicht gefunden.</p>,
});
