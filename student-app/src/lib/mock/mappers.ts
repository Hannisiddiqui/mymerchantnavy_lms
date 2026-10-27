/**
 * MOCK only: turns the raw mock catalog into the same shapes the API returns.
 * The backend does this job with Laravel API Resources.
 */
import type {
  AttemptResult,
  ContentItem,
  CourseSummary,
  Question,
  SubjectSummary,
  TestSummary,
} from "@/types";
import { scoreAttempt } from "../test-engine";
import { mockCourses, mockSubjects, mockTests, type MockContent, type MockSubject, type MockTest } from "./catalog";
import { optionId, questionBank } from "./questions";
import { readState, type MockAttempt } from "./store";

export const findTest = (id: number) => mockTests.find((t) => t.id === id);
export const findCourse = (id: number) => mockCourses.find((c) => c.id === id);
export const findSubject = (id: number) => mockSubjects.find((s) => s.id === id);

export function toQuestion(id: number): Question {
  const q = questionBank.find((x) => x.id === id)!;
  return {
    id: q.id,
    body: q.body,
    options: q.options.map((body, i) => ({ id: optionId(q.id, i), label: String(i + 1), body })),
  };
}

export const correctOptionOf = (questionId: number) => {
  const q = questionBank.find((x) => x.id === questionId)!;
  return optionId(q.id, q.answer);
};

const submittedAttempts = (testId?: number) =>
  readState().attempts.filter((a) => a.submittedAt && (testId === undefined || a.testId === testId));

export function toTestSummary(t: MockTest): TestSummary {
  const attempts = submittedAttempts(t.id);
  const scores = attempts.map((a) => scoreOf(t, a).score);
  const last = attempts.at(-1);
  return {
    id: t.id,
    title: t.title,
    kind: t.kind,
    courseId: t.courseId,
    courseTitle: findCourse(t.courseId)?.title ?? "",
    questionsCount: t.questionIds.length,
    durationMinutes: t.durationMinutes,
    marksCorrect: t.marksCorrect,
    marksWrong: t.marksWrong,
    totalMarks: t.questionIds.length * t.marksCorrect,
    attemptsCount: attempts.length,
    bestScore: scores.length ? Math.max(...scores) : undefined,
    lastAttemptId: last?.id,
  };
}

export function toContentItem(c: MockContent): ContentItem {
  const completed = c.completed || readState().completedContentIds.includes(c.id);
  if (c.type === "practice_test") {
    const { testId, ...rest } = c;
    return { ...rest, completed, test: toTestSummary(findTest(testId)!) };
  }
  return { ...c, completed };
}

const allContents = (s: MockSubject) => s.chapters.flatMap((ch) => ch.contents.map(toContentItem));

const percent = (done: number, total: number) => (total ? Math.round((done / total) * 100) : 0);

export function toSubjectSummary(s: MockSubject): SubjectSummary {
  const contents = allContents(s);
  return {
    id: s.id,
    title: s.title,
    description: s.description,
    chaptersCount: s.chapters.length,
    contentsCount: contents.length,
    progressPercent: percent(contents.filter((c) => c.completed).length, contents.length),
  };
}

export function toCourseSummary(courseId: number): CourseSummary {
  const c = findCourse(courseId)!;
  const subjects = mockSubjects.filter((s) => s.courseId === c.id);
  const contents = subjects.flatMap(allContents);
  return {
    id: c.id,
    title: c.title,
    description: c.description,
    accent: c.accent,
    subjectsCount: subjects.length,
    chaptersCount: subjects.reduce((n, s) => n + s.chapters.length, 0),
    practiceTestsCount: 0,
    mockTestsCount: mockTests.filter((t) => t.courseId === c.id).length,
    progressPercent: percent(contents.filter((x) => x.completed).length, contents.length),
    assignedAt: c.assignedAt,
  };
}

function scoreOf(t: MockTest, a: MockAttempt) {
  return scoreAttempt({
    questionIds: t.questionIds,
    correctOptionByQuestion: Object.fromEntries(t.questionIds.map((id) => [id, correctOptionOf(id)])),
    selectedOptionByQuestion: Object.fromEntries(t.questionIds.map((id) => [id, a.answers[id]?.selectedOptionId ?? null])),
    marksCorrect: t.marksCorrect,
    marksWrong: t.marksWrong,
  });
}

/** Fake but stable rank/percentile. The backend computes these from all students' first attempts. */
function fakeRanking(testId: number, score: number, totalMarks: number) {
  const totalCandidates = 240 + (testId % 7) * 23;
  const fraction = Math.max(0, score) / totalMarks;
  const rank = Math.max(1, Math.round((1 - fraction) ** 1.6 * totalCandidates));
  const percentile = Math.round(((totalCandidates - rank) / totalCandidates) * 10000) / 100;
  return { rank, totalCandidates, percentile };
}

export function toAttemptResult(a: MockAttempt): AttemptResult {
  const t = findTest(a.testId)!;
  const test = toTestSummary(t);
  const s = scoreOf(t, a);
  const ranking = fakeRanking(t.id, s.score, test.totalMarks);
  return {
    id: a.id,
    testId: t.id,
    testTitle: t.title,
    testKind: t.kind,
    courseTitle: test.courseTitle,
    attemptNo: a.attemptNo,
    submittedAt: a.submittedAt!,
    totalMarks: test.totalMarks,
    test,
    timeTakenSeconds: a.timeTakenSeconds ?? 0,
    ...s,
    ...ranking,
    attempts: submittedAttempts(t.id).map((x) => ({ id: x.id, attemptNo: x.attemptNo })),
    solutions: t.questionIds.map((id) => {
      const q = questionBank.find((x) => x.id === id)!;
      return {
        ...toQuestion(id),
        correctOptionId: correctOptionOf(id),
        selectedOptionId: a.answers[id]?.selectedOptionId ?? null,
        explanation: q.explanation,
      };
    }),
  };
}
