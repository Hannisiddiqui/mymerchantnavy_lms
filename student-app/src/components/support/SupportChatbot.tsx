"use client";

import { useEffect, useState, useRef } from "react";
import { 
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
  RotateCcw,
  CheckCheck,
  ExternalLink
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

interface ChatMessage {
  id: string;
  sender: "support" | "student";
  text: string;
  time: string;
  category?: string;
}

const CATEGORIES: IssueCategory[] = [
  {
    id: "whatsapp_chat",
    title: "Chat on WhatsApp",
    description: "Connect 1-on-1 with counselors directly inside this chatbot",
    icon: Phone,
    defaultPrompt: "Hello My Merchant Navy, I would like to speak with an academic counselor.",
  },
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
    defaultPrompt: "I have a query regarding my course payment or package assignment.",
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
  const [step, setStep] = useState<"menu" | "form" | "whatsapp_chat" | "submitted">("menu");
  const [selectedCategory, setSelectedCategory] = useState<IssueCategory | null>(null);

  const [config, setConfig] = useState<SupportConfig>({
    support_email: "support.mymerchantnavy@gmail.com",
    support_whatsapp_number: "919205914737",
  });

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");
  const [whatsappInput, setWhatsappInput] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  
  const [whatsappMessages, setWhatsappMessages] = useState<ChatMessage[]>([]);

  const chatScrollRef = useRef<HTMLDivElement>(null);

  // Fetch dynamic support config & logged in student details on mount
  useEffect(() => {
    fetch(`${apiBaseUrl()}/support/config`)
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (data?.data) {
          setConfig({
            support_email: data.data.support_email || "support.mymerchantnavy@gmail.com",
            support_whatsapp_number: data.data.support_whatsapp_number || "919205914737",
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

  // Initialize WhatsApp in-chat message stream
  useEffect(() => {
    if (step === "whatsapp_chat" && whatsappMessages.length === 0) {
      setWhatsappMessages([
        {
          id: "welcome-1",
          sender: "support",
          text: "Namaste! 👋 Welcome to My Merchant Navy Support. You are connected with our official Counselor Desk. How can we help you with your preparation or courses today?",
          time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        },
      ]);
    }
  }, [step, whatsappMessages.length]);

  // Auto-scroll chat to bottom
  useEffect(() => {
    if (chatScrollRef.current) {
      chatScrollRef.current.scrollTop = chatScrollRef.current.scrollHeight;
    }
  }, [step, selectedCategory, whatsappMessages]);

  function handleSelectCategory(cat: IssueCategory) {
    setSelectedCategory(cat);
    if (cat.id === "whatsapp_chat") {
      setWhatsappInput(cat.defaultPrompt);
      setStep("whatsapp_chat");
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

  function openExternalWhatsApp(customText?: string) {
    const textToSend = customText || whatsappInput || message || "Hello My Merchant Navy Support";
    const cleanNum = config.support_whatsapp_number.replace(/\D/g, "");
    const url = `https://wa.me/${cleanNum}?text=${encodeURIComponent(textToSend)}`;
    window.open(url, "_blank");
  }

  // Send WhatsApp message directly inside the chatbot without redirecting
  async function handleSendInChatWhatsApp() {
    const text = whatsappInput.trim();
    if (!text) return;

    const timeNow = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    const userMsgId = `student-${Date.now()}`;

    // Add student message bubble
    setWhatsappMessages((prev) => [
      ...prev,
      {
        id: userMsgId,
        sender: "student",
        text,
        time: timeNow,
        category: selectedCategory?.title,
      },
    ]);
    setWhatsappInput("");
    setSubmitting(true);

    try {
      // Dispatches the single tailored email to support desk behind the scenes
      await fetch(`${apiBaseUrl()}/support/query`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: name || "Student",
          email: email || "student@mymerchantnavy.com",
          phone: phone || config.support_whatsapp_number,
          category: selectedCategory?.title || "WhatsApp Support Query",
          message: text,
        }),
      });

      // Add automated counselor acknowledgment bubble
      setTimeout(() => {
        setWhatsappMessages((prev) => [
          ...prev,
          {
            id: `reply-${Date.now()}`,
            sender: "support",
            text: `Thank you, ${name || "Aspirant"}! Your message has been received by our counseling desk (+${config.support_whatsapp_number}). A mentor is reviewing your query and will reply promptly.`,
            time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
          },
        ]);
        setSubmitting(false);
      }, 700);
    } catch {
      setSubmitting(false);
    }
  }

  // Handle standard query form submission
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
        throw new Error("Unable to submit query. Please try WhatsApp directly.");
      }

      setStep("submitted");
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to deliver message. Please try WhatsApp.";
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
            className="group flex items-center gap-3 rounded-full bg-gradient-to-r from-navy-900 via-navy-800 to-brand-700 px-5 py-3.5 text-white shadow-2xl transition-all duration-300 hover:scale-105 hover:shadow-brand-500/25 active:scale-95 focus:outline-none border border-brand-400/30"
            aria-label="Open Support Assistant"
          >
            <div className="relative flex size-6 items-center justify-center rounded-full bg-white/20">
              <Bot className="size-4 text-white" />
              <span className="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-emerald-400 ring-2 ring-navy-900" />
            </div>
            <div className="text-left leading-tight">
              <span className="block text-[11px] font-semibold tracking-wide text-brand-200 uppercase">Need Help?</span>
              <span className="block text-sm font-bold">Ask Support</span>
            </div>
          </button>
        )}
      </div>

      {/* Chat Window Modal */}
      {isOpen && (
        <div className="fixed bottom-4 right-4 z-50 flex h-[620px] max-h-[92vh] w-[420px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-2xl transition-all duration-300 animate-in fade-in slide-in-from-bottom-4">
          
          {/* Header */}
          {step === "whatsapp_chat" ? (
            /* Authentic WhatsApp In-Chat Header */
            <div className="flex items-center justify-between bg-[#075e54] px-4 py-3 text-white shadow-md">
              <div className="flex items-center gap-2.5">
                <button
                  onClick={resetToMenu}
                  className="rounded-full p-1.5 hover:bg-white/15 transition text-white"
                  title="Go back to raise other issues"
                >
                  <ArrowLeft className="size-5" />
                </button>
                <div className="relative flex size-9 items-center justify-center rounded-full bg-white/20">
                  <Phone className="size-4 text-white" />
                  <span className="absolute bottom-0 right-0 size-2.5 rounded-full bg-emerald-400 ring-2 ring-[#075e54]" />
                </div>
                <div>
                  <div className="flex items-center gap-1.5">
                    <h3 className="text-sm font-bold text-white">MMN Support</h3>
                    <span className="rounded bg-emerald-500/40 px-1 py-0.2 text-[9px] font-bold uppercase tracking-wider text-emerald-100">
                      WhatsApp
                    </span>
                  </div>
                  <p className="text-[11px] text-emerald-100/90 leading-tight">
                    Online • +{config.support_whatsapp_number}
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-1">
                <button
                  onClick={resetToMenu}
                  className="rounded-lg bg-white/10 hover:bg-white/20 px-2.5 py-1 text-xs font-semibold text-white transition"
                  title="Raise another issue"
                >
                  Menu
                </button>
                <button
                  onClick={() => setIsOpen(false)}
                  className="rounded-lg p-1.5 text-emerald-100 hover:bg-white/10 hover:text-white transition"
                  aria-label="Close"
                >
                  <X className="size-5" />
                </button>
              </div>
            </div>
          ) : (
            /* Standard LMS Support Header */
            <div className="flex items-center justify-between bg-gradient-to-r from-navy-900 via-navy-800 to-brand-800 px-5 py-4 text-white shadow-md">
              <div className="flex items-center gap-3">
                <div className="relative flex size-10 items-center justify-center rounded-full bg-white/15 ring-2 ring-white/30 backdrop-blur-sm">
                  <Bot className="size-5 text-white" />
                  <span className="absolute bottom-0 right-0 size-2.5 rounded-full bg-emerald-400 ring-2 ring-navy-900" />
                </div>
                <div>
                  <h3 className="text-base font-bold tracking-tight">Support Assistant</h3>
                  <p className="text-xs font-medium text-brand-200">Instant Issue Resolution & Help</p>
                </div>
              </div>
              
              <div className="flex items-center gap-1">
                {step !== "menu" && (
                  <button
                    onClick={resetToMenu}
                    title="Raise another issue"
                    className="rounded-lg p-1.5 text-brand-200 hover:bg-white/10 hover:text-white transition-colors"
                  >
                    <RotateCcw className="size-4" />
                  </button>
                )}
                <button
                  onClick={() => setIsOpen(false)}
                  className="rounded-lg p-1.5 text-brand-200 hover:bg-white/10 hover:text-white transition-colors"
                  aria-label="Close Support Assistant"
                >
                  <X className="size-5" />
                </button>
              </div>
            </div>
          )}

          {/* Conversation Stream */}
          <div 
            ref={chatScrollRef}
            className={`flex-1 overflow-y-auto p-4 space-y-3.5 text-sm ${
              step === "whatsapp_chat" ? "bg-[#efeae2]" : "bg-slate-50"
            }`}
          >
            {/* IN-CHAT WHATSAPP SCREEN (No external redirect) */}
            {step === "whatsapp_chat" ? (
              <div className="space-y-3">
                {/* Back to raise other issues button at top */}
                <div className="flex justify-center pb-1">
                  <button
                    onClick={resetToMenu}
                    className="inline-flex items-center gap-1.5 rounded-full bg-white/90 border border-slate-300 px-3.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-white transition"
                  >
                    <ArrowLeft className="size-3 text-slate-500" />
                    Back to raise another issue
                  </button>
                </div>

                {/* Date stamp pill */}
                <div className="flex justify-center">
                  <span className="rounded-lg bg-white/80 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-sm uppercase tracking-wide">
                    WhatsApp Chat • Today
                  </span>
                </div>

                {/* Message bubbles */}
                {whatsappMessages.map((m) => {
                  const isStudent = m.sender === "student";
                  return (
                    <div
                      key={m.id}
                      className={`flex ${isStudent ? "justify-end" : "justify-start"} animate-in fade-in duration-200`}
                    >
                      <div
                        className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 shadow-sm text-xs leading-relaxed ${
                          isStudent
                            ? "bg-[#d9fdd3] text-slate-900 rounded-tr-sm border border-[#c1e8ba]"
                            : "bg-white text-slate-900 rounded-tl-sm border border-slate-200/80"
                        }`}
                      >
                        <p className="whitespace-pre-wrap">{m.text}</p>
                        <div className="mt-1 flex items-center justify-end gap-1 text-[10px] text-slate-500">
                          <span>{m.time}</span>
                          {isStudent && <CheckCheck className="size-3 text-[#53bdeb]" />}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              /* STANDARD BOT FLOW */
              <>
                {/* Bot Greeting Bubble */}
                <div className="flex items-start gap-2.5">
                  <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-semibold text-xs border border-brand-200">
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
                      const isWa = cat.id === "whatsapp_chat";
                      return (
                        <button
                          key={cat.id}
                          onClick={() => handleSelectCategory(cat)}
                          className={`group flex w-full items-start gap-3 rounded-xl border p-3 text-left shadow-sm transition-all duration-200 hover:shadow-md active:scale-[0.99] ${
                            isWa 
                              ? "border-emerald-200 bg-emerald-50/50 hover:border-emerald-500 hover:bg-emerald-50" 
                              : "border-line bg-white hover:border-brand-400 hover:bg-brand-50/40"
                          }`}
                        >
                          <div className={`flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors ${
                            isWa 
                              ? "bg-emerald-100 text-emerald-700 group-hover:bg-emerald-600 group-hover:text-white" 
                              : "bg-slate-100 text-slate-700 group-hover:bg-brand-600 group-hover:text-white"
                          }`}>
                            <Icon className="size-4" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between">
                              <h4 className="text-sm font-semibold text-slate-900 group-hover:text-brand-700 transition-colors">
                                {cat.title}
                              </h4>
                              {isWa && (
                                <span className="rounded bg-emerald-600 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">
                                  In-Chat
                                </span>
                              )}
                            </div>
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
                    <div className="max-w-[80%] rounded-2xl rounded-tr-sm bg-gradient-to-r from-navy-900 to-brand-700 px-4 py-2 text-white shadow-sm font-medium text-xs">
                      {selectedCategory.title}
                    </div>
                  </div>
                )}

                {/* STEP 2: Issue Details & Raising Form */}
                {step === "form" && selectedCategory && (
                  <>
                    <div className="flex items-start gap-2.5">
                      <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-semibold text-xs border border-brand-200">
                        <Bot className="size-4" />
                      </div>
                      <div className="rounded-2xl rounded-tl-sm bg-white p-3.5 text-slate-800 shadow-sm border border-slate-200/80 leading-relaxed max-w-[88%]">
                        <p>
                          Please fill in your details below. You can chat on WhatsApp inside this bot or submit via Email:
                        </p>
                      </div>
                    </div>

                    <div className="ml-10 rounded-2xl border border-line bg-white p-4 shadow-sm space-y-3">
                      {errorMsg && (
                        <div className="rounded-lg bg-rose-50 border border-rose-200 p-2 text-xs text-rose-700">
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
                            className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
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
                              className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            />
                          </div>
                          <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">Phone / WhatsApp</label>
                            <input
                              type="tel"
                              value={phone}
                              onChange={(e) => setPhone(e.target.value)}
                              placeholder="e.g. 9876543210"
                              className="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            />
                          </div>
                        </div>

                        <div>
                          <label className="block text-xs font-semibold text-slate-700 mb-1">Describe Your Issue / Question</label>
                          <textarea
                            rows={3}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder="Describe what issue you are facing..."
                            className="w-full rounded-lg border border-slate-200 p-2.5 text-xs text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                          />
                        </div>
                      </div>

                      {/* Action Buttons: Chat on WhatsApp inside bot OR Submit Email */}
                      <div className="space-y-2 pt-1">
                        <button
                          type="button"
                          onClick={() => {
                            setWhatsappInput(message);
                            setStep("whatsapp_chat");
                          }}
                          className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#075e54] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-[#0c7064] hover:shadow-md active:scale-[0.99]"
                        >
                          <Phone className="size-4" />
                          Chat on WhatsApp Inside Bot
                        </button>

                        <button
                          type="button"
                          disabled={submitting}
                          onClick={handleSendEmail}
                          className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-brand-700 hover:shadow-md active:scale-[0.99] disabled:opacity-60"
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

                      <div className="flex items-center justify-between pt-1">
                        <button
                          type="button"
                          onClick={resetToMenu}
                          className="flex items-center gap-1 text-xs text-slate-500 hover:text-brand-600 transition-colors"
                        >
                          <ArrowLeft className="size-3" />
                          Back to raise other issues
                        </button>
                      </div>
                    </div>
                  </>
                )}

                {/* STEP 3: Submitted Confirmation Screen */}
                {step === "submitted" && (
                  <div className="flex items-start gap-2.5">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 font-semibold text-xs border border-emerald-200">
                      <CheckCircle2 className="size-4" />
                    </div>
                    <div className="rounded-2xl rounded-tl-sm bg-white p-4 text-slate-800 shadow-sm border border-emerald-100 space-y-2.5 max-w-[88%]">
                      <div className="flex items-center gap-1.5 text-emerald-700 font-bold text-sm">
                        <CheckCircle2 className="size-4" />
                        Query Submitted Successfully!
                      </div>
                      <p className="text-xs text-slate-600 leading-relaxed">
                        Thank you, <strong>{name || "Student"}</strong>. Your query has been delivered directly to our academic faculty desk at <span className="font-semibold text-navy-900">{config.support_email}</span>.
                      </p>
                      <p className="text-xs text-slate-500 leading-relaxed">
                        We will reply directly to your email shortly. You can also chat with our counselor right here:
                      </p>
                      
                      <div className="pt-2 space-y-2">
                        <button
                          onClick={() => {
                            setWhatsappInput(`Following up on query: ${message}`);
                            setStep("whatsapp_chat");
                          }}
                          className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#075e54] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#0c7064] transition-all"
                        >
                          <Phone className="size-3.5" />
                          Chat with Counselor on WhatsApp
                        </button>

                        <button
                          onClick={resetToMenu}
                          className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-all"
                        >
                          <ArrowLeft className="size-3.5" />
                          Back to Raise Another Issue
                        </button>
                      </div>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>

          {/* Chat Footer / Input area */}
          {step === "whatsapp_chat" ? (
            /* WhatsApp Input Bar inside Chatbot */
            <div className="border-t border-slate-200 bg-[#f0f2f5] p-2.5">
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={whatsappInput}
                  onChange={(e) => setWhatsappInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault();
                      handleSendInChatWhatsApp();
                    }
                  }}
                  placeholder="Type your WhatsApp message..."
                  className="flex-1 rounded-full border border-slate-300 bg-white px-4 py-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                />
                <button
                  onClick={handleSendInChatWhatsApp}
                  disabled={submitting || !whatsappInput.trim()}
                  className="flex size-9 items-center justify-center rounded-full bg-[#075e54] text-white shadow-sm hover:bg-[#0c7064] active:scale-95 transition disabled:opacity-50"
                  title="Send message"
                >
                  {submitting ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                </button>
              </div>

              <div className="flex items-center justify-between mt-2 px-1 text-[11px] text-slate-500">
                <button
                  onClick={resetToMenu}
                  className="inline-flex items-center gap-1 font-semibold text-slate-600 hover:text-slate-900"
                >
                  <ArrowLeft className="size-3" />
                  Raise another issue
                </button>
                <button
                  onClick={() => openExternalWhatsApp()}
                  className="inline-flex items-center gap-1 text-[10px] text-emerald-700 hover:underline"
                  title="Open external WhatsApp app"
                >
                  Open WhatsApp App
                  <ExternalLink className="size-2.5" />
                </button>
              </div>
            </div>
          ) : (
            /* Standard LMS Footer */
            <div className="border-t border-slate-100 bg-white px-4 py-2 text-center flex items-center justify-between text-[11px] text-slate-400">
              <span>My Merchant Navy Support Desk</span>
              {step !== "menu" && (
                <button
                  onClick={resetToMenu}
                  className="text-brand-600 hover:underline font-semibold"
                >
                  Raise another issue
                </button>
              )}
            </div>
          )}
        </div>
      )}
    </>
  );
}
