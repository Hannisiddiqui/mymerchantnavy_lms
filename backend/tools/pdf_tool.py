#!/usr/bin/env python3
"""
PDF helper used by the Laravel backend (app/Services/Pdf/PdfTool.php).
Needs PyMuPDF:  pip install pymupdf

Commands (JSON is written to stdout, UTF-8):
  extract <pdf>                         text of every page, rebuilt line by line from glyph positions
  render  <pdf> <out_dir> [--dpi 110]   page images 1.png, 2.png, ...
  split   <pdf> <out_pdf> <from> <to>   copy pages from..to (1-based, inclusive) into a new PDF

Why not plain text extraction? Special glyphs (cube root, symbols in another font) are often stored as
separate text runs and come out in the wrong place. Typeset maths (LaTeX) is worse: a fraction is two
rows of digits and a drawn bar, a square root is a symbol plus a drawn bar, a vector hat is a floating mark.
Here every CHARACTER is placed by its coordinates, and the drawn bars are used to rebuild:
    fractions       16/3, (a + b)/(c − d)
    square roots    √(8² + 6²)
    vector hats     î ĵ n̂ (attached to their letter)
    powers/indices  x², F₁, A_{x}
    printed vectors single bold letters are marked with \\x01 … \\x02 in the "rich" text
"""
import argparse
import json
import os
import re
import sys
import unicodedata

try:
    import fitz  # PyMuPDF
except ImportError:
    sys.stdout.write(json.dumps({"error": "PyMuPDF is not installed. Run: pip install pymupdf"}))
    sys.exit(2)

SUPERSCRIPT = str.maketrans("0123456789+-=()ni−", "⁰¹²³⁴⁵⁶⁷⁸⁹⁺⁻⁼⁽⁾ⁿⁱ⁻")   # "−" is the typographic minus
SUBSCRIPT = str.maketrans("0123456789+-=()−", "₀₁₂₃₄₅₆₇₈₉₊₋₌₍₎₋")
COMBINING = {chr(c) for c in range(0x0300, 0x0370)} | {chr(c) for c in range(0x20D0, 0x2100)}
ROOT_GLYPHS = "√∛∜"
BOLD_OPEN, BOLD_CLOSE = "\x01", "\x02"   # around a single bold letter (a printed vector such as A or î)
# Around a rebuilt fraction or root in the "rich" text:  \x03 <LaTeX> \x05 <plain text> \x04
# (the plain text is what the "text" field and the answer-line comparison use; the LaTeX is what gets stored)
MATH_OPEN, MATH_SEP, MATH_CLOSE = "\x03", "\x05", "\x04"
ROOT_TEX = {"√": "\\sqrt{%s}", "∛": "\\sqrt[3]{%s}", "∜": "\\sqrt[4]{%s}"}
MATH_MARKS = re.compile("\x03[^\x05]*\x05([^\x04]*)\x04")


def out(data):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stdout.write(json.dumps(data, ensure_ascii=False))


# ----------------------------------------------------------------------------- reading characters

def collect_chars(page):
    """Every character of the page with its box. Math-italic letters (𝜆 𝑡 𝐴) become plain letters (λ t A)."""
    chars = []
    order = 0
    for block in page.get_text("rawdict")["blocks"]:
        for line in block.get("lines", []):
            for span in line["spans"]:
                bold = "bold" in span["font"].lower() or bool(span["flags"] & 16)
                last_x = -1.0
                for ch in span["chars"]:
                    x0, y0, x1, y1 = ch["bbox"]
                    c = ch["c"]
                    if 0x1D400 <= ord(c) <= 0x1D7FF:
                        c = unicodedata.normalize("NFKC", c)
                    # Inside ONE text run the stream order is right (ligatures such as "ti" have overlapping
                    # boxes), so never let x go backwards within a run. Other runs (e.g. a ∛ symbol in a
                    # different font) are placed by their real position.
                    x_sort = max(x0, last_x + 1e-3)
                    last_x = x_sort
                    order += 1
                    chars.append({"c": c, "x0": x0, "y0": y0, "x1": x1, "y1": y1, "xs": x_sort, "o": order,
                                  "size": span["size"], "bold": bold, "dead": False, "math": None})
    return chars


