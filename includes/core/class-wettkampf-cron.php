<?php
/**
 * Cron job management class - KORRIGIERT für E-Mail-Versand
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
        
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            WettkampfHelpers::log_error('Cron Job: Keine Export-E-Mail konfiguriert');
            return;
        }
        
        WettkampfHelpers::log_error('Cron Job: Export-E-Mail konfiguriert: ' . $export_email);
        
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
            
            WettkampfHelpers::log_error('Cron Job: Sende Export für Wettkampf ' . $wettkampf->name . ' (ID: ' . $wettkampf->id . ')');
            
            $result = $this->send_automatic_export($wettkampf);
            
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
     * Send automatic CSV export for a competition
     */
    public function send_automatic_export($wettkampf) {
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            WettkampfHelpers::log_error('send_automatic_export: Keine Export-E-Mail konfiguriert');
            return false;
        }
        
        // Lade EmailManager falls noch nicht geladen
        if (!class_exists('EmailManager')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-email-manager.php';
        }
        
        $email_manager = new EmailManager();
        $result = $email_manager->send_automatic_export($wettkampf, $export_email);
        
        return $result;
    }
    
    /**
     * Manuelle Test-Funktion für Debugging
     */
    public static function test_cron_manually() {
        $cron = new self();
        
        // Temporär die Stundenprüfung umgehen
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            return 'Keine Export-E-Mail konfiguriert';
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
        $result = $cron->send_automatic_export($wettkampf);
        
        if ($result) {
            update_option('wettkampf_export_sent_' . $wettkampf->id, current_time('mysql'));
            return 'Test-Export erfolgreich gesendet für: ' . $wettkampf->name;
        } else {
            return 'Fehler beim Senden des Test-Exports für: ' . $wettkampf->name;
        }
    }
}