<?php
/**
 * Cron job management class
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
        $current_hour = date('H', current_time('timestamp'));
        
        // Only run between 02:00 and 03:00 to avoid multiple sends
        if ($current_hour != '02') {
            return;
        }
        
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            return;
        }
        
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Find competitions where registration deadline passed yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $expired_competitions = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
            WHERE w.anmeldeschluss = %s
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}options 
                WHERE option_name = CONCAT('wettkampf_export_sent_', w.id)
            )
            GROUP BY w.id
            HAVING anmeldungen_count > 0
        ", $yesterday));
        
        foreach ($expired_competitions as $wettkampf) {
            $this->send_automatic_export($wettkampf);
            update_option('wettkampf_export_sent_' . $wettkampf->id, current_time('mysql'));
        }
    }
    
    /**
     * Send automatic CSV export for a competition
     */
    public function send_automatic_export($wettkampf) {
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            return false;
        }
        
        $email_manager = new EmailManager();
        return $email_manager->send_automatic_export($wettkampf, $export_email);
    }
}