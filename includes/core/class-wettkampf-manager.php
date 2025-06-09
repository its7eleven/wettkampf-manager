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
     * Plugin loader
     */
    protected $loader;
    
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
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helpers.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-category-calculator.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-email-manager.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-security-manager.php';
        
        // Core classes
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-database.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-cron.php';
        
        // Admin classes (only if in admin)
        if (is_admin()) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-wettkampf-admin.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-menu.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-wettkampf.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-disziplinen.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-anmeldungen.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-export.php';
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-settings.php';
            
            $this->admin = new WettkampfAdmin();
        }
        
        // Frontend classes
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/frontend/class-wettkampf-frontend.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/frontend/class-frontend-display.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/frontend/class-frontend-forms.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/frontend/class-frontend-ajax.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/frontend/class-frontend-shortcodes.php';
        
        $this->frontend = new WettkampfFrontend();
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
        $shortcodes = new FrontendShortcodes();
        $shortcodes->init();
    }
    
    /**
     * Initialize AJAX handlers
     */
    public function init_ajax_handlers() {
        $ajax = new FrontendAjax();
        $ajax->init();
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wettkampf') === false) {
            return;
        }
        
        $cache_version = $this->version . '.1';
        
        wp_enqueue_style(
            'wettkampf-admin',
            WETTKAMPF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $cache_version
        );
        
        if (isset($_GET['page']) && $_GET['page'] === 'wettkampf-anmeldungen') {
            wp_enqueue_script(
                'wettkampf-admin-core',
                WETTKAMPF_PLUGIN_URL . 'assets/js/admin/admin-core.js',
                array('jquery'),
                $cache_version,
                true
            );
            
            wp_enqueue_script(
                'wettkampf-admin-export',
                WETTKAMPF_PLUGIN_URL . 'assets/js/admin/admin-export.js',
                array('wettkampf-admin-core'),
                $cache_version,
                true
            );
            
            wp_localize_script('wettkampf-admin-core', 'wettkampf_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wettkampf_admin_ajax')
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        $cache_version = $this->version . '.1';
        
        // CSS
        wp_enqueue_style(
            'wettkampf-frontend',
            WETTKAMPF_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $cache_version
        );
        
        // Core JavaScript
        wp_enqueue_script(
            'wettkampf-frontend-core',
            WETTKAMPF_PLUGIN_URL . 'assets/js/frontend/frontend-core.js',
            array('jquery'),
            $cache_version,
            true
        );
        
        // Modal management
        wp_enqueue_script(
            'wettkampf-frontend-modals',
            WETTKAMPF_PLUGIN_URL . 'assets/js/frontend/frontend-modals.js',
            array('wettkampf-frontend-core'),
            $cache_version,
            true
        );
        
        // Form handling
        wp_enqueue_script(
            'wettkampf-frontend-forms',
            WETTKAMPF_PLUGIN_URL . 'assets/js/frontend/frontend-forms.js',
            array('wettkampf-frontend-core'),
            $cache_version,
            true
        );
        
        // AJAX requests
        wp_enqueue_script(
            'wettkampf-frontend-ajax',
            WETTKAMPF_PLUGIN_URL . 'assets/js/frontend/frontend-ajax.js',
            array('wettkampf-frontend-core'),
            $cache_version,
            true
        );
        
        // reCAPTCHA
        $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }
        
        // Localize script
        wp_localize_script('wettkampf-frontend-core', 'wettkampf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wettkampf_ajax')
        ));
    }
    
    /**
     * Initialize cron jobs
     */
    private function init_cron_jobs() {
        $cron = new WettkampfCron();
        $cron->init();
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
        
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->update_database_to_1_2_0();
        }
    }
    
    /**
     * Update database to version 1.1.0 - Add kategorie column
     */
    private function update_database_to_1_1_0() {
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
    
    /**
     * Update database to version 1.2.0 - Future updates
     */
    private function update_database_to_1_2_0() {
        // Future database updates will go here
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Plugin is ready to run
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }
}