<?php
/**
 * Main admin class - coordinates all admin functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfAdmin {
    
    /**
     * Admin menu handler
     */
    private $menu;
    
    /**
     * Competition management
     */
    private $wettkampf_admin;
    
    /**
     * Discipline management
     */
    private $disziplinen_admin;
    
    /**
     * Registration management
     */
    private $anmeldungen_admin;
    
    /**
     * Export functionality
     */
    private $export_admin;
    
    /**
     * Settings management
     */
    private $settings_admin;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_admin_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize admin components
     */
    private function init_admin_components() {
        $this->menu = new AdminMenu();
        $this->wettkampf_admin = new AdminWettkampf();
        $this->disziplinen_admin = new AdminDisziplinen();
        $this->anmeldungen_admin = new AdminAnmeldungen();
        $this->export_admin = new AdminExport();
        $this->settings_admin = new AdminSettings();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this->menu, 'add_admin_menu'));
        add_action('admin_post_save_wettkampf', array($this->wettkampf_admin, 'save_wettkampf'));
        add_action('admin_post_delete_wettkampf', array($this->wettkampf_admin, 'delete_wettkampf'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }
    
    /**
     * Handle general admin actions
     */
    public function handle_admin_actions() {
        // Handle exports
        if (isset($_GET['export']) && wp_verify_nonce($_GET['_wpnonce'], 'export_anmeldungen')) {
            $this->export_admin->handle_export();
        }
        
        // Handle delete actions
        if (isset($_GET['delete']) && isset($_GET['page'])) {
            $this->handle_delete_actions();
        }
    }
    
    /**
     * Handle delete actions based on page
     */
    private function handle_delete_actions() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }
        
        $page = $_GET['page'];
        $id = intval($_GET['delete']);
        
        switch ($page) {
            case 'wettkampf-manager':
                if (wp_verify_nonce($_GET['_wpnonce'], 'delete_wettkampf')) {
                    $this->wettkampf_admin->delete_wettkampf_by_id($id);
                }
                break;
                
            case 'wettkampf-disziplinen':
                if (wp_verify_nonce($_GET['_wpnonce'], 'delete_disziplin')) {
                    $this->disziplinen_admin->delete_disziplin($id);
                }
                break;
                
            case 'wettkampf-anmeldungen':
                if (wp_verify_nonce($_GET['_wpnonce'], 'delete_anmeldung')) {
                    $this->anmeldungen_admin->delete_anmeldung($id);
                }
                break;
        }
    }
    
    /**
     * Get admin menu instance
     */
    public function get_menu() {
        return $this->menu;
    }
    
    /**
     * Get wettkampf admin instance
     */
    public function get_wettkampf_admin() {
        return $this->wettkampf_admin;
    }
    
    /**
     * Get disziplinen admin instance
     */
    public function get_disziplinen_admin() {
        return $this->disziplinen_admin;
    }
    
    /**
     * Get anmeldungen admin instance
     */
    public function get_anmeldungen_admin() {
        return $this->anmeldungen_admin;
    }
    
    /**
     * Get export admin instance
     */
    public function get_export_admin() {
        return $this->export_admin;
    }
    
    /**
     * Get settings admin instance
     */
    public function get_settings_admin() {
        return $this->settings_admin;
    }
}