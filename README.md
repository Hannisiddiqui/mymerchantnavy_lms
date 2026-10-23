# My Merchant Navy LMS

Student learning platform (`dashboard.mymerchantnavy.com`) and admin panel (`admin.mymerchantnavy.com`).
The full plan and all decisions are in **[docs/PLAN.md](docs/PLAN.md)**.

## What's where

| Folder | What it is |
|---|---|
| `student-app/` | Next.js student website (what students see) |
| `backend/` | Laravel 12 API + database + (next) the Filament admin panel |
| `docs/` | Plan, decisions, API list |

### Student app (`student-app/src`)

| To change… | Look in |
|---|---|
| Brand name, logo, sidebar menu, exam list | `lib/config.ts` |
| Colours and fonts | `app/globals.css` (`@theme` block) |
| A page | `app/(student)/<page>/page.tsx`, login pages in `app/(auth)/`, test screens in `app/tests/` |
| Buttons, cards, inputs and other shared pieces | `components/ui/` |
| Course, content, test and result pieces | `components/course/`, `components/content/`, `components/test/`, `components/result/` |
| Where data comes from | `lib/api/` (one file per area; each function shows its backend endpoint) |
| Test rules (palette colours, scoring) | `lib/test-engine.ts` and `components/test/PaletteLegend.tsx` |
| Sample data used before the backend is connected | `lib/mock/` |

Put the logo at `student-app/public/brand/logo.png`; it replaces the text logo automatically.

### Backend (`backend/app`)

| To change… | Look in |
|---|---|
| API URLs | `routes/api.php` |
| What an endpoint does | `Http/Controllers/Api/V1/` |
| JSON sent to the app | `Http/Resources/` |
| Business rules (OTP, devices, scoring) | `Services/` |
| SMS provider | `config/otp.php` + `Services/Otp/` |
| Platform limits (devices etc.) | `config/lms.php` |
| Database tables | `database/migrations/` |

## Running locally

Student app (mock data, no backend needed):

```bash
npm --prefix student-app run dev
```

Open http://localhost:3000, log in with any 10-digit number starting 6–9 and any 6-digit code.

Backend (PHP 8.3 from Laragon):

1. Create a PostgreSQL database named `mmp_lms`, then put your postgres password in `backend/.env` (`DB_PASSWORD=`).
2. From `backend/`, run `php artisan migrate --seed`, then `php artisan serve`.
3. Run the tests with `php artisan test` (they use an in-memory SQLite database).

**Admin panel** (Filament, inside `backend/`): open `/admin` (in production: `admin.mymerchantnavy.com`, set `ADMIN_DOMAIN`).
Local demo login after `php artisan migrate:fresh --seed`: `admin@mymerchantnavy.com` / `password` (development only, change before going live).
To preview without PostgreSQL, run the backend with `DB_CONNECTION=sqlite` set in the environment.

| To change… | Look in |
|---|---|
| Menu groups, colours, admin domain | `app/Providers/Filament/AdminPanelProvider.php` |
| Course builder (outline of subjects → chapters → content, mock tests in between) | `app/Filament/Resources/CourseResource/Pages/ManageCurriculum.php`, its layout `resources/views/filament/course-builder/page.blade.php`, logic in `app/Services/Courses/CourseOutline.php` |
| Forms for each content type (video, PDF, notes…) | `app/Filament/Support/ContentForm.php` |
| Test question builder (questions in a test + question bank) | `app/Filament/Resources/TestResource/Pages/ManageQuestions.php` + `resources/views/filament/test-builder/page.blade.php` |
| Question editor and how questions are saved | `app/Filament/Support/QuestionForm.php`, `app/Services/Tests/QuestionService.php` |
| Builder styles and formula (KaTeX) display in admin | `resources/views/filament/admin-head.blade.php` |
| Any admin list/form | `app/Filament/Resources/<Name>Resource.php` |
| Dashboard | `app/Filament/Widgets/` |

**Login:** students log in with their **email address**; a 6-digit code is emailed (`LMS_LOGIN_METHOD=email`, the default).
With `OTP_EMAIL_DRIVER=log`, codes are written to `backend/storage/logs/laravel.log` instead of being emailed.
The mobile-number + SMS login is still in the code but switched off: set `LMS_LOGIN_METHOD=phone` in `backend/.env` and
`NEXT_PUBLIC_LOGIN_METHOD=phone` in the student app to bring it back (the two must match).
Students stay logged in until they log out themselves (`LMS_MAX_DEVICES=0` means no device limit; set a number such as `2` to
sign out the oldest device when a student logs in on one more).

The student app talks to this backend through `student-app/.env.local` (copy `student-app/.env.example`):

