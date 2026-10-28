"use client";

import { useEffect, useState, useRef } from "react";
import { 
  MessageCircle, 
  X, 
  Send, 
  CheckCircle2, 
  Phone, 
  Loader2, 
  Bot, 
  ArrowLeft, 
  BookOpen, 
  FileCheck2, 
  Clock, 
  CreditCard, 
  Mail,
  RotateCcw
} from "lucide-react";
import { apiBaseUrl } from "@/lib/api/client";
import * as api from "@/lib/api";

interface SupportConfig {
  support_email: string;
  support_whatsapp_number: string;
}

interface IssueCategory {
  id: string;
  title: string;
  description: string;
  icon: any;
  defaultPrompt: string;
}

const CATEGORIES: IssueCategory[] = [
  {
    id: "lesson_doubt",
    title: "Course & Lesson Doubt",
    description: "Ask faculty regarding study materials, formulas & video lessons",
    icon: BookOpen,
    defaultPrompt: "I have a doubt in my course lesson. Could the faculty please assist?",
  },
  {
    id: "test_issue",
    title: "Mock Test / Question Error",
    description: "Report a calculation issue, question typo, or answer key doubt",
    icon: FileCheck2,
    defaultPrompt: "I noticed an issue with a question in the practice/mock test.",
  },
  {
    id: "validity_issue",
    title: "Course Validity & Access",
    description: "Inquire about remaining access duration or extend expired course",
    icon: Clock,
    defaultPrompt: "I have a question regarding my course validity and access period.",
  },
  {
    id: "billing_issue",
    title: "Payment & Enrollment Query",
    description: "Verify course activation, invoice, or package purchase",
    icon: CreditCard,
    defaultPrompt: "I have a query regarding my course payment or batch assignment.",
  },
  {
    id: "whatsapp_direct",
    title: "Chat Directly on WhatsApp",
    description: "Immediate 1-on-1 discussion with our senior counselors & mentors",
    icon: Phone,
    defaultPrompt: "Hello My Merchant Navy, I would like to speak with a counselor.",
  },
  {
    id: "general_email",
    title: "General Academic Query",
    description: "Submit a general inquiry or feedback to the academic desk",
    icon: Mail,
    defaultPrompt: "I would like to submit a general inquiry to the faculty.",
  },
];

