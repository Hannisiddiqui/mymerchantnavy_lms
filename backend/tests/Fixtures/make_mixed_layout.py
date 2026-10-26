"""Builds mixed-layout.pdf: the layout of the Number System paper, where questions 1-5 are numbered lines
("1. text") whose explanations contain numbered STEPS ("1. ...", "2. ..."), and questions 6-8 use
"Question 3 Medium · Numerical" headers. The reader once read only one of the two layouts.

  python make_mixed_layout.py            (needs: pip install pymupdf)
"""
import fitz

doc = fitz.open()


def text(page, x, y, s, size=9.2, bold=False):
    page.insert_text((x, y), s, fontsize=size, fontname="hebo" if bold else "helv")


p = doc.new_page()
text(p, 50, 60, "Number System", 8.2)
y = 90
for n, (q, opts, ans, steps) in enumerate([
    ("The smallest family that holds -15 is", ("integers", "natural numbers", "whole numbers", "rational numbers"), "(a) integers (Easy · Concept)", ["-15 is negative.", "It is an integer."]),
    ("Which of these is NOT a whole number?", ("0", "17", "-4", "250"), "(c) -4 (Easy · Concept)", ["Whole numbers start at 0.", "-4 is negative.", "So -4 is not whole."]),
    ("What is 1 + 1?", ("1", "2", "3", "4"), "(b) 2 (Easy · Numerical)", ["Add the two ones.", "The sum is 2."]),
    ("What is 2 + 2?", ("3", "4", "5", "6"), "(b) 4 (Easy · Numerical)", ["Add the twos.", "The sum is 4."]),
    ("What is 3 + 3?", ("5", "6", "7", "8"), "(b) 6 (Easy · Numerical)", ["Add the threes.", "The sum is 6."]),
], start=1):
    text(p, 50, y, f"{n}. {q}"); y += 14
    text(p, 68, y, f"(a) {opts[0]}   (b) {opts[1]}", 8.8); y += 12
    text(p, 68, y, f"(c) {opts[2]}   (d) {opts[3]}", 8.8); y += 16
    text(p, 61, y, f"Answer: {ans}"); y += 14
    text(p, 61, y, "Explanation:", bold=True); y += 13
    for i, step in enumerate(steps, start=1):
        text(p, 63, y, f"{i}. {step}"); y += 13
    y += 12

text(p, 40, y, "Part D: Additional exam-level questions", 11.5, True)  # a banner between questions, not part of any explanation

for n, (q, opts, ans) in enumerate([
    ("What is 6 + 7?", ("11", "12", "13", "14"), "(c) 13"),
    ("What is 9 - 4?", ("3", "4", "5", "6"), "(c) 5"),
    ("What is 2 + 2?", ("3", "4", "5", "6"), "(b) 4"),
], start=6):
    p = doc.new_page()
    text(p, 50, 60, "Number System", 8.2)
    text(p, 48, 100, f"Question {n} Medium · Numerical", 8.8)
    text(p, 50, 118, q, 10, True)
    text(p, 68, 132, f"(a) {opts[0]}   (b) {opts[1]}", 8.8)
    text(p, 68, 144, f"(c) {opts[2]}   (d) {opts[3]}", 8.8)
    text(p, 50, 162, f"Answer: {ans}", 10, True)
    text(p, 50, 178, "Explanation:", bold=True)
    text(p, 54, 191, "1. Do the sum.")
    text(p, 54, 204, "2. Write the result.")

doc.save("mixed-layout.pdf")