```
NEXT_PUBLIC_USE_MOCK=false          # "true" = built-in sample data, no backend needed (off unless set to exactly true)
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

Restart `npm run dev` after changing it. In development `OTP_FIXED_CODE=123456` in `backend/.env` means every login code is `123456`.
The student demo login is the seeded email `aarav@example.com`.
Create a test student from the terminal with `php artisan lms:create-student someone@example.com --name="Someone" --all-courses`.

### Importing questions from PDF / Excel

Admin → a test → **Import from PDF or Excel** / **Parse PDF with AI** (also on the Question bank and **Learning → Question imports**).
The file is read into a **review list**; nothing is added to a test until you tick the questions and press **Add selected to a test**.

| Method | When | Needs |
|---|---|---|
| PDF (automatic reader) | PDFs with numbered questions, options (a)–(d), an `Answer:` line and optional solution parts. Two layouts are known: `Q1.` / `Q1 · Part 1 · …` and `Question 6` + an id line (`VEC-T1-C-006 ⋅ Topic 1 ⋅ Easy ⋅ Concept`). Instant, free. Rebuilds every line from glyph positions, so symbols like ∛ √ ² π stay in place. For LaTeX-typeset papers it also rebuilds fractions (`16/3`), square roots (`√(8²+6²)`), vector hats (`î ĵ n̂`), powers and indices (`F₁`, `R_x`) and keeps printed vectors bold. Questions with a rebuilt fraction or root are marked “check”. Checks every answer against the PDF's own answer key or `Answer:` lines. | Python + PyMuPDF: `pip install pymupdf` |
| PDF with AI | Any other layout, and PDFs with typeset formulas (stacked fractions, roots, vectors). The AI looks at the page images and returns maths as LaTeX, a few pages at a time. Every question is checked against the PDF: its words, and its answer against the PDF's own `Answer:` lines. | the above + ONE key in `backend/.env`: `GEMINI_API_KEY` (cheapest), `OPENAI_API_KEY` or `ANTHROPIC_API_KEY`; run `php artisan queue:work` (or `QUEUE_CONNECTION=sync`) |
| Excel / CSV | One question per row. Download the template from the same screen. | nothing extra |

| To change… | Look in |
|---|---|
| Which labels count as the explanation (Given, Rule, Working…) and the PDF layout rules | `app/Services/Imports/PdfRuleParser.php` (`LABELS`) |
| How text, fractions, roots, hats and powers are rebuilt from the page | `backend/tools/pdf_tool.py` |
| The AI instructions and the output format | `app/Services/Imports/AiPdfParser.php` |
| Which AI service is used, its request format, models | `app/Services/Imports/Ai/` (one file per provider), `config/services.php` |
| Checks and warnings shown on each question | `app/Services/Imports/ImportValidator.php` |
| Excel column names accepted | `app/Services/Imports/ExcelParser.php` (`COLUMNS`) |
| Saving the approved questions (duplicates are reused, 200 per test) | `app/Services/Imports/QuestionImportService.php` |
| The review screen | `app/Filament/Resources/QuestionImportResource/Pages/ReviewImport.php` + `resources/views/filament/import-review/page.blade.php` |

### Running it for real (server checklist)

| Needed | Why |
|---|---|
| `php artisan schedule:run` every minute (cron) | auto-submits tests whose time ran out, sends "live class starting soon" reminders |
| `php artisan queue:work` + `QUEUE_CONNECTION=database` (or redis) | emails and PDF processing run in the background (locally it is `sync`, so no worker is needed) |
| Python + `pip install pymupdf` (or `poppler-utils`) | renders uploaded PDFs to page images (students never receive the PDF file) and reads questions out of PDFs |
| `MAIL_*` settings (Brevo SMTP) + `OTP_EMAIL_DRIVER=mail`, remove `OTP_FIXED_CODE` | the emailed login code, and the course-assigned / result / live-class emails |
| `OTP_DRIVER=twilio` + Twilio keys, `LMS_LOGIN_METHOD=phone` | only if you switch the SMS login back on |

### Where student API logic lives (`backend/app`)

| To change… | Look in |
|---|---|
| What a student can see (courses, content, progress) | `Services/Student/StudentCatalog.php` |
| Test rules: start/resume, save, submit, auto-submit | `Services/Tests/AttemptService.php` |
| Marking and rank/percentile | `Services/Tests/ScoringService.php`, `RankingService.php` |
| Result page data | `Services/Tests/ResultPresenter.php` |
| Protected PDFs and watermark | `Services/Pdf/`, `Http/Controllers/Api/V1/PdfPageController.php` |
| Notifications | `Notifications/` (in-app + email) |
| Scheduled jobs | `routes/console.php`, `Console/Commands/` |

## Going live

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for the server setup and the list of things you must arrange yourself. Create the first admin with `php artisan lms:create-admin you@example.com`.
