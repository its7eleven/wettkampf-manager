<?php
/**
 * Database operations class - ERWEITERT mit "wir fahren direkt" Option und besserer Fehlerbehandlung
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfDatabase {
    
    /**
     * Get table names
     */
    public static function get_table_names() {
        global $wpdb;
        
        return array(
            'wettkampf' => $wpdb->prefix . 'wettkampf',
            'disziplinen' => $wpdb->prefix . 'wettkampf_disziplinen',
            'disziplin_zuordnung' => $wpdb->prefix . 'wettkampf_disziplin_zuordnung',
            'anmeldung' => $wpdb->prefix . 'wettkampf_anmeldung',
            'anmeldung_disziplinen' => $wpdb->prefix . 'wettkampf_anmeldung_disziplinen'
        );
    }
    
    /**
     * Get all competitions with registration count
     */
    public static function get_competitions_with_counts($filter = 'all') {
        global $wpdb;
        $tables = self::get_table_names();
        
        $query = "
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
        ";
        
        switch ($filter) {
            case 'active':
                $query .= " WHERE w.datum >= CURDATE() ";
                break;
            case 'inactive':
                $query .= " WHERE w.datum < CURDATE() ";
                break;
        }
        
        $query .= " GROUP BY w.id ORDER BY w.datum DESC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get competition by ID
     */
    public static function get_competition($id) {
        global $wpdb;
        $tables = self::get_table_names();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['wettkampf']} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get competition disciplines mit besserem Kategorie-Filter
     */
    public static function get_competition_disciplines($wettkampf_id, $kategorie = null) {
        global $wpdb;
        $tables = self::get_table_names();
        
        $query = "
            SELECT d.* 
            FROM {$tables['disziplin_zuordnung']} z 
            JOIN {$tables['disziplinen']} d ON z.disziplin_id = d.id 
            WHERE z.wettkampf_id = %d AND d.aktiv = 1
        ";
        
        $params = array($wettkampf_id);
        
        // Bessere Kategorie-Filterung
        if ($kategorie && $kategorie !== '') {
            // Zeige Disziplinen für die spezifische Kategorie ODER für "Alle"
            $query .= " AND (d.kategorie = %s OR d.kategorie = 'Alle' OR d.kategorie IS NULL OR d.kategorie = '')";
            $params[] = $kategorie;
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WettkampfDatabase: Filtering disciplines for category '$kategorie' in wettkampf $wettkampf_id");
            }
        }
        
        $query .= " ORDER BY d.sortierung ASC, d.name ASC";
        
        $result = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $count = is_array($result) ? count($result) : 0;
            error_log("WettkampfDatabase: Found $count disciplines for wettkampf $wettkampf_id" . ($kategorie ? " with category filter '$kategorie'" : " without category filter"));
            
            if ($count > 0 && is_array($result)) {
                foreach ($result as $discipline) {
                    error_log("WettkampfDatabase: - Discipline: {$discipline->name} (Category: {$discipline->kategorie})");
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get all disciplines grouped by category
     */
    public static function get_disciplines_grouped() {
        global $wpdb;
        $tables = self::get_table_names();
        
        $disciplines = $wpdb->get_results("
            SELECT * FROM {$tables['disziplinen']} 
            WHERE aktiv = 1 
            ORDER BY kategorie ASC, sortierung ASC, name ASC
        ");
        
        $grouped = array();
        foreach ($disciplines as $discipline) {
            $category = !empty($discipline->kategorie) ? $discipline->kategorie : 'Alle';
            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }
            $grouped[$category][] = $discipline;
        }
        
        return $grouped;
    }
    
    /**
     * Get registrations with filters
     */
    public static function get_registrations($filters = array()) {
        global $wpdb;
        $tables = self::get_table_names();
        
        $where_conditions = array('1=1');
        $where_params = array();
        
        if (!empty($filters['wettkampf_id'])) {
            $where_conditions[] = 'a.wettkampf_id = %d';
            $where_params[] = intval($filters['wettkampf_id']);
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(a.vorname LIKE %s OR a.name LIKE %s OR a.email LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM {$tables['anmeldung']} a 
            JOIN {$tables['wettkampf']} w ON a.wettkampf_id = w.id 
            WHERE $where_clause
            ORDER BY w.datum DESC, a.anmeldedatum DESC
        ";
        
        return $wpdb->get_results($wpdb->prepare($query, $where_params));
    }
    
    /**
     * Get registration disciplines
     */
    public static function get_registration_disciplines($anmeldung_id) {
        global $wpdb;
        $tables = self::get_table_names();
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT d.name 
            FROM {$tables['anmeldung_disziplinen']} ad 
            JOIN {$tables['disziplinen']} d ON ad.disziplin_id = d.id 
            WHERE ad.anmeldung_id = %d 
            ORDER BY d.sortierung ASC, d.name ASC
        ", $anmeldung_id));
    }
    
    /**
     * Save competition
     */
    public static function save_competition($data, $id = null) {
        global $wpdb;
        $tables = self::get_table_names();
        
        $competition_data = array(
            'name' => sanitize_text_field($data['name']),
            'datum' => sanitize_text_field($data['datum']),
            'ort' => sanitize_text_field($data['ort']),
            'beschreibung' => sanitize_textarea_field($data['beschreibung']),
            'startberechtigte' => sanitize_textarea_field($data['startberechtigte']),
            'anmeldeschluss' => sanitize_text_field($data['anmeldeschluss']),
            'event_link' => esc_url_raw($data['event_link']),
            'lizenziert' => isset($data['lizenziert']) ? 1 : 0
        );
        
        if ($id) {
            $wpdb->update($tables['wettkampf'], $competition_data, array('id' => $id));
            $wettkampf_id = $id;
        } else {
            $wpdb->insert($tables['wettkampf'], $competition_data);
            $wettkampf_id = $wpdb->insert_id;
        }
        
        // Update discipline assignments
        if (isset($data['disziplinen'])) {
            self::update_competition_disciplines($wettkampf_id, $data['disziplinen']);
        }
        
        return $wettkampf_id;
    }
    
    /**
     * Update competition disciplines
     */
    public static function update_competition_disciplines($wettkampf_id, $discipline_ids) {
        global $wpdb;
        $tables = self::get_table_names();
        
        // Delete old assignments
        $wpdb->delete($tables['disziplin_zuordnung'], array('wettkampf_id' => $wettkampf_id));
        
        // Insert new assignments
        if (is_array($discipline_ids)) {
            foreach ($discipline_ids as $discipline_id) {
                $wpdb->insert($tables['disziplin_zuordnung'], array(
                    'wettkampf_id' => $wettkampf_id,
                    'disziplin_id' => intval($discipline_id)
                ));
            }
        }
    }
    
    /**
     * Delete competition with cascade
     */
    public static function delete_competition($id) {
        global $wpdb;
        $tables = self::get_table_names();
        
        // Get all registration IDs
        $anmeldung_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$tables['anmeldung']} WHERE wettkampf_id = %d", 
            $id
        ));
        
        // Delete registration-discipline relations
        if (!empty($anmeldung_ids)) {
            $placeholders = implode(',', array_fill(0, count($anmeldung_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tables['anmeldung_disziplinen']} WHERE anmeldung_id IN ($placeholders)", 
                ...$anmeldung_ids
            ));
        }
        
        // Delete registrations
        $wpdb->delete($tables['anmeldung'], array('wettkampf_id' => $id));
        
        // Delete competition-discipline relations
        $wpdb->delete($tables['disziplin_zuordnung'], array('wettkampf_id' => $id));
        
        // Delete competition
        return $wpdb->delete($tables['wettkampf'], array('id' => $id));
    }
    
    /**
     * Save registration - ERWEITERT mit Transport-Optionen und besserer Fehlerbehandlung
     */
    public static function save_registration($data, $id = null) {
        global $wpdb;
        $tables = self::get_table_names();
        
        try {
            // ERWEITERTE Logik für eltern_fahren und freie_plaetze
            $eltern_fahren = sanitize_text_field($data['eltern_fahren']);
            
            // Validiere Transport-Option
            if (!in_array($eltern_fahren, array('ja', 'nein', 'direkt'))) {
                WettkampfHelpers::log_error('Invalid eltern_fahren value: ' . $eltern_fahren);
                return false;
            }
            
            $freie_plaetze = 0;
            
            // Nur bei "ja" sollen freie Plätze gespeichert werden
            if ($eltern_fahren === 'ja') {
                $freie_plaetze = intval($data['freie_plaetze']);
            }
            
            $registration_data = array(
                'wettkampf_id' => intval($data['wettkampf_id']),
                'vorname' => sanitize_text_field($data['vorname']),
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'geschlecht' => sanitize_text_field($data['geschlecht']),
                'jahrgang' => intval($data['jahrgang']),
                'eltern_fahren' => $eltern_fahren,
                'freie_plaetze' => $freie_plaetze
            );
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WettkampfDatabase: Saving registration with data: ' . print_r($registration_data, true));
            }
            
            if ($id) {
                $result = $wpdb->update($tables['anmeldung'], $registration_data, array('id' => $id));
                if ($result === false) {
                    WettkampfHelpers::log_error('Database update failed: ' . $wpdb->last_error);
                    return false;
                }
                $anmeldung_id = $id;
            } else {
                $result = $wpdb->insert($tables['anmeldung'], $registration_data);
                if ($result === false) {
                    WettkampfHelpers::log_error('Database insert failed: ' . $wpdb->last_error);
                    return false;
                }
                $anmeldung_id = $wpdb->insert_id;
            }
            
            // Update discipline assignments
            if (isset($data['disziplinen'])) {
                self::update_registration_disciplines($anmeldung_id, $data['disziplinen']);
            }
            
            return $anmeldung_id;
            
        } catch (Exception $e) {
            WettkampfHelpers::log_error('Exception in save_registration: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update registration disciplines
     */
    public static function update_registration_disciplines($anmeldung_id, $discipline_ids) {
        global $wpdb;
        $tables = self::get_table_names();
        
        // Delete old assignments
        $wpdb->delete($tables['anmeldung_disziplinen'], array('anmeldung_id' => $anmeldung_id));
        
        // Insert new assignments
        if (is_array($discipline_ids)) {
            foreach ($discipline_ids as $discipline_id) {
                $wpdb->insert($tables['anmeldung_disziplinen'], array(
                    'anmeldung_id' => $anmeldung_id,
                    'disziplin_id' => intval($discipline_id)
                ));
            }
        }
    }
    
    /**
     * Delete registration
     */
    public static function delete_registration($id) {
        global $wpdb;
        $tables = self::get_table_names();
        
        // Delete discipline assignments first
        $wpdb->delete($tables['anmeldung_disziplinen'], array('anmeldung_id' => $id));
        
        // Delete registration
        return $wpdb->delete($tables['anmeldung'], array('id' => $id));
    }
    
    /**
     * Get statistics
     */
    public static function get_statistics() {
        global $wpdb;
        $tables = self::get_table_names();
        
        return array(
            'total_anmeldungen' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['anmeldung']}"),
            'anmeldungen_heute' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['anmeldung']} WHERE DATE(anmeldedatum) = %s", 
                date('Y-m-d')
            )),
            'anmeldungen_woche' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['anmeldung']} WHERE anmeldedatum >= %s", 
                date('Y-m-d', strtotime('-7 days'))
            ))
        );
    }
}