export function SupportChatbot() {
  const [isOpen, setIsOpen] = useState(false);
  const [step, setStep] = useState<"menu" | "form" | "submitted" | "whatsapp_picker">("menu");
  const [selectedCategory, setSelectedCategory] = useState<IssueCategory | null>(null);

  const [config, setConfig] = useState<SupportConfig>({
    support_email: "support.mymerchantnavy@gmail.com",
    support_whatsapp_number: "919911223344",
  });

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  
  const chatScrollRef = useRef<HTMLDivElement>(null);

  // Fetch dynamic support config & logged in student details on mount
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

    // Prefill from current student profile API
    api.profile.getMe()
      .then((me) => {
        if (me) {
          if (me.name) setName(me.name);
          if (me.email) setEmail(me.email);
          if (me.phone) setPhone(me.phone);
        }
      })
      .catch(() => {
        // Fallback to local storage if API call fails
        try {
          const stored = localStorage.getItem("mmn_user");
          if (stored) {
            const u = JSON.parse(stored);
            if (u.name) setName(u.name);
            if (u.email) setEmail(u.email);
            if (u.phone) setPhone(u.phone);
          }
        } catch {}
      });
  }, []);

  // Auto-scroll chat to bottom when step changes
  useEffect(() => {
    if (chatScrollRef.current) {
      chatScrollRef.current.scrollTop = chatScrollRef.current.scrollHeight;
    }
  }, [step, selectedCategory]);

  function handleSelectCategory(cat: IssueCategory) {
    setSelectedCategory(cat);
    if (cat.id === "whatsapp_direct") {
      setStep("whatsapp_picker");
    } else {
      setMessage(cat.defaultPrompt);
      setStep("form");
    }
  }

  function resetToMenu() {
    setStep("menu");
    setSelectedCategory(null);
    setErrorMsg("");
  }

  function launchWhatsApp(customMsg?: string) {
    const issueText = customMsg || message || selectedCategory?.defaultPrompt || "Hello My Merchant Navy Support";
    const studentInfo = `Student: ${name || "Student"} | Email: ${email || "—"} | Phone: ${phone || "—"}`;
    const categoryTag = selectedCategory ? `Category: [${selectedCategory.title}]` : "Support Query";
    
    const formatted = `*MMN Support Query*\n${categoryTag}\n${studentInfo}\n\n*Issue Details:*\n${issueText}`;
    const cleanNumber = config.support_whatsapp_number.replace(/\D/g, "");
    const url = `https://wa.me/${cleanNumber}?text=${encodeURIComponent(formatted)}`;
    window.open(url, "_blank");
  }

  async function handleSendEmail(e: React.FormEvent) {
    e.preventDefault();
    if (!name || !email || !message) {
      setErrorMsg("Please provide your name, email, and issue details.");
      return;
    }

    setSubmitting(true);
    setErrorMsg("");

    try {
      const res = await fetch(`${apiBaseUrl()}/support/query`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
          name, 
          email, 
          phone, 
          category: selectedCategory?.title || "Support Query", 
          message 
        }),
      });

      if (!res.ok) {
        throw new Error("Unable to submit query. Please try WhatsApp or email directly.");
      }

      setStep("submitted");
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to deliver message. Please contact us on WhatsApp.";
      setErrorMsg(msg);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      {/* Floating Action Launcher Button */}
      <div className="fixed bottom-5 right-5 z-40">
        {!isOpen && (
          <button
            onClick={() => setIsOpen(true)}
            className="group flex items-center gap-3 rounded-full bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-700 px-5 py-3.5 text-white shadow-2xl transition-all duration-300 hover:scale-105 hover:shadow-indigo-500/25 active:scale-95 focus:outline-none"
            aria-label="Open Support Assistant"
          >
            <div className="relative flex size-6 items-center justify-center rounded-full bg-white/20">
              <Bot className="size-4 text-white" />
              <span className="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-emerald-400 ring-2 ring-white" />
            </div>
            <div className="text-left leading-tight">
              <span className="block text-xs font-semibold tracking-wide text-indigo-100 uppercase">Need Help?</span>
              <span className="block text-sm font-bold">Ask Support</span>
            </div>
          </button>
        )}
      </div>

      {/* Landbot-Style Chat Window */}
      {isOpen && (
        <div className="fixed bottom-4 right-4 z-50 flex h-[620px] max-h-[92vh] w-[410px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-2xl transition-all duration-300 animate-in fade-in slide-in-from-bottom-4">
          
          {/* Header - Purple/Indigo Gradient */}
          <div className="flex items-center justify-between bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-700 px-5 py-4 text-white shadow-md">
            <div className="flex items-center gap-3">
              <div className="relative flex size-10 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/30 backdrop-blur-sm">
                <Bot className="size-5 text-white" />
                <span className="absolute bottom-0 right-0 size-2.5 rounded-full bg-emerald-400 ring-2 ring-indigo-700" />
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">Support Assistant</h3>
                <p className="text-xs font-medium text-indigo-100">Instant Issue Resolution & Help</p>
              </div>
            </div>
            
            <div className="flex items-center gap-1">
              {step !== "menu" && (
                <button
                  onClick={resetToMenu}
                  title="Start Over"
                  className="rounded-lg p-1.5 text-indigo-100 hover:bg-white/10 hover:text-white transition-colors"
                >
                  <RotateCcw className="size-4" />
                </button>
              )}
              <button
                onClick={() => setIsOpen(false)}
                className="rounded-lg p-1.5 text-indigo-100 hover:bg-white/10 hover:text-white transition-colors"
                aria-label="Close Support Assistant"
              >
                <X className="size-5" />
              </button>
            </div>
          </div>

          {/* Conversation Stream */}
          <div 
            ref={chatScrollRef}
            className="flex-1 overflow-y-auto bg-slate-50 p-4 space-y-4 text-sm"
          >
            {/* Bot Greeting Bubble */}
            <div className="flex items-start gap-2.5">
              <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-semibold text-xs border border-indigo-200">
                <Bot className="size-4" />
              </div>
              <div className="space-y-2 max-w-[85%]">
                <div className="rounded-2xl rounded-tl-sm bg-white p-3.5 text-slate-800 shadow-sm border border-slate-200/80 leading-relaxed">
                  <p className="font-semibold text-slate-900 mb-1">Hello! 👋</p>
                  <p>Welcome to <strong>My Merchant Navy Support</strong>. How can we help you today? Please choose an issue below to get started:</p>
                </div>
              </div>
            </div>

            {/* STEP 1: Main Menu Categories */}
            {step === "menu" && (
              <div className="space-y-2 pl-10 pr-1 animate-in fade-in slide-in-from-bottom-2 duration-200">
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                  Select your inquiry or issue:
                </p>
                {CATEGORIES.map((cat) => {
                  const Icon = cat.icon;
                  return (
                    <button
                      key={cat.id}
                      onClick={() => handleSelectCategory(cat)}
                      className="group flex w-full items-start gap-3 rounded-xl border border-indigo-100 bg-white p-3 text-left shadow-sm transition-all duration-200 hover:border-indigo-400 hover:bg-indigo-50/40 hover:shadow-md active:scale-[0.99]"
                    >
                      <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <Icon className="size-4" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <h4 className="text-sm font-semibold text-slate-900 group-hover:text-indigo-700 transition-colors">
                          {cat.title}
                        </h4>
                        <p className="text-xs text-slate-500 line-clamp-1 mt-0.5">
                          {cat.description}
                        </p>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}

            {/* User Selected Category Bubble */}
            {step !== "menu" && selectedCategory && (
              <div className="flex justify-end">
                <div className="max-w-[80%] rounded-2xl rounded-tr-sm bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-2.5 text-white shadow-sm font-medium">
                  {selectedCategory.title}
                </div>
              </div>
            )}

            {/* STEP 2: Issue Details & Raising Form */}
            {step === "form" && selectedCategory && (
              <>
                <div className="flex items-start gap-2.5">
                  <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-semibold text-xs border border-indigo-200">
                    <Bot className="size-4" />
                  </div>
                  <div className="rounded-2xl rounded-tl-sm bg-white p-3.5 text-slate-800 shadow-sm border border-slate-200/80 leading-relaxed max-w-[88%]">
                    <p>
                      Please provide your details below. You can immediately <strong>send this to WhatsApp</strong> or <strong>submit via Email</strong>:
                    </p>
                  </div>
                </div>

                <div className="ml-10 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm space-y-3">
                  {errorMsg && (
                    <div className="rounded-lg bg-rose-50 border border-rose-200 p-2.5 text-xs text-rose-700">
                      {errorMsg}
                    </div>
                  )}

                  <div className="grid grid-cols-1 gap-2.5">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Your Name</label>
                      <input
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g. Rahul Sharma"
                        className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                        <input
                          type="email"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          placeholder="student@example.com"
                          className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">WhatsApp / Phone</label>
                        <input
                          type="tel"
                          value={phone}
                          onChange={(e) => setPhone(e.target.value)}
                          placeholder="e.g. 9876543210"
                          className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Describe Your Issue / Question</label>
                      <textarea
                        rows={3}
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        placeholder="Please describe what problem you are facing or which question/chapter you have a doubt in..."
                        className="w-full rounded-lg border border-slate-200 p-2.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                      />
                    </div>
                  </div>

                  {/* Dual Action Buttons */}
                  <div className="space-y-2 pt-1">
                    <button
                      type="button"
                      onClick={() => launchWhatsApp()}
                      className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-700 hover:shadow-md active:scale-[0.99]"
                    >
                      <Phone className="size-4" />
                      Send on WhatsApp (+{config.support_whatsapp_number})
                    </button>

                    <button
                      type="button"
                      disabled={submitting}
                      onClick={handleSendEmail}
                      className="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-indigo-700 hover:shadow-md active:scale-[0.99] disabled:opacity-60"
                    >
                      {submitting ? (
                        <>
                          <Loader2 className="size-4 animate-spin" />
                          Submitting Ticket...
                        </>
                      ) : (
                        <>
                          <Mail className="size-4" />
                          Submit Query via Email
                        </>
                      )}
                    </button>
                  </div>

                  <button
                    type="button"
                    onClick={resetToMenu}
                    className="flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 pt-1 transition-colors"
                  >
                    <ArrowLeft className="size-3" />
                    Back to options
                  </button>
                </div>
              </>
            )}

            {/* STEP: Direct WhatsApp Picker */}
            {step === "whatsapp_picker" && (
              <>
                <div className="flex items-start gap-2.5">
                  <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-semibold text-xs border border-indigo-200">
                    <Bot className="size-4" />
                  </div>
                  <div className="rounded-2xl rounded-tl-sm bg-white p-3.5 text-slate-800 shadow-sm border border-slate-200/80 leading-relaxed max-w-[88%]">
                    <p>Our mentors and faculty are online! Choose a quick topic or chat directly:</p>
                  </div>
                </div>

                <div className="ml-10 space-y-2">
                  {[
                    "I have a doubt in today's lesson",
                    "Course validity & test access question",
                    "Sponsorship Exam & Interview guidance",
                    "General inquiry about My Merchant Navy",
                  ].map((preset, idx) => (
                    <button
                      key={idx}
                      onClick={() => launchWhatsApp(preset)}
                      className="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white p-2.5 text-left text-xs font-medium text-slate-800 hover:border-emerald-500 hover:bg-emerald-50/30 transition-all shadow-sm"
                    >
                      <span>{preset}</span>
                      <Phone className="size-3.5 text-emerald-600" />
                    </button>
                  ))}

                  <button
                    onClick={() => launchWhatsApp()}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-all"
                  >
                    <Phone className="size-4" />
                    Open WhatsApp Chat (+{config.support_whatsapp_number})
                  </button>

                  <button
                    onClick={resetToMenu}
                    className="flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 pt-1"
                  >
                    <ArrowLeft className="size-3" />
                    Back to options
                  </button>
                </div>
              </>
            )}

            {/* STEP 3: Submitted Confirmation Bubble */}
            {step === "submitted" && (
              <>
                <div className="flex items-start gap-2.5">
                  <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 font-semibold text-xs border border-emerald-200">
                    <CheckCircle2 className="size-4" />
                  </div>
                  <div className="rounded-2xl rounded-tl-sm bg-white p-4 text-slate-800 shadow-sm border border-emerald-100 space-y-2 max-w-[88%]">
                    <div className="flex items-center gap-1.5 text-emerald-700 font-bold text-sm">
                      <CheckCircle2 className="size-4" />
                      Issue Submitted Successfully!
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Thank you, <strong>{name || "Student"}</strong>. Your query has been delivered to our academic faculty at <span className="font-semibold">{config.support_email}</span>.
                    </p>
                    <p className="text-xs text-slate-500 leading-relaxed">
                      We usually review and reply to your email within a few hours. For immediate follow-up, click below to chat on WhatsApp:
                    </p>
                    
                    <div className="pt-2 space-y-2">
                      <button
                        onClick={() => launchWhatsApp(`Following up on query: ${message}`)}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-all"
                      >
                        <Phone className="size-3.5" />
                        Follow up on WhatsApp
                      </button>

                      <button
                        onClick={resetToMenu}
                        className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-all"
                      >
                        <RotateCcw className="size-3.5" />
                        Ask Another Question
                      </button>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>

          {/* Chat Footer */}
          <div className="border-t border-slate-100 bg-white px-4 py-2.5 text-center">
            <p className="text-[11px] text-slate-400">
              My Merchant Navy Support Desk · Direct Faculty Connect
            </p>
          </div>
        </div>
      )}
    </>
  );
}
