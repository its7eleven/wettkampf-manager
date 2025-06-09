<?php
/**
 * Age category calculation utility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CategoryCalculator {
    
    /**
     * Available age categories
     */
    const CATEGORIES = array('U10', 'U12', 'U14', 'U16', 'U18');
    
    /**
     * Calculate age category based on birth year
     * Only uses categories: U10, U12, U14, U16, U18
     * Always uses the next appropriate category
     */
    public static function calculate($jahrgang, $current_year = null) {
        if (!$current_year) {
            $current_year = date('Y');
        }
        
        $age = $current_year - $jahrgang;
        
        // Only these 5 categories, always next appropriate choice
        if ($age < 10) return 'U10';  // All under 10 years → U10
        if ($age < 12) return 'U12';  // 10-11 years → U12
        if ($age < 14) return 'U14';  // 12-13 years → U14
        if ($age < 16) return 'U16';  // 14-15 years → U16
        if ($age < 18) return 'U18';  // 16-17 years → U18
        
        return 'U18'; // All 18+ years stay in U18
    }
    
    /**
     * Get category with explanation
     */
    public static function get_category_with_explanation($jahrgang, $current_year = null) {
        if (!$current_year) {
            $current_year = date('Y');
        }
        
        $age = $current_year - $jahrgang;
        $category = self::calculate($jahrgang, $current_year);
        
        return array(
            'category' => $category,
            'age' => $age,
            'explanation' => self::get_category_explanation($category, $age)
        );
    }
    
    /**
     * Get explanation for category
     */
    public static function get_category_explanation($category, $age) {
        $explanations = array(
            'U10' => 'unter 10 Jahre',
            'U12' => 'unter 12 Jahre (10-11 Jahre)',
            'U14' => 'unter 14 Jahre (12-13 Jahre)',
            'U16' => 'unter 16 Jahre (14-15 Jahre)',
            'U18' => 'unter 18 Jahre (16+ Jahre)'
        );
        
        return isset($explanations[$category]) ? $explanations[$category] : $category;
    }
    
    /**
     * Get all categories with descriptions
     */
    public static function get_all_categories() {
        return array(
            'U10' => array(
                'name' => 'U10',
                'description' => 'unter 10 Jahre',
                'age_range' => 'bis 9 Jahre'
            ),
            'U12' => array(
                'name' => 'U12',
                'description' => 'unter 12 Jahre',
                'age_range' => '10-11 Jahre'
            ),
            'U14' => array(
                'name' => 'U14',
                'description' => 'unter 14 Jahre',
                'age_range' => '12-13 Jahre'
            ),
            'U16' => array(
                'name' => 'U16',
                'description' => 'unter 16 Jahre',
                'age_range' => '14-15 Jahre'
            ),
            'U18' => array(
                'name' => 'U18',
                'description' => 'unter 18 Jahre',
                'age_range' => '16+ Jahre'
            ),
            'Alle' => array(
                'name' => 'Alle',
                'description' => 'Alle Kategorien',
                'age_range' => 'alle Altersgruppen'
            )
        );
    }
    
    /**
     * Get categories for select dropdown
     */
    public static function get_categories_for_select($include_all = true) {
        $categories = array();
        
        if ($include_all) {
            $categories[''] = 'Bitte wählen';
        }
        
        $all_categories = self::get_all_categories();
        foreach ($all_categories as $key => $category) {
            $categories[$key] = $category['name'] . ' (' . $category['description'] . ')';
        }
        
        return $categories;
    }
    
    /**
     * Validate birth year
     */
    public static function validate_birth_year($jahrgang) {
        $current_year = date('Y');
        
        if (!is_numeric($jahrgang)) {
            return array(
                'valid' => false,
                'error' => 'Jahrgang muss eine Zahl sein'
            );
        }
        
        $jahrgang = intval($jahrgang);
        
        if ($jahrgang < 1900 || $jahrgang > $current_year) {
            return array(
                'valid' => false,
                'error' => 'Jahrgang muss zwischen 1900 und ' . $current_year . ' liegen'
            );
        }
        
        if (strlen((string)$jahrgang) !== 4) {
            return array(
                'valid' => false,
                'error' => 'Jahrgang muss 4-stellig sein'
            );
        }
        
        return array(
            'valid' => true,
            'category' => self::calculate($jahrgang),
            'age' => $current_year - $jahrgang
        );
    }
    
    /**
     * Get participants by category for a competition
     */
    public static function get_participants_by_category($wettkampf_id) {
        global $wpdb;
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $participants = $wpdb->get_results($wpdb->prepare("
            SELECT jahrgang, COUNT(*) as count 
            FROM $table_anmeldung 
            WHERE wettkampf_id = %d 
            GROUP BY jahrgang
        ", $wettkampf_id));
        
        $by_category = array();
        foreach (self::CATEGORIES as $category) {
            $by_category[$category] = 0;
        }
        
        foreach ($participants as $participant) {
            $category = self::calculate($participant->jahrgang);
            $by_category[$category] += $participant->count;
        }
        
        return $by_category;
    }
    
    /**
     * Check if category is valid
     */
    public static function is_valid_category($category) {
        $valid_categories = array_merge(self::CATEGORIES, array('Alle'));
        return in_array($category, $valid_categories);
    }
    
    /**
     * Get category order for sorting
     */
    public static function get_category_sort_order() {
        return array('U10' => 1, 'U12' => 2, 'U14' => 3, 'U16' => 4, 'U18' => 5, 'Alle' => 6);
    }
    
    /**
     * Sort categories array
     */
    public static function sort_categories($categories) {
        $order = self::get_category_sort_order();
        
        uksort($categories, function($a, $b) use ($order) {
            $pos_a = isset($order[$a]) ? $order[$a] : 999;
            $pos_b = isset($order[$b]) ? $order[$b] : 999;
            return $pos_a - $pos_b;
        });
        
        return $categories;
    }
}