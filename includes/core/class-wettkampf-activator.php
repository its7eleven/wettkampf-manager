<?php
/**
 * Plugin activation and deactivation handler - ERWEITERT mit Transport-Optionen
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfActivator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::update_existing_tables(); // NEU: Update bestehende Tabellen
        self::create_pages();
        self::setup_cron_jobs();
        self::set_default_options();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        self::cleanup_cron_jobs();
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables - ERWEITERT mit Transport-Optionen
     */
    private static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
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
        
        // Anmeldungen Tabelle - ERWEITERT mit ENUM für Transport-Optionen
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $sql_anmeldung = "CREATE TABLE $table_anmeldung (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wettkampf_id mediumint(9) NOT NULL,
            vorname varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            geschlecht enum('maennlich','weiblich','divers') NOT NULL,
            jahrgang year NOT NULL,
            eltern_fahren enum('ja','nein','direkt') NOT NULL DEFAULT 'nein',
            freie_plaetze int DEFAULT 0,
            anmeldedatum datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_wettkampf_id (wettkampf_id),
            INDEX idx_email (email),
            INDEX idx_anmeldedatum (anmeldedatum),
            INDEX idx_eltern_fahren (eltern_fahren),
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
        
        // Create tables
        dbDelta($sql_wettkampf);
        dbDelta($sql_disziplinen);
        dbDelta($sql_wettkampf_disziplinen);
        dbDelta($sql_anmeldung);
        dbDelta($sql_anmeldung_disziplinen);
        
        // Insert default disciplines
        self::insert_default_disciplines();
        
        // Set database version
        update_option('wettkampf_db_version', WETTKAMPF_DB_VERSION);
    }
    
    /**
     * Update existing tables - NEU
     */
    private static function update_existing_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wettkampf_anmeldung';
        
        // Check current column type
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'eltern_fahren'");
        
        if ($column_info && strpos($column_info->Type, "enum('ja','nein','direkt')") === false) {
            // Update column to new ENUM type
            WettkampfHelpers::log_error('Updating eltern_fahren column to ENUM type');
            
            // Create temporary column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN eltern_fahren_temp VARCHAR(20) AFTER eltern_fahren");
            
            // Copy and convert data
            $wpdb->query("UPDATE $table_name SET eltern_fahren_temp = 
                CASE 
                    WHEN eltern_fahren = '1' THEN 'ja'
                    WHEN eltern_fahren = '0' THEN 'nein'
                    WHEN eltern_fahren = 1 THEN 'ja'
                    WHEN eltern_fahren = 0 THEN 'nein'
                    WHEN eltern_fahren = 'ja' THEN 'ja'
                    WHEN eltern_fahren = 'nein' THEN 'nein'
                    WHEN eltern_fahren = 'direkt' THEN 'direkt'
                    ELSE 'nein'
                END
            ");
            
            // Drop old column
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN eltern_fahren");
            
            // Create new ENUM column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN eltern_fahren ENUM('ja','nein','direkt') NOT NULL DEFAULT 'nein' AFTER jahrgang");
            
            // Copy data back
            $wpdb->query("UPDATE $table_name SET eltern_fahren = eltern_fahren_temp");
            
            // Drop temporary column
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN eltern_fahren_temp");
            
            WettkampfHelpers::log_error('eltern_fahren column updated successfully');
        }
    }
    
    /**
     * Insert default disciplines
     */
    private static function insert_default_disciplines() {
        global $wpdb;
        
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Check if disciplines already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_disziplinen");
        if ($existing_count > 0) {
            return;
        }
        
        $default_disziplinen = array(
            // U10 Disziplinen
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
    
    /**
     * Create default pages
     */
    private static function create_pages() {
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
    
    /**
     * Setup cron jobs
     */
    private static function setup_cron_jobs() {
        wp_clear_scheduled_hook('wettkampf_check_expired_registrations');
        wp_schedule_event(time(), 'hourly', 'wettkampf_check_expired_registrations');
    }
    
    /**
     * Cleanup cron jobs
     */
    private static function cleanup_cron_jobs() {
        wp_clear_scheduled_hook('wettkampf_check_expired_registrations');
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        add_option('wettkampf_sender_email', get_option('admin_email'));
        add_option('wettkampf_sender_name', get_option('blogname'));
    }
}