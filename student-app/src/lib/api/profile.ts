/** Student profile & logged-in devices. Backend: app/Http/Controllers/Api/V1/ProfileController.php */
import type { Device, Student } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";
import { readState, writeState } from "../mock/store";

const demoStudent: Student = {
  id: 1,
  phone: "9876543210",
  name: "Aarav Sharma",
  nickname: "Aarav",
  email: "aarav.sharma@example.com",
  emailVerified: true,
  targetExam: "IMU-CET",
  attemptYear: 2027,
};

export async function getMe(): Promise<Student> {
  if (USE_MOCK) {
    await mockDelay(150);
    const { student } = readState();
    return student?.name ? student : { ...demoStudent, ...(student?.phone ? { phone: student.phone } : {}), ...(student?.email ? { email: student.email } : {}) };
  }
  return request<Student>("/me");
}

/** With email login the server ignores `email` (the verified login) and keeps `phone` as an optional extra. */
export type ProfileInput = Pick<Student, "name" | "nickname" | "email" | "phone" | "targetExam" | "attemptYear">;

export async function updateMe(input: ProfileInput): Promise<Student> {
  if (USE_MOCK) {
    await mockDelay(400);
    const next = writeState((s) => ({
      ...s,
      student: { ...(s.student ?? demoStudent), ...input },
    }));
    return next.student!;
  }
  return request<Student>("/me", { method: "PUT", body: JSON.stringify(input) });
}

export async function getDevices(): Promise<Device[]> {
  if (USE_MOCK) {
    await mockDelay();
    return [
      { id: 1, name: "Chrome on Windows", lastActiveAt: new Date().toISOString(), isCurrent: true },
      { id: 2, name: "Android phone", lastActiveAt: new Date(Date.now() - 2 * 86400_000).toISOString(), isCurrent: false },
    ];
  }
  return request<Device[]>("/me/devices");
}

export async function removeDevice(id: number): Promise<void> {
  if (USE_MOCK) return mockDelay();
  await request(`/me/devices/${id}`, { method: "DELETE" });
}
