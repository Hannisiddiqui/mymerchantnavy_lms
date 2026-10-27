"use client";

import { useState } from "react";
import { CircleCheck, Flag, MessageSquareReply } from "lucide-react";
import * as api from "@/lib/api";
import { cn, timeAgo } from "@/lib/utils";
import type { QuestionReportInfo, ReportReason } from "@/types";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";

/** Same keys as ReportReason in the backend (app/Enums/ReportReason.php). */
const reasons: { key: ReportReason; label: string; hint: string }[] = [
  { key: "wrong_answer", label: "The correct answer is wrong", hint: "The marked answer should be a different option" },
  { key: "question_error", label: "Mistake in the question", hint: "Missing data, wrong numbers or unclear wording" },
  { key: "option_error", label: "Mistake in the options", hint: "Duplicate, missing or mistyped option" },
  { key: "explanation_error", label: "Solution is wrong or unclear", hint: "The steps or the reasoning do not add up" },
  { key: "formatting", label: "Typo or display problem", hint: "Broken formula, odd symbols, spelling" },
  { key: "other", label: "Something else", hint: "Tell us in a few words" },
];

/**
 * "Report a problem" under a question in the detailed solutions. Shows a small status once a report exists:
 * pending, or the admin's reply when it was closed. A student can send a new report after one is closed.
 */
export function ReportQuestion({ attemptId, questionId, number, report }: { attemptId: string; questionId: number; number: number; report?: QuestionReportInfo | null }) {
  const [current, setCurrent] = useState<QuestionReportInfo | null>(report ?? null);
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState<ReportReason>("wrong_answer");
  const [details, setDetails] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const pending = current?.status === "open";
  const needsDetails = reason === "other";

  function start() {
    setReason(pending ? current!.reason : "wrong_answer");
    setDetails(pending ? current!.details : "");
    setError(null);
    setOpen(true);
  }

  async function send() {
    if (needsDetails && !details.trim()) return setError("Please tell us what is wrong.");
    setSending(true);
    setError(null);
    try {
      setCurrent(await api.results.reportQuestion(attemptId, questionId, { reason, details: details.trim() || undefined }));
      setOpen(false);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not send your report. Please try again.");
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="mt-4 border-t border-dashed border-line pt-3">
      {current && current.status !== "open" && (
        <div className={cn("mb-3 flex gap-2.5 rounded-xl p-3 text-sm", current.status === "resolved" ? "bg-green-50 text-green-900" : "bg-slate-50 text-slate-700")}>
          {current.status === "resolved" ? <CircleCheck className="mt-0.5 size-4 shrink-0 text-green-600" /> : <MessageSquareReply className="mt-0.5 size-4 shrink-0 text-slate-500" />}
          <div className="min-w-0">
            <p className="font-semibold">{current.status === "resolved" ? "Thanks! We fixed this question." : "We checked this question and it is correct as it is."}</p>
            {current.adminNote && <p className="mt-0.5 whitespace-pre-line break-words">{current.adminNote}</p>}
          </div>
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-2">
        {pending ? (
          <Badge tone="amber">
            <Flag className="size-3" /> Reported · we&apos;re checking · {timeAgo(current!.createdAt)}
          </Badge>
        ) : (
          <span className="text-xs text-slate-500">Found something wrong with this question?</span>
        )}
        <Button variant="outline" size="sm" onClick={start} className="text-slate-700">
          <Flag className="size-4" /> {pending ? "Edit report" : "Report a problem"}
        </Button>
      </div>

      <Modal
        open={open}
        onClose={() => !sending && setOpen(false)}
        title={`Report question ${number}`}
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)} disabled={sending}>
              Cancel
            </Button>
            <Button onClick={send} loading={sending}>
              Send report
            </Button>
          </>
        }
      >
        <p className="mb-3 text-sm text-slate-600">What is wrong with this question? Our team checks every report and fixes real mistakes.</p>
        <div role="radiogroup" aria-label="What is wrong" className="space-y-2">
          {reasons.map((r) => (
            <label
              key={r.key}
              className={cn(
                "flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition",
                reason === r.key ? "border-brand-500 bg-brand-50 ring-2 ring-brand-100" : "border-line hover:bg-slate-50",
              )}
            >
              <input type="radio" name="reason" className="mt-1 accent-brand-600" checked={reason === r.key} onChange={() => setReason(r.key)} />
              <span>
                <span className="block text-sm font-semibold text-navy-900">{r.label}</span>
                <span className="block text-xs text-slate-500">{r.hint}</span>
              </span>
            </label>
          ))}
        </div>
        <label className="mt-4 block">
          <span className="mb-1.5 block text-sm font-semibold text-navy-900">
            Details {needsDetails ? <span className="text-red-600">*</span> : <span className="font-normal text-slate-400">(optional)</span>}
          </span>
          <textarea
            value={details}
            onChange={(e) => setDetails(e.target.value)}
            maxLength={1000}
            rows={3}
            placeholder="e.g. Option B and C look the same, or I think the answer is (a) because…"
            className="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm text-navy-900 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
          />
        </label>
        {error && <p className="mt-2 text-sm font-medium text-red-600">{error}</p>}
      </Modal>
    </div>
  );
}
