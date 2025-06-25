<?php
/**
 * General helper functions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfHelpers {
    
    /**
     * Sanitize filename for different operating systems
     */
    public static function sanitize_filename($filename) {
        // Remove/replace problematic characters
        $filename = preg_replace('/[^a-zA-Z0-9äöüÄÖÜ_-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename); // Remove multiple underscores
        $filename = trim($filename, '_');
        
        // Limit length
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }
        
        // Ensure we have something
        if (empty($filename)) {
            $filename = 'wettkampf';
        }
        
        return $filename;
    }
    
    /**
     * Escape CSV values
     */
    public static function csv_escape($value) {
        // Convert to string and trim
        $value = trim((string)$value);
        
        // If value contains semicolon, comma, quotes, or newlines, wrap in quotes and escape internal quotes
        if (strpos($value, ';') !== false || 
            strpos($value, ',') !== false || 
            strpos($value, '"') !== false || 
            strpos($value, "\n") !== false || 
            strpos($value, "\r") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
    
    /**
     * Format German date
     */
    public static function format_german_date($date, $include_time = false) {
        if (empty($date)) {
            return '';
        }
        
        $format = 'd.m.Y';
        if ($include_time) {
            $format .= ' H:i';
        }
        
        return date($format, strtotime($date));
    }
    
    /**
     * Check if user agent is mobile
     */
    public static function is_mobile_user_agent() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return preg_match('/Mobile|Android|iPhone|iPad/', $user_agent);
    }
    
    /**
     * Generate nonce with custom action
     */
    public static function create_nonce($action = 'wettkampf_action') {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify nonce with custom action
     */
    public static function verify_nonce($nonce, $action = 'wettkampf_action') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Check if current user can manage options
     */
    public static function current_user_can_manage() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get plugin option with default
     */
    public static function get_option($option_name, $default = '') {
        return get_option('wettkampf_' . $option_name, $default);
    }
    
    /**
     * Update plugin option
     */
    public static function update_option($option_name, $value) {
        return update_option('wettkampf_' . $option_name, $value);
    }
    
    /**
     * Add admin notice
     */
    public static function add_admin_notice($message, $type = 'success') {
        $class = ($type === 'error') ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . $class . '"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Log error message
     */
    public static function log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Wettkampf Manager: ' . $message . (!empty($context) ? ' Context: ' . print_r($context, true) : ''));
        }
    }
    
    /**
     * Get current page URL
     */
    public static function get_current_admin_url($remove_params = array()) {
        $url = admin_url('admin.php');
        
        if (!empty($_GET['page'])) {
            $url .= '?page=' . urlencode($_GET['page']);
            
            foreach ($_GET as $key => $value) {
                if ($key !== 'page' && !in_array($key, $remove_params)) {
                    $url .= '&' . urlencode($key) . '=' . urlencode($value);
                }
            }
        }
        
        return $url;
    }
    
    /**
     * Render admin table row
     */
    public static function render_form_row($label, $input_html, $description = '') {
        echo '<tr>';
        echo '<th><label>' . esc_html($label) . '</label></th>';
        echo '<td>' . $input_html;
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status, $text = '') {
        $classes = array(
            'active' => 'status-badge active',
            'inactive' => 'status-badge inactive',
            'expired' => 'status-badge expired'
        );
        
        $class = isset($classes[$status]) ? $classes[$status] : 'status-badge';
        $display_text = !empty($text) ? $text : ucfirst($status);
        
        return '<span class="' . esc_attr($class) . '">' . esc_html($display_text) . '</span>';
    }
    
    /**
     * Get category badge HTML
     */
    public static function get_category_badge($category) {
        $class = 'kategorie-badge kategorie-' . strtolower($category);
        return '<span class="' . esc_attr($class) . '">' . esc_html($category) . '</span>';
    }
    
    /**
     * Truncate text with ellipsis
     */
    public static function truncate_text($text, $length = 100, $append = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $append;
    }
    
    /**
     * Convert array to options HTML
     */
    public static function array_to_options($array, $selected = '', $include_empty = false) {
        $html = '';
        
        if ($include_empty) {
            $html .= '<option value="">Bitte wählen</option>';
        }
        
        foreach ($array as $value => $label) {
            $selected_attr = selected($selected, $value, false);
            $html .= '<option value="' . esc_attr($value) . '"' . $selected_attr . '>' . esc_html($label) . '</option>';
        }
        
        return $html;
    }
}