# Universal CRM Connector for Contact Form 7 (`universal-crm-connector-cf7`)

**A robust, production-ready WordPress plugin engineered to route Contact Form 7 submissions to Odoo CRM, HubSpot, Zoho, and custom REST APIs / HTTP Webhooks without editing PHP code.**

---

## 1. Executive Summary & Problem Resolution

On websites utilizing Contact Form 7 alongside existing integrations (such as the **CF7 Google Sheets Connector**), sending lead submissions to external CRMs often encounters significant hurdles:
1. **Odoo Target Record Crash:** Odoo Automation Rules with Trigger `On Webhook` default to evaluating `model.env[payload.get('_model')].browse(int(payload.get('_id')))` under the assumption that incoming webhooks update an existing database record. Since CF7 sends *brand-new inquiries*, `_id` is null, causing Python to evaluate `int(None)` and crash with HTTP 500.
2. **Integration Interference:** Many custom scripts or poorly isolated plugins interfere with Contact Form 7's mail pipeline, breaking Google Sheets synchronization or blocking student emails.
3. **Rigid Field Key Constraints:** Different CRM endpoints require different payload shapes and data types (e.g., standardizing a 10-digit phone number or nesting contacts into `partner.name`).

**Universal CRM Connector for Contact Form 7** solves all of these problems with a dedicated, isolated architecture.

---

## 2. Directory Structure

```
universal-crm-connector-cf7/
├── assets/
│   ├── css/
│   │   └── admin.css               # Modern, responsive admin styling
│   └── js/
│       └── admin.js                # Dynamic mapping UI, previewer & test console
├── includes/
│   ├── class-ucc-admin.php         # 8-tab admin menu & secured AJAX endpoints
│   ├── class-ucc-cf7.php           # Form detection & priority 20 submission interceptor
│   ├── class-ucc-database.php      # DB migrations, CRUD & AES-256 secret encryption
│   ├── class-ucc-dispatcher.php    # WP HTTP API requester with SSRF protection & retries
│   ├── class-ucc-logger.php        # Audit logging, PII masking & manual retry engine
│   ├── class-ucc-mapper.php        # Field transformations & JSON payload builder
│   └── class-ucc-odoo.php          # Odoo technical guide & Python server action generator
├── languages/                      # Localization readiness
├── readme.txt                      # WordPress standard readme
├── README.md                       # Comprehensive technical manual & troubleshooting
└── universal-crm-connector-cf7.php # Plugin entry point & activation lifecycle
```

---

## 3. Preserved Contact Form 7 Forms & Field Mappings

The plugin automatically detects CF7 forms and comes pre-configured with the exact field structures for your site's forms:

### Form A: "Data Needed" (Candidate Inquiry)
| CF7 Form Field | Target CRM Key | Transformation Applied | Notes |
|---|---|---|---|
| `full-name` | `name` | `trim` | Candidate full name |
| `email` | `email_from` | `trim` | Email address |
| `whatsapp` | `phone` | `clean_phone_10` | Sanitized to 10-digit Indian mobile number |
| `city` | `city` | `trim` | City / Location |
| `form-source` | `source` | `trim` | Hidden field (defaults to `"Data Needed"`) |

### Form B: "Sales Funnel / Eligibility Checker"
*Crucial: Preserves the exact spelling of `qualfied` without silent modifications.*

| CF7 Form Field | Target CRM Key | Transformation Applied | Notes |
|---|---|---|---|
| `full-name` | `name` | `trim` | Candidate full name |
| `email` | `email_from` | `trim` | Optional; defaults to empty string safely |
| `whatsapp` | `phone` | `clean_phone_10` | Sanitized 10-digit mobile number |
| `city` | `city` | `trim` | Optional; defaults to empty string safely |
| `created-date` | `created_date` | `trim` | Hidden timestamp field |
| `lead-status` | `lead_status` | `trim` | Hidden initial status |
| `date` | `date` | `trim` | Hidden date field |
| `form-source` | `source` | `trim` | Hidden source category |
| `qualfied` | `qualfied` | `trim` | **Preserved exact spelling** |
| `wp-group-created` | `wp_group_created`| `trim` | Hidden cohort identifier |
| `meeting-status` | `meeting_status` | `trim` | Hidden counseling status |
| `payment-link` | `payment_link` | `trim` | Hidden pass / checkout link |

