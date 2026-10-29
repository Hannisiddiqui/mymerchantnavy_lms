import { request } from "./client";

export interface CustomPageListItem {
  id: number;
  title: string;
  slug: string;
  icon?: string;
  badge_text?: string;
  show_in_sidebar: boolean;
  sort_order: number;
}

export interface CustomPageBlock {
  type:
    | "hero"
    | "rich_text"
    | "cards_grid"
    | "faq"
    | "cta_box"
    | "alert_notice"
    | "media_image"
    | "split_content"
    | "stats_numbers"
    | "testimonials"
    | "video_embed"
    | string;
  data: Record<string, any>;
}

export interface CustomPageDetail extends CustomPageListItem {
  content?: string | null;
  blocks?: CustomPageBlock[] | null;
  meta_description?: string | null;
  updated_at?: string;
}

export async function list(): Promise<CustomPageListItem[]> {
  try {
    return await request<CustomPageListItem[]>("/custom-pages");
  } catch {
    return [];
  }
}

export async function get(slug: string): Promise<CustomPageDetail> {
  return request<CustomPageDetail>(`/custom-pages/${slug}`);
}

