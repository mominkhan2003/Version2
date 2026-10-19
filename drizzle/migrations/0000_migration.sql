create type public.app_role as enum ('admin','coordinator','volunteer');
create type public.event_status as enum ('planned','open','in_progress','completed','cancelled');
create type public.donation_status as enum ('new','contacted','confirmed','received','closed');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text, email text, phone text, skills text[] default '{}', active boolean not null default true,
  must_change_password boolean not null default false,
  created_at timestamptz not null default now()
);
grant select, insert, update on public.profiles to authenticated; grant all on public.profiles to service_role;
alter table public.profiles enable row level security;

create table public.user_roles (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references auth.users(id) on delete cascade not null,
  role app_role not null, unique(user_id, role)
);
grant select on public.user_roles to authenticated; grant all on public.user_roles to service_role;
alter table public.user_roles enable row level security;

create or replace function public.has_role(_user_id uuid, _role app_role) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.user_roles where user_id=_user_id and role=_role) $$;
create or replace function public.is_staff(_user_id uuid) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.user_roles where user_id=_user_id and role in ('admin','coordinator')) $$;

create policy "own roles or admin" on public.user_roles for select to authenticated using (user_id=auth.uid() or public.has_role(auth.uid(),'admin'));
create policy "admin manage roles" on public.user_roles for all to authenticated using (public.has_role(auth.uid(),'admin')) with check (public.has_role(auth.uid(),'admin'));
grant insert, update, delete on public.user_roles to authenticated;

create policy "members read profiles" on public.profiles for select to authenticated using (true);
create policy "own profile update" on public.profiles for update to authenticated using (id=auth.uid() or public.has_role(auth.uid(),'admin'));
create policy "own profile insert" on public.profiles for insert to authenticated with check (id=auth.uid());

create or replace function public.handle_new_user() returns trigger language plpgsql security definer set search_path=public as $$
begin
  insert into public.profiles(id, email, display_name) values (new.id, new.email, coalesce(new.raw_user_meta_data->>'display_name', split_part(new.email,'@',1)));
  insert into public.user_roles(user_id, role) values (new.id, 'volunteer');
  return new;
end $$;
create trigger on_auth_user_created after insert on auth.users for each row execute function public.handle_new_user();

create table public.events (
  id uuid primary key default gen_random_uuid(),
  event_date date not null,
  title text not null,
  activity_type text not null default 'other',
  meeting_point text, location text,
  start_time text, end_time text,
  volunteers_needed int,
  extra_info text, description text, internal_notes text,
  cover_url text,
  status event_status not null default 'open',
  published boolean not null default true,
  needs_review boolean not null default false,
  review_note text,
  is_demo boolean not null default false,
  created_at timestamptz not null default now()
);
grant select on public.events to anon; grant select, insert, update, delete on public.events to authenticated; grant all on public.events to service_role;
alter table public.events enable row level security;
create policy "public published events" on public.events for select to anon using (published);
create policy "members read events" on public.events for select to authenticated using (published or public.is_staff(auth.uid()));
create policy "staff write events" on public.events for all to authenticated using (public.is_staff(auth.uid())) with check (public.is_staff(auth.uid()));

create table public.assignments (
  id uuid primary key default gen_random_uuid(),
  event_id uuid not null references public.events(id) on delete cascade,
  person_name text not null,
  user_id uuid,
  constraint_note text,
  created_at timestamptz not null default now()
);
grant select, insert, update, delete on public.assignments to authenticated; grant all on public.assignments to service_role;
alter table public.assignments enable row level security;
create policy "members read assignments" on public.assignments for select to authenticated using (true);
create policy "staff manage assignments" on public.assignments for all to authenticated using (public.is_staff(auth.uid())) with check (public.is_staff(auth.uid()));
create policy "self signup" on public.assignments for insert to authenticated with check (user_id=auth.uid());
create policy "self remove" on public.assignments for delete to authenticated using (user_id=auth.uid());

create or replace function public.public_event_counts() returns table(event_id uuid, assigned bigint)
language sql stable security definer set search_path=public as $$
  select a.event_id, count(*) from public.assignments a join public.events e on e.id=a.event_id where e.published group by a.event_id $$;
grant execute on function public.public_event_counts() to anon, authenticated;

create table public.donation_requests (
  id uuid primary key default gen_random_uuid(),
  full_name text not null check (char_length(full_name) between 1 and 120),
  email text not null check (char_length(email) between 3 and 200),
  phone text check (phone is null or char_length(phone) <= 40),
  amount numeric(10,2) check (amount is null or amount >= 0),
  donation_type text not null check (donation_type in ('money','food','ingredients','equipment','other')),
  contact_method text not null default 'email',
  message text check (message is null or char_length(message) <= 2000),
  consent boolean not null check (consent),
  status donation_status not null default 'new',
  internal_notes text,
  contact_history jsonb not null default '[]',
  is_demo boolean not null default false,
  created_at timestamptz not null default now()
);
grant insert on public.donation_requests to anon, authenticated; grant select, update, delete on public.donation_requests to authenticated; grant all on public.donation_requests to service_role;
alter table public.donation_requests enable row level security;
create policy "anyone submit" on public.donation_requests for insert to anon, authenticated with check (status='new' and internal_notes is null);
create policy "admin read donations" on public.donation_requests for select to authenticated using (public.has_role(auth.uid(),'admin'));
create policy "admin update donations" on public.donation_requests for update to authenticated using (public.has_role(auth.uid(),'admin'));
create policy "admin delete donations" on public.donation_requests for delete to authenticated using (public.has_role(auth.uid(),'admin'));

