"""Builds inline-header.pdf: three questions in the "Question N <section> Easy . Concept" layout (header on one line,
numbered step lines inside the explanation) that the automatic PDF reader once read as 5 broken questions.

  python make_inline_header.py            (needs: pip install pymupdf)
"""
import fitz

doc = fitz.open()


def text(page, x, y, s, size=10, bold=False):
    page.insert_text((x, y), s, fontsize=size, fontname="hebo" if bold else "helv")


for n in range(1, 4):
    p = doc.new_page()
    text(p, 40, 20, "Mathematics - Practice Test", 6)
    text(p, 47, 100, f"Question {n} 1.1 The order of operations: BODMAS Easy · Concept E", 8.8)
    text(p, 49, 118, f"What is {n} + {n + 1}?", 10.3, True)
    text(p, 49, 132, "(a) 1", 10.3); text(p, 300, 132, "(b) 2", 10.3)
    text(p, 49, 146, "(c) " + str(2 * n + 1), 10.3); text(p, 300, 146, "(d) 9", 10.3)
    text(p, 49, 162, f"Answer: (c) {2 * n + 1}", 10.3, True)
    text(p, 49, 178, "What the question is asking. Add the two numbers.", 9.6)
    text(p, 49, 192, "Step by step", 9.6, True)
    text(p, 54, 206, "1. Write the sum.", 9.6)
    text(p, 54, 220, "2. Add them.", 9.6)
    text(p, 40, 800, f"Created by - MyMerchantNavy.com Page {n} of 3", 6)

doc.save("inline-header.pdf")
