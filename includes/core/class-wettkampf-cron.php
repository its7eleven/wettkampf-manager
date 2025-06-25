<?php
/**
 * Cron job management class - ERWEITERT mit Multi-E-Mail Support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfCron {
    
    /**
     * Initialize cron jobs
     */
    public function init() {
        add_action('wettkampf_check_expired_registrations', array($this, 'check_expired_registrations'));
        
        if (!wp_next_scheduled('wettkampf_check_expired_registrations')) {
            wp_schedule_event(time(), 'hourly', 'wettkampf_check_expired_registrations');
        }
    }
    
    /**
     * Check for expired registrations and send CSV exports
     */
    public function check_expired_registrations() {
        // Debug logging
        WettkampfHelpers::log_error('Cron Job: check_expired_registrations läuft');
        
        $current_hour = date('H', current_time('timestamp'));
        
        // Only run between 02:00 and 03:00 to avoid multiple sends
        if ($current_hour != '02') {
            WettkampfHelpers::log_error('Cron Job: Läuft nicht - aktuelle Stunde ist ' . $current_hour);
            return;
        }
        
        // Get export emails
        if (class_exists('AdminSettings')) {
            $admin_settings = new AdminSettings();
            $export_emails = $admin_settings->get_export_emails();
        } else {
            // Fallback: Parse from option
            $export_email_text = get_option('wettkampf_export_email', '');
            $export_emails = $this->parse_export_emails($export_email_text);
        }
        
        if (empty($export_emails)) {
            WettkampfHelpers::log_error('Cron Job: Keine Export-E-Mail-Adressen konfiguriert');
            return;
        }
        
        WettkampfHelpers::log_error('Cron Job: Export-E-Mail-Adressen konfiguriert: ' . implode(', ', $export_emails));
        
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Find competitions where registration deadline passed yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // KORRIGIERT: Prüfe ob Export bereits gesendet wurde
        $expired_competitions = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
            WHERE w.anmeldeschluss = %s
            GROUP BY w.id
            HAVING anmeldungen_count > 0
        ", $yesterday));
        
        WettkampfHelpers::log_error('Cron Job: Gefundene abgelaufene Wettkämpfe: ' . count($expired_competitions));
        
        foreach ($expired_competitions as $wettkampf) {
            // Prüfe ob bereits gesendet
            $sent_flag = get_option('wettkampf_export_sent_' . $wettkampf->id);
            
            if ($sent_flag) {
                WettkampfHelpers::log_error('Cron Job: Export für Wettkampf ' . $wettkampf->name . ' (ID: ' . $wettkampf->id . ') wurde bereits gesendet');
                continue;
            }
            
            WettkampfHelpers::log_error('Cron Job: Sende Export für Wettkampf ' . $wettkampf->name . ' (ID: ' . $wettkampf->id . ') an ' . count($export_emails) . ' E-Mail-Adressen');
            
            $result = $this->send_automatic_export($wettkampf, $export_emails);
            
            if ($result) {
                // Markiere als gesendet
                update_option('wettkampf_export_sent_' . $wettkampf->id, current_time('mysql'));
                WettkampfHelpers::log_error('Cron Job: Export erfolgreich gesendet für Wettkampf ' . $wettkampf->id);
            } else {
                WettkampfHelpers::log_error('Cron Job: Export FEHLER für Wettkampf ' . $wettkampf->id);
            }
        }
    }
    
    /**
     * Send automatic CSV export for a competition - ERWEITERT mit Multi-E-Mail Support
     */
    public function send_automatic_export($wettkampf, $export_emails = null) {
        // Get export emails if not provided
        if ($export_emails === null) {
            if (class_exists('AdminSettings')) {
                $admin_settings = new AdminSettings();
                $export_emails = $admin_settings->get_export_emails();
            } else {
                // Fallback: Parse from option
                $export_email_text = get_option('wettkampf_export_email', '');
                $export_emails = $this->parse_export_emails($export_email_text);
            }
        }
        
        if (empty($export_emails)) {
            WettkampfHelpers::log_error('send_automatic_export: Keine Export-E-Mail-Adressen konfiguriert');
            return false;
        }
        
        // Lade EmailManager falls noch nicht geladen
        if (!class_exists('EmailManager')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-email-manager.php';
        }
        
        $email_manager = new EmailManager();
        $result = $email_manager->send_automatic_export($wettkampf, $export_emails);
        
        return $result;
    }
    
    /**
     * Parse export email addresses from text
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
     * Manuelle Test-Funktion für Debugging - ERWEITERT
     */
    public static function test_cron_manually() {
        $cron = new self();
        
        // Get export emails
        if (class_exists('AdminSettings')) {
            $admin_settings = new AdminSettings();
            $export_emails = $admin_settings->get_export_emails();
        } else {
            // Fallback: Parse from option
            $export_email_text = get_option('wettkampf_export_email', '');
            $export_emails = $cron->parse_export_emails($export_email_text);
        }
        
        if (empty($export_emails)) {
            return 'Keine Export-E-Mail-Adressen konfiguriert';
        }
        
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Finde ALLE Wettkämpfe mit abgelaufenem Anmeldeschluss
        $today = date('Y-m-d');
        
        $expired_competitions = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
            WHERE w.anmeldeschluss < %s
            GROUP BY w.id
            HAVING anmeldungen_count > 0
            ORDER BY w.anmeldeschluss DESC
            LIMIT 1
        ", $today));
        
        if (empty($expired_competitions)) {
            return 'Keine abgelaufenen Wettkämpfe mit Anmeldungen gefunden';
        }
        
        $wettkampf = $expired_competitions[0];
        
        // Lösche temporär die "bereits gesendet" Markierung
        delete_option('wettkampf_export_sent_' . $wettkampf->id);
        
        // Sende Export
        $result = $cron->send_automatic_export($wettkampf, $export_emails);
        
        if ($result) {
            update_option('wettkampf_export_sent_' . $wettkampf->id, current_time('mysql'));
            return 'Test-Export erfolgreich gesendet für: ' . $wettkampf->name . ' an ' . count($export_emails) . ' E-Mail-Adresse(n): ' . implode(', ', $export_emails);
        } else {
            return 'Fehler beim Senden des Test-Exports für: ' . $wettkampf->name;
        }
    }
}