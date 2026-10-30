<?php
/**
 * Odoo CRM Integration Helper, Diagnostics, and Python Server Action Generator.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Odoo
{
    /**
     * Get pre-built Odoo Python Server Action code for Odoo Automation Rules.
     */
    public static function get_odoo_server_action_python()
    {
        return <<<'PYTHON'
# ==============================================================================
# Odoo CRM Automation Rule: Contact Form 7 Lead Receiver
# Trigger: On Webhook
# Target Record: (LEAVE BLANK! DO NOT use model.env[...].browse(...))
# Action To Do: Execute Python Code
# ==============================================================================

data = payload or {}

# 1. Extract contact fields safely
candidate_name = data.get('name') or data.get('full-name') or 'Website Inquiry'
candidate_email = data.get('email') or data.get('email_from') or ''
candidate_phone = data.get('phone') or data.get('whatsapp') or ''
candidate_city  = data.get('city') or ''
source_label    = data.get('source') or data.get('form-source') or 'Contact Form 7'
form_title      = data.get('cf7_form_title') or 'CF7 Form'
submission_id   = data.get('submission_id') or ''

# 2. Extract hidden qualification fields (Form B)
qualified_status = data.get('qualfied') or ''
lead_status      = data.get('lead_status') or data.get('lead-status') or ''
created_date     = data.get('created_date') or data.get('created-date') or ''
meeting_status   = data.get('meeting_status') or data.get('meeting-status') or ''
payment_link     = data.get('payment_link') or data.get('payment-link') or ''
wp_group         = data.get('wp_group_created') or data.get('wp-group-created') or ''

# 3. Compile audit description
notes_lines = [
    f"--- CONTACT FORM 7 SUBMISSION ---",
    f"Form: {form_title}",
    f"Source: {source_label}",
    f"Submission ID: {submission_id}",
]
if qualified_status:
    notes_lines.append(f"Qualified: {qualified_status}")
if meeting_status:
    notes_lines.append(f"Meeting Status: {meeting_status}")
if payment_link:
    notes_lines.append(f"Payment Link: {payment_link}")
if wp_group:
    notes_lines.append(f"WP Group: {wp_group}")
if lead_status:
    notes_lines.append(f"Initial Lead Status: {lead_status}")
if created_date:
    notes_lines.append(f"Submission Timestamp: {created_date}")

# 4. Create the Lead in crm.lead
lead_vals = {
    'name': f"{candidate_name} - {source_label}",
    'contact_name': candidate_name,
    'email_from': candidate_email,
    'phone': candidate_phone,
    'mobile': candidate_phone,
    'city': candidate_city,
    'description': "\n".join(notes_lines),
    'type': 'lead', # Or 'opportunity' depending on your pipeline
}

# Check for existing lead by phone or email to prevent duplicates if desired
existing = None
if candidate_phone:
    existing = env['crm.lead'].sudo().search([('phone', '=', candidate_phone)], limit=1)
if not existing and candidate_email:
    existing = env['crm.lead'].sudo().search([('email_from', '=', candidate_email)], limit=1)

if existing:
    # Update existing lead with latest inquiry details
    existing.sudo().write({
        'description': existing.description + "\n\n" + "\n".join(notes_lines)
    })
    response = {'status': 'updated', 'lead_id': existing.id}
else:
    # Create brand new CRM lead
    new_lead = env['crm.lead'].sudo().create(lead_vals)
    response = {'status': 'created', 'lead_id': new_lead.id}
PYTHON;
    }

    /**
     * Explanation of known Odoo issue and resolution steps.
     */
    public static function get_diagnostic_guide()
    {
        return [
            'issue_title' => 'Why did the previous Odoo Webhook test fail on Target Record?',
            'root_cause'  => 'In Odoo Automation Rules with Trigger "On Webhook", Odoo pre-populates a Target Record expression: model.env[payload.get(\'_model\')].browse(int(payload.get(\'_id\'))). This formula assumes the incoming request wants to update an existing record by its database ID. But website inquiries from Contact Form 7 are brand new submissions, so neither _model nor _id exist in the submission, resulting in Python evaluating int(None) which raises an unhandled exception and returns HTTP 500.',
            'solution_steps' => [
                '1. Open your Odoo Database as Administrator.',
                '2. Go to Settings > Technical > Automation > Automation Rules (or Automated Actions in Odoo 16).',
                '3. Open your Webhook Automation Rule.',
                '4. In the "Target Record" field, DELETE the formula entirely (leave Target Record empty/unbound).',
                '5. In the "Action To Do" dropdown, select "Execute Python Code".',
                '6. Copy and paste the provided Python snippet from the plugin Documentation page.',
                '7. Save the Automation Rule.',
                '8. In Universal CRM Connector, click "Send Test Request" — it will now return HTTP 200 and create the lead!',
            ],
            'verification' => 'Never rely solely on an HTTP 200 response to assume a lead exists. An HTTP 200 merely confirms the HTTP server accepted the webhook. Always open Odoo CRM > Pipeline (or CRM > Leads) and search for the lead by name, phone, or email to verify record creation.',
        ];
    }
}
