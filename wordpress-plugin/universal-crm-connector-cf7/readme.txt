=== Universal CRM Connector for Contact Form 7 ===
Contributors: mymerchantnavy
Tags: contact form 7, crm, odoo, webhook, rest api, lead generation, integration
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Contact Form 7 submissions to Odoo CRM, HubSpot, Zoho, and custom REST APIs / Webhooks with visual field mapping, automatic retries, SSRF protection, and audit logs without interfering with Google Sheets or transactional emails.

== Description ==

**Universal CRM Connector for Contact Form 7** is a high-reliability, production-ready WordPress plugin engineered to route Contact Form 7 submissions directly to CRM endpoints (such as Odoo, HubSpot, Zoho, or any generic HTTP webhook) without editing PHP code.

### Core Architecture Highlights

* **Multi-Form Routing:** Detects all Contact Form 7 forms installed on your WordPress site. Each form can route to independent CRM endpoints or share endpoints with distinct source labels and field mappings.
* **Preserves Existing Integrations:** Hooks into `wpcf7_mail_sent` at priority 20 inside isolated `try...catch` blocks. Your CF7 notification emails and plugins like **CF7 Google Sheets Connector** run uninterrupted.
* **Odoo CRM Compatibility:** Directly resolves the known Odoo Automation Rule failure (`model.env[payload.get('_model')].browse(int(payload.get('_id')))`). Includes copy-paste Python Server Action code to create `crm.lead` records seamlessly.
* **Enterprise Security & SSRF Protection:** Enforces TLS verification (`sslverify => true`), validates destination hosts against RFC1918 private and loopback networks to prevent Server-Side Request Forgery, and encrypts saved credentials using AES-256.
* **Privacy by Default:** Sensitive authorization tokens and passwords are redacted from all logs. Full personal data payload logging is turned OFF by default to respect data privacy standards.
* **Safe Resiliency & Retries:** Automatically distinguishes between permanent 4xx configuration errors (which are not retried endlessly) and transient 5xx or network timeouts (which can be retried up to 3 times or manually re-triggered from the admin console).

== Installation ==

1. Download the `universal-crm-connector-cf7.zip` archive.
2. In your WordPress admin dashboard, navigate to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.
5. Navigate to the new **Universal CRM** menu in your WordPress sidebar.

== Frequently Asked Questions ==

= Will this interfere with my Google Sheets integration? =
No. The plugin hooks into `wpcf7_mail_sent` at priority 20 after Google Sheets and transactional emails have finished. It never suppresses mail or alters POST data.

= How does it solve the Odoo Target Record issue? =
In Odoo Automation Rules (Trigger: On Webhook), Odoo defaults to an expression expecting `_id` to update existing records. Because Contact Form 7 submissions are brand new leads, evaluating `int(None)` causes an HTTP 500 error. The included Odoo documentation guides you to clear the Target Record formula and use `env['crm.lead'].sudo().create(...)`.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Automated detection of Contact Form 7 forms.
* Pre-configured mappings for Form A ("Data Needed") and Form B ("Sales Funnel / Eligibility Checker").
* Full Odoo Python Server Action guide and live test console.
