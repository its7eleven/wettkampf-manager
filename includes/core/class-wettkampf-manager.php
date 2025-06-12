<?php
/**
 * KORRIGIERTE VERSION - Main plugin management class
 * Fix für WettkampfHelpers Klassen-Problem
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
     * Load plugin dependencies - KORRIGIERT MIT HELPER-FIX
     */
    private function load_dependencies() {
        // Core utilities - KORRIGIERTE REIHENFOLGE UND NAMEN
        
        // 1. ZUERST Helper-Klasse laden (verschiedene Dateinamen prüfen)
        if (file_exists(WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helpers.php')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helpers.php';
        } elseif (file_exists(WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helper.php')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helper.php';
        } else {
            // Erstelle minimale Helper-Klasse als Fallback
            $this->create_minimal_helpers_class();
        }
        
        // 2. Dann andere Utils
        $this->require_file_safe('includes/utils/class-category-calculator.php');
        $this->require_file_safe('includes/utils/class-email-manager.php');
        $this->require_file_safe('includes/utils/class-security-manager.php');
        
        // 3. Core classes
        $this->require_file_safe('includes/core/class-wettkampf-database.php');
        $this->require_file_safe('includes/core/class-wettkampf-cron.php');
        
        // 4. Admin classes (only if in admin)
        if (is_admin()) {
            $this->require_file_safe('includes/admin/class-admin-menu.php');
            $this->require_file_safe('includes/admin/class-admin-wettkampf.php');
            $this->require_file_safe('includes/admin/class-admin-disziplinen.php');
            $this->require_file_safe('includes/admin/class-admin-anmeldungen.php');
            $this->require_file_safe('includes/admin/class-admin-export.php');
            $this->require_file_safe('includes/admin/class-admin-settings.php');
            
            // Admin-Manager laden
            if (file_exists(WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-wettkampf-admin.php')) {
                require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-wettkampf-admin.php';
            } elseif (file_exists(WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-manager.php')) {
                require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-manager.php';
            } else {
                $this->create_minimal_admin_class();
            }
            
            // Nur instanziieren wenn Klasse existiert
            if (class_exists('WettkampfAdmin')) {
                $this->admin = new WettkampfAdmin();
            }
        }
        
        // 5. Frontend classes
        $this->require_file_safe('includes/frontend/class-frontend-display.php');
        $this->require_file_safe('includes/frontend/class-frontend-forms.php');
        $this->require_file_safe('includes/frontend/class-frontend-ajax.php');
        $this->require_file_safe('includes/frontend/class-frontend-shortcodes.php');
        $this->require_file_safe('includes/frontend/class-wettkampf-frontend.php');
        
        // Nur instanziieren wenn Klasse existiert
        if (class_exists('WettkampfFrontend')) {
            $this->frontend = new WettkampfFrontend();
        }
    }
    
    /**
     * NEUE FUNKTION: Erstelle minimale WettkampfHelpers Klasse als Fallback
     */
    private function create_minimal_helpers_class() {
        if (!class_exists('WettkampfHelpers')) {
            eval('
            class WettkampfHelpers {
                public static function sanitize_filename($filename) {
                    return preg_replace("/[^a-zA-Z0-9_-]/", "_", $filename);
                }
                
                public static function csv_escape($value) {
                    $value = trim((string)$value);
                    if (strpos($value, ";") !== false || strpos($value, ",") !== false || strpos($value, "\"") !== false) {
                        $value = "\"" . str_replace("\"", "\"\"", $value) . "\"";
                    }
                    return $value;
                }
                
                public static function format_german_date($date, $include_time = false) {
                    if (empty($date)) return "";
                    $format = $include_time ? "d.m.Y H:i" : "d.m.Y";
                    return date($format, strtotime($date));
                }
                
                public static function is_mobile_user_agent() {
                    $user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
                    return preg_match("/Mobile|Android|iPhone|iPad/", $user_agent);
                }
                
                public static function get_option($option_name, $default = "") {
                    return get_option("wettkampf_" . $option_name, $default);
                }
                
                public static function update_option($option_name, $value) {
                    return update_option("wettkampf_" . $option_name, $value);
                }
                
                public static function add_admin_notice($message, $type = "success") {
                    $class = ($type === "error") ? "notice-error" : "notice-success";
                    echo "<div class=\"notice " . $class . "\"><p>" . esc_html($message) . "</p></div>";
                }
                
                public static function log_error($message, $context = array()) {
                    if (defined("WP_DEBUG") && WP_DEBUG) {
                        error_log("Wettkampf Manager: " . $message . (!empty($context) ? " Context: " . print_r($context, true) : ""));
                    }
                }
                
                public static function get_current_admin_url($remove_params = array()) {
                    $url = admin_url("admin.php");
                    if (!empty($_GET["page"])) {
                        $url .= "?page=" . urlencode($_GET["page"]);
                        foreach ($_GET as $key => $value) {
                            if ($key !== "page" && !in_array($key, $remove_params)) {
                                $url .= "&" . urlencode($key) . "=" . urlencode($value);
                            }
                        }
                    }
                    return $url;
                }
                
                public static function render_form_row($label, $input_html, $description = "") {
                    echo "<tr>";
                    echo "<th><label>" . esc_html($label) . "</label></th>";
                    echo "<td>" . $input_html;
                    if (!empty($description)) {
                        echo "<p class=\"description\">" . esc_html($description) . "</p>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                
                public static function get_status_badge($status, $text = "") {
                    $classes = array(
                        "active" => "status-badge active",
                        "inactive" => "status-badge inactive",
                        "expired" => "status-badge expired"
                    );
                    $class = isset($classes[$status]) ? $classes[$status] : "status-badge";
                    $display_text = !empty($text) ? $text : ucfirst($status);
                    return "<span class=\"" . esc_attr($class) . "\">" . esc_html($display_text) . "</span>";
                }
                
                public static function get_category_badge($category) {
                    $class = "kategorie-badge kategorie-" . strtolower($category);
                    return "<span class=\"" . esc_attr($class) . "\">" . esc_html($category) . "</span>";
                }
                
                public static function truncate_text($text, $length = 100, $append = "...") {
                    if (strlen($text) <= $length) {
                        return $text;
                    }
                    return substr($text, 0, $length) . $append;
                }
                
                public static function array_to_options($array, $selected = "", $include_empty = false) {
                    $html = "";
                    if ($include_empty) {
                        $html .= "<option value=\"\">Bitte wählen</option>";
                    }
                    foreach ($array as $value => $label) {
                        $selected_attr = selected($selected, $value, false);
                        $html .= "<option value=\"" . esc_attr($value) . "\"" . $selected_attr . ">" . esc_html($label) . "</option>";
                    }
                    return $html;
                }
            }
            ');
            
            error_log('Wettkampf Manager: Minimale WettkampfHelpers Klasse erstellt');
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
            error_log("Wettkampf Manager: Datei nicht gefunden: $relative_path");
        }
    }
    
    /**
     * Erstelle eine minimale Admin-Klasse als Fallback
     */
    private function create_minimal_admin_class() {
        if (!class_exists('WettkampfAdmin')) {
            eval('
            class WettkampfAdmin {
                public function __construct() {
                    add_action("admin_menu", array($this, "add_admin_menu"));
                }
                
                public function add_admin_menu() {
                    add_menu_page(
                        "Wettkampf Manager",
                        "Wettkämpfe", 
                        "manage_options",
                        "wettkampf-manager",
                        array($this, "display_page"),
                        "dashicons-awards",
                        30
                    );
                }
                
                public function display_page() {
                    echo "<div class=\"wrap\">";
                    echo "<h1>Wettkampf Manager</h1>";
                    echo "<p>Plugin wird geladen... Einige Dateien fehlen noch.</p>";
                    echo "<h3>Fehlende Dateien prüfen:</h3>";
                    echo "<ul>";
                    
                    $required_files = array(
                        "includes/utils/class-wettkampf-helper.php (oder class-wettkampf-helpers.php)",
                        "includes/admin/class-admin-menu.php",
                        "includes/admin/class-admin-wettkampf.php",
                        "includes/admin/class-admin-disziplinen.php",
                        "includes/admin/class-admin-anmeldungen.php",
                        "includes/admin/class-admin-export.php",
                        "includes/admin/class-admin-settings.php",
                        "assets/js/frontend.js",
                        "assets/css/frontend.css"
                    );
                    
                    foreach ($required_files as $file) {
                        echo "<li>" . esc_html($file) . "</li>";
                    }
                    
                    echo "</ul>";
                    echo "<p><strong>Hinweis:</strong> Das Plugin verwendet Fallback-Klassen für fehlende Dateien.</p>";
                    echo "</div>";
                }
            }
            ');
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
        
        $cache_version = $this->version . '.3';
        
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
        $cache_version = $this->version . '.3';
        
        // CSS
        $frontend_css = WETTKAMPF_PLUGIN_PATH . 'assets/css/frontend.css';
        if (file_exists($frontend_css)) {
            wp_enqueue_style(
                'wettkampf-frontend',
                WETTKAMPF_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                $cache_version
            );
        } else {
            error_log('Wettkampf Manager: frontend.css nicht gefunden in: ' . $frontend_css);
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
                'nonce' => wp_create_nonce('wettkampf_ajax'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Wettkampf Manager: frontend.js erfolgreich geladen');
            }
        } else {
            error_log('Wettkampf Manager: frontend.js nicht gefunden in: ' . $frontend_js);
            add_action('wp_footer', array($this, 'add_fallback_javascript'));
        }
    }
    
    /**
     * Fallback JavaScript
     */
    public function add_fallback_javascript() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Wettkampf Manager: Fallback JavaScript geladen');
            
            // Basis Details Toggle
            $(document).on('click', '.details-toggle', function(e) {
                e.preventDefault();
                const wettkampfId = $(this).data('wettkampf-id');
                const detailsDiv = $('#details-' + wettkampfId);
                const toggleText = $(this).find('.toggle-text');
                
                if (detailsDiv.is(':visible')) {
                    detailsDiv.slideUp();
                    if (toggleText.length) toggleText.text('Details anzeigen');
                    $(this).removeClass('active');
                } else {
                    detailsDiv.slideDown();
                    if (toggleText.length) toggleText.text('Details ausblenden');
                    $(this).addClass('active');
                }
            });
            
            console.log('Basis-Funktionalität geladen. Bitte lade die vollständige frontend.js hoch.');
        });
        </script>
        <?php
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Wettkampf Manager: Plugin initialisiert - Version ' . $this->version);
        }
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }
}