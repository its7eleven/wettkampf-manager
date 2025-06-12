<?php
/**
 * KOMPLETT KORRIGIERTE Frontend AJAX functionality
 * Verwendet wp_send_json_success() und wp_send_json_error()
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FrontendAjax {
    
    /**
     * Initialize AJAX handlers
     */
    public function init() {
        // Standard AJAX Actions
        add_action('wp_ajax_wettkampf_anmeldung', array($this, 'process_anmeldung'));
        add_action('wp_ajax_nopriv_wettkampf_anmeldung', array($this, 'process_anmeldung'));
        add_action('wp_ajax_wettkampf_mutation', array($this, 'process_mutation'));
        add_action('wp_ajax_nopriv_wettkampf_mutation', array($this, 'process_mutation'));
        add_action('wp_ajax_get_wettkampf_disziplinen', array($this, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_nopriv_get_wettkampf_disziplinen', array($this, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_wettkampf_view_only', array($this, 'process_view_only'));
        add_action('wp_ajax_nopriv_wettkampf_view_only', array($this, 'process_view_only'));
        
        // Debug für Entwicklung
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_ajax_test_wettkampf_ajax', array($this, 'test_ajax_handler'));
            add_action('wp_ajax_nopriv_test_wettkampf_ajax', array($this, 'test_ajax_handler'));
        }
    }
    
    /**
     * Test AJAX Handler
     */
    public function test_ajax_handler() {
        wp_send_json_success(array(
            'message' => 'AJAX funktioniert perfekt!',
            'timestamp' => current_time('mysql'),
            'test' => 'OK'
        ));
    }
    
    /**
     * KORRIGIERTE Disziplinen-Abfrage
     */
    public function get_wettkampf_disziplinen() {
        // 1. Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_send_json_error(array(
                'message' => 'Sicherheitsfehler - Nonce ungültig'
            ));
        }
        
        // 2. Parameter validieren
        $wettkampf_id = isset($_POST['wettkampf_id']) ? intval($_POST['wettkampf_id']) : 0;
        $jahrgang = isset($_POST['jahrgang']) ? intval($_POST['jahrgang']) : null;
        
        if ($wettkampf_id <= 0) {
            wp_send_json_error(array(
                'message' => 'Ungültige Wettkampf-ID'
            ));
        }
        
        // 3. Kategorie berechnen
        $user_category = null;
        if ($jahrgang && $jahrgang > 1900) {
            if (class_exists('CategoryCalculator')) {
                $user_category = CategoryCalculator::calculate($jahrgang);
            } else {
                // Fallback-Kategorieberechnung
                $current_year = date('Y');
                $age = $current_year - $jahrgang;
                if ($age < 10) $user_category = 'U10';
                elseif ($age < 12) $user_category = 'U12';
                elseif ($age < 14) $user_category = 'U14';
                elseif ($age < 16) $user_category = 'U16';
                else $user_category = 'U18';
            }
        }
        
        // 4. Disziplinen laden
        try {
            if (class_exists('WettkampfDatabase')) {
                $disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id, $user_category);
            } else {
                // Fallback: Direkte DB-Abfrage
                $disciplines = $this->get_disciplines_fallback($wettkampf_id, $user_category);
            }
            
            // 5. Daten für JSON vorbereiten
            $disciplines_array = array();
            if (is_array($disciplines)) {
                foreach ($disciplines as $discipline) {
                    if (is_object($discipline) && isset($discipline->id)) {
                        $disciplines_array[] = array(
                            'id' => intval($discipline->id),
                            'name' => isset($discipline->name) ? sanitize_text_field($discipline->name) : '',
                            'beschreibung' => isset($discipline->beschreibung) ? sanitize_text_field($discipline->beschreibung) : '',
                            'kategorie' => isset($discipline->kategorie) ? sanitize_text_field($discipline->kategorie) : 'Alle'
                        );
                    }
                }
            }
            
            // 6. Erfolgreiche Antwort senden
            wp_send_json_success(array(
                'data' => $disciplines_array,
                'user_category' => $user_category,
                'wettkampf_id' => $wettkampf_id,
                'jahrgang' => $jahrgang,
                'count' => count($disciplines_array)
            ));
            
        } catch (Exception $e) {
            // 7. Fehler-Antwort
            error_log('Wettkampf AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Datenbankfehler beim Laden der Disziplinen'
            ));
        }
    }
    
    /**
     * Fallback für Disziplinen-Abfrage
     */
    private function get_disciplines_fallback($wettkampf_id, $user_category = null) {
        global $wpdb;
        
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
        
        $query = "
            SELECT d.* 
            FROM {$table_zuordnung} z 
            JOIN {$table_disziplinen} d ON z.disziplin_id = d.id 
            WHERE z.wettkampf_id = %d AND d.aktiv = 1
        ";
        
        $params = array($wettkampf_id);
        
        if ($user_category) {
            $query .= " AND (d.kategorie = %s OR d.kategorie = 'Alle' OR d.kategorie IS NULL OR d.kategorie = '')";
            $params[] = $user_category;
        }
        
        $query .= " ORDER BY d.sortierung ASC, d.name ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        return $results ? $results : array();
    }
    
    /**
     * Process registration
     */
    public function process_anmeldung() {
        // Nonce-Überprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_send_json_error(array('message' => 'Sicherheitsfehler'));
        }
        
        // Rate limiting
        if (!SecurityManager::check_rate_limit('anmeldung', 5, 600)) {
            wp_send_json_error(array('message' => 'Zu viele Anmeldeversuche. Bitte warte 10 Minuten.'));
        }
        
        // reCAPTCHA
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (!SecurityManager::verify_recaptcha($recaptcha_response)) {
            wp_send_json_error(array('message' => 'reCAPTCHA Verifikation fehlgeschlagen'));
        }
        
        // Daten bereinigen
        $sanitization_rules = array(
            'wettkampf_id' => 'int',
            'vorname' => 'text',
            'name' => 'text',
            'email' => 'email',
            'geschlecht' => 'text',
            'jahrgang' => 'int',
            'eltern_fahren' => 'int',
            'freie_plaetze' => 'int',
            'disziplinen' => 'array'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        
        // Duplikate prüfen
        if (SecurityManager::check_duplicate_registration($data['wettkampf_id'], $data['email'], $data['vorname'], $data['jahrgang'])) {
            wp_send_json_error(array('message' => 'Eine Person mit dieser E-Mail, diesem Vornamen und Jahrgang ist bereits angemeldet!'));
        }
        
        // Validierung
        $validation_rules = array(
            'wettkampf_id' => array('required' => true),
            'vorname' => array('required' => true, 'min_length' => 2),
            'name' => array('required' => true, 'min_length' => 2),
            'email' => array('required' => true, 'email' => true),
            'geschlecht' => array('required' => true),
            'jahrgang' => array('required' => true, 'year' => true),
            'eltern_fahren' => array('required' => true)
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            wp_send_json_error(array('message' => 'Validierungsfehler: ' . implode(', ', $validation['errors'])));
        }
        
        // Speichern
        try {
            $anmeldung_id = WettkampfDatabase::save_registration($data);
            
            if ($anmeldung_id) {
                // E-Mail senden
                if (class_exists('EmailManager')) {
                    $email_manager = new EmailManager();
                    $email_manager->send_confirmation_email($anmeldung_id);
                }
                
                SecurityManager::reset_rate_limit('anmeldung');
                
                wp_send_json_success(array(
                    'message' => 'Anmeldung erfolgreich!',
                    'anmeldung_id' => $anmeldung_id
                ));
            } else {
                wp_send_json_error(array('message' => 'Fehler beim Speichern der Anmeldung'));
            }
        } catch (Exception $e) {
            error_log('Wettkampf Anmeldung Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Ein unerwarteter Fehler ist aufgetreten.'));
        }
    }
    
    /**
     * Process mutation (edit/delete)
     */
    public function process_mutation() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_send_json_error(array('message' => 'Sicherheitsfehler'));
        }
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        try {
            if ($action_type === 'verify') {
                $this->verify_registration_ownership($anmeldung_id);
            } elseif ($action_type === 'delete') {
                $this->delete_registration($anmeldung_id);
            } else {
                $this->update_registration($anmeldung_id);
            }
        } catch (Exception $e) {
            error_log('Wettkampf Mutation Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Ein unerwarteter Fehler ist aufgetreten.'));
        }
    }
    
    /**
     * Verify registration ownership
     */
    private function verify_registration_ownership($anmeldung_id) {
        // Rate limiting
        if (!SecurityManager::check_rate_limit('mutation_verify', 5, 600)) {
            wp_send_json_error(array('message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.'));
        }
        
        $verify_email = sanitize_email($_POST['verify_email']);
        $verify_jahrgang = intval($_POST['verify_jahrgang']);
        
        if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
            wp_send_json_error(array('message' => 'E-Mail oder Jahrgang stimmen nicht überein'));
        }
        
        // Daten laden
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
        
        if (!$anmeldung) {
            wp_send_json_error(array('message' => 'Anmeldung nicht gefunden'));
        }
        
        // Disziplinen laden
        $anmeldung_disciplines = $wpdb->get_results($wpdb->prepare("
            SELECT disziplin_id 
            FROM {$tables['anmeldung_disziplinen']} 
            WHERE anmeldung_id = %d
        ", $anmeldung_id));
        
        $discipline_ids = array();
        foreach ($anmeldung_disciplines as $d) {
            $discipline_ids[] = $d->disziplin_id;
        }
        
        SecurityManager::reset_rate_limit('mutation_verify');
        
        wp_send_json_success(array(
            'data' => array(
                'id' => $anmeldung->id,
                'wettkampf_id' => $anmeldung->wettkampf_id,
                'vorname' => $anmeldung->vorname,
                'name' => $anmeldung->name,
                'email' => $anmeldung->email,
                'geschlecht' => $anmeldung->geschlecht,
                'jahrgang' => $anmeldung->jahrgang,
                'eltern_fahren' => $anmeldung->eltern_fahren,
                'freie_plaetze' => $anmeldung->freie_plaetze,
                'disziplinen' => $discipline_ids
            )
        ));
    }
    
    /**
     * Delete registration
     */
    private function delete_registration($anmeldung_id) {
        $result = WettkampfDatabase::delete_registration($anmeldung_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Anmeldung erfolgreich gelöscht'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Löschen'));
        }
    }
    
    /**
     * Update registration
     */
    private function update_registration($anmeldung_id) {
        // Daten bereinigen
        $sanitization_rules = array(
            'vorname' => 'text',
            'name' => 'text',
            'email' => 'email',
            'geschlecht' => 'text',
            'jahrgang' => 'int',
            'eltern_fahren' => 'int',
            'freie_plaetze' => 'int',
            'disziplinen' => 'array'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        
        // Validierung
        $validation_rules = array(
            'vorname' => array('required' => true, 'min_length' => 2),
            'name' => array('required' => true, 'min_length' => 2),
            'email' => array('required' => true, 'email' => true),
            'geschlecht' => array('required' => true),
            'jahrgang' => array('required' => true, 'year' => true)
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            wp_send_json_error(array('message' => 'Validierungsfehler: ' . implode(', ', $validation['errors'])));
        }
        
        $result = WettkampfDatabase::save_registration($data, $anmeldung_id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Anmeldung erfolgreich aktualisiert'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aktualisieren'));
        }
    }
    
    /**
     * Process view-only request
     */
    public function process_view_only() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_send_json_error(array('message' => 'Sicherheitsfehler'));
        }
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        
        // Rate limiting
        if (!SecurityManager::check_rate_limit('view_only', 5, 600)) {
            wp_send_json_error(array('message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.'));
        }
        
        $verify_email = sanitize_email($_POST['verify_email']);
        $verify_jahrgang = intval($_POST['verify_jahrgang']);
        
        if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
            wp_send_json_error(array('message' => 'E-Mail oder Jahrgang stimmen nicht überein'));
        }
        
        try {
            // Anmeldung laden
            global $wpdb;
            $tables = WettkampfDatabase::get_table_names();
            
            $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
            
            if (!$anmeldung) {
                wp_send_json_error(array('message' => 'Anmeldung nicht gefunden'));
            }
            
            // Disziplinen als Text laden
            $anmeldung_disciplines = WettkampfDatabase::get_registration_disciplines($anmeldung_id);
            
            $discipline_names = array();
            if (is_array($anmeldung_disciplines) && !empty($anmeldung_disciplines)) {
                foreach ($anmeldung_disciplines as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $discipline_names[] = $d->name;
                    }
                }
            }
            
            SecurityManager::reset_rate_limit('view_only');
            
            wp_send_json_success(array(
                'data' => array(
                    'vorname' => $anmeldung->vorname,
                    'name' => $anmeldung->name,
                    'email' => $anmeldung->email,
                    'geschlecht' => $anmeldung->geschlecht,
                    'jahrgang' => $anmeldung->jahrgang,
                    'eltern_fahren' => $anmeldung->eltern_fahren,
                    'freie_plaetze' => $anmeldung->freie_plaetze,
                    'anmeldedatum' => $anmeldung->anmeldedatum,
                    'disziplinen_text' => !empty($discipline_names) ? implode(', ', $discipline_names) : 'Keine'
                )
            ));
            
        } catch (Exception $e) {
            error_log('Wettkampf View Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Ein unerwarteter Fehler ist aufgetreten.'));
        }
    }
}