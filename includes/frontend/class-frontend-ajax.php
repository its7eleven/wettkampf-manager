<?php
/**
 * Frontend AJAX functionality - KORRIGIERT für Update-Funktion
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
     * Send JSON response and exit
     */
    private function send_json_response($data) {
        wp_send_json($data);
        exit;
    }
    
    /**
     * Send JSON error and exit
     */
    private function send_json_error($message, $data = null) {
        $response = array('success' => false, 'message' => $message);
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->send_json_response($response);
    }
    
    /**
     * Send JSON success and exit
     */
    private function send_json_success($message, $data = null) {
        $response = array('success' => true, 'message' => $message);
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->send_json_response($response);
    }
    
    /**
     * Process registration
     */
    public function process_anmeldung() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
                $this->send_json_error('Sicherheitsfehler: Ungültige Nonce');
            }
            
            // Check rate limiting
            if (!SecurityManager::check_rate_limit('anmeldung', 5, 600)) {
                $this->send_json_error('Zu viele Anmeldeversuche. Bitte warte 10 Minuten.');
            }
            
            // Verify reCAPTCHA if configured
            $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key');
            if (!empty($recaptcha_site_key)) {
                $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
                if (empty($recaptcha_response)) {
                    $this->send_json_error('Bitte bestätige das reCAPTCHA');
                }
                
                if (!SecurityManager::verify_recaptcha($recaptcha_response)) {
                    $this->send_json_error('reCAPTCHA Verifikation fehlgeschlagen');
                }
            }
            
            // Sanitize and validate data
            $sanitization_rules = array(
                'wettkampf_id' => 'int',
                'vorname' => 'text',
                'name' => 'text',
                'email' => 'email',
                'geschlecht' => 'text',
                'jahrgang' => 'int',
                'eltern_fahren' => 'text',
                'freie_plaetze' => 'int',
                'disziplinen' => 'array'
            );
            
            $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
            
            // Check for duplicates
            if (SecurityManager::check_duplicate_registration($data['wettkampf_id'], $data['email'], $data['vorname'], $data['jahrgang'])) {
                $this->send_json_error('Eine Person mit dieser E-Mail, diesem Vornamen und Jahrgang ist bereits angemeldet!');
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
                $this->send_json_error('Validierungsfehler: ' . implode(', ', $validation['errors']));
            }
            
            // Validate eltern_fahren value
            if (!in_array($data['eltern_fahren'], array('ja', 'nein', 'direkt'))) {
                $this->send_json_error('Ungültige Transport-Option gewählt');
            }
            
            // Handle freie_plaetze
            if ($data['eltern_fahren'] !== 'ja') {
                $data['freie_plaetze'] = 0;
            }
            
            // Save registration
            $anmeldung_id = WettkampfDatabase::save_registration($data);
            
            if ($anmeldung_id) {
                // Send confirmation email
                try {
                    $email_manager = new EmailManager();
                    $email_manager->send_confirmation_email($anmeldung_id);
                } catch (Exception $e) {
                    // Continue anyway - registration was successful
                }
                
                // Reset rate limit on success
                SecurityManager::reset_rate_limit('anmeldung');
                
                $this->send_json_success('Anmeldung erfolgreich! Du erhältst eine Bestätigungs-E-Mail.');
            } else {
                $this->send_json_error('Fehler beim Speichern der Anmeldung. Bitte versuche es erneut.');
            }
            
        } catch (Exception $e) {
            $this->send_json_error('Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }
    
    /**
     * Process mutation (edit/delete)
     */
    public function process_mutation() {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
                $this->send_json_error('Sicherheitsfehler');
            }
            
            $anmeldung_id = intval($_POST['anmeldung_id']);
            $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
            
            if ($action_type === 'verify') {
                $this->verify_registration_ownership($anmeldung_id);
            } elseif ($action_type === 'delete') {
                $this->delete_registration($anmeldung_id);
            } elseif ($action_type === 'update') {
                $this->update_registration($anmeldung_id);
            } else {
                $this->send_json_error('Ungültige Aktion');
            }
        } catch (Exception $e) {
            WettkampfHelpers::log_error('Mutation error: ' . $e->getMessage());
            $this->send_json_error('Ein Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify registration ownership
     */
    private function verify_registration_ownership($anmeldung_id) {
        // Rate limiting
        if (!SecurityManager::check_rate_limit('mutation_verify', 5, 600)) {
            $this->send_json_error('Zu viele Versuche. Bitte warte 10 Minuten.');
        }
        
        $verify_email = sanitize_email($_POST['verify_email']);
        $verify_jahrgang = intval($_POST['verify_jahrgang']);
        
        if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
            $this->send_json_error('E-Mail oder Jahrgang stimmen nicht überein');
        }
        
        // Success - load full registration data with disciplines
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
        
        if (!$anmeldung) {
            $this->send_json_error('Anmeldung nicht gefunden');
        }
        
        // Load disciplines
        $anmeldung_disciplines = $wpdb->get_results($wpdb->prepare("
            SELECT disziplin_id 
            FROM {$tables['anmeldung_disziplinen']} 
            WHERE anmeldung_id = %d
        ", $anmeldung_id));
        $anmeldung->disziplinen = array_map(function($d) { return $d->disziplin_id; }, $anmeldung_disciplines);
        
        // Reset rate limit on success
        SecurityManager::reset_rate_limit('mutation_verify');
        
        $this->send_json_success('Verifikation erfolgreich', $anmeldung);
    }
    
    /**
     * Delete registration
     */
    private function delete_registration($anmeldung_id) {
        $result = WettkampfDatabase::delete_registration($anmeldung_id);
        
        if ($result) {
            $this->send_json_success('Anmeldung erfolgreich gelöscht');
        } else {
            $this->send_json_error('Fehler beim Löschen');
        }
    }
    
    /**
     * Update registration - KORRIGIERT
     */
    private function update_registration($anmeldung_id) {
        try {
            WettkampfHelpers::log_error('Update registration started for ID: ' . $anmeldung_id);
            
            // Get wettkampf_id from existing registration
            global $wpdb;
            $tables = WettkampfDatabase::get_table_names();
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT wettkampf_id FROM {$tables['anmeldung']} WHERE id = %d", 
                $anmeldung_id
            ));
            
            if (!$existing) {
                $this->send_json_error('Anmeldung nicht gefunden');
            }
            
            // Sanitize data
            $sanitization_rules = array(
                'vorname' => 'text',
                'name' => 'text',
                'email' => 'email',
                'geschlecht' => 'text',
                'jahrgang' => 'int',
                'eltern_fahren' => 'text',
                'freie_plaetze' => 'int',
                'disziplinen' => 'array'
            );
            
            $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
            
            // Add wettkampf_id from existing registration
            $data['wettkampf_id'] = $existing->wettkampf_id;
            
            // Validation
            $validation_rules = array(
                'vorname' => array('required' => true, 'min_length' => 2),
                'name' => array('required' => true, 'min_length' => 2),
                'email' => array('required' => true, 'email' => true),
                'geschlecht' => array('required' => true),
                'jahrgang' => array('required' => true, 'year' => true),
                'eltern_fahren' => array('required' => true)
            );
            
            $validation = SecurityManager::validate_form_data($data, $validation_rules);
            
            if (!$validation['valid']) {
                $this->send_json_error('Validierungsfehler: ' . implode(', ', $validation['errors']));
            }
            
            // Validate eltern_fahren value
            if (!in_array($data['eltern_fahren'], array('ja', 'nein', 'direkt'))) {
                $this->send_json_error('Ungültige Transport-Option gewählt');
            }
            
            // Handle freie_plaetze
            if ($data['eltern_fahren'] !== 'ja') {
                $data['freie_plaetze'] = 0;
            }
            
            WettkampfHelpers::log_error('Update data prepared: ' . print_r($data, true));
            
            $result = WettkampfDatabase::save_registration($data, $anmeldung_id);
            
            if ($result !== false) {
                WettkampfHelpers::log_error('Update successful for ID: ' . $anmeldung_id);
                $this->send_json_success('Anmeldung erfolgreich aktualisiert');
            } else {
                WettkampfHelpers::log_error('Update failed for ID: ' . $anmeldung_id);
                $this->send_json_error('Fehler beim Aktualisieren der Anmeldung');
            }
            
        } catch (Exception $e) {
            WettkampfHelpers::log_error('Update exception: ' . $e->getMessage());
            $this->send_json_error('Fehler: ' . $e->getMessage());
        }
    }
    
    /**
     * Process view-only request
     */
    public function process_view_only() {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
                $this->send_json_error('Sicherheitsfehler');
            }
            
            $anmeldung_id = intval($_POST['anmeldung_id']);
            
            // Rate limiting
            if (!SecurityManager::check_rate_limit('view_only', 5, 600)) {
                $this->send_json_error('Zu viele Versuche. Bitte warte 10 Minuten.');
            }
            
            $verify_email = sanitize_email($_POST['verify_email']);
            $verify_jahrgang = intval($_POST['verify_jahrgang']);
            
            if (!SecurityManager::verify_registration_ownership($anmeldung_id, $verify_email, $verify_jahrgang)) {
                $this->send_json_error('E-Mail oder Jahrgang stimmen nicht überein');
            }
            
            // Success - load registration data
            global $wpdb;
            $tables = WettkampfDatabase::get_table_names();
            
            $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['anmeldung']} WHERE id = %d", $anmeldung_id));
            
            if (!$anmeldung) {
                $this->send_json_error('Anmeldung nicht gefunden');
            }
            
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
            
            $this->send_json_success('Daten geladen', $anmeldung);
            
        } catch (Exception $e) {
            $this->send_json_error('Ein Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }
    
    /**
     * Get competition disciplines with category filter
     */
    public function get_wettkampf_disziplinen() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
                $this->send_json_error('Sicherheitsfehler: Ungültige Nonce');
            }
            
            // Get parameters
            $wettkampf_id = isset($_POST['wettkampf_id']) ? intval($_POST['wettkampf_id']) : 0;
            $jahrgang = isset($_POST['jahrgang']) ? intval($_POST['jahrgang']) : 0;
            
            if ($wettkampf_id <= 0) {
                $this->send_json_error('Ungültige Wettkampf-ID');
            }
            
            // Calculate category
            $user_category = null;
            if ($jahrgang > 1900) {
                if (class_exists('CategoryCalculator')) {
                    $user_category = CategoryCalculator::calculate($jahrgang);
                } else {
                    // Fallback category calculation
                    $current_year = date('Y');
                    $age = $current_year - $jahrgang;
                    if ($age < 10) $user_category = 'U10';
                    elseif ($age < 12) $user_category = 'U12';
                    elseif ($age < 14) $user_category = 'U14';
                    elseif ($age < 16) $user_category = 'U16';
                    else $user_category = 'U18';
                }
            }
            
            // Load disciplines
            $disciplines = array();
            
            if (class_exists('WettkampfDatabase')) {
                $disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id, $user_category);
            } else {
                // Fallback: Direct database query
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
            }
            
            // Prepare data for JSON
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
                'jahrgang' => $jahrgang
            );
            
            $this->send_json_response($response);
            
        } catch (Exception $e) {
            $this->send_json_error('Datenbankfehler: ' . $e->getMessage());
        }
    }
}