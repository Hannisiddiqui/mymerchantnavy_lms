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

export async function get(): Promise<PageVisibilitySettings> {
  try {
    return await request<PageVisibilitySettings>("/page-visibility");
  } catch {
    return defaultPageVisibility;
  }
}
