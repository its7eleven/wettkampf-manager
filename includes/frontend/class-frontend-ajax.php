<?php
/**
 * Frontend AJAX functionality - KOMPLETT KORRIGIERT
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
     * KORRIGIERT: Get competition disciplines with category filter
     */
    public function get_wettkampf_disziplinen() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !SecurityManager::verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        $wettkampf_id = intval($_POST['wettkampf_id']);
        $jahrgang = isset($_POST['jahrgang']) && !empty($_POST['jahrgang']) ? intval($_POST['jahrgang']) : null;
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Wettkampf AJAX: Loading disciplines for wettkampf_id=$wettkampf_id, jahrgang=$jahrgang");
        }
        
        if (empty($wettkampf_id)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Wettkampf ID fehlt')));
        }
        
        $user_category = null;
        if ($jahrgang && $jahrgang > 1900) {
            $user_category = CategoryCalculator::calculate($jahrgang);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Wettkampf AJAX: Calculated user category: $user_category for year $jahrgang");
            }
        }
        
        // Get disciplines for this competition with category filter
        $disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id, $user_category);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Wettkampf AJAX: Found " . count($disciplines) . " disciplines");
        }
        
        // Convert to array format for JSON response
        $disciplines_array = array();
        if (is_array($disciplines) && !empty($disciplines)) {
            foreach ($disciplines as $discipline) {
                if (is_object($discipline)) {
                    $disciplines_array[] = array(
                        'id' => intval($discipline->id),
                        'name' => sanitize_text_field($discipline->name),
                        'beschreibung' => sanitize_text_field($discipline->beschreibung),
                        'kategorie' => sanitize_text_field($discipline->kategorie)
                    );
                }
            }
        }
        
        $response_data = array(
            'success' => true, 
            'data' => $disciplines_array,
            'user_category' => $user_category,
            'wettkampf_id' => $wettkampf_id,
            'jahrgang' => $jahrgang
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Wettkampf AJAX: Sending response: " . json_encode($response_data));
        }
        
        wp_die(json_encode($response_data));
    }
}