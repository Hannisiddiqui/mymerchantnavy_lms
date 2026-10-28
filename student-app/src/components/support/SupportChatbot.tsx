"use client";

import { useEffect, useState } from "react";
import { MessageCircle, X, Send, CheckCircle2, MessageSquare, Phone, Loader2 } from "lucide-react";
import { apiBaseUrl } from "@/lib/api/client";

interface SupportConfig {
  support_email: string;
  support_whatsapp_number: string;
}

export function SupportChatbot() {
  const [isOpen, setIsOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<"whatsapp" | "email">("whatsapp");
  const [config, setConfig] = useState<SupportConfig>({
    support_email: "support.mymerchantnavy@gmail.com",
    support_whatsapp_number: "919911223344",
  });

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [category, setCategory] = useState("Lesson Doubt");
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");

  // Fetch dynamic support config on mount
  useEffect(() => {
    fetch(`${apiBaseUrl()}/support/config`)
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (data?.data) {
          setConfig({
            support_email: data.data.support_email || "support.mymerchantnavy@gmail.com",
            support_whatsapp_number: data.data.support_whatsapp_number || "919911223344",
          });
        }
      })
      .catch(() => {});

    // Try prefilling user info from localStorage if available
    try {
      const stored = localStorage.getItem("mmn_user");
      if (stored) {
        const u = JSON.parse(stored);
        if (u.name) setName(u.name);
        if (u.email) setEmail(u.email);
        if (u.phone) setPhone(u.phone);
      }
    } catch {}
  }, []);

  function openWhatsApp(customText?: string) {
    const text = customText || `Hello My Merchant Navy Team, I am ${name || "a student"} and I have a question regarding my course:`;
    const cleanNumber = config.support_whatsapp_number.replace(/\D/g, "");
    const url = `https://wa.me/${cleanNumber}?text=${encodeURIComponent(text)}`;
    window.open(url, "_blank");
  }

  async function handleSendEmail(e: React.FormEvent) {
    e.preventDefault();
    if (!name || !email || !message) {
      setErrorMsg("Please fill in your name, email, and message.");
      return;
    }

    setSubmitting(true);
    setErrorMsg("");

    try {
      const res = await fetch(`${apiBaseUrl()}/support/query`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, phone, category, message }),
      });

      if (!res.ok) {
        throw new Error("Unable to submit query. Please try WhatsApp or email directly.");
      }

      setSuccess(true);
      setMessage("");
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to deliver message. Please contact us on WhatsApp.";
      setErrorMsg(msg);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      {/* Floating Action Button */}
      <div className="fixed bottom-5 right-5 z-40">
        {!isOpen && (
          <button
            onClick={() => setIsOpen(true)}
            className="flex items-center gap-2 rounded-full bg-brand-600 px-4 py-3 text-white shadow-xl transition-all duration-200 hover:bg-brand-700 hover:scale-105 active:scale-95 focus:outline-none"
            aria-label="Open Academic Support Chatbot"
          >
            <span className="relative flex size-3">
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75" />
              <span className="relative inline-flex size-3 rounded-full bg-green-500" />
            </span>
            <MessageCircle className="size-5" />
            <span className="text-sm font-bold tracking-wide">Doubt / Support</span>
          </button>
        )}
      </div>

      {/* Floating Chat / Support Dialog */}
      {isOpen && (
        <div className="fixed bottom-5 right-5 z-50 w-full max-w-sm overflow-hidden rounded-2xl border border-line bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 animate-in fade-in slide-in-from-bottom-5 duration-200">
          {/* Header */}
          <div className="flex items-center justify-between bg-gradient-to-r from-navy-900 to-brand-700 px-4 py-3.5 text-white">
            <div className="flex items-center gap-2.5">
              <div className="grid size-8 place-items-center rounded-full bg-white/20 text-white font-bold text-sm">
                MMN
              </div>
              <div>
                <h3 className="text-sm font-bold leading-tight">Maritime Faculty Support</h3>
                <p className="flex items-center gap-1.5 text-[11px] text-green-300">
                  <span className="size-1.5 rounded-full bg-green-400" /> Mentors Online
                </p>
              </div>
            </div>
            <button
              onClick={() => {
                setIsOpen(false);
                setSuccess(false);
                setErrorMsg("");
              }}
              className="rounded-full p-1 text-white/80 transition hover:bg-white/20 hover:text-white"
            >
              <X className="size-5" />
            </button>
          </div>

          {/* Channel Selector Tabs */}
          <div className="flex border-b border-line bg-slate-50 text-xs font-semibold dark:border-slate-800 dark:bg-slate-800/60">
            <button
              onClick={() => {
                setActiveTab("whatsapp");
                setSuccess(false);
              }}
              className={`flex-1 py-2.5 text-center transition ${activeTab === "whatsapp" ? "border-b-2 border-emerald-600 bg-white font-bold text-emerald-600 dark:bg-slate-900 dark:text-emerald-400" : "text-slate-500 hover:text-slate-800 dark:text-slate-400"}`}
            >
              WhatsApp Support
            </button>
            <button
              onClick={() => {
                setActiveTab("email");
                setSuccess(false);
              }}
              className={`flex-1 py-2.5 text-center transition ${activeTab === "email" ? "border-b-2 border-brand-600 bg-white font-bold text-brand-600 dark:bg-slate-900 dark:text-brand-400" : "text-slate-500 hover:text-slate-800 dark:text-slate-400"}`}
            >
              Ask Academic Team
            </button>
          </div>

          {/* Tab Content */}
          <div className="p-4 max-h-[75vh] overflow-y-auto">
            {activeTab === "whatsapp" ? (
              <div className="space-y-3.5 text-center">
                <div className="mx-auto grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                  <MessageSquare className="size-6" />
                </div>
                <div>
                  <h4 className="text-sm font-bold text-slate-800 dark:text-slate-100">Instant Doubt Resolution</h4>
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Connect directly with our senior instructors and exam counselors on WhatsApp.
                  </p>
                </div>

                <div className="space-y-1.5 text-left text-xs">
                  <p className="font-semibold text-slate-600 dark:text-slate-300">Quick inquiries:</p>
                  <button
                    onClick={() => openWhatsApp("Hello, I need help understanding a concept in today's lesson.")}
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-left text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50/50 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300"
                  >
                    • I have a doubt in today&apos;s lesson
                  </button>
                  <button
                    onClick={() => openWhatsApp("Hello, I have a query regarding course validity and mock test attempts.")}
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-left text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50/50 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300"
                  >
                    • Course validity & test access question
                  </button>
                  <button
                    onClick={() => openWhatsApp("Hello, I would like guidance for IMU-CET & Company Sponsorship interviews.")}
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-left text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50/50 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300"
                  >
                    • Sponsorship Exam & Interview guidance
                  </button>
                </div>

                <button
                  type="button"
                  onClick={() => openWhatsApp()}
                  className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white shadow-md transition hover:bg-emerald-700 active:scale-[0.99]"
                >
                  <Phone className="size-4" /> Chat on WhatsApp (+{config.support_whatsapp_number})
                </button>
              </div>
            ) : success ? (
              <div className="py-6 text-center space-y-3">
                <div className="mx-auto grid size-12 place-items-center rounded-full bg-green-100 text-green-600 dark:bg-green-950/40">
                  <CheckCircle2 className="size-7" />
                </div>
                <h4 className="text-base font-bold text-slate-800 dark:text-slate-100">Query Delivered!</h4>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  Your query has been sent to our faculty at <strong className="text-slate-700 dark:text-slate-200">{config.support_email}</strong>. We will review it and reply to your registered email shortly.
                </p>
                <button
                  onClick={() => setSuccess(false)}
                  className="mt-2 inline-block text-xs font-bold text-brand-600 hover:underline"
                >
                  Send another inquiry
                </button>
              </div>
            ) : (
              <form onSubmit={handleSendEmail} className="space-y-3 text-xs">
                {errorMsg && (
                  <div className="rounded-lg bg-rose-50 p-2.5 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">
                    {errorMsg}
                  </div>
                )}

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Your Name *</label>
                  <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    placeholder="Enter your name"
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    placeholder="student@example.com"
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  />
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone (Optional)</label>
                    <input
                      type="tel"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="WhatsApp number"
                      className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                  </div>
                  <div>
                    <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Category</label>
                    <select
                      value={category}
                      onChange={(e) => setCategory(e.target.value)}
                      className="w-full rounded-lg border border-line bg-slate-50 px-2.5 py-2 text-xs text-slate-800 focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                      <option value="Lesson Doubt">Lesson Doubt</option>
                      <option value="Question / Test Doubt">Question Doubt</option>
                      <option value="Course Validity / Access">Course Validity</option>
                      <option value="Sponsorship Guidance">Sponsorship</option>
                      <option value="Technical Bug">Technical Issue</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Query / Question *</label>
                  <textarea
                    rows={3}
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    required
                    placeholder="Describe your question or doubt in detail..."
                    className="w-full rounded-lg border border-line bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                  />
                </div>

                <button
                  type="submit"
                  disabled={submitting}
                  className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 py-2.5 text-xs font-bold text-white shadow transition hover:bg-brand-700 disabled:opacity-60"
                >
                  {submitting ? (
                    <>
                      <Loader2 className="size-3.5 animate-spin" /> Sending to Faculty...
                    </>
                  ) : (
                    <>
                      <Send className="size-3.5" /> Submit to Academic Team
                    </>
                  )}
                </button>
              </form>
            )}
          </div>
        </div>
      )}
    </>
  );
}
