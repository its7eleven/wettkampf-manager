<?php
/**
 * Admin menu management - ERWEITERT mit Debug-Seite
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminMenu {
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            'Wettkampf Manager',
            'Wettkämpfe',
            'manage_options',
            'wettkampf-manager',
            array($this, 'display_main_page'),
            'dashicons-awards',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'wettkampf-manager',
            'Neuer Wettkampf',
            'Neuer Wettkampf',
            'manage_options',
            'wettkampf-new',
            array($this, 'display_wettkampf_page')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Disziplinen',
            'Disziplinen',
            'manage_options',
            'wettkampf-disziplinen',
            array($this, 'display_disziplinen_page')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Anmeldungen',
            'Anmeldungen',
            'manage_options',
            'wettkampf-anmeldungen',
            array($this, 'display_anmeldungen_page')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'wettkampf-settings',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Auto-Export Status',
            'Auto-Export Status',
            'manage_options',
            'wettkampf-export-status',
            array($this, 'display_export_status_page')
        );
        
        // Debug-Seite (nur wenn WP_DEBUG aktiv)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'wettkampf-manager',
                'Debug',
                'Debug 🐛',
                'manage_options',
                'wettkampf-debug',
                array($this, 'display_debug_page')
            );
        }
    }
    
    /**
     * Display main page
     */
    public function display_main_page() {
        $wettkampf_admin = new AdminWettkampf();
        $wettkampf_admin->display_overview_page();
    }
    
    /**
     * Display wettkampf form page
     */
    public function display_wettkampf_page() {
        $wettkampf_admin = new AdminWettkampf();
        $wettkampf_admin->display_form_page();
    }
    
    /**
     * Display disziplinen page
     */
    public function display_disziplinen_page() {
        $disziplinen_admin = new AdminDisziplinen();
        $disziplinen_admin->display_page();
    }
    
    /**
     * Display anmeldungen page
     */
    public function display_anmeldungen_page() {
        $anmeldungen_admin = new AdminAnmeldungen();
        $anmeldungen_admin->display_page();
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        $settings_admin = new AdminSettings();
        $settings_admin->display_page();
    }
    
    /**
     * Display export status page
     */
    public function display_export_status_page() {
        $export_admin = new AdminExport();
        $export_admin->display_status_page();
    }
    
    /**
     * Display debug page
     */
    public function display_debug_page() {
        // Lade Debug-Klasse wenn noch nicht geladen
        if (!class_exists('AdminDebug')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/admin/class-admin-debug.php';
        }
        
        $debug_admin = new AdminDebug();
        $debug_admin->display_page();
    }
    
    /**
     * Get admin page URL
     */
    public function get_admin_url($page, $params = array()) {
        $url = admin_url('admin.php?page=' . $page);
        
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Add admin notices
     */
    public function add_admin_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            WettkampfHelpers::add_admin_notice($message, $type);
        });
    }
}