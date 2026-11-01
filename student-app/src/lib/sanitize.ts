import DOMPurify from "isomorphic-dompurify";

/**
 * Second lock: the backend already cleans admin HTML before it is stored, and this cleans it again before it is shown,
 * so even a bad record in the database (or a future bug) can never run a script in a student's browser.
 * YouTube / Vimeo players are the only iframes allowed.
 */
const VIDEO_HOSTS = ["www.youtube.com", "youtube.com", "www.youtube-nocookie.com", "player.vimeo.com"];

let hooked = false;
function ensureHook() {
  if (hooked) return;
  hooked = true;
  DOMPurify.addHook("uponSanitizeElement", (node, data) => {
    if (data.tagName === "iframe") {
      const src = (node as Element).getAttribute?.("src") ?? "";
      try {
        const u = new URL(src);
        if (u.protocol !== "https:" || !VIDEO_HOSTS.includes(u.hostname)) node.parentNode?.removeChild(node);
      } catch {
        node.parentNode?.removeChild(node);
      }
    }
  });
  DOMPurify.addHook("afterSanitizeAttributes", (node) => {
    if (node.tagName === "A" && node.getAttribute("target")) node.setAttribute("rel", "noopener noreferrer");
  });
}

export function cleanHtml(html: string | null | undefined): string {
  ensureHook();
  return DOMPurify.sanitize(html ?? "", {
    ADD_TAGS: ["iframe"],
    ADD_ATTR: ["allowfullscreen", "target"],
    FORBID_TAGS: ["style", "form", "input", "button", "textarea", "select", "object", "embed", "svg", "math"],
    FORBID_ATTR: ["srcdoc", "formaction"],
  });
}