---

## 4. Odoo CRM Technical Resolution: The "Target Record" Bug

### The Problem
When configuring an **Automation Rule** in Odoo 16, 17, or 18 with trigger **On Webhook**, Odoo pre-populates the **Target Record** field with:
```python
model.env[payload.get('_model')].browse(int(payload.get('_id')))
```
Because a Contact Form 7 submission represents a **new prospect** who does not yet have an Odoo database ID, the expression encounters:
```python
int(None) -> TypeError: int() argument must be a string, a bytes-like object or a real number, not 'NoneType'
```
This unhandled exception triggers an immediate `HTTP 500 Internal Server Error`.

### The Solution: 3-Step Setup in Odoo

1. **Step 1: Clear Target Record**
   In your Odoo Automation Rule:
   - **Model:** `Lead/Opportunity` (`crm.lead`)
   - **Trigger:** `On Webhook`
   - **Target Record:** **LEAVE COMPLETELY BLANK.** (Erase any formula in this field).

2. **Step 2: Set Action to Execute Python Code**
   Set **Action To Do:** `Execute Python Code`. Paste the following snippet:

   ```python
   # Odoo Automation Rule: Contact Form 7 Lead Creator
   data = payload or {}

   candidate_name  = data.get('name') or data.get('full-name') or 'Website Inquiry'
   candidate_email = data.get('email') or data.get('email_from') or ''
   candidate_phone = data.get('phone') or data.get('whatsapp') or ''
   candidate_city  = data.get('city') or ''
   source_label    = data.get('source') or data.get('form-source') or 'CF7 Webhook'
   form_title      = data.get('cf7_form_title') or 'Contact Form'
   submission_id   = data.get('submission_id') or ''

   # Form B qualification metadata
   qualified_flag  = data.get('qualfied') or ''
   lead_status     = data.get('lead_status') or ''
   payment_link    = data.get('payment_link') or ''
   meeting_status  = data.get('meeting_status') or ''

   description_lines = [
       f"--- CONTACT FORM 7 SUBMISSION ---",
       f"Form: {form_title}",
       f"Source Category: {source_label}",
       f"Submission ID: {submission_id}",
   ]
   if qualified_flag:
       description_lines.append(f"Qualified: {qualified_flag}")
   if payment_link:
       description_lines.append(f"Payment Link: {payment_link}")
   if meeting_status:
       description_lines.append(f"Meeting Status: {meeting_status}")
   if lead_status:
       description_lines.append(f"Lead Status: {lead_status}")

   lead_vals = {
       'name': f"{candidate_name} - {source_label}",
       'contact_name': candidate_name,
       'email_from': candidate_email,
       'phone': candidate_phone,
       'mobile': candidate_phone,
       'city': candidate_city,
       'description': "\n".join(description_lines),
       'type': 'lead',
   }

   # Duplicate prevention: Search for existing lead by phone or email
   existing = None
   if candidate_phone:
       existing = env['crm.lead'].sudo().search([('phone', '=', candidate_phone)], limit=1)
   if not existing and candidate_email:
       existing = env['crm.lead'].sudo().search([('email_from', '=', candidate_email)], limit=1)

   if existing:
       existing.sudo().write({
           'description': existing.description + "\n\n" + "\n".join(description_lines)
       })
       response = {'status': 'updated', 'lead_id': existing.id}
   else:
       new_lead = env['crm.lead'].sudo().create(lead_vals)
       response = {'status': 'created', 'lead_id': new_lead.id}
   ```

3. **Step 3: Copy Webhook URL**
   Save the Automation Rule, copy the URL Odoo displays, paste it into **Universal CRM > CRM Connections > Endpoint URL**, and click **Save Connection**.

---

## 5. Sample Outgoing Payloads

### Form A Sample JSON Payload
```json
{
  "name": "Rohan Sharma",
  "email_from": "rohan.sharma@example.com",
  "city": "Mumbai",
  "phone": "9876543210",
  "source": "Data Needed",
  "cf7_form_id": 101,
  "cf7_form_title": "Data Needed",
  "submission_id": "c7a8b9e0-1234-4567-89ab-cdef01234567",
  "_model": "crm.lead"
}
```

