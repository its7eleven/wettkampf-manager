<?php
/**
 * Plugin uninstallation script
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include database helper
require_once plugin_dir_path(__FILE__) . 'includes/core/class-wettkampf-database.php';

/**
 * Remove all plugin data
 */
function wettkampf_uninstall_cleanup() {
    global $wpdb;
    
    $tables = WettkampfDatabase::get_table_names();
    
    // Drop all tables (in reverse order due to foreign keys)
    $wpdb->query("DROP TABLE IF EXISTS {$tables['anmeldung_disziplinen']}");
    $wpdb->query("DROP TABLE IF EXISTS {$tables['anmeldung']}");
    $wpdb->query("DROP TABLE IF EXISTS {$tables['disziplin_zuordnung']}");
    $wpdb->query("DROP TABLE IF EXISTS {$tables['disziplinen']}");
    $wpdb->query("DROP TABLE IF EXISTS {$tables['wettkampf']}");
    
    // Remove all plugin options
    $options_to_remove = array(
        'wettkampf_db_version',
        'wettkampf_recaptcha_site_key',
        'wettkampf_recaptcha_secret_key',
        'wettkampf_sender_email',
        'wettkampf_sender_name',
        'wettkampf_export_email'
    );
    
    foreach ($options_to_remove as $option) {
        delete_option($option);
    }
    
    // Remove export sent flags
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'wettkampf_export_sent_%'");
    
    // Remove rate limiting transients
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_wettkampf_rate_limit_%' OR option_name LIKE '_transient_timeout_wettkampf_rate_limit_%'");
    
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('wettkampf_check_expired_registrations');
    
    // Remove pages created by plugin (optional - you might want to keep them)
    $page = get_page_by_title('Wettkämpfe');
    if ($page) {
        wp_delete_post($page->ID, true);
    }
}

// Run cleanup
wettkampf_uninstall_cleanup();