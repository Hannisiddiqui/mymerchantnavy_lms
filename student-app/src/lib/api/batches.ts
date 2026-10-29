/** Batches API for Student LMS (PhysicsWallah-style storefront). */
import type { BatchItem } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";

export const fallbackMockBatches: BatchItem[] = [
  {
    id: 1,
    title: "IMU-CET 2027 Live Target Batch",
    slug: "imu-cet-2027-target-batch",
    batchCode: "#IMU-2027-LIVE",
    targetExam: "IMU-CET",
    imageUrl: "https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80",
    description: "Comprehensive high-intensity batch for IMU-CET 2027 aspirants. Includes complete syllabus notes, formula sheets, 10,000+ chapterwise MCQs, and full-length all-India mock test series.",
    highlights: [
      "Full Syllabus Notes & Formula Sheets",
      "10,000+ Chapterwise MCQs & Ladder Problems",
      "Mock Test Series with Detailed Solutions",
      "Doubt Support & Maritime Guidance",
    ],
    mrpPrice: 5999,
    price: 2999,
    discountPercent: 50,
    startsAt: "2026-11-01",
    validityText: "Validity: Till Exam 2027",
    purchaseUrl: "https://mymerchantnavy.com/pass/",
    footerTag: "Free Test Series & Question Bank Included",
    isLive: true,
    status: "active",
    isEnrolled: false,
    courses: [{ id: 1, title: "IMU-CET 2027 Complete Course", slug: "imu-cet-2027" }],
    tests: [{ id: 101, title: "IMU-CET Full Mock 1", durationMinutes: 180 }],
  },
  {
    id: 2,
    title: "DNS Sponsorship Cracker Batch 2027",
    slug: "dns-sponsorship-cracker-batch-2027",
    batchCode: "#DNS-CRACKER-2027",
    targetExam: "DNS Sponsorship",
    imageUrl: "https://images.unsplash.com/photo-1505705694340-019e1e335916?auto=format&fit=crop&w=1200&q=80",
    description: "Focused preparation for top shipping company sponsorship tests (Synergy, Anglo Eastern, Fleet Management, Scorpio, Great Eastern) with company-specific aptitude and technical modules.",
    highlights: [
      "Company-Specific Sponsorship Mock Tests",
      "Psychometric & Aptitude Ladder Questions",
      "Shipping Company Interview Questions & Tips",
      "Ship Familiarization & Maritime Basics",
    ],
    mrpPrice: 4999,
    price: 2499,
    discountPercent: 50,
    startsAt: "2026-11-05",
    validityText: "Validity: 1 Year / Till Selection",
    purchaseUrl: "https://mymerchantnavy.com/pass/",
    footerTag: "Includes Company Mock Papers & Interview Prep",
    isLive: true,
    status: "active",
    isEnrolled: false,
    courses: [{ id: 2, title: "Company Sponsorship Exam Prep", slug: "company-sponsorship" }],
    tests: [{ id: 102, title: "Sponsorship Aptitude Mock", durationMinutes: 90 }],
  },
  {
    id: 3,
    title: "G.P. Rating Complete Pass Batch",
    slug: "gp-rating-complete-pass-batch",
    batchCode: "#GP-RATING-PASS",
    targetExam: "G.P. Rating",
    imageUrl: "https://images.unsplash.com/photo-1518241353330-0f7941c2d9b5?auto=format&fit=crop&w=1200&q=80",
    description: "Structured academic preparation for GP Rating entrance and pre-sea training. Covers basic seamanship, mathematics, science, English comprehension, and general maritime safety.",
    highlights: [
      "Complete GP Rating Pre-Sea Syllabus",
      "Maths, Science & English Chapter Notes",
      "Practice Ladder with Step-by-Step Solutions",
      "Real Exam Pattern Mock Tests",
    ],
    mrpPrice: 3999,
    price: 1999,
    discountPercent: 50,
    startsAt: "2026-11-01",
    validityText: "Validity: 6 Calendar Months",
    purchaseUrl: "https://mymerchantnavy.com/pass/",
    footerTag: "Instant LMS Activation on Purchase",
    isLive: true,
    status: "active",
    isEnrolled: false,
    courses: [{ id: 1, title: "G.P Rating Academic Prep", slug: "gp-rating" }],
    tests: [{ id: 103, title: "GP Rating Practice Test", durationMinutes: 60 }],
  },
];

export async function list(targetExam?: string): Promise<BatchItem[]> {
  if (USE_MOCK) {
    await mockDelay();
    if (targetExam && targetExam !== "all" && targetExam !== "All Batches") {
      return fallbackMockBatches.filter((b) => b.targetExam?.toLowerCase() === targetExam.toLowerCase());
    }
    return fallbackMockBatches;
  }

  const query = targetExam && targetExam !== "all" && targetExam !== "All Batches"
    ? `?target_exam=${encodeURIComponent(targetExam)}`
    : "";
  return request<BatchItem[]>(`/batches${query}`);
}

export async function get(slug: string): Promise<BatchItem> {
  if (USE_MOCK) {
    await mockDelay();
    const found = fallbackMockBatches.find((b) => b.slug === slug || String(b.id) === slug);
    if (!found) throw new Error("Batch not found");
    return found;
  }
  return request<BatchItem>(`/batches/${slug}`);
}
