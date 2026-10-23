"use client";

import { useState } from "react";
import { CircleCheck, Monitor, Smartphone } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { timeAgo } from "@/lib/utils";
import { ProfileForm } from "@/components/profile/ProfileForm";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

export default function ProfilePage() {
  const { data: me, loading, error, reload } = useApi(() => api.profile.getMe(), []);
  const { data: devices, setData: setDevices } = useApi(() => api.profile.getDevices(), []);
  const [saved, setSaved] = useState(false);

  if (loading) return <PageSkeleton />;
  if (error || !me) return <ErrorState message={error ?? "Could not load profile"} onRetry={reload} />;

  async function removeDevice(id: number) {
    await api.profile.removeDevice(id);
    setDevices((d) => d?.filter((x) => x.id !== id) ?? null);
  }

  return (
    <>
      <PageHeader title="My Profile" subtitle="Keep your details up to date so we can reach you" />
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <Card>
          <CardHeader title="Personal details" />
          <div className="p-5 sm:p-6">
            {saved && (
              <p className="mb-5 flex items-center gap-2 rounded-lg bg-green-50 px-3.5 py-2.5 text-sm font-medium text-green-700">
                <CircleCheck className="size-4" /> Profile saved
              </p>
            )}
            <ProfileForm
              emailVerified={me.emailVerified}
              initial={{ name: me.name, nickname: me.nickname, email: me.email, phone: me.phone, targetExam: me.targetExam, attemptYear: me.attemptYear }}
              submitLabel="Save changes"
              onSubmit={async (values) => {
                setSaved(false);
                await api.profile.updateMe(values);
                setSaved(true);
              }}
            />
          </div>
        </Card>

        <Card className="self-start">
          <CardHeader title="Logged-in devices" />
          <div className="p-5">
            <p className="mb-4 text-xs leading-relaxed text-slate-500">
              You stay logged in on each of these devices until you log out. If you no longer use a device, log it out here.
            </p>
            <ul className="space-y-3">
              {devices?.map((d) => (
                <li key={d.id} className="flex items-center gap-3 rounded-xl border border-line p-3">
                  <span className="grid size-10 place-items-center rounded-lg bg-slate-100 text-slate-600">
                    {/phone|android|ios/i.test(d.name) ? <Smartphone className="size-5" /> : <Monitor className="size-5" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-navy-900">{d.name}</p>
                    <p className="text-xs text-slate-500">{d.isCurrent ? "Active now" : `Last active ${timeAgo(d.lastActiveAt)}`}</p>
                  </div>
                  {d.isCurrent ? (
                    <Badge tone="green">This device</Badge>
                  ) : (
                    <Button size="sm" variant="ghost" className="text-red-600 hover:bg-red-50 hover:text-red-700" onClick={() => removeDevice(d.id)}>
                      Log out
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          </div>
        </Card>
      </div>
    </>
  );
}
