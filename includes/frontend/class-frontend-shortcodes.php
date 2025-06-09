<?php
/**
 * Frontend shortcodes functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FrontendShortcodes {
    
    /**
     * Initialize shortcodes
     */
    public function init() {
        add_shortcode('wettkampf_liste', array($this, 'display_wettkampf_liste'));
        add_shortcode('wettkampf_anmeldung', array($this, 'display_anmeldung_form'));
    }
    
    /**
     * Display competition list shortcode
     */
    public function display_wettkampf_liste($atts) {
        $display = new FrontendDisplay();
        return $display->display_wettkampf_liste($atts);
    }
    
    /**
     * Display registration form shortcode
     */
    public function display_anmeldung_form($atts) {
        $forms = new FrontendForms();
        return $forms->display_anmeldung_form($atts);
    }
}