"""Builds exponent-options.pdf: a paper with "Question N ..." headers whose options sit in two columns and whose powers
are written at about 83% of the text size, raised by 4 pt ("x3 = 8x" must be read as x³ = 8x), plus an explanation line
that wraps and starts with "2) ÷ 2 (Section 1.1)." (it is NOT question 2).

  python make_exponent_options.py            (needs: pip install pymupdf)
"""
import fitz

doc = fitz.open()
BIG, SMALL = 10.3, 8.6


def put(page, x, y, s, size=BIG, bold=False):
    font = "hebo" if bold else "helv"
    page.insert_text((x, y), s, fontsize=size, fontname=font)
    return x + fitz.get_text_length(s, fontname=font, fontsize=size)


def expr(page, x, y, parts):
    """parts: strings, or ("sup", "3") for a raised power."""
    for p in parts:
        if isinstance(p, tuple):
            x = put(page, x, y - 4.2, p[1], SMALL)
        else:
            x = put(page, x, y, p)
    return x


questions = [
    ("Which of these is a linear equation?", [["(a) x", ("sup", "2"), " - 4 = 12"], ["(b) x + y = 9"]], [["(c) x", ("sup", "3"), " = 8x"], ["(d) 7x - 4 = 3x + 8"]], "(d) 7x - 4 = 3x + 8"),
    ("Which equation has the root 2?", [["(a) x + 1 = 5"], ["(b) 2x - 1 = 3"]], [["(c) x", ("sup", "2"), " = 9"], ["(d) 3x = 12"]], "(b) 2x - 1 = 3"),
    ("Which is a factorisation of x2 - 4?", [["(a) (x - 2)", ("sup", "2")], ["(b) (x + 2)(x - 2)"]], [["(c) (x + 2)", ("sup", "2")], ["(d) x(x - 4)"]], "(b) (x + 2)(x - 2)"),
]
for n, (stem, row1, row2, ans) in enumerate(questions, 1):
    p = doc.new_page()
    put(p, 40, 30, "Algebra · Practice Test", 8.5)
    put(p, 47.5, 100, f"Question {n} · 1.1 Linear equations E · C", 10, True)
    put(p, 50, 118, stem, BIG, True)
    expr(p, 50, 134, row1[0]); expr(p, 300, 134, row1[1])
    expr(p, 50, 148, row2[0]); expr(p, 300, 148, row2[1])
    put(p, 60, 168, f"Answer: {ans}", BIG, True)
    put(p, 50, 184, "What the question is asking: Pick the option that fits the rule, then check it by substituting.")
    put(p, 50, 197, f"{n + 1}) ÷ 2 (Section 1.1).")          # a wrapped line that starts like the next question number
    put(p, 50, 211, "Step by step:", BIG, True)
    put(p, 54, 224, "1. Test each option.")
    put(p, 54, 237, "2. Keep the one that works.")

doc.save("exponent-options.pdf")
