<?php
/**
 * Security management utility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SecurityManager {
    
    /**
     * Rate limiting cache
     */
    private static $rate_limits = array();
    
    /**
     * Verify reCAPTCHA response
     */
    public static function verify_recaptcha($response) {
        $secret_key = WettkampfHelpers::get_option('recaptcha_secret_key');
        
        if (empty($secret_key) || empty($response)) {
            return true; // Skip verification if no key set or no response
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => self::get_user_ip()
        );
        
        $response = wp_remote_post($url, array('body' => $data));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return isset($result['success']) && $result['success'] === true;
    }
    
    /**
     * Check rate limiting for user actions
     */
    public static function check_rate_limit($action, $max_attempts = 5, $time_window = 600) {
        $user_ip = self::get_user_ip();
        $cache_key = 'wettkampf_rate_limit_' . $action . '_' . md5($user_ip);
        
        $attempts = get_transient($cache_key);
        
        if ($attempts === false) {
            // First attempt
            set_transient($cache_key, 1, $time_window);
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return false;
        }
        
        // Increment attempts
        set_transient($cache_key, $attempts + 1, $time_window);
        return true;
    }
    
    /**
     * Reset rate limit for specific action
     */
    public static function reset_rate_limit($action) {
        $user_ip = self::get_user_ip();
        $cache_key = 'wettkampf_rate_limit_' . $action . '_' . md5($user_ip);
        delete_transient($cache_key);
    }
    
    /**
     * Sanitize and validate form data
     */
    public static function sanitize_form_data($data, $rules = array()) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            $rule = isset($rules[$key]) ? $rules[$key] : 'text';
            
            switch ($rule) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case 'int':
                    $sanitized[$key] = intval($value);
                    break;
                case 'float':
                    $sanitized[$key] = floatval($value);
                    break;
                case 'array':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : array();
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate form data against rules
     */
    public static function validate_form_data($data, $rules = array()) {
        $errors = array();
        
        foreach ($rules as $field => $rule_set) {
            $value = isset($data[$field]) ? $data[$field] : '';
            
            // Required field check
            if (isset($rule_set['required']) && $rule_set['required'] && empty($value)) {
                $errors[$field] = 'Dieses Feld ist erforderlich';
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value)) {
                continue;
            }
            
            // Email validation
            if (isset($rule_set['email']) && $rule_set['email'] && !is_email($value)) {
                $errors[$field] = 'Bitte gib eine gültige E-Mail-Adresse ein';
            }
            
            // URL validation
            if (isset($rule_set['url']) && $rule_set['url'] && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$field] = 'Bitte gib eine gültige URL ein';
            }
            
            // Year validation
            if (isset($rule_set['year']) && $rule_set['year']) {
                $validation = CategoryCalculator::validate_birth_year($value);
                if (!$validation['valid']) {
                    $errors[$field] = $validation['error'];
                }
            }
            
            // Length validation
            if (isset($rule_set['min_length']) && strlen($value) < $rule_set['min_length']) {
                $errors[$field] = 'Mindestens ' . $rule_set['min_length'] . ' Zeichen erforderlich';
            }
            
            if (isset($rule_set['max_length']) && strlen($value) > $rule_set['max_length']) {
                $errors[$field] = 'Maximal ' . $rule_set['max_length'] . ' Zeichen erlaubt';
            }
            
            // Custom validation
            if (isset($rule_set['custom']) && is_callable($rule_set['custom'])) {
                $custom_result = call_user_func($rule_set['custom'], $value);
                if ($custom_result !== true) {
                    $errors[$field] = $custom_result;
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Check for duplicate registrations
     */
    public static function check_duplicate_registration($wettkampf_id, $email, $vorname, $jahrgang, $exclude_id = null) {
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $query = "SELECT COUNT(*) FROM $table_anmeldung WHERE wettkampf_id = %d AND email = %s AND vorname = %s AND jahrgang = %d";
        $params = array($wettkampf_id, $email, $vorname, $jahrgang);
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, $params));
        
        return $count > 0;
    }
    
    /**
     * Verify ownership of registration
     */
    public static function verify_registration_ownership($anmeldung_id, $email, $jahrgang) {
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $anmeldung = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_anmeldung WHERE id = %d", 
            $anmeldung_id
        ));
        
        if (!$anmeldung) {
            return false;
        }
        
        return ($anmeldung->email === $email && $anmeldung->jahrgang == $jahrgang);
    }
    
    /**
     * Get user IP address
     */
    public static function get_user_ip() {
        $ip_fields = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Log security event
     */
    public static function log_security_event($event, $details = array()) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'ip' => self::get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'event' => $event,
            'details' => $details
        );
        
        WettkampfHelpers::log_error('Security Event: ' . $event, $log_data);
    }
    
    /**
     * Check if registration deadline has passed
     */
    public static function is_registration_deadline_passed($anmeldeschluss) {
        return strtotime($anmeldeschluss) < strtotime('today');
    }
    
    /**
     * Escape output for HTML context
     */
    public static function escape_html($text) {
        return esc_html($text);
    }
    
    /**
     * Escape output for attribute context
     */
    public static function escape_attr($text) {
        return esc_attr($text);
    }
    
    /**
     * Escape output for URL context
     */
    public static function escape_url($url) {
        return esc_url($url);
    }
    
    /**
     * Prepare SQL query safely
     */
    public static function prepare_query($query, $params = array()) {
        global $wpdb;
        
        if (empty($params)) {
            return $query;
        }
        
        return $wpdb->prepare($query, $params);
    }
    
    /**
     * Check admin permissions
     */
    public static function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            wp_die('Du hast keine Berechtigung für diese Aktion.');
        }
    }
    
    /**
     * Generate secure random token
     */
    public static function generate_token($length = 32) {
        return wp_generate_password($length, false);
    }
    
    /**
     * Hash sensitive data
     */
    public static function hash_data($data, $salt = '') {
        if (empty($salt)) {
            $salt = wp_salt();
        }
        
        return hash('sha256', $data . $salt);
    }
}