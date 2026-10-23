"use client";

import { useRouter } from "next/navigation";
import { UserRound } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { ProfileForm } from "@/components/profile/ProfileForm";
import { Skeleton } from "@/components/ui/States";

/** First login: the student completes their profile before entering the dashboard. */
export default function OnboardingPage() {
  const router = useRouter();
  const { data: me } = useApi(() => api.profile.getMe(), []);

  return (
    <div>
      <div className="mb-6 grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <UserRound className="size-6" />
      </div>
      <h2 className="text-2xl text-navy-900 sm:text-3xl">Set up your profile</h2>
      <p className="mt-2 mb-8 text-slate-500">Just a few details so we can personalise your dashboard and keep you updated.</p>
      {!me ? (
        <Skeleton className="h-96" />
      ) : (
        <ProfileForm
          emailVerified={me.emailVerified}
          initial={{ name: "", nickname: "", email: me.email, phone: me.phone, targetExam: "", attemptYear: 0 }}
          submitLabel="Save & go to dashboard"
          onSubmit={async (values) => {
            await api.profile.updateMe(values);
            router.replace("/my-courses");
          }}
        />
      )}
    </div>
  );
}
