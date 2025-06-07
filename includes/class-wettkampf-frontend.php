<?php
/**
 * Plugin Name: Wettkampf Manager
 * Plugin URI: https://7eleven.ch/
 * Description: Plugin für interne Wettkampfausschreibungen und Anmeldungen
 * Version: 1.1.0
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
define('WETTKAMPF_VERSION', '1.1.0');
define('WETTKAMPF_DB_VERSION', '1.1.0');

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
        
        // Database updates check
        $this->check_database_updates();
        
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
        
        // Initialize cron job for automatic exports
        $this->init_cron_jobs();
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_pages();
        $this->setup_cron_jobs();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        $this->cleanup_cron_jobs();
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
        
        // Disziplinen Tabelle - ERWEITERT mit kategorie Spalte
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        $sql_disziplinen = "CREATE TABLE $table_disziplinen (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            beschreibung text,
            kategorie varchar(20) DEFAULT 'Alle',
            aktiv tinyint(1) DEFAULT 1,
            sortierung int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_aktiv (aktiv),
            INDEX idx_sortierung (sortierung),
            INDEX idx_kategorie (kategorie)
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
        
        // Beispiel-Disziplinen mit Kategorien einfügen (nur bei erstmaliger Installation)
        $existing_disziplinen = $wpdb->get_var("SELECT COUNT(*) FROM $table_disziplinen");
        if ($existing_disziplinen == 0) {
            $default_disziplinen = array(
                // U10 Disziplinen (für alle unter 10 Jahren)
                array('name' => '50m Sprint', 'beschreibung' => '50 Meter Sprint für die Jüngsten', 'kategorie' => 'U10', 'sortierung' => 10),
                array('name' => 'Ballwurf', 'beschreibung' => 'Ballwurf für U10', 'kategorie' => 'U10', 'sortierung' => 80),
                array('name' => 'Weitsprung Zone', 'beschreibung' => 'Weitsprung aus der Zone', 'kategorie' => 'U10', 'sortierung' => 60),
                
                // U12 Disziplinen
                array('name' => '75m Sprint', 'beschreibung' => '75 Meter Sprint', 'kategorie' => 'U12', 'sortierung' => 15),
                array('name' => '600m Lauf', 'beschreibung' => '600 Meter Lauf', 'kategorie' => 'U12', 'sortierung' => 40),
                
                // U14 Disziplinen
                array('name' => '100m', 'beschreibung' => '100 Meter Sprint', 'kategorie' => 'U14', 'sortierung' => 20),
                array('name' => '800m', 'beschreibung' => '800 Meter Lauf', 'kategorie' => 'U14', 'sortierung' => 45),
                
                // U16 Disziplinen
                array('name' => '200m', 'beschreibung' => '200 Meter Sprint', 'kategorie' => 'U16', 'sortierung' => 25),
                array('name' => '1500m', 'beschreibung' => '1500 Meter Lauf', 'kategorie' => 'U16', 'sortierung' => 50),
                array('name' => 'Kugelstoss', 'beschreibung' => 'Kugelstossen', 'kategorie' => 'U16', 'sortierung' => 85),
                
                // U18 Disziplinen
                array('name' => '400m', 'beschreibung' => '400 Meter Lauf', 'kategorie' => 'U18', 'sortierung' => 30),
                array('name' => '3000m', 'beschreibung' => '3000 Meter Lauf', 'kategorie' => 'U18', 'sortierung' => 55),
                
                // Alle Kategorien
                array('name' => 'Weitsprung', 'beschreibung' => 'Weitsprung für alle Kategorien', 'kategorie' => 'Alle', 'sortierung' => 65),
                array('name' => 'Hochsprung', 'beschreibung' => 'Hochsprung für alle Kategorien', 'kategorie' => 'Alle', 'sortierung' => 70)
            );
            
            foreach ($default_disziplinen as $disziplin) {
                $wpdb->insert($table_disziplinen, $disziplin);
            }
        }
        
        // Set initial database version
        update_option('wettkampf_db_version', WETTKAMPF_DB_VERSION);
    }
    
    /**
     * Check for database updates for existing installations
     */
    public function check_database_updates() {
        $current_version = get_option('wettkampf_db_version', '1.0.0');
        
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->update_database_to_1_1_0();
            update_option('wettkampf_db_version', '1.1.0');
        }
    }
    
    /**
     * Update database to version 1.1.0 - Add kategorie column
     */
    private function update_database_to_1_1_0() {
        global $wpdb;
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Check if kategorie column already exists
        $column_exists = $wpdb->get_results($wpdb->prepare("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE table_name = %s 
            AND column_name = 'kategorie'
            AND table_schema = %s
        ", $table_disziplinen, DB_NAME));
        
        if (empty($column_exists)) {
            // Add column
            $wpdb->query("ALTER TABLE $table_disziplinen ADD COLUMN kategorie varchar(20) DEFAULT 'Alle' AFTER beschreibung");
            $wpdb->query("ALTER TABLE $table_disziplinen ADD INDEX idx_kategorie (kategorie)");
            
            // Set existing disciplines to 'Alle'
            $wpdb->query("UPDATE $table_disziplinen SET kategorie = 'Alle' WHERE kategorie IS NULL OR kategorie = ''");
            
            error_log('Wettkampf Manager: Database updated to version 1.1.0 - Added kategorie column');
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
        $cache_version = WETTKAMPF_VERSION . '.7'; // .7 added for category features
        
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
    
    /**
     * Helper function to calculate age category
     * Only uses categories: U10, U12, U14, U16, U18
     * Always uses the next appropriate category
     */
    public static function calculateAgeCategory($jahrgang, $currentYear = null) {
        if (!$currentYear) {
            $currentYear = date('Y');
        }
        
        $age = $currentYear - $jahrgang;
        
        // Nur diese 5 Kategorien verwenden, immer nächst passende wählen
        if ($age < 10) return 'U10';  // Alle unter 10 Jahren → U10
        if ($age < 12) return 'U12';  // 10-11 Jahre → U12
        if ($age < 14) return 'U14';  // 12-13 Jahre → U14
        if ($age < 16) return 'U16';  // 14-15 Jahre → U16
        if ($age < 18) return 'U18';  // 16-17 Jahre → U18
        
        return 'U18'; // Alle 18+ Jahre bleiben in U18
    }
    
    /**
     * Initialize cron jobs for automatic exports
     */
    private function init_cron_jobs() {
        // Hook for the cron job
        add_action('wettkampf_check_expired_registrations', array($this, 'check_expired_registrations'));
        
        // Schedule the cron job if not already scheduled
        if (!wp_next_scheduled('wettkampf_check_expired_registrations')) {
            wp_schedule_event(time(), 'hourly', 'wettkampf_check_expired_registrations');
        }
    }
    
    /**
     * Setup cron jobs on activation
     */
    private function setup_cron_jobs() {
        // Clear any existing cron job first
        wp_clear_scheduled_hook('wettkampf_check_expired_registrations');
        
        // Schedule new cron job to run every hour
        wp_schedule_event(time(), 'hourly', 'wettkampf_check_expired_registrations');
    }
    
    /**
     * Cleanup cron jobs on deactivation
     */
    private function cleanup_cron_jobs() {
        wp_clear_scheduled_hook('wettkampf_check_expired_registrations');
    }
    
    /**
     * Check for expired registrations and send Excel exports
     */
    public function check_expired_registrations() {
        global $wpdb;
        
        // Get current time - 2 hours after midnight means we check at 02:00
        $current_time = current_time('mysql');
        $current_hour = date('H', current_time('timestamp'));
        
        // Only run between 02:00 and 03:00 to avoid multiple sends
        if ($current_hour != '02') {
            return;
        }
        
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        // Find competitions where registration deadline passed within the last 24 hours
        // and we haven't sent an export yet
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        
        $expired_competitions = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM $table_wettkampf w 
            LEFT JOIN $table_anmeldung a ON w.id = a.wettkampf_id 
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
            
            // Mark as sent
            update_option('wettkampf_export_sent_' . $wettkampf->id, current_time('mysql'));
        }
    }
    
    /**
     * Send automatic Excel export for a competition
     */
    public function send_automatic_export($wettkampf) {
        global $wpdb;
        
        $export_email = get_option('wettkampf_export_email', '');
        if (empty($export_email)) {
            error_log('Wettkampf Manager: No export email configured for automatic export');
            return;
        }
        
        // Generate Excel file
        $excel_content = $this->generate_excel_export($wettkampf->id);
        
        if (!$excel_content) {
            error_log('Wettkampf Manager: Failed to generate Excel export for competition ' . $wettkampf->id);
            return;
        }
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name($wettkampf->name) . '_anmeldungen_' . date('Y-m-d') . '.xls';
        $temp_file = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents($temp_file, $excel_content);
        
        // Send email
        $subject = 'Anmeldungen für ' . $wettkampf->name . ' (Anmeldeschluss abgelaufen)';
        
        $message = "Hallo,\n\n";
        $message .= "die Anmeldefrist für den Wettkampf '" . $wettkampf->name . "' ist abgelaufen.\n\n";
        $message .= "Wettkampf-Details:\n";
        $message .= "- Name: " . $wettkampf->name . "\n";
        $message .= "- Datum: " . date('d.m.Y', strtotime($wettkampf->datum)) . "\n";
        $message .= "- Ort: " . $wettkampf->ort . "\n";
        $message .= "- Anmeldeschluss: " . date('d.m.Y', strtotime($wettkampf->anmeldeschluss)) . "\n";
        $message .= "- Anzahl Anmeldungen: " . $wettkampf->anmeldungen_count . "\n\n";
        $message .= "Im Anhang findest du die Excel-Datei mit allen Anmeldungen.\n\n";
        $message .= "Diese E-Mail wurde automatisch vom Wettkampf Manager generiert.\n";
        
        $sender_email = get_option('wettkampf_sender_email', get_option('admin_email'));
        $sender_name = get_option('wettkampf_sender_name', get_option('blogname'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        $sent = wp_mail($export_email, $subject, $message, $headers, array($temp_file));
        
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if ($sent) {
            error_log('Wettkampf Manager: Automatic export sent for competition ' . $wettkampf->name . ' to ' . $export_email);
        } else {
            error_log('Wettkampf Manager: Failed to send automatic export for competition ' . $wettkampf->name);
        }
    }
    
    /**
     * Generate Excel export content
     */
    private function generate_excel_export($wettkampf_id) {
        global $wpdb;
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Get competition info
        $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_wettkampf WHERE id = %d", $wettkampf_id));
        if (!$wettkampf) {
            return false;
        }
        
        // Get registrations
        $anmeldungen = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM $table_anmeldung a 
            JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
            WHERE a.wettkampf_id = %d
            ORDER BY a.anmeldedatum ASC
        ", $wettkampf_id));
        
        // Start output buffering
        ob_start();
        
        // Output BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        // HTML table for Excel
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Wettkampf Anmeldungen - ' . htmlspecialchars($wettkampf->name) . '</title>';
        echo '</head>';
        echo '<body>';
        echo '<table border="1" style="border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<th>Vorname</th>';
        echo '<th>Name</th>';
        echo '<th>E-Mail</th>';
        echo '<th>Geschlecht</th>';
        echo '<th>Jahrgang</th>';
        echo '<th>Kategorie</th>';
        echo '<th>Wettkampf</th>';
        echo '<th>Wettkampf Datum</th>';
        echo '<th>Wettkampf Ort</th>';
        echo '<th>Eltern fahren</th>';
        echo '<th>Freie Plätze</th>';
        echo '<th>Disziplinen</th>';
        echo '<th>Anmeldedatum</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($anmeldungen as $anmeldung) {
            // Load disciplines for this registration
            $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
                SELECT d.name 
                FROM $table_anmeldung_disziplinen ad 
                JOIN $table_disziplinen d ON ad.disziplin_id = d.id 
                WHERE ad.anmeldung_id = %d 
                ORDER BY d.sortierung ASC, d.name ASC
            ", $anmeldung->id));
            
            $disziplin_names = array();
            if (is_array($anmeldung_disziplinen) && !empty($anmeldung_disziplinen)) {
                foreach ($anmeldung_disziplinen as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $disziplin_names[] = $d->name;
                    }
                }
            }
            
            $user_category = $this->calculateAgeCategory($anmeldung->jahrgang);
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($anmeldung->vorname, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->name, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->email, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->geschlecht, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->jahrgang, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($user_category, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->wettkampf_name, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . date('d.m.Y', strtotime($anmeldung->wettkampf_datum)) . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->wettkampf_ort, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . ($anmeldung->eltern_fahren ? 'Ja' : 'Nein') . '</td>';
            echo '<td>' . ($anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '') . '</td>';
            echo '<td>' . htmlspecialchars(!empty($disziplin_names) ? implode(', ', $disziplin_names) : '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . date('d.m.Y H:i:s', strtotime($anmeldung->anmeldedatum)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
        
        return ob_get_clean();
    }
}

// Initialize the plugin
new WettkampfManager();