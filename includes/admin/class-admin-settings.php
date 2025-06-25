<?php
/**
 * Settings management admin class - ERWEITERT mit Multi-E-Mail Support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminSettings {
    
    /**
     * Display settings page
     */
    public function display_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['settings_nonce'], 'save_settings')) {
            $this->save_settings();
        }
        
        // Handle test email
        if (isset($_POST['test_email']) && wp_verify_nonce($_POST['test_nonce'], 'test_email')) {
            $this->test_email_configuration();
        }
        
        // Get current settings
        $recaptcha_site_key = WettkampfHelpers::get_option('recaptcha_site_key', '');
        $recaptcha_secret_key = WettkampfHelpers::get_option('recaptcha_secret_key', '');
        $sender_email = WettkampfHelpers::get_option('sender_email', get_option('admin_email'));
        $sender_name = WettkampfHelpers::get_option('sender_name', get_option('blogname'));
        $export_email = WettkampfHelpers::get_option('export_email', '');
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Einstellungen</h1>
            
            <form method="post">
                <?php wp_nonce_field('save_settings', 'settings_nonce'); ?>
                
                <!-- reCAPTCHA Settings -->
                <div class="settings-section">
                    <h3>🔒 reCAPTCHA Einstellungen</h3>
                    <p>Schütze Anmeldungen vor Spam durch Google reCAPTCHA v2.</p>
                    
                    <table class="form-table">
                        <?php
                        WettkampfHelpers::render_form_row(
                            'reCAPTCHA Site Key',
                            '<input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="' . SecurityManager::escape_attr($recaptcha_site_key) . '" class="regular-text">',
                            'Den Site Key erhältst du von <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA</a>'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'reCAPTCHA Secret Key',
                            '<input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key" value="' . SecurityManager::escape_attr($recaptcha_secret_key) . '" class="regular-text">',
                            'Der Secret Key bleibt geheim und wird nur auf dem Server verwendet'
                        );
                        ?>
                    </table>
                </div>
                
                <!-- Email Settings -->
                <div class="settings-section">
                    <h3>📧 E-Mail Einstellungen</h3>
                    <p>Konfiguriere die E-Mail-Adressen für ausgehende Nachrichten.</p>
                    
                    <table class="form-table">
                        <?php
                        WettkampfHelpers::render_form_row(
                            'Absender E-Mail',
                            '<input type="email" id="sender_email" name="sender_email" value="' . SecurityManager::escape_attr($sender_email) . '" class="regular-text">',
                            'E-Mail-Adresse für ausgehende Nachrichten (Bestätigungen, etc.) - <strong>NUR eine E-Mail-Adresse</strong>'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'Absender Name',
                            '<input type="text" id="sender_name" name="sender_name" value="' . SecurityManager::escape_attr($sender_name) . '" class="regular-text">',
                            'Name für ausgehende Nachrichten'
                        );
                        ?>
                    </table>
                    
                    <!-- Test Email Button -->
                    <div style="margin-top: 15px;">
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('test_email', 'test_nonce'); ?>
                            <button type="submit" name="test_email" class="button button-secondary">
                                📧 Test-E-Mail senden
                            </button>
                            <span style="margin-left: 10px; color: #666; font-size: 13px;">
                                Sendet eine Test-E-Mail an <?php echo get_option('admin_email'); ?>
                            </span>
                        </form>
                    </div>
                </div>
                
                <!-- Auto-Export Settings -->
                <div class="settings-section">
                    <h3>🤖 Automatischer Export</h3>
                    <p>Automatische CSV-Exports nach Anmeldeschluss konfigurieren.</p>
                    
                    <table class="form-table">
                        <?php
                        WettkampfHelpers::render_form_row(
                            'Export E-Mail-Adressen',
                            '<textarea id="export_email" name="export_email" rows="3" class="large-text" placeholder="max@mustermann.de&#10;trainer@verein.de&#10;vorstand@verein.de">' . SecurityManager::escape_html($export_email) . '</textarea>',
                            'E-Mail-Adressen für automatische CSV-Exports nach Anmeldeschluss (eine pro Zeile, max. 5 Adressen)'
                        );
                        ?>
                    </table>
                    
                    <?php 
                    $export_emails = $this->parse_export_emails($export_email);
                    if (!empty($export_emails)): 
                    ?>
                        <div class="notice notice-success inline" style="margin: 10px 0; padding: 8px 12px;">
                            <p style="margin: 0;"><strong>✓ Automatische Exports sind aktiviert für:</strong></p>
                            <ul style="margin: 5px 0 0 20px;">
                                <?php foreach ($export_emails as $email): ?>
                                    <li><?php echo SecurityManager::escape_html($email); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px;">
                            <p style="margin: 0;"><strong>⚠ Automatische Exports sind deaktiviert</strong> (keine gültigen E-Mail-Adressen hinterlegt)</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Einstellungen speichern">
                </p>
            </form>
            
            <!-- Information Box -->
            <div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                <h3>🤖 Automatische CSV-Exports</h3>
                <p><strong>Funktionsweise:</strong></p>
                <ul>
                    <li>Das System prüft stündlich, ob Anmeldefristen abgelaufen sind</li>
                    <li>2 Stunden nach Mitternacht des Anmeldeschlusstages wird automatisch ein CSV-Export generiert</li>
                    <li>Der Export wird an alle konfigurierten E-Mail-Adressen gesendet</li>
                    <li>Pro Wettkampf wird nur einmal ein automatischer Export versendet</li>
                    <li>CSV-Format für beste Kompatibilität mit allen E-Mail-Clients und Mobilgeräten</li>
                    <li><strong>Multi-E-Mail:</strong> Du kannst bis zu 5 E-Mail-Adressen angeben (eine pro Zeile)</li>
                </ul>
                
                <p><strong>Technische Details:</strong></p>
                <ul>
                    <li>WordPress Cron-Job läuft stündlich</li>
                    <li>Export-Zeitfenster: 02:00 - 03:00 Uhr</li>
                    <li>Nur Wettkämpfe mit Anmeldungen werden exportiert</li>
                    <li>UTF-8 Kodierung mit BOM für korrekte Umlaute</li>
                    <li>Jede E-Mail-Adresse wird separat validiert</li>
                </ul>
                
                <?php
                $next_cron = wp_next_scheduled('wettkampf_check_expired_registrations');
                if ($next_cron):
                ?>
                <p><strong>Nächste Prüfung:</strong> <?php echo date('d.m.Y H:i:s', $next_cron + (get_option('gmt_offset') * HOUR_IN_SECONDS)); ?> Uhr</p>
                <?php endif; ?>
                
                <p><strong>Status prüfen:</strong> <a href="?page=wettkampf-export-status">Auto-Export Status ansehen</a></p>
            </div>
            
            <!-- E-Mail Guidelines -->
            <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <h3>📧 E-Mail-Richtlinien</h3>
                <ul>
                    <li><strong>Absender E-Mail:</strong> Nur EINE E-Mail-Adresse erlaubt (WordPress-Standard)</li>
                    <li><strong>Export E-Mails:</strong> Mehrere E-Mail-Adressen möglich (eine pro Zeile, max. 5)</li>
                    <li><strong>Validierung:</strong> Ungültige E-Mail-Adressen werden automatisch ignoriert</li>
                    <li><strong>Sicherheit:</strong> Alle E-Mail-Adressen werden vor der Speicherung validiert</li>
                    <li><strong>Performance:</strong> Begrenzung auf 5 Adressen verhindert Missbrauch</li>
                </ul>
            </div>
            
            <!-- Security Information -->
            <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <h3>🔐 Sicherheitshinweise</h3>
                <ul>
                    <li><strong>reCAPTCHA:</strong> Aktiviere reCAPTCHA um Spam-Anmeldungen zu verhindern</li>
                    <li><strong>Rate Limiting:</strong> Das System verhindert automatisch zu viele Versuche von der gleichen IP</li>
                    <li><strong>Datenvalidierung:</strong> Alle Eingaben werden serverseitig validiert und bereinigt</li>
                    <li><strong>E-Mail-Verifikation:</strong> Anmeldungen können nur mit korrekter E-Mail und Jahrgang bearbeitet werden</li>
                    <li><strong>CSRF-Schutz:</strong> Alle Formulare sind durch WordPress Nonces geschützt</li>
                </ul>
            </div>
        </div>
        
        <style>
        .settings-section {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .settings-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #c3c4c7;
            color: #1d2327;
        }
        
        .notice.inline {
            display: block;
            border-left: 4px solid;
            background: #fff;
            border-radius: 4px;
        }
        
        .notice.notice-success.inline {
            border-left-color: #00a32a;
            background: #f0f6fc;
        }
        
        .notice.notice-warning.inline {
            border-left-color: #dba617;
            background: #fcf9e8;
        }
        
        #export_email {
            font-family: monospace;
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    /**
     * Parse and validate export email addresses
     */
    private function parse_export_emails($export_email_text) {
        if (empty($export_email_text)) {
            return array();
        }
        
        $lines = explode("\n", $export_email_text);
        $valid_emails = array();
        
        foreach ($lines as $line) {
            $email = trim($line);
            
            // Skip empty lines
            if (empty($email)) {
                continue;
            }
            
            // Validate email
            if (is_email($email)) {
                $valid_emails[] = $email;
                
                // Limit to 5 addresses
                if (count($valid_emails) >= 5) {
                    break;
                }
            }
        }
        
        return $valid_emails;
    }
    
    /**
     * Save settings - ERWEITERT mit Multi-E-Mail Support
     */
    private function save_settings() {
        SecurityManager::check_admin_permissions();
        
        // Sanitize settings data
        $sanitization_rules = array(
            'recaptcha_site_key' => 'text',
            'recaptcha_secret_key' => 'text',
            'sender_email' => 'email',
            'sender_name' => 'text',
            'export_email' => 'textarea'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        
        // Validation for sender email (single email only)
        if (!empty($data['sender_email']) && !is_email($data['sender_email'])) {
            WettkampfHelpers::add_admin_notice('Absender E-Mail ist ungültig', 'error');
            return;
        }
        
        // Validate and clean export emails
        $export_emails = $this->parse_export_emails($data['export_email']);
        $invalid_count = 0;
        
        if (!empty($data['export_email'])) {
            $all_lines = explode("\n", $data['export_email']);
            $total_lines = count(array_filter($all_lines, function($line) {
                return !empty(trim($line));
            }));
            $invalid_count = $total_lines - count($export_emails);
        }
        
        // Convert valid emails back to text format
        $data['export_email'] = implode("\n", $export_emails);
        
        // Save settings
        $settings_saved = true;
        
        $settings_saved &= WettkampfHelpers::update_option('recaptcha_site_key', $data['recaptcha_site_key']);
        $settings_saved &= WettkampfHelpers::update_option('recaptcha_secret_key', $data['recaptcha_secret_key']);
        $settings_saved &= WettkampfHelpers::update_option('sender_email', $data['sender_email']);
        $settings_saved &= WettkampfHelpers::update_option('sender_name', $data['sender_name']);
        $settings_saved &= WettkampfHelpers::update_option('export_email', $data['export_email']);
        
        if ($settings_saved) {
            $message = 'Einstellungen gespeichert!';
            
            if (count($export_emails) > 0) {
                $message .= ' Export aktiviert für ' . count($export_emails) . ' E-Mail-Adresse(n).';
            }
            
            if ($invalid_count > 0) {
                $message .= ' ' . $invalid_count . ' ungültige E-Mail-Adresse(n) wurden ignoriert.';
            }
            
            WettkampfHelpers::add_admin_notice($message);
        } else {
            WettkampfHelpers::add_admin_notice('Fehler beim Speichern der Einstellungen', 'error');
        }
    }
    
    /**
     * Test email configuration
     */
    private function test_email_configuration() {
        SecurityManager::check_admin_permissions();
        
        $email_manager = new EmailManager();
        $result = $email_manager->test_email_configuration();
        
        if ($result) {
            WettkampfHelpers::add_admin_notice('✅ Test-E-Mail erfolgreich versendet an ' . get_option('admin_email'));
        } else {
            WettkampfHelpers::add_admin_notice('❌ Fehler beim Versenden der Test-E-Mail. Bitte prüfe die E-Mail-Konfiguration.', 'error');
        }
    }
    
    /**
     * Get setting with default value
     */
    public function get_setting($key, $default = '') {
        return WettkampfHelpers::get_option($key, $default);
    }
    
    /**
     * Update setting
     */
    public function update_setting($key, $value) {
        return WettkampfHelpers::update_option($key, $value);
    }
    
    /**
     * Check if reCAPTCHA is configured
     */
    public function is_recaptcha_configured() {
        $site_key = $this->get_setting('recaptcha_site_key');
        $secret_key = $this->get_setting('recaptcha_secret_key');
        
        return !empty($site_key) && !empty($secret_key);
    }
    
    /**
     * Check if auto-export is configured
     */
    public function is_auto_export_configured() {
        $export_email = $this->get_setting('export_email');
        $valid_emails = $this->parse_export_emails($export_email);
        return !empty($valid_emails);
    }
    
    /**
     * Get all valid export email addresses
     */
    public function get_export_emails() {
        $export_email = $this->get_setting('export_email');
        return $this->parse_export_emails($export_email);
    }
}