/** Payload types mirroring backend/src/Mappers.php output. */

export interface CmsLink { href: string; label: string }

export interface CmsSection {
  id: string;
  slug: string;
  kind: string;
  position: number;
  is_visible: boolean;
  texts: Record<string, string>;
  images: Array<{ image_key: string; image_url: string }>;
  links: Record<string, CmsLink>;
}

export interface CmsStory {
  id: string;
  title: string;
  body: string;
  media_id: string | null;
  image_url: string | null;
}

export interface PublicEvent {
  id: string;
  event_date: string;
  title: string;
  activity_type: string;
  meeting_point: string | null;
  location: string | null;
  start_time: string | null;
  end_time: string | null;
  volunteers_needed: number | null;
  extra_info: string | null;
  description: string | null;
  cover_url: string | null;
  status: string;
  is_demo: boolean;
}

export interface ImpactMetric {
  id: string;
  key: string;
  value: number;
  label_de: string;
  label_en: string;
  is_sample: boolean;
}

export interface SiteSettings {
  request_enabled: boolean;
  bank_enabled: boolean;
  bank_holder: string | null;
  bank_iban: string | null;
  bank_bic: string | null;
  bank_name: string | null;
  bank_reference: string | null;
  bank_instructions: string | null;
  contact_email: string | null;
  contact_address: string | null;
  instagram_handle: string;
  instagram_posts: string[];
}

export interface FinanceRow { category: string; amount: number; description: string }

export interface PublicFinances {
  received: number;
  received_count: number;
  spent: number;
  available: number;
  by_category: Array<{ category: string; total: number }>;
  recent: Array<{ spent_on: string; category: string; description: string; amount: number }>;
  expenses: FinanceRow[];
}

export interface PublicData {
  version: string;
  sections: CmsSection[];
  lists: Record<string, string[]>;
  stories: CmsStory[];
  events: PublicEvent[];
  metrics: ImpactMetric[];
  counts: Array<{ event_id: string; assigned: number }>;
  finances: PublicFinances;
  settings: SiteSettings;
  instagram: { handle: string; grid_enabled: boolean; tiles: Array<{ position: number; post_url: string | null; alt: string | null; image_url: string | null }> };
  pages: Record<string, { title: string; body: string }>;
}

export interface TeamProfile {
  display_name: string | null;
  phone: string | null;
  skills: string[];
}

export interface PlannerEvent {
  id: string;
  event_date: string;
  title: string;
  activity_type: string;
  meeting_point: string | null;
  location: string | null;
  location_id: number | null;
  start_time: string | null;
  end_time: string | null;
  volunteers_needed: number | null;
  extra_info: string | null;
  description: string | null;
  internal_notes: string | null;
  needs_review: boolean;
  review_note: string | null;
  cover_url: string | null;
  status: string;
  published: boolean;
  is_demo: boolean;
}

export interface PlannerAssignment {
  id: string;
  event_id: string;
  user_id: string | null;
  person_name: string;
  constraint_note: string | null;
}

export interface TeamMember {
  id: string;
  display_name: string | null;
  skills: string[];
  active: boolean;
}

export interface TeamData {
  userId: string;
  roles: string[];
  profile: TeamProfile | null;
  events: PlannerEvent[];
  assignments: PlannerAssignment[];
  profiles: TeamMember[];
}

export interface DonationRequest {
  id: string;
  full_name: string;
  email: string;
  phone: string | null;
  amount: number | null;
  donation_type: string;
  contact_method: string;
  message: string | null;
  status: string;
  created_at: string;
}

export interface MediaAsset {
  id: string;
  filename: string;
  mime_type: string;
  kind: string;
  width: number | null;
  height: number | null;
  url: string;
  thumb_url: string;
  uploaded_at: string;
}