def collect_rules(page):
    """Thin horizontal lines drawn on the page: fraction bars and the bar over a root. [(x0, x1, y)]"""
    rules = []
    for drawing in page.get_drawings():
        r = drawing["rect"]
        if r.height <= 0.8 and 3 <= r.width <= 200:
            rules.append((r.x0, r.x1, (r.y0 + r.y1) / 2))
    return rules


# ----------------------------------------------------------------------------- maths composition

def alive(chars):
    return [c for c in chars if not c["dead"] and c["c"].strip()]


def render(chars):
    """Text of a group of characters, left to right (used for numerators, radicands…)."""
    chars = sorted(chars, key=lambda c: (c["xs"], c["o"]))
    if not chars:
        return ""
    main = max(c["size"] for c in chars)  # a numerator or radicand may hold more indices than letters
    return clean(build_text(chars, main, baseline(chars, main))).strip()


def render_tex(chars):
    """Same as render() but as LaTeX (inner fractions and roots included): the part inside \\frac{..}{..} or \\sqrt{..}."""
    chars = sorted(chars, key=lambda c: (c["xs"], c["o"]))
    if not chars:
        return ""
    main = max(c["size"] for c in chars)
    return build_text(chars, main, baseline(chars, main), tex=True).strip()


def pseudo(text, x0, x1, y0, y1, size, kind, order, tex=None):
    return {"c": text, "x0": x0, "y0": y0, "x1": x1, "y1": y1, "xs": x0, "o": order, "size": size,
            "bold": False, "dead": False, "math": kind, "tex": tex}


def wrap(text):
    """Brackets around anything that is not a single number or word, so 'a+b' over 'c' stays readable."""
    return text if re.fullmatch(r"[\w.,−-]*\w", text) and not re.search(r"[−-]", text[1:]) else f"({text})"


def compose_math(chars, rules):
    """Replaces hats, roots and fractions by single pseudo characters carrying their final text."""
    order = max((c["o"] for c in chars), default=0)

    # 1. Hats: a zero-width combining mark floating over its letter
    for hat in [c for c in chars if c["c"] in COMBINING]:
        best, best_dx = None, 5.0
        hx = (hat["x0"] + hat["x1"]) / 2
        for base in chars:
            if base is hat or base["dead"] or base["c"] in COMBINING or not base["c"][:1].isalpha():
                continue
            dx = abs((base["x0"] + base["x1"]) / 2 - hx)
            if dx <= best_dx and abs((base["y0"] + base["y1"]) / 2 - (hat["y0"] + hat["y1"]) / 2) <= 0.9 * hat["size"]:
                best, best_dx = base, dx
        if best:
            best["c"] = unicodedata.normalize("NFC", best["c"] + hat["c"])  # i + ̂  →  î
            best["x1"] = max(best["x1"], hat["x1"])
        hat["dead"] = True

    # 2. Square roots: the √ glyph's right edge touches the left end of a bar drawn over the radicand
    root_bars = set()
    for x0, x1, y in rules:
        root = next((c for c in chars if not c["dead"] and c["c"] in ROOT_GLYPHS
                     and abs(c["x1"] - x0) <= 2.5 and c["y0"] - 4.5 <= y <= c["y1"] + 1.5), None)
        if not root:
            continue
        radicand = [c for c in alive(chars) if c is not root and c["c"] not in ROOT_GLYPHS and c["x0"] >= x0 - 0.8 and c["x1"] <= x1 + 0.8
                    and c["y0"] >= y - 1.5 and c["y0"] <= y + 0.95 * root["size"] and c["y1"] <= y + 1.6 * root["size"]]
        if not radicand:
            continue
        text = render(radicand)
        tex = ROOT_TEX.get(root["c"], ROOT_TEX["√"]) % render_tex(radicand)
        root_bars.add((x0, x1, y))
        for c in radicand:
            c["dead"] = True
        root["dead"] = True
        order += 1
        chars.append(pseudo(root["c"] + wrap(text), root["x0"], x1, min(c["y0"] for c in radicand), max(c["y1"] for c in radicand),
                            root["size"], "root", order, tex))

    # 3. Fractions: a bar with a tight stack of characters above and below it
    for x0, x1, y in rules:
        if x1 - x0 > 160 or (x0, x1, y) in root_bars:
            continue
        pool = alive(chars)
        center = lambda c: (c["x0"] + c["x1"]) / 2
        mid = lambda c: (c["y0"] + c["y1"]) / 2
        under_bar = [c for c in pool if x0 - 1.5 <= center(c) <= x1 + 1.5]
        over = [c for c in under_bar if c["y1"] <= y + 1.0 and y - c["y1"] <= 4.5]
        under = [c for c in under_bar if c["y0"] >= y - 1.0 and c["y0"] - y <= 4.5]
        if not over or not under:
            continue  # not a tight stack: a table line, an underline or the page header rule
        # Numerator and denominator are the ONE text row touching the bar on each side. A row is found from the
        # character closest to the bar; anything a full row further away (the next line of options, say) is not part of it.
        top = max(over, key=lambda c: c["y1"])
        bottom = min(under, key=lambda c: c["y0"])
        above = [c for c in under_bar if c["y1"] <= y + 1.0 and abs(mid(c) - mid(top)) <= 0.75 * top["size"]]
        below = [c for c in under_bar if c["y0"] >= y - 1.0 and abs(mid(c) - mid(bottom)) <= 0.75 * bottom["size"]]
        size = max(c["size"] for c in above + below)
        # A fraction is set in a smaller type than the text around it, but it stands in that text's line: give it the
        # size of the characters that sit on the bar's own height (the "(a)" in front of it, the "−5" after it).
        on_the_bar = [c["size"] for c in pool if c["size"] > size and abs(mid(c) - y) <= 0.45 * c["size"] and c not in above and c not in below]
        size = max(on_the_bar, default=size)
        num, den = render(above), render(below)
        tex = "\\frac{%s}{%s}" % (render_tex(above), render_tex(below))
        for c in above + below:
            c["dead"] = True
        order += 1
        chars.append(pseudo(f"{wrap(num)}/{wrap(den)}", x0, x1, y - 0.5 * size, y + 0.5 * size, size, "frac", order, tex))

    return [c for c in chars if not c["dead"]]


