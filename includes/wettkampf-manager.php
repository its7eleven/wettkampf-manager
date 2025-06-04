<?php
/**
 * Plugin Name: Wettkampf Manager
 * Plugin URI: https://7eleven.ch/
 * Description: Plugin für interne Wettkampfausschreibungen und Anmeldungen
 * Version: 1.0.5
 * Author: 7eleven
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WETTKAMPF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WETTKAMPF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WETTKAMPF_VERSION', '1.0.5');

// Include class files
require_once WETTKAMPF_PLUGIN_PATH . 'includes/class-wettkampf-admin.php';
require_once WETTKAMPF_PLUGIN_PATH . 'includes/class-wettkampf-frontend.php';

class WettkampfManager {
    
    private $admin;
    private $frontend;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('wettkampf-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->init_database();
        
        // Initialize admin and frontend classes
        if (is_admin()) {
            $this->admin = new WettkampfAdmin();
        }
        $this->frontend = new WettkampfFrontend();
        
        $this->init_ajax();
        
        // Add shortcodes
        add_shortcode('wettkampf_liste', array($this->frontend, 'display_wettkampf_liste'));
        add_shortcode('wettkampf_anmeldung', array($this->frontend, 'display_anmeldung_form'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_pages();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Wettkämpfe Tabelle
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $sql_wettkampf = "CREATE TABLE $table_wettkampf (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            datum date NOT NULL,
            ort varchar(255) NOT NULL,
            beschreibung text,
            startberechtigte text,
            anmeldeschluss date NOT NULL,
            event_link varchar(500),
            lizenziert tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_datum (datum),
            INDEX idx_anmeldeschluss (anmeldeschluss)
        ) $charset_collate;";
        
        // Disziplinen Tabelle
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        $sql_disziplinen = "CREATE TABLE $table_disziplinen (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            beschreibung text,
            aktiv tinyint(1) DEFAULT 1,
            sortierung int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_aktiv (aktiv),
            INDEX idx_sortierung (sortierung)
        ) $charset_collate;";
        
        // Wettkampf-Disziplinen Zuordnung
        $table_wettkampf_disziplinen = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
        $sql_wettkampf_disziplinen = "CREATE TABLE $table_wettkampf_disziplinen (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wettkampf_id mediumint(9) NOT NULL,
            disziplin_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_wettkampf_id (wettkampf_id),
            INDEX idx_disziplin_id (disziplin_id),
            FOREIGN KEY (wettkampf_id) REFERENCES $table_wettkampf(id) ON DELETE CASCADE,
            FOREIGN KEY (disziplin_id) REFERENCES $table_disziplinen(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Anmeldungen Tabelle
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $sql_anmeldung = "CREATE TABLE $table_anmeldung (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wettkampf_id mediumint(9) NOT NULL,
            vorname varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            geschlecht enum('männlich','weiblich','divers') NOT NULL,
            jahrgang year NOT NULL,
            eltern_fahren tinyint(1) DEFAULT 0,
            freie_plaetze int DEFAULT 0,
            anmeldedatum datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_wettkampf_id (wettkampf_id),
            INDEX idx_email (email),
            INDEX idx_anmeldedatum (anmeldedatum),
            FOREIGN KEY (wettkampf_id) REFERENCES $table_wettkampf(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Anmeldung-Disziplinen Zuordnung
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $sql_anmeldung_disziplinen = "CREATE TABLE $table_anmeldung_disziplinen (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            anmeldung_id mediumint(9) NOT NULL,
            disziplin_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_anmeldung_id (anmeldung_id),
            INDEX idx_disziplin_id (disziplin_id),
            FOREIGN KEY (anmeldung_id) REFERENCES $table_anmeldung(id) ON DELETE CASCADE,
            FOREIGN KEY (disziplin_id) REFERENCES $table_disziplinen(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_wettkampf);
        dbDelta($sql_disziplinen);
        dbDelta($sql_wettkampf_disziplinen);
        dbDelta($sql_anmeldung);
        dbDelta($sql_anmeldung_disziplinen);
        
        // Beispiel-Disziplinen einfügen (nur bei erstmaliger Installation)
        $existing_disziplinen = $wpdb->get_var("SELECT COUNT(*) FROM $table_disziplinen");
        if ($existing_disziplinen == 0) {
            $default_disziplinen = array(
                array('name' => '100m', 'beschreibung' => '100 Meter Sprint', 'sortierung' => 10),
                array('name' => '200m', 'beschreibung' => '200 Meter Sprint', 'sortierung' => 20),
                array('name' => '400m', 'beschreibung' => '400 Meter Lauf', 'sortierung' => 30),
                array('name' => '800m', 'beschreibung' => '800 Meter Lauf', 'sortierung' => 40),
                array('name' => '1500m', 'beschreibung' => '1500 Meter Lauf', 'sortierung' => 50),
                array('name' => 'Weitsprung', 'beschreibung' => 'Weitsprung', 'sortierung' => 60),
                array('name' => 'Hochsprung', 'beschreibung' => 'Hochsprung', 'sortierung' => 70),
                array('name' => 'Kugelstoss', 'beschreibung' => 'Kugelstossen', 'sortierung' => 80)
            );
            
            foreach ($default_disziplinen as $disziplin) {
                $wpdb->insert($table_disziplinen, $disziplin);
            }
        }
    }
    
    private function create_pages() {
        // Hauptseite für Wettkampfliste erstellen
        $page_title = 'Wettkämpfe';
        $page_content = '[wettkampf_liste]';
        $page_check = get_page_by_title($page_title);
        
        if (!isset($page_check->ID)) {
            $page = array(
                'post_type' => 'page',
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_slug' => 'wettkaempfe'
            );
            wp_insert_post($page);
        }
    }
    
    private function init_database() {
        // Database operations class would go here
    }
    
    private function init_ajax() {
        // AJAX handlers
        add_action('wp_ajax_wettkampf_anmeldung', array($this->frontend, 'process_anmeldung'));
        add_action('wp_ajax_nopriv_wettkampf_anmeldung', array($this->frontend, 'process_anmeldung'));
        add_action('wp_ajax_wettkampf_mutation', array($this->frontend, 'process_mutation'));
        add_action('wp_ajax_nopriv_wettkampf_mutation', array($this->frontend, 'process_mutation'));
        add_action('wp_ajax_get_wettkampf_disziplinen', array($this->frontend, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_nopriv_get_wettkampf_disziplinen', array($this->frontend, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_wettkampf_view_only', array($this->frontend, 'process_view_only'));
        add_action('wp_ajax_nopriv_wettkampf_view_only', array($this->frontend, 'process_view_only'));
    }
    
    public function enqueue_frontend_scripts() {
        // Cache-busting version - increment when files change
        $cache_version = WETTKAMPF_VERSION . '.6'; // .6 added for cascade deletion fixes
        
        wp_enqueue_script('wettkampf-frontend', WETTKAMPF_PLUGIN_URL . 'assets/frontend.js', array('jquery'), $cache_version, true);
        wp_enqueue_style('wettkampf-frontend', WETTKAMPF_PLUGIN_URL . 'assets/frontend.css', array(), $cache_version);
        
        // reCAPTCHA
        $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }
        
        wp_localize_script('wettkampf-frontend', 'wettkampf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wettkampf_ajax')
        ));
    }
}

// Initialize the plugin
new WettkampfManager();