create table public.expenses (
  id uuid primary key default gen_random_uuid(),
  spent_on date not null default current_date,
  category text not null, amount numeric(10,2) not null check (amount>=0),
  supplier text, description text, receipt_url text,
  is_demo boolean not null default false,
  created_at timestamptz not null default now()
);
grant select, insert, update, delete on public.expenses to authenticated; grant all on public.expenses to service_role;
alter table public.expenses enable row level security;
create policy "staff manage expenses" on public.expenses for all to authenticated using (public.is_staff(auth.uid())) with check (public.has_role(auth.uid(),'admin'));

create or replace function public.public_finances() returns json language sql stable security definer set search_path=public as $$
  select json_build_object(
    'received', coalesce((select sum(amount) from donation_requests where status in ('received','closed') and donation_type='money'),0),
    'received_count', (select count(*) from donation_requests where status in ('received','closed')),
    'spent', coalesce((select sum(amount) from expenses),0),
    'by_category', coalesce((select json_agg(x) from (select category, sum(amount) total from expenses group by category order by 2 desc) x),'[]'),
    'recent', coalesce((select json_agg(y) from (select spent_on, category, description, amount from expenses order by spent_on desc limit 6) y),'[]')
  ) $$;
grant execute on function public.public_finances() to anon, authenticated;

create table public.impact_metrics (
  key text primary key, value numeric not null default 0, label_de text not null, label_en text not null,
  is_sample boolean not null default true, sort int not null default 0
);
grant select on public.impact_metrics to anon, authenticated; grant insert, update, delete on public.impact_metrics to authenticated; grant all on public.impact_metrics to service_role;
alter table public.impact_metrics enable row level security;
create policy "public read metrics" on public.impact_metrics for select to anon, authenticated using (true);
create policy "admin write metrics" on public.impact_metrics for all to authenticated using (public.has_role(auth.uid(),'admin')) with check (public.has_role(auth.uid(),'admin'));

create table public.site_settings (
  id int primary key default 1 check (id=1),
  request_enabled boolean not null default true,
  bank_enabled boolean not null default false,
  bank_holder text, bank_iban text, bank_bic text, bank_name text, bank_reference text, bank_instructions text,
  contact_email text, contact_address text,
  instagram_handle text default 'mensacomunistagraz',
  instagram_posts text[] not null default '{}',
  imprint text
);
grant select on public.site_settings to anon, authenticated; grant update on public.site_settings to authenticated; grant all on public.site_settings to service_role;
alter table public.site_settings enable row level security;
create policy "public read settings" on public.site_settings for select to anon, authenticated using (true);
create policy "admin update settings" on public.site_settings for update to authenticated using (public.has_role(auth.uid(),'admin'));
insert into public.site_settings(id) values (1);

create table public.notifications (
  id uuid primary key default gen_random_uuid(),
  kind text not null, title text not null, link text, read boolean not null default false,
  created_at timestamptz not null default now()
);
grant select, update, delete on public.notifications to authenticated; grant all on public.notifications to service_role;
alter table public.notifications enable row level security;
create policy "admin notifications" on public.notifications for all to authenticated using (public.has_role(auth.uid(),'admin'));

create or replace function public.notify_donation() returns trigger language plpgsql security definer set search_path=public as $$
begin insert into notifications(kind,title,link) values ('donation','Neue Spendenanfrage: '||new.full_name,'/teams/admin/donations'); return new; end $$;
create trigger on_donation after insert on public.donation_requests for each row execute function public.notify_donation();
create or replace function public.notify_signup() returns trigger language plpgsql security definer set search_path=public as $$
begin if new.user_id is not null and not public.is_staff(new.user_id) then insert into notifications(kind,title,link) values ('signup',new.person_name||' hat sich für eine Schicht eingetragen','/teams/admin/events'); end if; return new; end $$;
create trigger on_assignment after insert on public.assignments for each row execute function public.notify_signup();

create table public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor uuid default auth.uid(), action text not null, entity text, details jsonb,
  created_at timestamptz not null default now()
);
grant select, insert on public.audit_logs to authenticated; grant all on public.audit_logs to service_role;
alter table public.audit_logs enable row level security;
create policy "admin read audit" on public.audit_logs for select to authenticated using (public.has_role(auth.uid(),'admin'));
create policy "staff write audit" on public.audit_logs for insert to authenticated with check (public.is_staff(auth.uid()) and actor=auth.uid());

create table public.posts (
  id uuid primary key default gen_random_uuid(),
  title text not null, caption text, template text not null, format text not null,
  image_urls text[] not null default '{}', created_by uuid default auth.uid(),
  created_at timestamptz not null default now()
);
grant select, insert, delete on public.posts to authenticated; grant all on public.posts to service_role;
alter table public.posts enable row level security;
create policy "staff posts" on public.posts for all to authenticated using (public.is_staff(auth.uid())) with check (public.is_staff(auth.uid()));

alter publication supabase_realtime add table public.notifications;

insert into public.impact_metrics(key,value,label_de,label_en,sort) values
 ('meals',0,'Mahlzeiten ausgegeben','Meals served',1),
 ('volunteers',0,'Freiwillige','Volunteers',2),
 ('events',0,'Aktionen durchgeführt','Actions completed',3);