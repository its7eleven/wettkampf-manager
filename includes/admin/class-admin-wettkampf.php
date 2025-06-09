<?php
/**
 * Competition management admin class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminWettkampf {
    
    /**
     * Display overview page
     */
    public function display_overview_page() {
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_wettkampf')) {
            $id = intval($_GET['delete']);
            $this->delete_wettkampf_by_id($id);
            WettkampfHelpers::add_admin_notice('Wettkampf und alle Anmeldungen gelöscht!');
        }
        
        // Get current filter
        $current_filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $wettkaempfe = WettkampfDatabase::get_competitions_with_counts($current_filter);
        
        // Calculate counts
        $alle_count = count(WettkampfDatabase::get_competitions_with_counts('all'));
        $aktive_count = count(WettkampfDatabase::get_competitions_with_counts('active'));
        $vergangene_count = count(WettkampfDatabase::get_competitions_with_counts('inactive'));
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager <a href="?page=wettkampf-new" class="page-title-action">Neuer Wettkampf</a></h1>
            
            <!-- Filter Tabs -->
            <ul class="subsubsub">
                <li class="all">
                    <a href="?page=wettkampf-manager&filter=all" class="<?php echo $current_filter === 'all' ? 'current' : ''; ?>">
                        Alle <span class="count">(<?php echo $alle_count; ?>)</span>
                    </a> |
                </li>
                <li class="active">
                    <a href="?page=wettkampf-manager&filter=active" class="<?php echo $current_filter === 'active' ? 'current' : ''; ?>">
                        Aktive <span class="count">(<?php echo $aktive_count; ?>)</span>
                    </a> |
                </li>
                <li class="inactive">
                    <a href="?page=wettkampf-manager&filter=inactive" class="<?php echo $current_filter === 'inactive' ? 'current' : ''; ?>">
                        Vergangene <span class="count">(<?php echo $vergangene_count; ?>)</span>
                    </a>
                </li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Datum</th>
                        <th>Ort</th>
                        <th>Anmeldeschluss</th>
                        <th>Anmeldungen</th>
                        <th>Lizenziert</th>
                        <th>Disziplinen</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wettkaempfe as $wettkampf): ?>
                    <tr>
                        <td><?php echo SecurityManager::escape_html($wettkampf->name); ?></td>
                        <td><?php echo WettkampfHelpers::format_german_date($wettkampf->datum); ?></td>
                        <td><?php echo SecurityManager::escape_html($wettkampf->ort); ?></td>
                        <td><?php echo WettkampfHelpers::format_german_date($wettkampf->anmeldeschluss); ?></td>
                        <td><strong><?php echo $wettkampf->anmeldungen_count; ?></strong></td>
                        <td><?php echo $wettkampf->lizenziert ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <?php echo $this->get_competition_disciplines_display($wettkampf->id); ?>
                        </td>
                        <td>
                            <a href="?page=wettkampf-new&edit=<?php echo $wettkampf->id; ?>">Bearbeiten</a> |
                            <a href="?page=wettkampf-anmeldungen&wettkampf_id=<?php echo $wettkampf->id; ?>">Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?>)</a> |
                            <a href="?page=wettkampf-manager&delete=<?php echo $wettkampf->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_wettkampf'); ?>" 
                               onclick="return confirm('⚠️ ACHTUNG: Beim Löschen werden auch ALLE Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?> Stück) gelöscht!\n\nWirklich fortfahren?')" 
                               style="color: #d63638;">Löschen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display form page (new/edit)
     */
    public function display_form_page() {
        $wettkampf = null;
        
        if (isset($_GET['edit'])) {
            $id = intval($_GET['edit']);
            $wettkampf = WettkampfDatabase::get_competition($id);
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $wettkampf ? 'Wettkampf bearbeiten' : 'Neuer Wettkampf'; ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_wettkampf">
                <?php if ($wettkampf): ?>
                <input type="hidden" name="wettkampf_id" value="<?php echo $wettkampf->id; ?>">
                <?php endif; ?>
                <?php wp_nonce_field('save_wettkampf', 'wettkampf_nonce'); ?>
                
                <table class="form-table">
                    <?php
                    WettkampfHelpers::render_form_row(
                        'Name',
                        '<input type="text" id="name" name="name" value="' . ($wettkampf ? SecurityManager::escape_attr($wettkampf->name) : '') . '" class="regular-text" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Datum',
                        '<input type="date" id="datum" name="datum" value="' . ($wettkampf ? $wettkampf->datum : '') . '" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Ort',
                        '<input type="text" id="ort" name="ort" value="' . ($wettkampf ? SecurityManager::escape_attr($wettkampf->ort) : '') . '" class="regular-text" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Beschreibung',
                        '<textarea id="beschreibung" name="beschreibung" rows="5" class="large-text">' . ($wettkampf ? SecurityManager::escape_html($wettkampf->beschreibung) : '') . '</textarea>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Startberechtigte',
                        '<textarea id="startberechtigte" name="startberechtigte" rows="3" class="large-text">' . ($wettkampf ? SecurityManager::escape_html($wettkampf->startberechtigte) : '') . '</textarea>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Anmeldeschluss',
                        '<input type="date" id="anmeldeschluss" name="anmeldeschluss" value="' . ($wettkampf ? $wettkampf->anmeldeschluss : '') . '" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Link zum Event',
                        '<input type="url" id="event_link" name="event_link" value="' . ($wettkampf ? SecurityManager::escape_attr($wettkampf->event_link) : '') . '" class="regular-text">'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Lizenzierter Event',
                        '<input type="checkbox" id="lizenziert" name="lizenziert" value="1" ' . (($wettkampf && $wettkampf->lizenziert) ? 'checked' : '') . '>'
                    );
                    ?>
                    
                    <tr>
                        <th><label for="disziplinen">Disziplinen</label></th>
                        <td>
                            <?php echo $this->render_disciplines_selector($wettkampf); ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php echo $wettkampf ? 'Aktualisieren' : 'Speichern'; ?>">
                    <a href="?page=wettkampf-manager" class="button">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render disciplines selector
     */
    private function render_disciplines_selector($wettkampf) {
        $grouped_disciplines = WettkampfDatabase::get_disciplines_grouped();
        
        // Get selected disciplines
        $selected_disciplines = array();
        if ($wettkampf) {
            global $wpdb;
            $tables = WettkampfDatabase::get_table_names();
            $assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT disziplin_id FROM {$tables['disziplin_zuordnung']} WHERE wettkampf_id = %d", 
                $wettkampf->id
            ));
            foreach ($assignments as $assignment) {
                $selected_disciplines[] = $assignment->disziplin_id;
            }
        }
        
        if (empty($grouped_disciplines)) {
            return '<p><em>Keine Disziplinen verfügbar. <a href="?page=wettkampf-disziplinen">Disziplinen verwalten</a></em></p>';
        }
        
        ob_start();
        ?>
        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
            <div style="margin-bottom: 10px; padding: 5px 10px; background: #e0f2fe; border-radius: 5px; font-size: 12px; color: #0891b2;">
                <strong>💡 Tipp:</strong> Wähle nur die Disziplinen aus, die für diesen Wettkampf relevant sind. 
                Die Teilnehmer sehen nur Disziplinen ihrer Alterskategorie.
            </div>
            
            <?php 
            $sorted_groups = CategoryCalculator::sort_categories($grouped_disciplines);
            foreach ($sorted_groups as $kategorie => $disziplinen): 
            ?>
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px;">
                        <?php echo WettkampfHelpers::get_category_badge($kategorie); ?>
                    </h4>
                    <div style="margin-left: 10px;">
                        <?php foreach ($disziplinen as $disziplin): ?>
                            <label style="display: block; margin-bottom: 8px; padding: 5px; border-radius: 3px; transition: background-color 0.2s;">
                                <input type="checkbox" name="disziplinen[]" value="<?php echo $disziplin->id; ?>" 
                                       <?php echo in_array($disziplin->id, $selected_disciplines) ? 'checked' : ''; ?>>
                                <strong><?php echo SecurityManager::escape_html($disziplin->name); ?></strong>
                                <?php if ($disziplin->beschreibung): ?>
                                    <small style="color: #666; margin-left: 10px;">(<?php echo SecurityManager::escape_html($disziplin->beschreibung); ?>)</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <small>Lasse alle unausgewählt, wenn dieser Wettkampf keine spezifischen Disziplinen hat.</small>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get competition disciplines display
     */
    private function get_competition_disciplines_display($wettkampf_id) {
        $disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id);
        
        $discipline_info = array();
        if (is_array($disciplines) && !empty($disciplines)) {
            foreach ($disciplines as $d) {
                if (is_object($d) && isset($d->name) && !empty($d->name)) {
                    $info = SecurityManager::escape_html($d->name);
                    if (!empty($d->kategorie) && $d->kategorie !== 'Alle') {
                        $info .= ' ' . WettkampfHelpers::get_category_badge($d->kategorie);
                    }
                    $discipline_info[] = $info;
                }
            }
        }
        
        if (!empty($discipline_info)) {
            return '<small>' . implode(', ', $discipline_info) . '</small>';
        } else {
            return '<small>Keine Disziplinen</small>';
        }
    }
    
    /**
     * Save competition
     */
    public function save_wettkampf() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['wettkampf_nonce'], 'save_wettkampf')) {
            wp_die('Sicherheitsfehler');
        }
        
        SecurityManager::check_admin_permissions();
        
        // Sanitize and validate data
        $sanitization_rules = array(
            'name' => 'text',
            'datum' => 'text',
            'ort' => 'text',
            'beschreibung' => 'textarea',
            'startberechtigte' => 'textarea',
            'anmeldeschluss' => 'text',
            'event_link' => 'url',
            'disziplinen' => 'array'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        
        // Validation rules
        $validation_rules = array(
            'name' => array('required' => true, 'min_length' => 3),
            'datum' => array('required' => true),
            'ort' => array('required' => true, 'min_length' => 2),
            'anmeldeschluss' => array('required' => true)
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            wp_die('Validierungsfehler: ' . implode(', ', $validation['errors']));
        }
        
        // Save competition
        $wettkampf_id = null;
        if (isset($_POST['wettkampf_id']) && !empty($_POST['wettkampf_id'])) {
            $wettkampf_id = intval($_POST['wettkampf_id']);
        }
        
        $result = WettkampfDatabase::save_competition($data, $wettkampf_id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=wettkampf-manager'));
            exit;
        } else {
            wp_die('Fehler beim Speichern des Wettkampfs');
        }
    }
    
    /**
     * Delete competition by ID
     */
    public function delete_wettkampf_by_id($id) {
        SecurityManager::check_admin_permissions();
        return WettkampfDatabase::delete_competition($id);
    }
}