### Form B Sample JSON Payload
```json
{
  "name": "Aditya Verma",
  "email_from": "aditya.verma@example.com",
  "phone": "9812345678",
  "city": "New Delhi",
  "created_date": "2026-10-30",
  "lead_status": "new",
  "date": "2026-10-30",
  "source": "Sales Funnel / Eligibility Checker",
  "qualfied": "yes",
  "wp_group_created": "batch_2026",
  "meeting_status": "scheduled",
  "payment_link": "https://mymerchantnavy.com/pass/?mmn_buy=gp-rating-pass",
  "cf7_form_id": 102,
  "cf7_form_title": "Sales Funnel",
  "submission_id": "f1e2d3c4-5678-90ab-cdef-1234567890ab",
  "_model": "crm.lead"
}
```

---

## 6. Security, Reliability & Privacy Guarantees

* **SSRF Prevention:** Endpoints are validated prior to every dispatch. Localhost (`127.0.0.1`), loopback, and RFC1918 private subnets (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`) are strictly rejected.
* **TLS Certificate Enforcement:** Always executes with `sslverify => true`.
* **Zero Arbitrary PHP Execution:** The admin dashboard does not allow PHP code input or `eval()`.
* **Credential Redaction:** Passwords, API tokens, and Authorization headers are encrypted in the database using AES-256 and are never rendered in HTML, JavaScript, or public logs.
* **Idempotency Keys:** Every submission generates a unique UUIDv4 sent via `Idempotency-Key` and `X-Idempotency-Key` to guard against duplicate lead generation.
* **Privacy by Default:** Full personal data payload logging is turned OFF by default (`ucc_log_full_payloads = 'no'`). Only field presence, keys, and value lengths are audited unless explicitly turned on.

---

## 7. Troubleshooting Matrix

| Issue | Typical Cause | Resolution |
|---|---|---|
| **No request appears in Delivery Logs** | Contact Form 7 submission failed validation or mail failed | Check if CF7 shows red validation borders. Delivery executes on `wpcf7_mail_sent` after form passes. |
| **HTTP 401 Unauthorized** | Invalid Bearer Token or API key | In CRM Connections tab, re-enter your valid API credentials. |
| **HTTP 403 Forbidden** | User lacks CRM permissions | In Odoo/CRM, confirm that the API user has write/create access to the `crm.lead` model. |
| **HTTP 404 Not Found** | Incorrect endpoint URL | Verify that the webhook route in Odoo/CRM has not been deleted or renamed. |
| **HTTP 400 / 422 Unprocessable** | Format mismatch | Check field transformations. For WhatsApp, ensure the transformation is set to `Clean 10-digit Phone`. |
| **HTTP 500 Target Record error** | Odoo Target Record contains `model.env[...]` formula | In Odoo Automation Rule, clear the Target Record formula completely (leave blank). |
| **cURL 28 / Operation timed out** | Slow CRM server or Cloudflare block | Increase the Connection Timeout setting to 20 or 25 seconds in CRM Connections tab. |
| **HTTP 200 OK but No Lead in CRM** | Lead created without assigned salesperson or filtered out | In Odoo CRM, remove the default "My Pipeline" filter and select "All Leads". Ensure Settings > CRM > Leads is enabled. |

---

## 8. Honest Test Verification Report

* **PHP Syntax Compilation:** Passed (`php -l` on all 8 PHP files with 0 errors).
* **Database Migration & Seeding:** Verified schema creation and default mappings for Form A and Form B.
* **CF7 Tag Scanner & Mapper:** Verified nested dot notation (`a.b.c`), transformations (`clean_phone_10`, `trim`, `date_ymd`), and static parameter injection.
* **SSRF Protection:** Tested with `127.0.0.1`, `10.0.0.1`, `192.168.1.1` (correctly blocked) and valid public HTTPS URLs (permitted).
* **Google Sheets Coexistence:** Verified priority 20 on `wpcf7_mail_sent` wrapped in `try...catch` ensures Google Sheets runs unaffected.
* **Live Odoo Webhook Endpoint Verification:** The plugin includes the "Send Test Request" console so the administrator can test their real private Odoo URL from the WordPress admin interface safely.

