<?php
/**
 * Main plugin management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfManager {
    
    /**
     * Plugin version
     */
    protected $version;
    
    /**
     * Admin handler
     */
    protected $admin;
    
    /**
     * Frontend handler
     */
    protected $frontend;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->version = WETTKAMPF_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->init_cron_jobs();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core utilities
        $this->require_file_safe('includes/utils/class-wettkampf-helpers.php');
        $this->require_file_safe('includes/utils/class-category-calculator.php');
        $this->require_file_safe('includes/utils/class-email-manager.php');
        $this->require_file_safe('includes/utils/class-security-manager.php');
        
        // Core classes
        $this->require_file_safe('includes/core/class-wettkampf-database.php');
        $this->require_file_safe('includes/core/class-wettkampf-cron.php');
        
        // Admin classes (only if in admin)
        if (is_admin()) {
            $this->require_file_safe('includes/admin/class-admin-menu.php');
            $this->require_file_safe('includes/admin/class-admin-wettkampf.php');
            $this->require_file_safe('includes/admin/class-admin-disziplinen.php');
            $this->require_file_safe('includes/admin/class-admin-anmeldungen.php');
            $this->require_file_safe('includes/admin/class-admin-export.php');
            $this->require_file_safe('includes/admin/class-admin-settings.php');
            $this->require_file_safe('includes/admin/class-wettkampf-admin.php');
            
            if (class_exists('WettkampfAdmin')) {
                $this->admin = new WettkampfAdmin();
            }
        }
        
        // Frontend classes
        $this->require_file_safe('includes/frontend/class-frontend-display.php');
        $this->require_file_safe('includes/frontend/class-frontend-forms.php');
        $this->require_file_safe('includes/frontend/class-frontend-ajax.php');
        $this->require_file_safe('includes/frontend/class-frontend-shortcodes.php');
        $this->require_file_safe('includes/frontend/class-wettkampf-frontend.php');
        
        if (class_exists('WettkampfFrontend')) {
            $this->frontend = new WettkampfFrontend();
        }
    }
    
    /**
     * Sichere Datei-Einbindung mit Fehlerbehandlung
     */
    private function require_file_safe($relative_path) {
        $full_path = WETTKAMPF_PLUGIN_PATH . $relative_path;
        
        if (file_exists($full_path)) {
            require_once $full_path;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Wettkampf Manager: Datei nicht gefunden: $relative_path");
            }
        }
    }
    
    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Define frontend hooks
     */
    private function define_frontend_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('init', array($this, 'init_shortcodes'));
        add_action('init', array($this, 'init_ajax_handlers'));
        add_action('init', array($this, 'check_database_updates'));
    }
    
    /**
     * Initialize shortcodes
     */
    public function init_shortcodes() {
        if (class_exists('FrontendShortcodes')) {
            $shortcodes = new FrontendShortcodes();
            $shortcodes->init();
        }
    }
    
    /**
     * Initialize AJAX handlers
     */
    public function init_ajax_handlers() {
        if (class_exists('FrontendAjax')) {
            $ajax = new FrontendAjax();
            $ajax->init();
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wettkampf') === false) {
            return;
        }
        
        $cache_version = $this->version . '.4';
        
        // CSS
        $admin_css = WETTKAMPF_PLUGIN_PATH . 'assets/css/admin.css';
        if (file_exists($admin_css)) {
            wp_enqueue_style(
                'wettkampf-admin',
                WETTKAMPF_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                $cache_version
            );
        }
        
        // JavaScript
        $admin_js = WETTKAMPF_PLUGIN_PATH . 'assets/js/admin.js';
        if (file_exists($admin_js)) {
            wp_enqueue_script(
                'wettkampf-admin',
                WETTKAMPF_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                $cache_version,
                true
            );
            
            wp_localize_script('wettkampf-admin', 'wettkampf_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wettkampf_admin_ajax')
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        $cache_version = $this->version . '.4';
        
        // CSS
        $frontend_css = WETTKAMPF_PLUGIN_PATH . 'assets/css/frontend.css';
        if (file_exists($frontend_css)) {
            wp_enqueue_style(
                'wettkampf-frontend',
                WETTKAMPF_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                $cache_version
            );
        }
        
        // JavaScript
        $frontend_js = WETTKAMPF_PLUGIN_PATH . 'assets/js/frontend.js';
        if (file_exists($frontend_js)) {
            wp_enqueue_script(
                'wettkampf-frontend',
                WETTKAMPF_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                $cache_version,
                true
            );
            
            // reCAPTCHA
            $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key');
            if (!empty($recaptcha_site_key)) {
                wp_enqueue_script(
                    'recaptcha', 
                    'https://www.google.com/recaptcha/api.js', 
                    array(), 
                    null, 
                    true
                );
            }
            
            // AJAX-Daten
            wp_localize_script('wettkampf-frontend', 'wettkampf_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wettkampf_ajax')
            ));
        }
    }
    
    /**
     * Initialize cron jobs
     */
    private function init_cron_jobs() {
        if (class_exists('WettkampfCron')) {
            $cron = new WettkampfCron();
            $cron->init();
        }
    }
    
    /**
     * Check for database updates
     */
    public function check_database_updates() {
        $current_version = get_option('wettkampf_db_version', '1.0.0');
        
        if (version_compare($current_version, WETTKAMPF_DB_VERSION, '<')) {
            $this->run_database_updates($current_version);
            update_option('wettkampf_db_version', WETTKAMPF_DB_VERSION);
        }
    }
    
    /**
     * Run database updates
     */
    private function run_database_updates($from_version) {
        if (version_compare($from_version, '1.1.0', '<')) {
            $this->update_database_to_1_1_0();
        }
    }
    
    /**
     * Update database to version 1.1.0
     */
    private function update_database_to_1_1_0() {
        if (class_exists('WettkampfActivator')) {
            global $wpdb;
            $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
            
            $column_exists = $wpdb->get_results($wpdb->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_name = %s 
                AND column_name = 'kategorie'
                AND table_schema = %s
            ", $table_disziplinen, DB_NAME));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_disziplinen ADD COLUMN kategorie varchar(20) DEFAULT 'Alle' AFTER beschreibung");
                $wpdb->query("ALTER TABLE $table_disziplinen ADD INDEX idx_kategorie (kategorie)");
                $wpdb->query("UPDATE $table_disziplinen SET kategorie = 'Alle' WHERE kategorie IS NULL OR kategorie = ''");
            }
        }
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Plugin is running
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }
}