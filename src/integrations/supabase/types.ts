export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.18"
  }
  public: {
    Tables: {
      assignments: {
        Row: {
          constraint_note: string | null
          created_at: string
          event_id: string
          id: string
          person_name: string
          user_id: string | null
        }
        Insert: {
          constraint_note?: string | null
          created_at?: string
          event_id: string
          id?: string
          person_name: string
          user_id?: string | null
        }
        Update: {
          constraint_note?: string | null
          created_at?: string
          event_id?: string
          id?: string
          person_name?: string
          user_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "assignments_event_id_fkey"
            columns: ["event_id"]
            isOneToOne: false
            referencedRelation: "events"
            referencedColumns: ["id"]
          },
        ]
      }
      audit_logs: {
        Row: {
          action: string
          actor: string | null
          created_at: string
          details: Json | null
          entity: string | null
          id: string
        }
        Insert: {
          action: string
          actor?: string | null
          created_at?: string
          details?: Json | null
          entity?: string | null
          id?: string
        }
        Update: {
          action?: string
          actor?: string | null
          created_at?: string
          details?: Json | null
          entity?: string | null
          id?: string
        }
        Relationships: []
      }
      donation_requests: {
        Row: {
          amount: number | null
          consent: boolean
          contact_history: Json
          contact_method: string
          created_at: string
          donation_type: string
          email: string
          full_name: string
          id: string
          internal_notes: string | null
          is_demo: boolean
          message: string | null
          phone: string | null
          status: Database["public"]["Enums"]["donation_status"]
        }
        Insert: {
          amount?: number | null
          consent: boolean
          contact_history?: Json
          contact_method?: string
          created_at?: string
          donation_type: string
          email: string
          full_name: string
          id?: string
          internal_notes?: string | null
          is_demo?: boolean
          message?: string | null
          phone?: string | null
          status?: Database["public"]["Enums"]["donation_status"]
        }
        Update: {
          amount?: number | null
          consent?: boolean
          contact_history?: Json
          contact_method?: string
          created_at?: string
          donation_type?: string
          email?: string
          full_name?: string
          id?: string
          internal_notes?: string | null
          is_demo?: boolean
          message?: string | null
          phone?: string | null
          status?: Database["public"]["Enums"]["donation_status"]
        }
        Relationships: []
      }
      events: {
        Row: {
          activity_type: string
          cover_url: string | null
          created_at: string
          description: string | null
          end_time: string | null
          event_date: string
          extra_info: string | null
          id: string
          internal_notes: string | null
          is_demo: boolean
          location: string | null
          meeting_point: string | null
          needs_review: boolean
          published: boolean
          review_note: string | null
          start_time: string | null
          status: Database["public"]["Enums"]["event_status"]
          title: string
          volunteers_needed: number | null
        }
        Insert: {
          activity_type?: string
          cover_url?: string | null
          created_at?: string
          description?: string | null
          end_time?: string | null
          event_date: string
          extra_info?: string | null
          id?: string
          internal_notes?: string | null
          is_demo?: boolean
          location?: string | null
          meeting_point?: string | null
          needs_review?: boolean
          published?: boolean
          review_note?: string | null
          start_time?: string | null
          status?: Database["public"]["Enums"]["event_status"]
          title: string
          volunteers_needed?: number | null
        }
        Update: {
          activity_type?: string
          cover_url?: string | null
          created_at?: string
          description?: string | null
          end_time?: string | null
          event_date?: string
          extra_info?: string | null
          id?: string
          internal_notes?: string | null
          is_demo?: boolean
          location?: string | null
          meeting_point?: string | null
          needs_review?: boolean
          published?: boolean
          review_note?: string | null
          start_time?: string | null
          status?: Database["public"]["Enums"]["event_status"]
          title?: string
          volunteers_needed?: number | null
        }
        Relationships: []
      }
      expenses: {
        Row: {
          amount: number
          category: string
          created_at: string
          description: string | null
          id: string
          is_demo: boolean
          receipt_url: string | null
          spent_on: string
          supplier: string | null
        }
        Insert: {
          amount: number
          category: string
          created_at?: string
          description?: string | null
          id?: string
          is_demo?: boolean
          receipt_url?: string | null
          spent_on?: string
          supplier?: string | null
        }
        Update: {
          amount?: number
          category?: string
          created_at?: string
          description?: string | null
          id?: string
          is_demo?: boolean
          receipt_url?: string | null
          spent_on?: string
          supplier?: string | null
        }
        Relationships: []
      }
      impact_metrics: {
        Row: {
          is_sample: boolean
          key: string
          label_de: string
          label_en: string
          sort: number
          value: number
        }
        Insert: {
          is_sample?: boolean
          key: string
          label_de: string
          label_en: string
          sort?: number
          value?: number
        }
        Update: {
          is_sample?: boolean
          key?: string
          label_de?: string
          label_en?: string
          sort?: number
          value?: number
        }
        Relationships: []
      }
      notifications: {
        Row: {
          created_at: string
          id: string
          kind: string
          link: string | null
          read: boolean
          title: string
        }
        Insert: {
          created_at?: string
          id?: string
          kind: string
          link?: string | null
          read?: boolean
          title: string
        }
        Update: {
          created_at?: string
          id?: string
          kind?: string
          link?: string | null
          read?: boolean
          title?: string
        }
        Relationships: []
      }
      posts: {
        Row: {
          caption: string | null
          created_at: string
          created_by: string | null
          format: string
          id: string
          image_urls: string[]
          template: string
          title: string
        }
        Insert: {
          caption?: string | null
          created_at?: string
          created_by?: string | null
          format: string
          id?: string
          image_urls?: string[]
          template: string
          title: string
        }
        Update: {
          caption?: string | null
          created_at?: string
          created_by?: string | null
          format?: string
          id?: string
          image_urls?: string[]
          template?: string
          title?: string
        }
        Relationships: []
      }
      profiles: {
        Row: {
          active: boolean
          created_at: string
          display_name: string | null
          email: string | null
          id: string
          must_change_password: boolean
          phone: string | null
          skills: string[] | null
        }
        Insert: {
          active?: boolean
          created_at?: string
          display_name?: string | null
          email?: string | null
          id: string
          must_change_password?: boolean
          phone?: string | null
          skills?: string[] | null
        }
        Update: {
          active?: boolean
          created_at?: string
          display_name?: string | null
          email?: string | null
          id?: string
          must_change_password?: boolean
          phone?: string | null
          skills?: string[] | null
        }
        Relationships: []
      }
      site_settings: {
        Row: {
          bank_bic: string | null
          bank_enabled: boolean
          bank_holder: string | null
          bank_iban: string | null
          bank_instructions: string | null
          bank_name: string | null
          bank_reference: string | null
          contact_address: string | null
          contact_email: string | null
          id: number
          imprint: string | null
          instagram_handle: string | null
          instagram_posts: string[]
          request_enabled: boolean
        }
        Insert: {
          bank_bic?: string | null
          bank_enabled?: boolean
          bank_holder?: string | null
          bank_iban?: string | null
          bank_instructions?: string | null
          bank_name?: string | null
          bank_reference?: string | null
          contact_address?: string | null
          contact_email?: string | null
          id?: number
          imprint?: string | null
          instagram_handle?: string | null
          instagram_posts?: string[]
          request_enabled?: boolean
        }
        Update: {
          bank_bic?: string | null
          bank_enabled?: boolean
          bank_holder?: string | null
          bank_iban?: string | null
          bank_instructions?: string | null
          bank_name?: string | null
          bank_reference?: string | null
          contact_address?: string | null
          contact_email?: string | null
          id?: number
          imprint?: string | null
          instagram_handle?: string | null
          instagram_posts?: string[]
          request_enabled?: boolean
        }
        Relationships: []
      }
      user_roles: {
        Row: {
          id: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Insert: {
          id?: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Update: {
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id?: string
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      has_role: {
        Args: {
          _role: Database["public"]["Enums"]["app_role"]
          _user_id: string
        }
        Returns: boolean
      }
      is_staff: { Args: { _user_id: string }; Returns: boolean }
      public_event_counts: {
        Args: never
        Returns: {
          assigned: number
          event_id: string
        }[]
      }
      public_finances: { Args: never; Returns: Json }
    }
    Enums: {
      app_role: "admin" | "coordinator" | "volunteer"
      donation_status: "new" | "contacted" | "confirmed" | "received" | "closed"
      event_status:
        | "planned"
        | "open"
        | "in_progress"
        | "completed"
        | "cancelled"
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends (DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never) = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends (PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never) = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {
      app_role: ["admin", "coordinator", "volunteer"],
      donation_status: ["new", "contacted", "confirmed", "received", "closed"],
      event_status: [
        "planned",
        "open",
        "in_progress",
        "completed",
        "cancelled",
      ],
    },
  },
} as const
