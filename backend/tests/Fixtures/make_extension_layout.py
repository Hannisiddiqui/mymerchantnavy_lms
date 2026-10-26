"""Builds extension-layout.pdf: a paper whose questions are tagged "... E · N" (level letter, then C/N kind), with an
introduction page and table before the added questions ("Part 2: Exam-level extension (Questions 2-3)"), a section
title that is not bold, and a page header printed with every letter doubled. None of that belongs to a question.

  python make_extension_layout.py            (needs: pip install pymupdf)
"""
import fitz

doc = fitz.open()


def text(page, x, y, s, size=10.3, bold=False):
    page.insert_text((x, y), s, fontsize=size, fontname="hebo" if bold else "helv")


def question(page, y, n, title, level, kind, q, opts, ans, steps):
    text(page, 50, y, f"Question {n} · {title} {level} · {kind}", 10, True)
    text(page, 50, y + 18, q, bold=True)
    text(page, 50, y + 32, f"(a) {opts[0]} (b) {opts[1]}")
    text(page, 50, y + 44, f"(c) {opts[2]} (d) {opts[3]}")
    text(page, 60, y + 62, f"Answer: {ans}", bold=True)
    text(page, 50, y + 78, "What the question is asking: Add the numbers.")
    text(page, 50, y + 92, "Step by step:", bold=True)
    for i, s in enumerate(steps, 1):
        text(page, 54, y + 92 + 13 * i, f"{i}. {s}")
    text(page, 60, y + 92 + 13 * (len(steps) + 1), "Concept behind the question: Basic addition. Revise section 1.1.", 9.6)


p = doc.new_page()
text(p, 40, 30, "Sums Practice Test", 8.5)
question(p, 100, 1, "1.1 Adding", "E", "N", "What is 2 + 3?", ("4", "5", "6", "7"), "(b) 5", ["Add 2 and 3.", "The sum is 5."])

p = doc.new_page()  # the introduction page of the extension
text(p, 40, 30, "Sums Practice Test", 8.5)
text(p, 50, 80, "Part 2: Exam-level extension (Questions 2-3)", 12, True)
text(p, 40, 110, "This extension adds new questions across the chapter sections.", 9.1)
text(p, 47, 130, "Section Added Medium Hard", 8.1, True)
text(p, 47, 144, "Total extension 2 1 1", 8.1, True)

for n, (q, opts, ans) in enumerate([("What is 4 + 4?", ("7", "8", "9", "10"), "(b) 8"), ("What is 6 + 5?", ("10", "11", "12", "13"), "(b) 11")], start=2):
    p = doc.new_page()
    text(p, 40, 30, "SSuummss  ··  PPrraaccttiiccee  TTeesstt", 8.5)           # a header printed twice over
    text(p, 40, 70, "1.1 Adding", 13)                                          # a section title that is not bold
    question(p, 100, n, "1.1 Adding", "M", "C", q, opts, ans, ["Add the two numbers.", "Write the sum."])

doc.save("extension-layout.pdf")
