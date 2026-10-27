/**
 * Shared data shapes. These mirror the JSON returned by the Laravel API
 * (backend/app/Http/Resources). Keep both sides in sync.
 */

// ---------- Student ----------

export interface Student {
  id: number;
  /** Optional: empty when the student logs in by email and has not added a number. */
  phone: string;
  name: string;
  nickname: string;
  /** The login when students log in by email. Verified by the one-time code, so it cannot be changed. */
  email: string;
  emailVerified: boolean;
  targetExam: string;
  attemptYear: number;
}

export interface Device {
  id: number;
  name: string; // e.g. "Chrome on Windows"
  lastActiveAt: string;
  isCurrent: boolean;
}

// ---------- Courses ----------

export interface CourseSummary {
  id: number;
  title: string;
  description: string;
  /** Tailwind gradient classes used for the course banner until thumbnails exist. */
  accent: string;
  subjectsCount: number;
  chaptersCount: number;
  testsCount: number;
  progressPercent: number;
  assignedAt: string;
}

export interface SubjectSummary {
  id: number;
  title: string;
  description: string;
  chaptersCount: number;
  contentsCount: number;
  progressPercent: number;
}

/** A course curriculum is an ordered list of subjects with mock tests placed anywhere between them. */
export type CurriculumItem =
  | { kind: "subject"; subject: SubjectSummary }
  | { kind: "test"; test: TestSummary };

export interface Course extends CourseSummary {
  curriculum: CurriculumItem[];
}

export interface Subject {
  id: number;
  courseId: number;
  courseTitle: string;
  title: string;
  description: string;
  chapters: Chapter[];
}

export interface Chapter {
  id: number;
  title: string;
  /** Set when this entry is a mock test placed between chapters (it has no contents). */
  test?: TestSummary | null;
  contents: ContentItem[];
}

// ---------- Chapter content ----------

export type ContentType = "video" | "live_class" | "pdf" | "note" | "assignment" | "practice_test";

interface ContentBase {
  id: number;
  chapterId: number;
  title: string;
  completed: boolean;
}

export type LiveStatus = "upcoming" | "live" | "ended";

export type ContentItem = ContentBase &
  (
    | { type: "video"; durationSeconds: number; provider: string; embedUrl?: string }
    | {
        type: "live_class";
        startsAt: string;
        durationMinutes: number;
        status: LiveStatus;
        /** Filled after the class when the recording is uploaded. */
        recording?: { durationSeconds: number; embedUrl?: string };
      }
    | { type: "pdf"; pageCount: number }
    | { type: "note"; readMinutes: number; body: string }
    | { type: "assignment"; dueAt?: string; instructions: string; submittedAt?: string }
    | { type: "practice_test"; test: TestSummary }
  );

// ---------- Tests ----------

export type TestKind = "practice" | "mock";

export interface TestSummary {
  id: number;
  title: string;
  kind: TestKind;
  courseId: number;
  courseTitle: string;
  questionsCount: number;
  durationMinutes: number;
  marksCorrect: number;
  marksWrong: number;
  totalMarks: number;
  attemptsCount: number;
  bestScore?: number;
  lastAttemptId?: string;
}

export interface QuestionOption {
  id: number;
  label: string; // "1".."4"
  body: string; // HTML + LaTeX
}

/** What the student receives while attempting — never contains the answer. */
export interface Question {
  id: number;
  body: string; // HTML + LaTeX ($...$ inline, $$...$$ block)
  options: QuestionOption[];
}

export interface TestDetail extends TestSummary {
  questions: Question[];
}

/** Per-question state kept while attempting. */
export interface AnswerState {
  selectedOptionId: number | null;
  markedForReview: boolean;
  visited: boolean;
}

export type PaletteStatus = "not_visited" | "not_answered" | "answered" | "review" | "answered_review";

// ---------- Results ----------

export type ReportReason = "wrong_answer" | "question_error" | "option_error" | "explanation_error" | "formatting" | "other";

export type ReportStatus = "open" | "resolved" | "rejected";

/** A problem the student reported on a question (backend: QuestionReport::toStudentArray). */
export interface QuestionReportInfo {
  id: number;
  reason: ReportReason;
  details: string;
  status: ReportStatus;
  /** The admin's reply once the report is closed. */
  adminNote: string;
  createdAt: string;
}

export interface SolutionQuestion extends Question {
  correctOptionId: number;
  selectedOptionId: number | null;
  explanation: string;
  /** The student's latest report on this question, if any. */
  report?: QuestionReportInfo | null;
}

export interface AttemptSummary {
  id: string;
  testId: number;
  testTitle: string;
  testKind: TestKind;
  courseTitle: string;
  attemptNo: number;
  submittedAt: string;
  score: number;
  totalMarks: number;
  accuracy: number;
  rank: number;
  totalCandidates: number;
}

export interface AttemptResult extends AttemptSummary {
  test: TestSummary;
  timeTakenSeconds: number;
  correct: number;
  incorrect: number;
  skipped: number;
  marksObtained: number;
  marksLost: number;
  percentile: number;
  /** Other attempts of the same test, for the attempt switcher. */
  attempts: { id: string; attemptNo: number }[];
  solutions: SolutionQuestion[];
}

// ---------- Notifications ----------

export type NotificationType = "course_assigned" | "live_class" | "result" | "announcement" | "report";

export interface AppNotification {
  id: string;
  type: NotificationType;
  title: string;
  body: string;
  href?: string;
  createdAt: string;
  read: boolean;
}

// ---------- Performance dashboard (backend: App\Services\Student\PerformanceReport) ----------

/** Right / wrong / skipped answers plus accuracy (null while nothing was answered). */
export interface PerformanceCounts {
  correct: number;
  incorrect: number;
  skipped: number;
  accuracy: number | null;
}

export interface ChapterPerformance extends PerformanceCounts {
  id: number;
  title: string;
  /** The chapter's own practice test, when it has one. */
  testId: number | null;
  attempts: number;
  bestScorePercent: number | null;
  lastScorePercent: number | null;
  lastAttemptId: string | null;
  lastAttemptAt: string | null;
}

export interface SubjectPerformance extends PerformanceCounts {
  id: number;
  title: string;
  courseTitle: string;
  chaptersTotal: number;
  chaptersStarted: number;
  avgScorePercent: number | null;
  chapters: ChapterPerformance[];
}

export interface PerformanceTrendPoint {
  attemptId: string;
  testTitle: string;
  testKind: TestKind;
  submittedAt: string;
  scorePercent: number;
  accuracy: number;
}

export interface PerformanceReport {
  overview: PerformanceCounts & {
    testsTaken: number;
    attempts: number;
    avgScorePercent: number | null;
    bestScorePercent: number | null;
    timeSpentSeconds: number;
    chaptersStarted: number;
    chaptersTotal: number;
    lastActivityAt: string | null;
  };
  trend: PerformanceTrendPoint[];
  subjects: SubjectPerformance[];
}
