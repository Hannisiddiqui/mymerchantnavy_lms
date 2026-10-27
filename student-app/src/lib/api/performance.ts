/** The student's performance dashboard. Backend: app/Http/Controllers/Api/V1/PerformanceController.php */
import type { PerformanceReport } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";

export async function getPerformance(): Promise<PerformanceReport> {
  if (USE_MOCK) {
    await mockDelay();
    return {
      overview: { testsTaken: 0, attempts: 0, correct: 0, incorrect: 0, skipped: 0, accuracy: null, avgScorePercent: null, bestScorePercent: null, timeSpentSeconds: 0, chaptersStarted: 0, chaptersTotal: 0, lastActivityAt: null },
      trend: [],
      subjects: [],
    };
  }
  return request<PerformanceReport>("/performance");
}
