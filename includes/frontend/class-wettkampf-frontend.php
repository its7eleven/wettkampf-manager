<?php
/**
 * Main frontend class - coordinates all frontend functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfFrontend {
    
    /**
     * Display handler
     */
    private $display;
    
    /**
     * Forms handler
     */
    private $forms;
    
    /**
     * AJAX handler
     */
    private $ajax;
    
    /**
     * Shortcodes handler
     */
    private $shortcodes;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_frontend_components();
    }
    
    /**
     * Initialize frontend components
     */
    private function init_frontend_components() {
        $this->display = new FrontendDisplay();
        $this->forms = new FrontendForms();
        $this->ajax = new FrontendAjax();
        $this->shortcodes = new FrontendShortcodes();
    }
    
    /**
     * Get display handler
     */
    public function get_display() {
        return $this->display;
    }
    
    /**
     * Get forms handler
     */
    public function get_forms() {
        return $this->forms;
    }
    
    /**
     * Get AJAX handler
     */
    public function get_ajax() {
        return $this->ajax;
    }
    
    /**
     * Get shortcodes handler
     */
    public function get_shortcodes() {
        return $this->shortcodes;
    }
}