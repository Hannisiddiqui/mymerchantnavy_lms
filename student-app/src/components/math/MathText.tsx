import katex from "katex";
import "katex/contrib/mhchem"; // chemistry: $\ce{H2O}$, $\ce{2H2 + O2 -> 2H2O}$
import { cn } from "@/lib/utils";
import { cleanHtml } from "@/lib/sanitize";

/**
 * Renders admin-written HTML that contains LaTeX (KaTeX).
 *   Inline:  $x^2 + y^2$      or  \(x^2 + y^2\)
 *   Block:   $$\int_0^1 x\,dx$$  or  \[\int_0^1 x\,dx\]
 * Used for questions, options, explanations, notes and results.
 * (HTML is cleaned by the backend before it is stored AND again here before it is shown.)
 */
const MATH = /\$\$([\s\S]+?)\$\$|\\\[([\s\S]+?)\\\]|\$([^$\n]+?)\$|\\\(([\s\S]+?)\\\)/g;

/** The stored HTML escapes < > & inside a formula too ("x &lt; 4"); KaTeX needs the real characters. */
const unescapeHtml = (s: string) => s.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, "&");

export function renderMath(html: string) {
  return html.replace(MATH, (_, block: string | undefined, bracket: string | undefined, inline: string | undefined, paren: string | undefined) => {
    const display = block !== undefined || bracket !== undefined;
    return katex.renderToString(unescapeHtml((block ?? bracket ?? inline ?? paren)!), {
      displayMode: display,
      throwOnError: false,
      strict: "ignore",
    });
  });
}

export function MathText({ html, className, as: Tag = "div" }: { html: string; className?: string; as?: "div" | "span" }) {
  return <Tag className={cn("rich-text", className)} dangerouslySetInnerHTML={{ __html: renderMath(cleanHtml(html)) }} />;
}
