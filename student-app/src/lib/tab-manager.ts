/**
 * Multi-tab coordinator for LMS sessions.
 * Enforces Super Admin's concurrent browser tab limit (e.g. 1 or 2 tabs).
 * If student opens more tabs than allowed, excess tabs are blocked and
 * instructed to close other tabs.
 */

const STORAGE_KEY = "mmn.active_tabs";
const SESSION_TAB_ID_KEY = "mmn.tab_id";
const CHANNEL_NAME = "mmn_tab_coordinator";
const STALE_TIMEOUT_MS = 6000; // 6 seconds without heartbeat = dead tab

type TabRegistry = Record<string, number>;

export function getTabId(): string {
  if (typeof window === "undefined") return "";
  let id = window.sessionStorage.getItem(SESSION_TAB_ID_KEY);
  if (!id) {
    id = "tab_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 8);
    window.sessionStorage.setItem(SESSION_TAB_ID_KEY, id);
  }
  return id;
}

function getStoredRegistry(): TabRegistry {
  if (typeof window === "undefined") return {};
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function saveRegistry(registry: TabRegistry): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(registry));
  } catch {
    // ignore quota errors
  }
}

function pruneRegistry(registry: TabRegistry, now = Date.now()): TabRegistry {
  const pruned: TabRegistry = {};
  for (const [id, ts] of Object.entries(registry)) {
    if (now - ts < STALE_TIMEOUT_MS) {
      pruned[id] = ts;
    }
  }
  return pruned;
}

let channel: BroadcastChannel | null = null;
function getChannel(): BroadcastChannel | null {
  if (typeof window === "undefined" || typeof BroadcastChannel === "undefined") return null;
  if (!channel) {
    channel = new BroadcastChannel(CHANNEL_NAME);
  }
  return channel;
}

export function notifyTabChange(action: "tab_opened" | "tab_closed" | "heartbeat"): void {
  const ch = getChannel();
  if (ch) {
    try {
      ch.postMessage({ action, tabId: getTabId(), timestamp: Date.now() });
    } catch {
      // ignore
    }
  }
}

export interface TabCheckResult {
  allowed: boolean;
  activeCount: number;
  maxTabs: number;
}

/**
 * Checks if current tab is permitted or exceeds concurrent tab limit.
 */
export function checkAndClaimTabSlot(maxTabs: number, enabled: boolean): TabCheckResult {
  if (typeof window === "undefined") {
    return { allowed: true, activeCount: 1, maxTabs };
  }

  if (!enabled || maxTabs <= 0) {
    return { allowed: true, activeCount: 1, maxTabs };
  }

  const tabId = getTabId();
  const now = Date.now();
  const current = pruneRegistry(getStoredRegistry(), now);

  // If this tab is already registered as active, refresh timestamp and allow
  if (current[tabId]) {
    current[tabId] = now;
    saveRegistry(current);
    return {
      allowed: true,
      activeCount: Object.keys(current).length,
      maxTabs,
    };
  }

  const activeCount = Object.keys(current).length;

  // If active tabs are already at or exceeding limit, block this new tab!
  if (activeCount >= maxTabs) {
    return {
      allowed: false,
      activeCount,
      maxTabs,
    };
  }

  // Slot available: claim it!
  current[tabId] = now;
  saveRegistry(current);
  notifyTabChange("tab_opened");

  return {
    allowed: true,
    activeCount: Object.keys(current).length,
    maxTabs,
  };
}

/**
 * Heartbeat call to let other tabs know this tab is still active.
 */
export function keepTabAlive(): void {
  if (typeof window === "undefined") return;
  const tabId = getTabId();
  const now = Date.now();
  const current = pruneRegistry(getStoredRegistry(), now);
  if (current[tabId]) {
    current[tabId] = now;
    saveRegistry(current);
  }
}

/**
 * Release this tab slot when tab is closed or user logs out.
 */
export function releaseTabSlot(): void {
  if (typeof window === "undefined") return;
  const tabId = getTabId();
  const current = getStoredRegistry();
  if (current[tabId]) {
    delete current[tabId];
    saveRegistry(current);
    notifyTabChange("tab_closed");
  }
}

/**
 * Subscribe to cross-tab updates (other tabs closing or opening).
 */
export function subscribeToTabEvents(onChange: () => void): () => void {
  if (typeof window === "undefined") return () => {};

  const ch = getChannel();
  const onMessage = () => onChange();
  if (ch) {
    ch.addEventListener("message", onMessage);
  }

  const onStorage = (e: StorageEvent) => {
    if (e.key === STORAGE_KEY) {
      onChange();
    }
  };
  window.addEventListener("storage", onStorage);
  window.addEventListener("focus", onChange);

  return () => {
    if (ch) {
      ch.removeEventListener("message", onMessage);
    }
    window.removeEventListener("storage", onStorage);
    window.removeEventListener("focus", onChange);
  };
}
