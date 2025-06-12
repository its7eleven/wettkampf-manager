<?php
/**
 * Frontend AJAX functionality - VOLLSTÄNDIG KORRIGIERT UND VEREINFACHT
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
        add_action('wp_ajax_wettkampf_anmeldung', array($this, 'process_anmeldung'));
        add_action('wp_ajax_nopriv_wettkampf_anmeldung', array($this, 'process_anmeldung'));
        add_action('wp_ajax_wettkampf_mutation', array($this, 'process_mutation'));
        add_action('wp_ajax_nopriv_wettkampf_mutation', array($this, 'process_mutation'));
        add_action('wp_ajax_get_wettkampf_disziplinen', array($this, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_nopriv_get_wettkampf_disziplinen', array($this, 'get_wettkampf_disziplinen'));
        add_action('wp_ajax_wettkampf_view_only', array($this, 'process_view_only'));
        add_action('wp_ajax_nopriv_wettkampf_view_only', array($this, 'process_view_only'));
    }
    
    /**
     * Process registration
     */
    public function process_anmeldung() {
        // Verify nonce
        if (!SecurityManager::verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        // Check rate limiting
        if (!SecurityManager::check_rate_limit('anmeldung', 5, 600)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Zu viele Anmeldeversuche. Bitte warte 10 Minuten.')));
        }
        
        // Verify reCAPTCHA
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        if (!SecurityManager::verify_recaptcha($recaptcha_response)) {
            wp_die(json_encode(array('success' => false, 'message' => 'reCAPTCHA Verifikation fehlgeschlagen')));
        }
        
        // Sanitize and validate data
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
        
        // Check for duplicates
        if (SecurityManager::check_duplicate_registration($data['wettkampf_id'], $data['email'], $data['vorname'], $data['jahrgang'])) {
            wp_die(json_encode(array('success' => false, 'message' => 'Eine Person mit dieser E-Mail, diesem Vornamen und Jahrgang ist bereits angemeldet!')));
        }
        
        // Validation rules
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
            wp_die(json_encode(array('success' => false, 'message' => 'Validierungsfehler: ' . implode(', ', $validation['errors']))));
        }
        
        // Save registration
        $anmeldung_id = WettkampfDatabase::save_registration($data);
        
        if ($anmeldung_id) {
            // Send confirmation email
            $email_manager = new EmailManager();
            $email_manager->send_confirmation_email($anmeldung_id);
            
            // Reset rate limit on success
            SecurityManager::reset_rate_limit('anmeldung');
            
            wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich!')));
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Fehler bei der Anmeldung')));
        }
    }
    
    /**
     * Process mutation (edit/delete)
     */
    public function process_mutation() {
        if (!SecurityManager::verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if ($action_type === 'verify') {
            $this->verify_registration_ownership($anmeldung_id);
        } elseif ($action_type === 'delete') {
            $this->delete_registration($anmeldung_id);
        } else {
            $this->update_registration($anmeldung_id);
        }
    }
    
    /**
     * Verify registration ownership
     */
    private function verify_registration_ownership($anmeldung_id) {
        // Rate limiting
        if (!SecurityManager::check_rate_limit('mutation_verify', 5, 600)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.')));
        }
        
        $verify_email = sanitize_email($_POST['verify_email']);
        $verify_jahrgang = intval($_POST['verify_jahrgang']);
        
        if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
            wp_die(json_encode(array('success' => false, 'message' => 'E-Mail oder Jahrgang stimmen nicht ueberein')));
        }
        
        // Success - load full registration data with disciplines
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
        
        // Load disciplines
        $anmeldung_disciplines = $wpdb->get_results($wpdb->prepare("
            SELECT disziplin_id 
            FROM {$tables['anmeldung_disziplinen']} 
            WHERE anmeldung_id = %d
        ", $anmeldung_id));
        $anmeldung->disziplinen = array_map(function($d) { return $d->disziplin_id; }, $anmeldung_disciplines);
        
        // Reset rate limit on success
        SecurityManager::reset_rate_limit('mutation_verify');
        
        wp_die(json_encode(array('success' => true, 'data' => $anmeldung)));
    }
    
    /**
     * Delete registration
     */
    private function delete_registration($anmeldung_id) {
        $result = WettkampfDatabase::delete_registration($anmeldung_id);
        
        if ($result) {
            wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich geloescht')));
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Fehler beim Loeschen')));
        }
    }
    
    /**
     * Update registration
     */
    private function update_registration($anmeldung_id) {
        // Sanitize data
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
        
        // Validation
        $validation_rules = array(
            'vorname' => array('required' => true, 'min_length' => 2),
            'name' => array('required' => true, 'min_length' => 2),
            'email' => array('required' => true, 'email' => true),
            'geschlecht' => array('required' => true),
            'jahrgang' => array('required' => true, 'year' => true)
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            wp_die(json_encode(array('success' => false, 'message' => 'Validierungsfehler: ' . implode(', ', $validation['errors']))));
        }
        
        $result = WettkampfDatabase::save_registration($data, $anmeldung_id);
        
        if ($result !== false) {
            wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich aktualisiert')));
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Fehler beim Aktualisieren')));
        }
    }
    
    /**
     * Process view-only request
     */
    public function process_view_only() {
        if (!SecurityManager::verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        
        // Rate limiting
        if (!SecurityManager::check_rate_limit('view_only', 5, 600)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.')));
        }
        
        $verify_email = sanitize_email($_POST['verify_email']);
        $verify_jahrgang = intval($_POST['verify_jahrgang']);
        
        if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
            wp_die(json_encode(array('success' => false, 'message' => 'E-Mail oder Jahrgang stimmen nicht ueberein')));
        }
        
        // Success - load registration data
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
        
        // Load disciplines as text
        $anmeldung_disciplines = WettkampfDatabase::get_registration_disciplines($anmeldung_id);
        
        $discipline_names = array();
        if (is_array($anmeldung_disciplines) && !empty($anmeldung_disciplines)) {
            foreach ($anmeldung_disciplines as $d) {
                if (is_object($d) && isset($d->name) && !empty($d->name)) {
                    $discipline_names[] = $d->name;
                }
            }
        }
        
        $anmeldung->disziplinen_text = !empty($discipline_names) ? implode(', ', $discipline_names) : 'Keine';
        
        // Reset rate limit on success
        SecurityManager::reset_rate_limit('view_only');
        
        wp_die(json_encode(array('success' => true, 'data' => $anmeldung)));
    }
    
    /**
     * VEREINFACHTE UND ROBUSTE VERSION: Get competition disciplines with category filter
     */
    public function get_wettkampf_disziplinen() {
        // Debug-Start
        error_log('WETTKAMPF AJAX: get_wettkampf_disziplinen called');
        error_log('WETTKAMPF AJAX: POST data: ' . print_r($_POST, true));
        
        // Basis-Sicherheitsprüfung - VEREINFACHT
        if (!isset($_POST['nonce'])) {
            error_log('WETTKAMPF AJAX: No nonce provided');
            wp_die(json_encode(array('success' => false, 'message' => 'Nonce fehlt')));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            error_log('WETTKAMPF AJAX: Invalid nonce');
            wp_die(json_encode(array('success' => false, 'message' => 'Ungültige Nonce')));
        }
        
        // Parameter extrahieren
        $wettkampf_id = isset($_POST['wettkampf_id']) ? intval($_POST['wettkampf_id']) : 0;
        $jahrgang = isset($_POST['jahrgang']) ? intval($_POST['jahrgang']) : 0;
        
        error_log("WETTKAMPF AJAX: wettkampf_id=$wettkampf_id, jahrgang=$jahrgang");
        
        if ($wettkampf_id <= 0) {
            error_log('WETTKAMPF AJAX: Invalid wettkampf_id');
            wp_die(json_encode(array('success' => false, 'message' => 'Ungültige Wettkampf-ID')));
        }
        
        // Kategorie berechnen
        $user_category = null;
        if ($jahrgang > 1900) {
            // Prüfe ob CategoryCalculator existiert
            if (class_exists('CategoryCalculator')) {
                $user_category = CategoryCalculator::calculate($jahrgang);
                error_log("WETTKAMPF AJAX: Calculated category: $user_category");
            } else {
                error_log('WETTKAMPF AJAX: CategoryCalculator class not found');
                // Fallback Kategorie-Berechnung
                $current_year = date('Y');
                $age = $current_year - $jahrgang;
                if ($age < 10) $user_category = 'U10';
                elseif ($age < 12) $user_category = 'U12';
                elseif ($age < 14) $user_category = 'U14';
                elseif ($age < 16) $user_category = 'U16';
                else $user_category = 'U18';
                error_log("WETTKAMPF AJAX: Fallback category: $user_category");
            }
        }
        
        try {
            // Disziplinen laden - mit Fallback
            $disciplines = array();
            
            if (class_exists('WettkampfDatabase')) {
                $disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id, $user_category);
                error_log('WETTKAMPF AJAX: Found ' . count($disciplines) . ' disciplines via WettkampfDatabase');
            } else {
                // Fallback: Direkte Datenbankabfrage
                error_log('WETTKAMPF AJAX: WettkampfDatabase not found, using fallback');
                global $wpdb;
                
                $query = "
                    SELECT d.* 
                    FROM {$wpdb->prefix}wettkampf_disziplin_zuordnung z 
                    JOIN {$wpdb->prefix}wettkampf_disziplinen d ON z.disziplin_id = d.id 
                    WHERE z.wettkampf_id = %d AND d.aktiv = 1
                ";
                
                $params = array($wettkampf_id);
                
                if ($user_category && $user_category !== '') {
                    $query .= " AND (d.kategorie = %s OR d.kategorie = 'Alle' OR d.kategorie IS NULL OR d.kategorie = '')";
                    $params[] = $user_category;
                }
                
                $query .= " ORDER BY d.sortierung ASC, d.name ASC";
                
                $disciplines = $wpdb->get_results($wpdb->prepare($query, $params));
                error_log('WETTKAMPF AJAX: Found ' . count($disciplines) . ' disciplines via fallback query');
            }
            
            // Daten für JSON vorbereiten
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
            
            $response = array(
                'success' => true,
                'data' => $disciplines_array,
                'user_category' => $user_category,
                'wettkampf_id' => $wettkampf_id,
                'jahrgang' => $jahrgang,
                'debug_info' => array(
                    'found_disciplines' => count($disciplines_array),
                    'calculated_category' => $user_category,
                    'query_params' => array(
                        'wettkampf_id' => $wettkampf_id,
                        'jahrgang' => $jahrgang
                    )
                )
            );
            
            error_log('WETTKAMPF AJAX: Sending success response with ' . count($disciplines_array) . ' disciplines');
            wp_die(json_encode($response));
            
        } catch (Exception $e) {
            error_log('WETTKAMPF AJAX: Exception caught: ' . $e->getMessage());
            wp_die(json_encode(array(
                'success' => false, 
                'message' => 'Datenbankfehler: ' . $e->getMessage(),
                'debug_info' => array(
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            )));
        }
    }
}