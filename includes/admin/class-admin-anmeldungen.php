<?php
/**
 * Registration management admin class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminAnmeldungen {
    
    /**
     * Display registrations management page
     */
    public function display_page() {
        // Handle edit action ZUERST, vor allem anderen
        if (isset($_POST['save_anmeldung']) && isset($_POST['anmeldung_nonce']) && wp_verify_nonce($_POST['anmeldung_nonce'], 'save_anmeldung')) {
            $this->save_anmeldung();
            // Nach dem Speichern sollte bereits ein Redirect stattgefunden haben
            return;
        }
        
        // Handle delete action
        if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_anmeldung')) {
            $id = intval($_GET['delete']);
            $this->delete_anmeldung($id);
            WettkampfHelpers::add_admin_notice('Anmeldung gelöscht!');
        }
        
        // Check if we're in edit mode
        $edit_anmeldung = null;
        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $edit_anmeldung = $this->get_registration($edit_id);
            
            if (!$edit_anmeldung) {
                WettkampfHelpers::add_admin_notice('Anmeldung nicht gefunden!', 'error');
            }
        }
        
        // Get filter parameters
        $filters = array(
            'wettkampf_id' => isset($_GET['wettkampf_id']) ? intval($_GET['wettkampf_id']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        );
        
        $anmeldungen = WettkampfDatabase::get_registrations($filters);
        $wettkaempfe = $this->get_competitions_for_filter();
        $statistics = WettkampfDatabase::get_statistics();
        
        ?>
        <div class="wrap">
            <h1>Anmeldungen verwalten</h1>
            
            <?php if ($edit_anmeldung): ?>
                <?php $this->render_edit_form($edit_anmeldung); ?>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="wettkampf-stats">
                <div class="stat-card">
                    <h3>Gesamt</h3>
                    <div class="stat-number"><?php echo $statistics['total_anmeldungen']; ?></div>
                    <div class="stat-description">Alle Anmeldungen</div>
                </div>
                <div class="stat-card">
                    <h3>Heute</h3>
                    <div class="stat-number"><?php echo $statistics['anmeldungen_heute']; ?></div>
                    <div class="stat-description">Neue Anmeldungen heute</div>
                </div>
                <div class="stat-card">
                    <h3>Diese Woche</h3>
                    <div class="stat-number"><?php echo $statistics['anmeldungen_woche']; ?></div>
                    <div class="stat-description">Anmeldungen der letzten 7 Tage</div>
                </div>
            </div>
            
            <!-- Filter and Export -->
            <div class="export-section">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <form method="get" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="page" value="wettkampf-anmeldungen">
                            
                            <select name="wettkampf_id" onchange="this.form.submit()">
                                <option value="">Alle Wettkämpfe</option>
                                <?php foreach ($wettkaempfe as $wettkampf): ?>
                                    <option value="<?php echo $wettkampf->id; ?>" <?php selected($filters['wettkampf_id'], $wettkampf->id); ?>>
                                        <?php echo SecurityManager::escape_html($wettkampf->name . ' (' . WettkampfHelpers::format_german_date($wettkampf->datum) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="text" name="search" value="<?php echo SecurityManager::escape_attr($filters['search']); ?>" placeholder="Name oder E-Mail suchen..." style="min-width: 200px;">
                            <button type="submit" class="button">Filtern</button>
                            <?php if (!empty($filters['search']) || !empty($filters['wettkampf_id'])): ?>
                                <a href="?page=wettkampf-anmeldungen" class="button">Zurücksetzen</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="export-buttons">
                        <a href="?page=wettkampf-anmeldungen&export=xlsx&wettkampf_id=<?php echo $filters['wettkampf_id']; ?>&search=<?php echo urlencode($filters['search']); ?>&_wpnonce=<?php echo wp_create_nonce('export_anmeldungen'); ?>" 
                           class="export-button xlsx" 
                           title="Excel/CSV Export - automatisch optimiert für dein Gerät">
                            📋 Export
                            <small style="display: block; font-size: 10px; opacity: 0.8; margin-top: 2px;">
                                Desktop: Excel | Mobile: CSV
                            </small>
                        </a>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-radius: 5px; border-left: 4px solid #3b82f6;">
                    <p style="margin: 0; font-size: 13px; color: #374151;">
                        <strong>📱 Export-Info:</strong> 
                        Auf Desktop-Geräten wird eine Excel-Datei (.xls) erstellt, auf mobilen Geräten eine CSV-Datei für bessere Kompatibilität. 
                        CSV-Dateien können in Excel mit "Daten → Text in Spalten" und Semikolon als Trennzeichen optimal formatiert werden.
                    </p>
                </div>
            </div>
            
            <!-- Results count -->
            <p><strong><?php echo count($anmeldungen); ?></strong> Anmeldung<?php echo count($anmeldungen) != 1 ? 'en' : ''; ?> gefunden</p>
            
            <!-- Registrations Table -->
            <table class="wp-list-table widefat fixed striped wettkampf-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Name</th>
                        <th style="width: 200px;">E-Mail</th>
                        <th style="width: 80px;">Geschlecht</th>
                        <th style="width: 80px;">Jahrgang</th>
                        <th style="width: 60px;">Kategorie</th>
                        <th style="width: 200px;">Wettkampf</th>
                        <th style="width: 100px;">Datum</th>
                        <th style="width: 120px;">Transport</th>
                        <th style="width: 80px;">Plätze</th>
                        <th style="width: 150px;">Disziplinen</th>
                        <th style="width: 120px;">Anmeldedatum</th>
                        <th style="width: 100px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($anmeldungen)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; color: #666; font-style: italic; padding: 40px;">
                                Keine Anmeldungen gefunden.
                                <?php if (!empty($filters['search']) || !empty($filters['wettkampf_id'])): ?>
                                    <br><a href="?page=wettkampf-anmeldungen">Alle Anmeldungen anzeigen</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($anmeldungen as $anmeldung): ?>
                            <?php
                            $disciplines = WettkampfDatabase::get_registration_disciplines($anmeldung->id);
                            $discipline_names = array();
                            if (is_array($disciplines) && !empty($disciplines)) {
                                foreach ($disciplines as $d) {
                                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                                        $discipline_names[] = SecurityManager::escape_html($d->name);
                                    }
                                }
                            }
                            
                            $user_category = CategoryCalculator::calculate($anmeldung->jahrgang);
                            
                            // Transport-Anzeige
                            $transport_display = $this->get_transport_display($anmeldung->eltern_fahren);
                            ?>
                            <tr>
                                <td><strong><?php echo SecurityManager::escape_html($anmeldung->vorname . ' ' . $anmeldung->name); ?></strong></td>
                                <td><?php echo SecurityManager::escape_html($anmeldung->email); ?></td>
                                <td><?php echo SecurityManager::escape_html($anmeldung->geschlecht); ?></td>
                                <td><?php echo SecurityManager::escape_html($anmeldung->jahrgang); ?></td>
                                <td>
                                    <?php echo WettkampfHelpers::get_category_badge($user_category); ?>
                                </td>
                                <td>
                                    <strong><?php echo SecurityManager::escape_html($anmeldung->wettkampf_name); ?></strong><br>
                                    <small><?php echo WettkampfHelpers::format_german_date($anmeldung->wettkampf_datum); ?> - <?php echo SecurityManager::escape_html($anmeldung->wettkampf_ort); ?></small>
                                </td>
                                <td><?php echo WettkampfHelpers::format_german_date($anmeldung->wettkampf_datum); ?></td>
                                <td>
                                    <?php echo $transport_display['badge']; ?>
                                </td>
                                <td>
                                    <?php if ($anmeldung->eltern_fahren === 'ja'): ?>
                                        <strong><?php echo $anmeldung->freie_plaetze; ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($discipline_names)): ?>
                                        <small><?php echo implode(', ', $discipline_names); ?></small>
                                    <?php else: ?>
                                        <small style="color: #999;">Keine</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo WettkampfHelpers::format_german_date($anmeldung->anmeldedatum, true); ?>
                                </td>
                                <td>
                                    <a href="?page=wettkampf-anmeldungen&edit=<?php echo $anmeldung->id; ?>" title="Bearbeiten">Bearbeiten</a> |
                                    <a href="?page=wettkampf-anmeldungen&delete=<?php echo $anmeldung->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_anmeldung'); ?>" 
                                       onclick="return confirm('Anmeldung wirklich löschen?')" title="Löschen">Löschen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="help-text">
                <h4>💡 Hinweise zur Anmeldungsverwaltung mit Kategorien:</h4>
                <ul>
                    <li><strong>Kategorien:</strong> Werden automatisch basierend auf dem Jahrgang berechnet (Alter im aktuellen Jahr)</li>
                    <li><strong>Transport:</strong> Drei Optionen - Ja (mit Plätzen), Nein (braucht Mitfahrgelegenheit), Direkt (fahren selbst)</li>
                    <li><strong>Disziplinen:</strong> Beim Bearbeiten werden nur Disziplinen der entsprechenden Kategorie angezeigt</li>
                    <li><strong>Filter:</strong> Verwende die Dropdown-Filter um spezifische Wettkämpfe oder Suchbegriffe zu finden</li>
                    <li><strong>Export:</strong> Exportiert alle gefilterten Anmeldungen als Excel/CSV-Datei (automatisch optimiert für dein Gerät)</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden auch alle Disziplin-Zuordnungen entfernt</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Funktion für Transport-Anzeige
     */
    private function get_transport_display($eltern_fahren) {
        switch ($eltern_fahren) {
            case 'ja':
                return array(
                    'badge' => WettkampfHelpers::get_status_badge('active', '✓ Ja'),
                    'text' => 'Ja, können andere mitnehmen'
                );
            case 'nein':
                return array(
                    'badge' => WettkampfHelpers::get_status_badge('inactive', '⚠ Nein'),
                    'text' => 'Nein, brauchen Mitfahrgelegenheit'
                );
            case 'direkt':
                return array(
                    'badge' => WettkampfHelpers::get_status_badge('expired', '🚗 Direkt'),
                    'text' => 'Fahren direkt zum Wettkampf'
                );
            default:
                // Fallback für alte Einträge
                if ($eltern_fahren == 1 || $eltern_fahren == '1') {
                    return array(
                        'badge' => WettkampfHelpers::get_status_badge('active', '✓ Ja'),
                        'text' => 'Ja'
                    );
                } else {
                    return array(
                        'badge' => WettkampfHelpers::get_status_badge('inactive', '✗ Nein'),
                        'text' => 'Nein (Fallback)'
                    );
                }
        }
    }
    
    /**
     * Render edit form
     */
    private function render_edit_form($edit_anmeldung) {
        ?>
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2>Anmeldung bearbeiten: <?php echo SecurityManager::escape_html($edit_anmeldung->vorname . ' ' . $edit_anmeldung->name); ?></h2>
            <form method="post" action="<?php echo admin_url('admin.php?page=wettkampf-anmeldungen'); ?>">
                <input type="hidden" name="action" value="save_anmeldung">
                <input type="hidden" name="anmeldung_id" value="<?php echo $edit_anmeldung->id; ?>">
                <input type="hidden" name="wettkampf_id" value="<?php echo $edit_anmeldung->wettkampf_id; ?>">
                <input type="hidden" name="save_anmeldung" value="1">
                <?php wp_nonce_field('save_anmeldung', 'anmeldung_nonce'); ?>
                
                <table class="form-table">
                    <?php
                    WettkampfHelpers::render_form_row(
                        'Vorname',
                        '<input type="text" id="vorname" name="vorname" value="' . SecurityManager::escape_attr($edit_anmeldung->vorname) . '" class="regular-text" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Name',
                        '<input type="text" id="name" name="name" value="' . SecurityManager::escape_attr($edit_anmeldung->name) . '" class="regular-text" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'E-Mail',
                        '<input type="email" id="email" name="email" value="' . SecurityManager::escape_attr($edit_anmeldung->email) . '" class="regular-text" required>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Geschlecht',
                        $this->render_gender_select($edit_anmeldung->geschlecht)
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Jahrgang',
                        '<input type="number" id="jahrgang" name="jahrgang" value="' . SecurityManager::escape_attr($edit_anmeldung->jahrgang) . '" min="1900" max="' . date('Y') . '" required>',
                        'Alterskategorie: <strong>' . CategoryCalculator::calculate($edit_anmeldung->jahrgang) . '</strong>'
                    );
                    
                    WettkampfHelpers::render_form_row(
                        'Transport',
                        $this->render_transport_options($edit_anmeldung)
                    );
                    ?>
                    
                    <tr id="freie_plaetze_row" style="<?php echo ($edit_anmeldung->eltern_fahren === 'ja') ? '' : 'display: none;'; ?>">
                        <th><label for="freie_plaetze">Freie Plätze</label></th>
                        <td>
                            <input type="number" id="freie_plaetze" name="freie_plaetze" 
                                   value="<?php echo SecurityManager::escape_attr($edit_anmeldung->freie_plaetze); ?>" 
                                   min="0" max="10" 
                                   <?php echo ($edit_anmeldung->eltern_fahren === 'ja') ? 'required' : ''; ?>>
                            <p class="description">Anzahl der Plätze für andere Kinder (inkl. eigene)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="disziplinen">Disziplinen</label></th>
                        <td>
                            <?php echo $this->render_disciplines_for_edit($edit_anmeldung); ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_anmeldung" class="button-primary" value="Anmeldung aktualisieren">
                    <a href="?page=wettkampf-anmeldungen" class="button">Abbrechen</a>
                </p>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleFreePlaetze() {
                var selectedValue = $('input[name="eltern_fahren"]:checked').val();
                var row = $('#freie_plaetze_row');
                var input = $('#freie_plaetze');
                
                if (selectedValue === 'ja') {
                    row.show();
                    input.attr('required', 'required');
                } else {
                    row.hide();
                    input.removeAttr('required');
                    if (selectedValue !== 'ja') {
                        input.val('0');
                    }
                }
            }
            
            $(document).on('change', 'input[name="eltern_fahren"]', function() {
                toggleFreePlaetze();
            });
            
            setTimeout(function() {
                toggleFreePlaetze();
            }, 100);
        });
        </script>
        <?php
    }
    
    /**
     * Render gender select
     */
    private function render_gender_select($selected) {
        $options = array(
            'maennlich' => 'Männlich',
            'weiblich' => 'Weiblich'
        );
        
        $html = '<select id="geschlecht" name="geschlecht" required>';
        $html .= WettkampfHelpers::array_to_options($options, $selected);
        $html .= '</select>';
        
        return $html;
    }
    
    /**
     * Render transport options
     */
    private function render_transport_options($anmeldung) {
        $current_value = $anmeldung->eltern_fahren;
        
        // Convert old numeric values if they still exist
        if ($current_value === '1' || $current_value === 1) {
            $current_value = 'ja';
        } elseif ($current_value === '0' || $current_value === 0) {
            $current_value = 'nein';
        }
        
        // Ensure we have a valid value
        if (!in_array($current_value, array('ja', 'nein', 'direkt'))) {
            $current_value = 'nein';
        }
        
        $html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
        $html .= '<label><input type="radio" name="eltern_fahren" value="ja" ' . checked($current_value, 'ja', false) . ' required> Ja, können andere Kinder mitnehmen</label>';
        $html .= '<label><input type="radio" name="eltern_fahren" value="nein" ' . checked($current_value, 'nein', false) . ' required> Nein, brauchen eine Mitfahrgelegenheit</label>';
        $html .= '<label><input type="radio" name="eltern_fahren" value="direkt" ' . checked($current_value, 'direkt', false) . ' required> Wir fahren direkt zum Wettkampf</label>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render disciplines for edit
     */
    private function render_disciplines_for_edit($anmeldung) {
        $user_category = CategoryCalculator::calculate($anmeldung->jahrgang);
        $wettkampf_disciplines = WettkampfDatabase::get_competition_disciplines($anmeldung->wettkampf_id, $user_category);
        
        // Get selected disciplines
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        $selected_disciplines = $wpdb->get_results($wpdb->prepare("
            SELECT disziplin_id 
            FROM {$tables['anmeldung_disziplinen']} 
            WHERE anmeldung_id = %d
        ", $anmeldung->id));
        $selected_ids = array_map(function($d) { return $d->disziplin_id; }, $selected_disciplines);
        
        if (empty($wettkampf_disciplines)) {
            return '<p><em>Keine Disziplinen für Kategorie ' . $user_category . ' bei diesem Wettkampf definiert.</em></p>';
        }
        
        $html = '<div style="background: #f0f6fc; padding: 10px; border-radius: 5px; margin-bottom: 10px;">';
        $html .= '<small><strong>Verfügbare Disziplinen für Kategorie ' . $user_category . ':</strong></small>';
        $html .= '</div>';
        $html .= '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        
        foreach ($wettkampf_disciplines as $discipline) {
            $checked = in_array($discipline->id, $selected_ids) ? 'checked' : '';
            $html .= '<label style="display: block; margin-bottom: 5px;">';
            $html .= '<input type="checkbox" name="disziplinen[]" value="' . $discipline->id . '" ' . $checked . '>';
            $html .= SecurityManager::escape_html($discipline->name);
            $html .= ' ' . WettkampfHelpers::get_category_badge($discipline->kategorie);
            if ($discipline->beschreibung) {
                $html .= '<small style="color: #666;"> (' . SecurityManager::escape_html($discipline->beschreibung) . ')</small>';
            }
            $html .= '</label>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get registration by ID
     */
    private function get_registration($id) {
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $id));
    }
    
    /**
     * Get competitions for filter dropdown
     */
    private function get_competitions_for_filter() {
        global $wpdb;
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        return $wpdb->get_results("SELECT id, name, datum FROM $table_wettkampf ORDER BY datum DESC");
    }
    
    /**
     * Save registration
     */
    private function save_anmeldung() {
        SecurityManager::check_admin_permissions();
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        $wettkampf_id = intval($_POST['wettkampf_id']);
        
        // Sanitize data
        $sanitization_rules = array(
            'vorname' => 'text',
            'name' => 'text',
            'email' => 'email',
            'geschlecht' => 'text',
            'jahrgang' => 'int',
            'eltern_fahren' => 'text',
            'freie_plaetze' => 'int',
            'disziplinen' => 'array'
        );
        
        $data = SecurityManager::sanitize_form_data($_POST, $sanitization_rules);
        
        // Add wettkampf_id to data
        $data['wettkampf_id'] = $wettkampf_id;
        
        // Validation
        $validation_rules = array(
            'vorname' => array('required' => true, 'min_length' => 2),
            'name' => array('required' => true, 'min_length' => 2),
            'email' => array('required' => true, 'email' => true),
            'geschlecht' => array('required' => true),
            'jahrgang' => array('required' => true, 'year' => true),
            'eltern_fahren' => array('required' => true, 'custom' => function($value) {
                return in_array($value, ['ja', 'nein', 'direkt']) ? true : 'Ungültige Transport-Option';
            })
        );
        
        $validation = SecurityManager::validate_form_data($data, $validation_rules);
        
        if (!$validation['valid']) {
            WettkampfHelpers::add_admin_notice('Validierungsfehler: ' . implode(', ', $validation['errors']), 'error');
            return;
        }
        
        // Handle freie_plaetze
        if ($data['eltern_fahren'] !== 'ja') {
            $data['freie_plaetze'] = 0;
        }
        
        $result = WettkampfDatabase::save_registration($data, $anmeldung_id);
        
        if ($result !== false) {
            WettkampfHelpers::add_admin_notice('Anmeldung erfolgreich aktualisiert!');
            wp_redirect(admin_url('admin.php?page=wettkampf-anmeldungen'));
            exit;
        } else {
            WettkampfHelpers::add_admin_notice('Fehler beim Aktualisieren der Anmeldung.', 'error');
        }
    }
    
    /**
     * Delete registration
     */
    public function delete_anmeldung($id) {
        SecurityManager::check_admin_permissions();
        return WettkampfDatabase::delete_registration($id);
    }
}