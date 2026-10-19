<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history — force pushing, or rebasing/amending/squashing commits
> that are already pushed — as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->

## Application architecture
- Public reads use narrow server publishable clients and Query-backed route loaders so private rosters and donor details never reach public pages.
- Team routes live under the integration-compatible client-only authenticated layout; every private server function independently validates the session.
- Feature modules own the planner, administration and browser canvas studio; database RLS is authoritative for all mutations.
- Canvas composition exports the selected photographs and branding at exact social-format dimensions rather than capturing the editor.
