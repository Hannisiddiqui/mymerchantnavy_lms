/**
 * Universal CRM Connector Admin JavaScript
 */
(function ($) {
    'use strict';

    $(function () {
        // 1. Toggle Auth Credentials Fields in Connection Form
        function updateAuthFields() {
            var selected = $('#ucc_conn_auth_type').val();
            $('#ucc-auth-creds-wrap .ucc-auth-field').hide();

            if (selected === 'bearer') {
                $('.ucc-auth-bearer').show();
            } else if (selected === 'basic') {
                $('.ucc-auth-basic').show();
            } else if (selected === 'custom_header') {
                $('.ucc-auth-custom').show();
            } else if (selected === 'odoo_api') {
                $('.ucc-auth-odoo').show();
            }
        }
        $('#ucc_conn_auth_type').on('change', updateAuthFields);
        updateAuthFields();

        // 2. Save Connection AJAX
        $('#ucc-connection-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $spinner = $form.find('.ucc-save-spinner');
            var $msg = $form.find('.ucc-save-msg');

            $spinner.addClass('is-active');
            $msg.text('').css('color', '');

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'ucc_save_connection' });
            formData.push({ name: 'nonce', value: uccAdmin.nonce });

            $.post(uccAdmin.ajaxurl, formData, function (res) {
                $spinner.removeClass('is-active');
                if (res && res.success) {
                    $msg.text(res.data.message || 'Saved!').css('color', '#15803d');
                    setTimeout(function () {
                        window.location.href = 'admin.php?page=universal-crm-connector&tab=connections';
                    }, 800);
                } else {
                    $msg.text((res && res.data && res.data.message) || 'Failed to save connection.').css('color', '#dc2626');
                }
            }).fail(function () {
                $spinner.removeClass('is-active');
                $msg.text('Network error while saving.').css('color', '#dc2626');
            });
        });

        // 3. Delete Connection
        $('.ucc-btn-delete-conn').on('click', function () {
            if (!confirm('Are you sure you want to delete this CRM connection? Any form mappings using it will need to be re-assigned.')) {
                return;
            }
            var connId = $(this).data('id');
            $.post(uccAdmin.ajaxurl, {
                action: 'ucc_delete_connection',
                nonce: uccAdmin.nonce,
                id: connId
            }, function (res) {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    alert((res && res.data && res.data.message) || 'Failed to delete connection.');
                }
            });
        });

        // 4. Dynamic Mapping Rows
        var mapRowIdx = $('#ucc-mapping-table tbody tr').length + 10;
        $('#ucc-btn-add-row').on('click', function () {
            mapRowIdx++;
            var rowHtml = '<tr>' +
                '<td><input type="text" name="mappings[' + mapRowIdx + '][cf7_field]" class="widefat" placeholder="e.g. city"></td>' +
                '<td><input type="text" name="mappings[' + mapRowIdx + '][crm_key]" class="widefat" placeholder="e.g. city"></td>' +
                '<td><select name="mappings[' + mapRowIdx + '][transform]" class="widefat">' +
                    '<option value="trim">Trim whitespace</option>' +
                    '<option value="clean_phone_10">Clean 10-digit Phone</option>' +
                    '<option value="uppercase">Uppercase</option>' +
                    '<option value="lowercase">Lowercase</option>' +
                    '<option value="date_ymd">Format Date (Y-m-d)</option>' +
                    '<option value="date_dmy">Format Date (d-m-Y)</option>' +
                    '<option value="boolean_int">Boolean to 1/0</option>' +
                '</select></td>' +
                '<td><input type="text" name="mappings[' + mapRowIdx + '][default_value]" class="widefat" placeholder="Fallback"></td>' +
                '<td><button type="button" class="button button-small button-link-delete ucc-btn-remove-row">&times;</button></td>' +
                '</tr>';
            $('#ucc-mapping-table tbody').append(rowHtml);
        });

        // 5. Dynamic Static Key Rows
        var staticRowIdx = $('#ucc-static-table tbody tr').length + 10;
        $('#ucc-btn-add-static').on('click', function () {
            staticRowIdx++;
            var staticHtml = '<tr>' +
                '<td><input type="text" name="static[' + staticRowIdx + '][key]" class="widefat" placeholder="e.g. _model"></td>' +
                '<td><input type="text" name="static[' + staticRowIdx + '][value]" class="widefat" placeholder="e.g. crm.lead"></td>' +
                '<td><button type="button" class="button button-small button-link-delete ucc-btn-remove-row">&times;</button></td>' +
                '</tr>';
            $('#ucc-static-table tbody').append(staticHtml);
        });

        // Remove row delegate
        $(document).on('click', '.ucc-btn-remove-row', function () {
            $(this).closest('tr').remove();
        });

        // 6. Save Mapping Form AJAX
        $('#ucc-mapping-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $spinner = $form.find('.ucc-save-spinner');
            var $msg = $form.find('.ucc-save-msg');

            $spinner.addClass('is-active');
            $msg.text('').css('color', '');

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'ucc_save_mapping' });
            formData.push({ name: 'nonce', value: uccAdmin.nonce });

            $.post(uccAdmin.ajaxurl, formData, function (res) {
                $spinner.removeClass('is-active');
                if (res && res.success) {
                    $msg.text(res.data.message || 'Saved!').css('color', '#15803d');
                    refreshPreview();
                } else {
                    $msg.text((res && res.data && res.data.message) || 'Failed to save mapping.').css('color', '#dc2626');
                }
            }).fail(function () {
                $spinner.removeClass('is-active');
                $msg.text('Error saving mapping.').css('color', '#dc2626');
            });
        });

        // 7. Refresh Preview
        function refreshPreview() {
            var formId = $('input[name="cf7_form_id"]').val();
            if (!formId) return;

            $.post(uccAdmin.ajaxurl, {
                action: 'ucc_preview_payload',
                nonce: uccAdmin.nonce,
                cf7_form_id: formId
            }, function (res) {
                if (res && res.success) {
                    $('#ucc-json-preview code').text(res.data.json);
                }
            });
        }
        $('#ucc-btn-refresh-preview').on('click', refreshPreview);

        // 8. Copy JSON Preview
        $('#ucc-btn-copy-preview').on('click', function () {
            var text = $('#ucc-json-preview code').text();
            navigator.clipboard.writeText(text).then(function () {
                alert('Sample JSON payload copied to clipboard!');
            });
        });

        // 9. Copy Python Code
        $('#ucc-btn-copy-odoo').on('click', function () {
            var text = $('#ucc-odoo-python').text();
            navigator.clipboard.writeText(text).then(function () {
                alert('Odoo Python Lead Creator code copied to clipboard!');
            });
        });

        // 10. Test Connection Presets
        $('#ucc_test_preset').on('change', function () {
            var val = $(this).val();
            if (val === 'form_a') {
                $('#ucc_test_payload').val(JSON.stringify({
                    "full-name": "Rohan Sharma",
                    "email": "rohan.sharma@example.com",
                    "whatsapp": "+91 9876543210",
                    "city": "Mumbai",
                    "form-source": "Data Needed",
                    "cf7_form_id": 8612,
                    "cf7_form_title": "Data Needed",
                    "submission_id": "test-" + Math.floor(Math.random() * 1000000)
                }, null, 2));
            } else if (val === 'form_b') {
                $('#ucc_test_payload').val(JSON.stringify({
                    "full-name": "Aditya Verma",
                    "email": "aditya.verma@example.com",
                    "whatsapp": "+91 9812345678",
                    "phone": "+91 9812345678",
                    "city": "Delhi",
                    "form-source": "Sales Funnel / Eligibility Checker",
                    "qualfied": "Eligible (IMU-CET Fit)",
                    "pcm%": "78.5%",
                    "course": "DNS & B.Sc Nautical Science Pass",
                    "created-date": "2026-10-30",
                    "lead-status": "new",
                    "meeting-status": "scheduled",
                    "payment-link": "https://mymerchantnavy.com/pass/?mmn_buy=dns-pass",
                    "cf7_form_id": 8795,
                    "cf7_form_title": "Sales Funnel / Eligibility Checker",
                    "submission_id": "test-" + Math.floor(Math.random() * 1000000)
                }, null, 2));
            }
        });

        // 11. Send Test Request AJAX
        $('#ucc-test-form').on('submit', function (e) {
            e.preventDefault();
            var connId = $('#ucc_test_conn').val();
            var payload = $('#ucc_test_payload').val();
            var $spinner = $('.ucc-test-spinner');
            var $badge = $('#ucc-test-badge');

            $spinner.addClass('is-active');
            $badge.attr('class', 'ucc-badge-warning').text('Sending Request...');
            $('#ucc-res-status').text('Connecting...');
            $('#ucc-res-latency').text('—');
            $('#ucc-res-body code').text('Awaiting response from CRM destination...');
            $('#ucc-res-diagnosis').hide();

            $.post(uccAdmin.ajaxurl, {
                action: 'ucc_test_connection',
                nonce: uccAdmin.nonce,
                connection_id: connId,
                payload: payload
            }, function (res) {
                $spinner.removeClass('is-active');
                if (res && res.success && res.data) {
                    var d = res.data;
                    $('#ucc-res-status').text(d.http_code ? d.http_code + (d.success ? ' OK' : ' Error') : 'Network Failed');
                    $('#ucc-res-latency').text((d.latency_ms || 0) + ' ms');

                    if (d.success) {
                        $badge.attr('class', 'ucc-badge-success').text('HTTP ' + d.http_code + ' Passed');
                    } else {
                        $badge.attr('class', 'ucc-badge-danger').text('HTTP ' + (d.http_code || 'Failed'));
                    }

                    $('#ucc-res-body code').text(d.body || (d.error ? 'Error: ' + d.error : '(Empty response body returned)'));

                    if (d.error) {
                        var diagHtml = '<strong>Diagnostic Note:</strong> ' + $('<div>').text(d.error).html();
                        if (d.error.indexOf('myodoo.example.com') !== -1 || (d.body && d.body.indexOf('myodoo.example.com') !== -1)) {
                            diagHtml += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #fecaca;">' +
                                '<strong>⚠️ Demo Placeholder URL Detected:</strong> The endpoint is currently pointing to the sample URL <code>https://myodoo.example.com</code>. ' +
                                'To connect your live Odoo instance, go to the <strong>CRM Connections</strong> tab and paste your actual Odoo Webhook URL.' +
                                '<div style="margin-top:10px;"><a href="admin.php?page=universal-crm-connector&tab=connections" class="button button-small button-primary">Edit CRM Connection URL &rarr;</a></div>' +
                                '</div>';
                        }
                        $('#ucc-res-diagnosis').show().css({ background: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c' })
                            .html(diagHtml);
                    } else if (d.success) {
                        $('#ucc-res-diagnosis').show().css({ background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#166534' })
                            .html('<strong>HTTP 200 Handshake Success:</strong> Request accepted by CRM. Please verify that the record is now visible in Odoo CRM &gt; Pipeline.');
                    }
                } else {
                    $badge.attr('class', 'ucc-badge-danger').text('Test Call Failed');
                    $('#ucc-res-status').text('Error');
                    $('#ucc-res-body code').text((res && res.data && res.data.message) || 'Unknown error occurred.');
                }
            }).fail(function (xhr) {
                $spinner.removeClass('is-active');
                $badge.attr('class', 'ucc-badge-danger').text('AJAX Network Error');
                $('#ucc-res-body code').text('AJAX call failed with HTTP status ' + xhr.status);
            });
        });

        // 12. View Log Details Modal
        $('.ucc-btn-view-log').on('click', function () {
            var log = $(this).data('log');
            if (!log) return;

            $('#ucc-modal-meta').html(
                '<strong>Form:</strong> ' + $('<div>').text(log.cf7_form_title).html() +
                ' | <strong>Source:</strong> ' + $('<div>').text(log.source_label || log.connection_name).html() +
                ' | <strong>Status:</strong> ' + $('<div>').text(log.status + ' (' + (log.http_code || 0) + ')').html() +
                ' | <strong>Latency:</strong> ' + log.execution_time_ms + 'ms' +
                '<br><strong>Endpoint:</strong> <code>' + $('<div>').text(log.endpoint_url).html() + '</code>'
            );

            $('#ucc-modal-request code').text(log.request_payload || '(Empty payload)');
            $('#ucc-modal-response code').text(log.response_body || log.error_message || '(No response body)');

            $('#ucc-log-modal').fadeIn(150);
        });

        $('.ucc-modal-close, #ucc-log-modal').on('click', function (e) {
            if (e.target === this) {
                $('#ucc-log-modal').fadeOut(150);
            }
        });

        // 13. Retry Delivery
        $('.ucc-btn-retry-log').on('click', function () {
            var $btn = $(this);
            var logId = $btn.data('id');
            $btn.prop('disabled', true).text('Retrying...');

            $.post(uccAdmin.ajaxurl, {
                action: 'ucc_retry_delivery',
                nonce: uccAdmin.nonce,
                log_id: logId
            }, function (res) {
                if (res && res.success) {
                    alert('Delivery retry dispatched! HTTP Code: ' + (res.data.http_code || 'N/A') + '. Refreshing logs...');
                    window.location.reload();
                } else {
                    alert('Retry failed: ' + ((res && res.data && res.data.message) || 'Unknown error.'));
                    $btn.prop('disabled', false).text('Retry');
                }
            }).fail(function () {
                alert('Network error while retrying delivery.');
                $btn.prop('disabled', false).text('Retry');
            });
        });

        // 14. Clear Logs
        $('#ucc-btn-clear-logs').on('click', function () {
            if (!confirm('Are you sure you want to delete all CRM delivery logs? This action cannot be undone.')) {
                return;
            }
            $.post(uccAdmin.ajaxurl, {
                action: 'ucc_clear_logs',
                nonce: uccAdmin.nonce
            }, function (res) {
                if (res && res.success) {
                    window.location.reload();
                }
            });
        });

        // 15. Accordion in Troubleshooting
        $('.ucc-acc-head').on('click', function () {
            $(this).next('.ucc-acc-body').slideToggle(200);
        });
    });
})(jQuery);

