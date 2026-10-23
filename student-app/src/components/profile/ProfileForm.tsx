"use client";

import { useState } from "react";
import { CircleCheck, Mail } from "lucide-react";
import type { ProfileInput } from "@/lib/api/profile";
import { LOGIN_METHOD, attemptYears, targetExams } from "@/lib/config";
import { Button } from "@/components/ui/Button";
import { Field, Input, Select } from "@/components/ui/Field";

type Errors = Partial<Record<keyof ProfileInput, string>>;

const byEmail = LOGIN_METHOD === "email";

function validate(v: ProfileInput): Errors {
  const e: Errors = {};
  if (v.name.trim().length < 2) e.name = "Enter your full name";
  if (!v.nickname.trim()) e.nickname = "Enter what we should call you";
  if (!byEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email)) e.email = "Enter a valid email address";
  if (byEmail && v.phone && !/^[6-9]\d{9}$/.test(v.phone)) e.phone = "Enter a valid 10-digit mobile number, or leave it empty";
  if (!v.targetExam) e.targetExam = "Choose your target exam";
  if (!v.attemptYear) e.attemptYear = "Choose your attempt year";
  return e;
}

/**
 * Shared by first-time onboarding and the My Profile page.
 * Email login (default): the email is already filled in and verified, so it is locked; the mobile number is optional.
 * Phone login: the number is the locked login, and the email is asked for.
 */
export function ProfileForm({
  initial,
  emailVerified,
  submitLabel,
  onSubmit,
}: {
  initial: ProfileInput;
  emailVerified: boolean;
  submitLabel: string;
  onSubmit: (values: ProfileInput) => Promise<void>;
}) {
  const [values, setValues] = useState<ProfileInput>(initial);
  const [errors, setErrors] = useState<Errors>({});
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState("");

  const set = <K extends keyof ProfileInput>(key: K, value: ProfileInput[K]) => {
    setValues((v) => ({ ...v, [key]: value }));
    setErrors((e) => ({ ...e, [key]: undefined }));
  };

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const found = validate(values);
    setErrors(found);
    if (Object.keys(found).length) return;
    setSaving(true);
    setServerError("");
    try {
      await onSubmit(values);
    } catch (err) {
      setServerError(err instanceof Error ? err.message : "Could not save your details");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <Field label="Full name" required error={errors.name}>
          <Input value={values.name} onChange={(e) => set("name", e.target.value)} placeholder="Aarav Sharma" autoComplete="name" />
        </Field>
        <Field label="Nickname" required error={errors.nickname} hint="What we call you on the dashboard">
          <Input value={values.nickname} onChange={(e) => set("nickname", e.target.value)} placeholder="Aarav" />
        </Field>
      </div>

      {byEmail ? (
        <>
          <Field label="Email address" hint="This is your login. Contact support to change it.">
            <div className="relative">
              <Input type="email" value={values.email} disabled className="pr-24" />
              {emailVerified && (
                <span className="pointer-events-none absolute right-3 top-1/2 flex -translate-y-1/2 items-center gap-1 text-xs font-semibold text-green-700">
                  <CircleCheck className="size-4" /> Verified
                </span>
              )}
            </div>
          </Field>
          <Field label="Mobile number (optional)" error={errors.phone} hint="Only used to contact you.">
            <Input
              inputMode="numeric"
              autoComplete="tel-national"
              maxLength={10}
              value={values.phone}
              onChange={(e) => set("phone", e.target.value.replace(/\D/g, ""))}
              placeholder="98765 43210"
            />
          </Field>
          <div className="-mt-2 flex gap-2.5 rounded-lg bg-brand-50 px-3.5 py-3 text-xs leading-relaxed text-brand-700">
            <Mail className="mt-0.5 size-4 shrink-0" />
            <span>We&apos;ll send course updates, live class reminders, results and important notices to your email.</span>
          </div>
        </>
      ) : (
        <>
          <Field label="Mobile number" hint="Your login number can't be changed here. Contact support to change it.">
            <Input value={`+91 ${values.phone}`} disabled />
          </Field>
          <Field label="Email address" required error={errors.email}>
            <Input type="email" value={values.email} onChange={(e) => set("email", e.target.value)} placeholder="you@example.com" autoComplete="email" />
          </Field>
          <div className="-mt-2 flex gap-2.5 rounded-lg bg-brand-50 px-3.5 py-3 text-xs leading-relaxed text-brand-700">
            <Mail className="mt-0.5 size-4 shrink-0" />
            <span>Please use an email you check regularly. We&apos;ll send course updates, live class reminders, results and important notices there.</span>
          </div>
        </>
      )}

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <Field label="Target exam" required error={errors.targetExam}>
          <Select value={values.targetExam} onChange={(e) => set("targetExam", e.target.value)}>
            <option value="">Select exam</option>
            {targetExams.map((x) => (
              <option key={x}>{x}</option>
            ))}
          </Select>
        </Field>
        <Field label="Year of attempt" required error={errors.attemptYear}>
          <Select value={values.attemptYear || ""} onChange={(e) => set("attemptYear", Number(e.target.value))}>
            <option value="">Select year</option>
            {attemptYears().map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      {serverError && <p className="text-sm font-medium text-red-600">{serverError}</p>}

      <Button type="submit" size="lg" className="w-full sm:w-auto" loading={saving}>
        {submitLabel}
      </Button>
    </form>
  );
}
