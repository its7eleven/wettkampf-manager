<?php
/**
 * Plugin Name: Wettkampf Manager
 * Plugin URI: https://github.com/dein-username/wettkampf-manager
 * Description: Professionelle Wettkampf-Anmeldungen für Sportvereine. Verwalte Wettkämpfe, Disziplinen und Teilnehmer-Anmeldungen mit automatischen Kategorien (U10-U18).
 * Version: 1.0.0
 * Author: 7eleven
 * Author URI: https://7eleven.ch/
 * Text Domain: wettkampf-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 *
 * @package WettkampfManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WETTKAMPF_VERSION', '1.0.0');
define('WETTKAMPF_DB_VERSION', '1.1.0');
define('WETTKAMPF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WETTKAMPF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WETTKAMPF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 */
function wettkampf_manager_activate() {
    // Load activator class
    require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-activator.php';
    WettkampfActivator::activate();
}
register_activation_hook(__FILE__, 'wettkampf_manager_activate');

/**
 * Plugin deactivation hook
 */
function wettkampf_manager_deactivate() {
    // Load activator class
    require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-activator.php';
    WettkampfActivator::deactivate();
}
register_deactivation_hook(__FILE__, 'wettkampf_manager_deactivate');

/**
 * Load plugin text domain for translations
 */
function wettkampf_manager_load_textdomain() {
    load_plugin_textdomain(
        'wettkampf-manager',
        false,
        dirname(WETTKAMPF_PLUGIN_BASENAME) . '/languages'
    );
}
add_action('plugins_loaded', 'wettkampf_manager_load_textdomain');

/**
 * Check if WordPress and PHP requirements are met
 */
function wettkampf_manager_check_requirements() {
    global $wp_version;
    
    $required_wp_version = '5.0';
    $required_php_version = '7.4';
    
    // Check WordPress version
    if (version_compare($wp_version, $required_wp_version, '<')) {
        deactivate_plugins(WETTKAMPF_PLUGIN_BASENAME);
        wp_die(
            sprintf(
                'Wettkampf Manager benötigt WordPress %s oder höher. Du verwendest Version %s.',
                $required_wp_version,
                $wp_version
            ),
            'Plugin-Anforderungen nicht erfüllt',
            array('back_link' => true)
        );
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, $required_php_version, '<')) {
        deactivate_plugins(WETTKAMPF_PLUGIN_BASENAME);
        wp_die(
            sprintf(
                'Wettkampf Manager benötigt PHP %s oder höher. Du verwendest Version %s.',
                $required_php_version,
                PHP_VERSION
            ),
            'Plugin-Anforderungen nicht erfüllt',
            array('back_link' => true)
        );
    }
}

/**
 * Initialize the plugin
 */
function wettkampf_manager_init() {
    // Check requirements first
    wettkampf_manager_check_requirements();
    
    // Load core classes
    require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-manager.php';
    
    // Run the plugin
    $plugin = new WettkampfManager();
    $plugin->run();
}

/**
 * Start the plugin on plugins_loaded hook
 */
add_action('plugins_loaded', 'wettkampf_manager_init');

/**
 * Add action links to plugin page
 */
function wettkampf_manager_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wettkampf-settings') . '">Einstellungen</a>';
    $docs_link = '<a href="' . admin_url('admin.php?page=wettkampf-manager') . '">Wettkämpfe</a>';
    
    array_unshift($links, $settings_link, $docs_link);
    
    return $links;
}
add_filter('plugin_action_links_' . WETTKAMPF_PLUGIN_BASENAME, 'wettkampf_manager_action_links');

/**
 * Add meta links to plugin page
 */
function wettkampf_manager_meta_links($links, $file) {
    if ($file === WETTKAMPF_PLUGIN_BASENAME) {
        $links[] = '<a href="https://github.com/dein-username/wettkampf-manager" target="_blank">GitHub</a>';
        $links[] = '<a href="https://github.com/dein-username/wettkampf-manager/issues" target="_blank">Support</a>';
        $links[] = '<a href="https://github.com/dein-username/wettkampf-manager/wiki" target="_blank">Dokumentation</a>';
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'wettkampf_manager_meta_links', 10, 2);

/**
 * Display admin notice if plugin requirements are not met
 */
function wettkampf_manager_admin_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Check if we're on the plugins page and this plugin was just activated
    $screen = get_current_screen();
    if ($screen && $screen->id === 'plugins' && isset($_GET['activate'])) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Wettkampf Manager aktiviert!</strong> ';
        echo 'Du kannst jetzt <a href="' . admin_url('admin.php?page=wettkampf-manager') . '">Wettkämpfe verwalten</a> ';
        echo 'oder das <a href="' . admin_url('admin.php?page=wettkampf-settings') . '">Plugin konfigurieren</a>.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'wettkampf_manager_admin_notice');

/**
 * Handle plugin uninstall
 */
if (!function_exists('wettkampf_manager_uninstall')) {
    function wettkampf_manager_uninstall() {
        // This will be handled by uninstall.php
    }
}

/**
 * Add custom cron schedules
 */
function wettkampf_manager_cron_schedules($schedules) {
    $schedules['wettkampf_hourly'] = array(
        'interval' => HOUR_IN_SECONDS,
        'display'  => 'Jede Stunde (Wettkampf)'
    );
    
    return $schedules;
}
add_filter('cron_schedules', 'wettkampf_manager_cron_schedules');

/**
 * Debug information for troubleshooting
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    function wettkampf_manager_debug_info() {
        if (current_user_can('manage_options')) {
            error_log('Wettkampf Manager: Plugin loaded successfully');
            error_log('Wettkampf Manager: Version ' . WETTKAMPF_VERSION);
            error_log('Wettkampf Manager: Path ' . WETTKAMPF_PLUGIN_PATH);
        }
    }
    add_action('init', 'wettkampf_manager_debug_info');
}

// End of file