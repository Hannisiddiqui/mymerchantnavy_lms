import { request } from "./client";

export interface PageVisibilitySettings {
  my_courses: boolean;
  batches: boolean;
  mock_tests: boolean;
  performance: boolean;
  results: boolean;
  notes: boolean;
  profile: boolean;
}

export const defaultPageVisibility: PageVisibilitySettings = {
  my_courses: true,
  batches: true,
  mock_tests: true,
  performance: true,
  results: true,
  notes: true,
  profile: true,
};

let cachedVisibility: PageVisibilitySettings | null = null;

const STORAGE_KEY = "mmn_page_visibility_cache";

export function getCached(): PageVisibilitySettings {
  if (cachedVisibility) {
    return cachedVisibility;
  }
  if (typeof window !== "undefined") {
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      if (stored) {
        cachedVisibility = JSON.parse(stored);
        return cachedVisibility!;
      }
    } catch {
      // ignore
    }
  }
  return defaultPageVisibility;
}

export async function get(): Promise<PageVisibilitySettings> {
  try {
    const data = await request<PageVisibilitySettings>("/page-visibility");
    cachedVisibility = data;
    if (typeof window !== "undefined") {
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
      } catch {
        // ignore
      }
    }
    return data;
  } catch {
    return getCached();
  }
}
