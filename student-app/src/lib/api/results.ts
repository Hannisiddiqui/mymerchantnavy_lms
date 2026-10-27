/** Test results, rank and solutions. Backend: app/Http/Controllers/Api/V1/ResultController.php */
import type { AttemptResult, AttemptSummary, QuestionReportInfo, ReportReason } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";
import { toAttemptResult } from "../mock/mappers";
import { readState } from "../mock/store";

export async function getResults(): Promise<AttemptSummary[]> {
  if (USE_MOCK) {
    await mockDelay();
    return readState()
      .attempts.filter((a) => a.submittedAt)
      .map((a) => {
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { solutions, attempts, test, ...summary } = toAttemptResult(a);
        return summary;
      })
      .sort((a, b) => b.submittedAt.localeCompare(a.submittedAt));
  }
  return request<AttemptSummary[]>("/results");
}

export async function getResult(attemptId: string): Promise<AttemptResult> {
  if (USE_MOCK) {
    await mockDelay();
    const a = readState().attempts.find((x) => x.id === attemptId && x.submittedAt);
    if (!a) throw new Error("Result not found");
    return toAttemptResult(a);
  }
  return request<AttemptResult>(`/attempts/${attemptId}/result`);
}

/** "Report a problem" on one question of a finished attempt. Backend: QuestionReportController. */
export async function reportQuestion(attemptId: string, questionId: number, input: { reason: ReportReason; details?: string }): Promise<QuestionReportInfo> {
  if (USE_MOCK) {
    await mockDelay();
    return { id: Date.now(), reason: input.reason, details: input.details ?? "", status: "open", adminNote: "", createdAt: new Date().toISOString() };
  }
  return request<QuestionReportInfo>(`/attempts/${attemptId}/questions/${questionId}/report`, { method: "POST", body: JSON.stringify(input) });
}