# ----------------------------------------------------------------------------- lines

def baseline(chs, main_size):
    """Vertical middle of the normal-sized characters (the line's baseline), used to spot powers and indices."""
    normal = [c for c in chs if c["size"] >= 0.9 * main_size and c["c"].strip()]
    # Letters and digits sit on the baseline; symbols such as = and − float above it and would pull the estimate up.
    letters = [c for c in normal if c["c"].isalnum()]
    mids = sorted((c["y0"] + c["y1"]) / 2 for c in (letters or normal))
    return mids[len(mids) // 2] if mids else (chs[0]["y0"] + chs[0]["y1"]) / 2


def page_lines(page):
    """Group every character of the page into lines (by vertical position), then order each line left to right."""
    chars = compose_math(collect_chars(page), collect_rules(page))
    sizes = [round(c["size"], 1) for c in chars]
    body = max(set(sizes), key=sizes.count) if sizes else 10.0
    normal = [c for c in chars if c["size"] >= 0.8 * body]
    small = [c for c in chars if c["size"] < 0.8 * body]

    lines = cluster_lines(normal)
    for line in lines:
        mids = sorted((c["y0"] + c["y1"]) / 2 for c in line["chars"])
        line["mid"] = mids[len(mids) // 2]
        line["x_lo"] = min(c["x0"] for c in line["chars"]) - 4
        line["x_hi"] = max(c["x1"] for c in line["chars"]) + 4
    # Powers and indices (smaller characters) belong to the nearest line that spans their position
    orphans = []
    for ch in sorted(small, key=lambda c: c["xs"]):
        mid, cx = (ch["y0"] + ch["y1"]) / 2, (ch["x0"] + ch["x1"]) / 2
        # A whole fraction or root is one unit: it belongs to a line when it starts right where that line's text ends
        # (an option label "(b)" with its fraction beside it), not only when its middle falls inside the line.
        anchor = ch["x0"] if ch["math"] in ("frac", "root") else cx
        fits = [l for l in lines if l["x_lo"] <= anchor <= l["x_hi"] and abs(mid - l["mid"]) <= 0.6 * l["size"]
                and 0.55 * l["size"] <= ch["size"] < 0.8 * l["size"]]
        if fits:
            line = min(fits, key=lambda l: abs(mid - l["mid"]))
            line["chars"].append(ch)
            line["x_hi"] = max(line["x_hi"], ch["x1"] + 4)  # a multi-character exponent (s⁻²) attaches one character at a time
        else:
            orphans.append(ch)  # genuinely small text (a footnote, a caption)
    lines += cluster_lines(orphans)

    result = []
    for line in lines:
        chs = sorted(line["chars"], key=lambda c: (c["xs"], c["o"]))
        sizes = [round(c["size"], 1) for c in chs]
        main = max(set(sizes), key=sizes.count)
        base = baseline(chs, main)
        # Split into column segments where there is a wide gap (two-column option grids etc.)
        segments, current = [], [chs[0]]
        for prev, ch in zip(chs, chs[1:]):
            if ch["x0"] - prev["x1"] > 0.68 * main:
                segments.append(current)
                current = []
            current.append(ch)
        segments.append(current)
        seg_out = []
        for seg in segments:
            rich = build_text(seg, main, baseline(seg, main)).strip()  # each column has its own baseline
            if rich:
                seg_out.append({"text": clean(rich), "rich": rich, "x0": round(min(c["x0"] for c in seg), 1), "x1": round(max(c["x1"] for c in seg), 1)})
        if seg_out:
            result.append({
                "text": " ".join(s["text"] for s in seg_out),
                "rich": " ".join(s["rich"] for s in seg_out),
                "segments": seg_out,
                "math": sorted({c["math"] for c in chs if c["math"]}),
                "x0": round(min(c["x0"] for c in chs), 1),
                "x1": round(max(c["x1"] for c in chs), 1),
                "y0": round(min(c["y0"] for c in chs), 1),
                "y1": round(max(c["y1"] for c in chs), 1),
                "size": main,
                "bold": sum(1 for c in chs if c["bold"]) > len(chs) / 2,
            })
    result.sort(key=lambda l: (l["y0"] + l["y1"]) / 2)
    return result


def cluster_lines(chars):
    """Characters whose vertical centres are close belong to one line."""
    chars = sorted(chars, key=lambda c: (c["y0"] + c["y1"]) / 2)
    lines = []
    for ch in chars:
        mid = (ch["y0"] + ch["y1"]) / 2
        for line in reversed(lines[-6:]):
            if abs(mid - line["mid"]) <= 0.55 * min(line["size"], ch["size"]):
                line["chars"].append(ch)
                break
        else:
            lines.append({"mid": mid, "size": ch["size"], "chars": [ch]})
    return lines


def clean(rich):
    """The plain text of a rich line: no bold marks, and rebuilt fractions/roots as their plain form."""
    return MATH_MARKS.sub(r"\1", rich).replace(BOLD_OPEN, "").replace(BOLD_CLOSE, "")


def lone_bold(chs, i):
    """True for a single bold letter standing between non-bold text: a printed vector such as A or î."""
    ch = chs[i]
    if not ch["bold"] or not ch["c"][:1].isalpha() or ch["math"]:
        return False
    for step in (-1, 1):
        j = i + step
        if 0 <= j < len(chs) and chs[j]["bold"] and chs[j]["c"].strip():
            return False  # touches more bold text: a label such as "Answer:" or the "(a)" before an option
    return True


TEX_ESCAPES = {"%": "\\%", "&": "\\&", "#": "\\#", "{": "\\{", "}": "\\}"}


def build_text(chs, main_size, base, tex=False):
    """Join characters; add a space where there is a visible gap; mark small raised/lowered runs as super/subscripts."""
    parts = []
    prev = None
    run = None  # (kind, text) while inside a super/subscript run
    for i, ch in enumerate(chs):
        small = ch["size"] < 0.8 * main_size
        mid = (ch["y0"] + ch["y1"]) / 2
        kind = None
        if small and ch["c"].strip():
            kind = "sup" if mid < base - 0.12 * main_size else ("sub" if mid > base + 0.12 * main_size else None)
        elif ch["size"] < 0.92 * main_size and ch["c"].strip() and abs(mid - base) > 0.25 * main_size:
            # a power or index written only slightly smaller (about 83% of the text size) but clearly raised or lowered: x², a₁
            kind = "sup" if mid < base else "sub"
        if run and kind != run[0]:
            parts.append(flush_run(run))
            run = None
        if prev is not None and not run and kind is None:
            gap = ch["x0"] - prev["x1"]
            beside_fraction = (prev["math"] == "frac" and ch["c"][:1].isalnum()) or (ch["math"] == "frac" and prev["c"][-1:].isalnum())
            if (gap > 0.28 * main_size or beside_fraction) and ch["c"] != " " and parts and not parts[-1].endswith(" "):
                parts.append(" ")
        if kind:
            run = run or (kind, "")
            run = (kind, run[1] + ch["c"])
        elif ch["math"] in ("frac", "root") and ch.get("tex"):
            parts.append(ch["tex"] if tex else MATH_OPEN + ch["tex"] + MATH_SEP + ch["c"] + MATH_CLOSE)
        elif tex:
            parts.append(TEX_ESCAPES.get(ch["c"], ch["c"]))
        elif lone_bold(chs, i):
            parts.append(BOLD_OPEN + ch["c"] + BOLD_CLOSE)
        else:
            parts.append(ch["c"])
        prev = ch
    if run:
        parts.append(flush_run(run))
    return "".join(parts)


def flush_run(run):
    kind, text = run
    if kind == "sup" and text.strip() in ("∘", "°", "o"):
        return "°"  # a raised circle is the degree sign: 60∘ → 60°
    table = SUPERSCRIPT if kind == "sup" else SUBSCRIPT
    converted = text.translate(table)
    if all(c in "⁰¹²³⁴⁵⁶⁷⁸⁹⁺⁻⁼⁽⁾ⁿⁱ₀₁₂₃₄₅₆₇₈₉₊₋₌₍₎" for c in converted):
        return converted
    return ("^{%s}" if kind == "sup" else "_{%s}") % text  # LaTeX-style marker for anything that has no Unicode form


# ----------------------------------------------------------------------------- commands

def cmd_extract(args):
    doc = fitz.open(args.pdf)
    pages = []
    for i, page in enumerate(doc):
        images = [list(map(lambda v: round(v, 1), r)) for img in page.get_images(full=True) for r in page.get_image_rects(img[0])]
        pages.append({
            "number": i + 1,
            "width": round(page.rect.width, 1),
            "height": round(page.rect.height, 1),
            "lines": page_lines(page),
            "images": images,
        })
    out({"title": doc.metadata.get("title") or "", "pageCount": len(doc), "pages": pages})


def cmd_render(args):
    os.makedirs(args.out_dir, exist_ok=True)
    doc = fitz.open(args.pdf)
    zoom = args.dpi / 72
    for i, page in enumerate(doc):
        page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False).save(os.path.join(args.out_dir, f"{i + 1}.png"))
    out({"pageCount": len(doc)})


def cmd_split(args):
    src = fitz.open(args.pdf)
    first, last = max(1, args.first), min(len(src), args.last)
    part = fitz.open()
    part.insert_pdf(src, from_page=first - 1, to_page=last - 1)
    part.save(args.out_pdf)
    out({"pages": last - first + 1})


def main():
    parser = argparse.ArgumentParser()
    sub = parser.add_subparsers(dest="cmd", required=True)
    p = sub.add_parser("extract"); p.add_argument("pdf"); p.set_defaults(fn=cmd_extract)
    p = sub.add_parser("render"); p.add_argument("pdf"); p.add_argument("out_dir"); p.add_argument("--dpi", type=int, default=110); p.set_defaults(fn=cmd_render)
    p = sub.add_parser("split"); p.add_argument("pdf"); p.add_argument("out_pdf"); p.add_argument("first", type=int); p.add_argument("last", type=int); p.set_defaults(fn=cmd_split)
    args = parser.parse_args()
    args.fn(args)


if __name__ == "__main__":
    main()
