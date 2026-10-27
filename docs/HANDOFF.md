# Handoff: how everything is connected and how to keep working

Written 27 Oct 2026 so work can continue from another Claude account. It describes the system as it is **now**.
Where this file and `README.md` / `docs/DEPLOYMENT.md` disagree, this file wins (those two still describe the old
PostgreSQL + VPS plan; the live pilot is on Hostinger with MariaDB, see `docs/HOSTINGER.md`).

**No passwords, keys or secrets are in this file, and none should ever be pasted into chat.** Where one is needed this file says where it lives.

---

## 1. The product in one paragraph

"My Merchant Navy" LMS for GP Rating / IMU-CET style exam prep. Students log in with email + a 6-digit emailed code, see courses
(subjects → chapters → content and practice tests), take timed MCQ tests (+2 correct, −0.25 wrong), and see results/rank.
Admins build courses and tests in a Filament admin panel. The marketing/payment site is WordPress (mymerchantnavy.com), linked to the LMS by a signed plugin.
Status: **pilot mode**.

## 2. The four pieces and their addresses

| Piece | Address | Tech | Where the code is |
|---|---|---|---|
| Student website | https://lms.mymerchantnavy.com | Next.js (Node.js Web App on Hostinger) | `student-app/` |
| API + admin panel | https://api.mymerchantnavy.com (`/api/v1/...`, admin at `/admin`) | Laravel 12 + Filament v4, PHP 8.3 | `backend/` |
| Database | Hostinger MySQL/MariaDB (name/user/password only in the server's `backend/.env`) | MariaDB | migrations in `backend/database/migrations/` |
| Marketing + payment site | https://mymerchantnavy.com | WordPress + plugin `mmn-lms-sync` + Razorpay | `wordpress-plugin/mmn-lms-sync/` (zip: `wordpress-plugin/mmn-lms-sync.zip`), docs in `docs/WORDPRESS.md` |

Also used: **Brevo** (SMTP for the login-code and notification emails), **Razorpay** (payments, on WordPress), optional AI key for reading hard PDFs (not needed day to day).

```
 Student browser ──► lms.mymerchantnavy.com (Next.js) ──HTTPS /api/v1──► api.mymerchantnavy.com (Laravel) ──► MariaDB
                                                                              ▲   ▲
 WordPress (mymerchantnavy.com) ── signed HMAC messages (/api/v1/integrations/wordpress/*) ┘   └─ /admin (Filament) used by the owner
```

The student site decides its API address from the page it is opened on (`lms.<domain>` → `api.<domain>`); `https://lms.mymerchantnavy.com/status` shows which server it talks to.

## 3. Git and code flow

- Repo on this PC: `E:\MMP`, branch `main`. Two remotes:
  - `hannisiddiqui` → https://github.com/Hannisiddiqui/mymerchantnavy_lms.git  **← the server pulls from this one**
  - `origin` → https://github.com/Chota-Thanos/Merchant-Navy.git
- Code changes: edit locally → commit → `git push hannisiddiqui main` → on the server `git pull` and run the update commands (section 5).
- **Questions and tests are data, not code**: they live in the server database only. They are changed by running small PHP scripts on the server (section 8), not by git. The scripts used are saved in `docs/handoff-tools/`.
- Local dev (`.claude/launch.json`): `student-app` on port 3000 (`npm --prefix student-app run dev`), `backend` on 8000 (Laragon PHP 8.3). Local student app uses `student-app/.env.local` (git-ignored).

## 4. Student-facing logic (where things live)

| Topic | File |
|---|---|
| API routes | `backend/routes/api.php` |
| Controllers / JSON | `backend/app/Http/Controllers/Api/V1/`, `Http/Resources/` |
| What a student can see | `Services/Student/StudentCatalog.php` |
| Attempts: start/resume/save/submit/auto-submit | `Services/Tests/AttemptService.php` (auto-submit by cron `attempts:auto-submit`) |
| Marking, rank, percentile | `Services/Tests/ScoringService.php`, `RankingService.php`, `ResultPresenter.php` |
| Login by email code | `Services/Auth`, `Services/Otp`, `config/otp.php`, `config/lms.php` |
| Protected PDFs (page images + watermark) | `Services/Pdf/`, `PdfPageController.php` |
| WordPress link | `Services/WordPress/WordPressLink.php` |
| Student UI pages | `student-app/src/app/(student)/`, test screens `app/tests/`, API calls `src/lib/api/` |

## 5. The server (Hostinger Business)

- SSH: host `147.93.109.99`, port **65002**, user `u563028037`. Key file on this PC: `~/.ssh/mmn_lms_hostinger2` (the private key never leaves the PC; **the other Claude account/PC will need its own key added in hPanel → Advanced → SSH Access**).
- Always connect like this (non-interactive, key only), and add `</dev/null` so ssh/scp never hang waiting on input:
  ```bash
  ssh -p 65002 -i ~/.ssh/mmn_lms_hostinger2 -o IdentitiesOnly=yes -o BatchMode=yes u563028037@147.93.109.99 '<command>' </dev/null
  scp -P 65002 -i ~/.ssh/mmn_lms_hostinger2 -o IdentitiesOnly=yes -o BatchMode=yes <local file> u563028037@147.93.109.99:lms/backend/ </dev/null
  ```
- **PHP on the command line must be 8.3**: `/opt/alt/php83/usr/bin/php` (plain `php` is 8.1 and will fail). Composer: `/usr/local/bin/composer`.
- Layout on the server:
  - `~/lms/` = clone of the GitHub repo; `~/lms/backend` = Laravel; `~/lms/backend/.env` = real settings (DB, Brevo, WP secret). Never print or copy `.env`.
  - `~/domains/api.mymerchantnavy.com/public_html` is a symlink to `~/lms/backend/public`.
  - `~/lms-venv` = Python 3.11 venv with PyMuPDF (PDF reader).
  - Cron (every minute): `artisan schedule:run` and `artisan queue:work --stop-when-empty --max-time=55`.
- Updating code after a push:
  ```bash
  cd ~/lms && git pull && cd backend && /opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader && /opt/alt/php83/usr/bin/php artisan migrate --force && /opt/alt/php83/usr/bin/php artisan config:cache && /opt/alt/php83/usr/bin/php artisan route:cache && /opt/alt/php83/usr/bin/php artisan view:cache
  ```
  The student site is rebuilt from hPanel → Websites → Node.js Web App (root `student-app`, build `npm run build`, start `npm run start`; `NEXT_PUBLIC_*` variables are read at build time).
- Rules agreed with the owner: **ask before any destructive server change** (deleting tests/questions, resetting data, force operations); never put secrets in chat; delete temporary scripts from the server after running them.

## 6. Data model (what a "test" is)

- `courses` → `subjects` (e.g. Maths, English) → `chapters` (e.g. "Percentage"). A chapter can hold content and a practice test; a mock test can also sit between chapters (`chapters.test_id`). Course page order is in `curriculum_items`.
- `tests`: `id, course_id, title, kind (practice|mock), duration_minutes, marks_correct, marks_wrong, instructions, is_published`.
- `questions` + `question_options` (4 options, one `is_correct`), with `subject_id`, `chapter_id`, `difficulty` (easy/medium/hard), `explanation`.
- `test_questions` (pivot): `test_id, question_id, position`. **A test's question order is the pivot `position`.** A question can be in several tests.
- Deleting a test deletes questions that belong only to it (see `Test::booted`).
- **Defaults agreed with the owner:** +2 correct, −0.25 wrong, **90 minutes** for practice/mock tests (live classes stay 60). A test is a single table row with those columns; new tests created from Filament default to +2/−0.25.

### Identifying tests: always by DB id **and** exact title

Tests are referred to by name to the owner, and by `tests.id` in scripts. Current catalogue (ids 4–67; ids 1–3 were not checked and are not part of the question work):

| Subject | Test ids (title) |
|---|---|
| Maths | 4 Number System · 5 Fractions And Decimals · 6 HCF And LCM · 7 BODMAS, Simplification And Estimation · 8 Squares, Square Roots, And Cube Roots · 9 Indices and Surds · 10 Ratio, Proportion and Mixtures · 11 Percentage · 12 Profit, Loss and Discount · 13 Simple and Compound Interest · 14 Average · 15 Time and Work · 16 Speed, Distance and Time · 17 Algebraic Expressions and Identities · 18 Lines and Angles · 19 Triangles · 20 Similarity and Pythagoras Theorem · 21 Perimeter and Area · 22 Surface Area and Volume · 23 Basic Trigonometry · 24 Linear Equations · 25 Statistics Mean, Median and Mode |
| Science | 26 Measurement and Units · 27 Motion · 28 Force and Law of motion · 29 Gravitation · 30 Work, Energy And Power · 31 Heat And Temperature · 32 Sound And Wave Motions · 33 Electricity And Its Applications · 34 The Universe · 35 Nature of Matter · 36 Metals And Non-Metals · 37 Carbon And Its Compounds |
| English | 38 Nouns And Pronouns · 39 Prepositions And Conjunctions · 40 Verbs, Tense, And Voice · 41 Articles, Adjectives And Adverbs · 42 Passage Reading · 43 Letter Writing · 44 Speech, Idioms, Punctuation And Sentence Types · 45 Paragraph Writing · 46 Agreement, Error Spotting and Vocabulary |
| General Knowledge (split into parts by design) | 47 + 61 Indian Geography (1/2, 2/2) · 48 + 62 Physical Geography (1/2, 2/2) · 49 + 63 + 64 World Geography (1/3–3/3) · 50 + 65 + 66 India: National Identity and Government (1/3–3/3) · 51 + 67 Sports And Awards (1/2, 2/2) · 52 Maritime Knowledge (44 q) · 53 Shipping Awareness (40 q) |
| Reasoning | 54 Age Related Problems · 55 Blood Relations · 56 Coding-Decoding · 57 Seating Arrangements · 58 Direction Sense · 59 Number and Letter Series · 60 Ranking and Order |

All titles end in " Practice Test". Every Maths/Science/English/Reasoning test is exactly **100 questions** (tests 4–46, 54–60). The GK parts are smaller by design; **52 and 53 must be left untouched** (owner instruction). **Do not split 100-question tests into 50-question halves** until the owner says so.

To list the live state at any time:
```bash
ssh … 'cd ~/lms/backend && /opt/alt/php83/usr/bin/php artisan tinker --execute="foreach(App\Models\Test::orderBy(\"id\")->get() as \$t) echo \$t->id.\" | \".\$t->title.\" | \".DB::table(\"test_questions\")->where(\"test_id\",\$t->id)->count().\"\n\";"' </dev/null
```

## 7. Source material (owner's PC)

Study guides and practice-test PDFs live in `C:\Users\Abrar\Desktop\IMU Exam\GP Rating Final\` with one folder per subject: `Maths`, `Science`, `English` (subfolders per chapter, e.g. `5. Passage Reading`), `Reasoning`, `G.K`. Each chapter normally has a **study guide** and a **practice test PDF**. The new account/PC needs a copy of this folder. (Known: the English Passage Reading PDF itself contains a stray "world." and one truncated sentence; both were repaired in the database only.)

## 8. How questions get from a PDF into the app, and how I push/identify them

### 8.1 First load of a chapter's test (done for all 64 tests)
1. The PDF is read by `PdfRuleParser` (`backend/app/Services/Imports/PdfRuleParser.php`, helper `backend/tools/pdf_tool.py` using PyMuPDF). It understands layouts `Q1.` and `Question N` + id line, options (a)–(d), `Answer:` lines and explanation sections; labels counted as explanation are listed in its `LABELS`.
2. The parsed list is checked by `ImportValidator` (answer vs the PDF's own key, missing options, etc.) and saved through `QuestionImportService` (review list → commit into a `Test`). In bulk, a PHP script on the server (copied with scp to `~/lms/backend/`, run with PHP 8.3, then deleted) drives these services, one test id per PDF, using a mapping file test-id → PDF name.
3. Each question is stored by `App\Services\Tests\QuestionService::save($form)` with form keys: `body, option_1..option_4, correct (1–4), explanation, difficulty, subject_id, chapter_id`. The test's `test_questions` rows set the order. Marking/duration are set on the `tests` row.

### 8.2 Adding/fixing questions later (what was done this week)
Use `docs/handoff-tools/`:
- `load_new.php [dry|apply] file.json …` — reads `{ "test": <id>, "questions": [form arrays + "order":[section, index]] }`, skips any question whose stem+options already exist in that test, saves via `QuestionService`, copies `subject_id/chapter_id` from the test's first question, **merges new questions into the existing order by guide section** (parsed from "Revise Section x.y" in explanations; or at a fixed `insert_at` index), rewrites the pivot positions in a transaction, and sets marks 2 / 0.25 and 90 minutes. **Always run `dry` first.**
- `vfy.php` — counts, answer-position balance (aim ≈25% each of a/b/c/d), difficulty mix, duplicates per test.
- `chk_src.py` — every new question must carry a verbatim `src` rule line from the study guide; this checks the quote exists in the guide text (alphanumeric-only comparison; run with `PYTHONUTF8=1`). This is how accuracy of new questions is proven.
- `build_en.py`, `build_ask.py`, `mk_agree.py` — build the explanation text in the house format (rule from the guide → steps → why the other options fail → concept) and rotate the correct-answer position.
- `show_t.py` — print a test compactly from a JSON dump for proof-reading.

Typical flow for a fix: write a small PHP file that loads models by id → `scp` → run with `/opt/alt/php83/usr/bin/php file.php` → read the printed read-back → `ssh rm file.php`. Edit text through `QuestionService::save($form, $existingQuestion)`, not raw SQL, so options/keys stay consistent.

### 8.3 Quality bar agreed with the owner
- ≥ 99.5% sure of accuracy on anything posted; new questions must be in sync with the existing ones (same style, difficulty mix, explanation format).
- Explanations must be proper and expanded, never one-liners.
- Correct answer positions balanced; no duplicate question inside a test.
- Verified so far: all Maths (2,200 keys re-solved), English (900), Science, Reasoning and GK checked earlier. Unverifiable: 2026-dated GK facts. Known harmless nits: six answer-line vs option-text wording mismatches (Science 31#9, 35#6, 37#91; English 39#11, 40#3, 44#24).

## 9. WordPress ↔ LMS link (summary; full detail in `docs/WORDPRESS.md`)

- Shared identity = email. WordPress registers a student (no course), and after a Razorpay payment sends a signed "enroll" message; the student is sent to `lms.…/sso#t=<token>` already signed in on the purchased course.
- Messages: `POST /api/v1/integrations/wordpress/{student|enroll}` with `X-MMN-Timestamp` and `X-MMN-Signature` (HMAC-SHA256 of `timestamp.body`). The shared secret is `LMS_WP_SECRET` in the server `.env` and in WordPress settings → **LMS link**; created by `bash ~/lms/backend/tools/set-wp-secret.sh` (prints once). Empty `LMS_WP_SECRET` switches the link off.
- Each course in the admin has a **WordPress product ID** matching the Razorpay product ids in the plugin ("Courses for sale"). Shortcodes: `[mmn_register]`, `[mmn_login]`, `[mmn_buy product="…"]`; buttons with class `mmn-buy` and `data-product`.

## 10. Admin panel (Filament, `/admin`)

Admin accounts are created with `php artisan lms:create-admin you@example.com`. Menus: Courses (course builder: subjects → chapters → content, mock tests), Tests (question builder + question bank, "Import from PDF or Excel", "Parse PDF with AI"), Question imports, Students, Enrollments, Attempts, Admins. Students are created with `php artisan lms:create-student email --name="…" --all-courses`. Key files are listed in `README.md` ("Backend").

## 11. Setting up the second Claude account/PC (checklist)

1. Install Git, PHP 8.3 (Laragon), Node 20+, Python 3 + `pip install pymupdf`.
2. `git clone https://github.com/Hannisiddiqui/mymerchantnavy_lms.git` (needs access to that GitHub repo) and add `origin` if wanted.
3. Add a **new SSH key** for that PC in Hostinger hPanel → SSH Access (do not copy the first PC's private key around); test with the ssh command in section 5.
4. Copy the `GP Rating Final` source folder (section 7).
5. In the new Claude session, point it to this file first: "Read `docs/HANDOFF.md`, then continue." Standing rules to repeat: refer to tests by name, no secrets in chat, ask before destructive server changes, +2/−0.25 marking, 90-minute default, explanations expanded, leave tests 52 and 53 alone, don't split 100-question tests yet.

## 12. Open items

- Splitting 100-question tests into 50-question halves: **on hold** (owner said "not yet").
- A few chapter titles differ slightly from the study documents: not changed unless the owner asks.
- Maritime Knowledge (52) and Shipping Awareness (53) left as they are.
- Confirm the repaired sentence in Passage Reading Q69 ("Many ports still keep their lights burning, and cadets are still taught to read them.").
- 2026-dated GK facts need a human check against a current source.
- `README.md` and `docs/DEPLOYMENT.md` still describe PostgreSQL and the three-subdomain VPS plan; update them if you want them to match the live pilot.
