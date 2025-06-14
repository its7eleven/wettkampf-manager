<?php
/**
 * Discipline management admin class - Mit korrekten Umlauten
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminDisziplinen {
    
    /**
     * Display disciplines management page
     */
    public function display_page() {
        // Handle save action
        if (isset($_POST['action']) && $_POST['action'] === 'save_disziplin' && wp_verify_nonce($_POST['disziplin_nonce'], 'save_disziplin')) {
            $this->save_disziplin();
        }
        
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_disziplin')) {
            $id = intval($_GET['delete']);
            $this->delete_disziplin($id);
            WettkampfHelpers::add_admin_notice('Disziplin gelöscht!');
        }
        
        $disziplinen = $this->get_all_disciplines();
        $edit_disziplin = null;
        
        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $edit_disziplin = $this->get_discipline($edit_id);
        }
        
        ?>
        <div class="wrap">
            <h1>Disziplinen verwalten</h1>
            
            <!-- Form for new/edit discipline -->
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2><?php echo $edit_disziplin ? 'Disziplin bearbeiten' : 'Neue Disziplin'; ?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="save_disziplin">
                    <?php if ($edit_disziplin): ?>
                    <input type="hidden" name="disziplin_id" value="<?php echo $edit_disziplin->id; ?>">
                    <?php endif; ?>
                    <?php wp_nonce_field('save_disziplin', 'disziplin_nonce'); ?>
                    
                    <table class="form-table">
                        <?php
                        WettkampfHelpers::render_form_row(
                            'Name *',
                            '<input type="text" id="name" name="name" value="' . ($edit_disziplin ? SecurityManager::escape_attr($edit_disziplin->name) : '') . '" class="regular-text" required>'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'Beschreibung',
                            '<textarea id="beschreibung" name="beschreibung" rows="3" class="large-text">' . ($edit_disziplin ? SecurityManager::escape_html($edit_disziplin->beschreibung) : '') . '</textarea>'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'Alterskategorie *',
                            $this->render_category_select($edit_disziplin),
                            'Wähle die Alterskategorie, für die diese Disziplin verfügbar ist. Maßgebend ist das Alter, das im aktuellen Jahr erreicht wird.'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'Sortierung',
                            '<input type="number" id="sortierung" name="sortierung" value="' . ($edit_disziplin ? $edit_disziplin->sortierung : 0) . '" min="0" max="999">',
                            'Niedrigere Zahlen werden zuerst angezeigt'
                        );
                        
                        WettkampfHelpers::render_form_row(
                            'Aktiv',
                            '<input type="checkbox" id="aktiv" name="aktiv" value="1" ' . ((!$edit_disziplin || $edit_disziplin->aktiv) ? 'checked' : '') . '>'
                        );
                        ?>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php echo $edit_disziplin ? 'Aktualisieren' : 'Erstellen'; ?>">
                        <?php if ($edit_disziplin): ?>
                        <a href="?page=wettkampf-disziplinen" class="button">Abbrechen</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <!-- List of all disciplines -->
            <h2>Alle Disziplinen (<?php echo count($disziplinen); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Kategorie</th>
                        <th>Sortierung</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($disziplinen)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666; font-style: italic;">
                                Keine Disziplinen vorhanden. Erstelle die erste Disziplin mit dem Formular oben.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($disziplinen as $disziplin): ?>
                        <tr>
                            <td><strong><?php echo SecurityManager::escape_html($disziplin->name); ?></strong></td>
                            <td><?php echo SecurityManager::escape_html($disziplin->beschreibung); ?></td>
                            <td>
                                <?php echo WettkampfHelpers::get_category_badge($disziplin->kategorie ?: 'Nicht gesetzt'); ?>
                            </td>
                            <td><?php echo $disziplin->sortierung; ?></td>
                            <td>
                                <?php if ($disziplin->aktiv): ?>
                                    <?php echo WettkampfHelpers::get_status_badge('active', '✓ Aktiv'); ?>
                                <?php else: ?>
                                    <?php echo WettkampfHelpers::get_status_badge('inactive', '✗ Inaktiv'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=wettkampf-disziplinen&edit=<?php echo $disziplin->id; ?>">Bearbeiten</a> |
                                <a href="?page=wettkampf-disziplinen&delete=<?php echo $disziplin->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_disziplin'); ?>" 
                                   onclick="return confirm('Wirklich löschen? Alle Zuordnungen zu Wettkämpfen und Anmeldungen werden ebenfalls gelöscht!')" 
                                   style="color: #d63638;">Löschen</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h3>💡 Hinweise zur Disziplin-Verwaltung mit Kategorien:</h3>
                <ul>
                    <li><strong>Alterskategorien:</strong> Es gibt nur 5 Kategorien: U10, U12, U14, U16, U18</li>
                    <li><strong>Zuordnung:</strong> Kinder unter 10 → U10, 10-11 Jahre → U12, 12-13 Jahre → U14, 14-15 Jahre → U16, 16+ Jahre → U18</li>
                    <li><strong>Berechnung:</strong> Maßgebend ist das Alter, das im Jahr <?php echo date('Y'); ?> erreicht wird</li>
                    <li><strong>Kategorie "Alle":</strong> Diese Disziplin wird allen Alterskategorien angezeigt</li>
                    <li><strong>Beispiel:</strong> Ein Kind geboren 2016 → 2025 - 2016 = 9 Jahre → Kategorie U10</li>
                    <li><strong>Beispiel:</strong> Ein Kind geboren 2014 → 2025 - 2014 = 11 Jahre → Kategorie U12</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden alle Zuordnungen zu Wettkämpfen und Anmeldungen entfernt</li>
                </ul>
            </div>
        </div>
        <?php
        
        $this->add_category_styles();
    }
    
    /**
     * Render category select dropdown
     */
    private function render_category_select($edit_disziplin) {
        $categories = CategoryCalculator::get_categories_for_select(true);
        $selected = $edit_disziplin ? $edit_disziplin->kategorie : '';
        
        $html = '<select id="kategorie" name="kategorie" required>';
        $html .= WettkampfHelpers::array_to_options($categories, $selected);
        $html .= '</select>';
        
        return $html;
    }
    
    /**
     * Get all disciplines
     */
    private function get_all_disciplines() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf_disziplinen';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY kategorie ASC, sortierung ASC, name ASC");
    }
    
    /**
     * Get single discipline
     */
    private function get_discipline($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf_disziplinen';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    /**
     * Save discipline
     */
    private function save_disziplin() {
        SecurityManager::check_admin_permissions();
        
        // Sanitize data
        $sanitization_rules = array(
            'name' => 'text',
            'beschreibung' => 'textarea',
            'kategorie' => 'text',
            'sortierung' => 'int'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        $data['aktiv'] = isset($_POST['aktiv']) ? 1 : 0;
        
        // Validation
        $validation_rules = array(
            'name' => array('required' => true, 'min_length' => 2),
            'kategorie' => array('required' => true, 'custom' => function($value) {
                return CategoryCalculator::is_valid_category($value) ? true : 'Ungültige Kategorie ausgewählt';
            })
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            WettkampfHelpers::add_admin_notice('Validierungsfehler: ' . implode(', ', $validation['errors']), 'error');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf_disziplinen';
        
        if (isset($_POST['disziplin_id']) && !empty($_POST['disziplin_id'])) {
            // Update existing
            $result = $wpdb->update($table_name, $data, array('id' => intval($_POST['disziplin_id'])));
            $message = 'Disziplin aktualisiert!';
        } else {
            // Insert new
            $result = $wpdb->insert($table_name, $data);
            $message = 'Disziplin erstellt!';
        }
        
        if ($result !== false) {
            WettkampfHelpers::add_admin_notice($message);
            // Redirect to clean URL
            wp_redirect(admin_url('admin.php?page=wettkampf-disziplinen'));
            exit;
        } else {
            WettkampfHelpers::add_admin_notice('Fehler beim Speichern der Disziplin', 'error');
        }
    }
    
    /**
     * Delete discipline
     */
    public function delete_disziplin($id) {
        SecurityManager::check_admin_permissions();
        
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Delete assignments first
        $wpdb->delete($tables['disziplin_zuordnung'], array('disziplin_id' => $id));
        $wpdb->delete($tables['anmeldung_disziplinen'], array('disziplin_id' => $id));
        
        // Delete discipline
        return $wpdb->delete($tables['disziplinen'], array('id' => $id));
    }
    
    /**
     * Add category badge styles
     */
    private function add_category_styles() {
        ?>
        <style>
        .kategorie-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #e5e7eb;
            color: #374151;
        }
        
        .kategorie-badge.kategorie-u10 { background: #dbeafe; color: #1e40af; }
        .kategorie-badge.kategorie-u12 { background: #dcfce7; color: #166534; }
        .kategorie-badge.kategorie-u14 { background: #fde2e8; color: #be185d; }
        .kategorie-badge.kategorie-u16 { background: #ede9fe; color: #7c3aed; }
        .kategorie-badge.kategorie-u18 { background: #fed7d7; color: #c53030; }
        .kategorie-badge.kategorie-alle { background: #d1fae5; color: #065f46; }
        </style>
        <?php
    }
}