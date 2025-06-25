<?php
/**
 * Competition management admin class - ERWEITERT mit Suchfunktion
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminWettkampf {
    
    /**
     * Display overview page - ERWEITERT mit Suchfunktion und Kopierfunktion
     */
    public function display_overview_page() {
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_wettkampf')) {
            $id = intval($_GET['delete']);
            $this->delete_wettkampf_by_id($id);
            WettkampfHelpers::add_admin_notice('Wettkampf und alle Anmeldungen gelöscht!');
        }
        
        // Handle copy action
        if (isset($_GET['copy']) && wp_verify_nonce($_GET['_wpnonce'], 'copy_wettkampf')) {
            $id = intval($_GET['copy']);
            $new_id = $this->copy_wettkampf($id);
            if ($new_id) {
                WettkampfHelpers::add_admin_notice('Wettkampf erfolgreich kopiert! Du kannst ihn jetzt bearbeiten.');
                // Redirect to edit the new competition
                wp_redirect(admin_url('admin.php?page=wettkampf-new&edit=' . $new_id));
                exit;
            } else {
                WettkampfHelpers::add_admin_notice('Fehler beim Kopieren des Wettkampfs.', 'error');
            }
        }
        
        // Get filter parameters
        $current_filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        $wettkaempfe = WettkampfDatabase::get_competitions_with_counts($current_filter, $search_term);
        
        // Calculate counts for tabs (without search)
        $alle_count = count(WettkampfDatabase::get_competitions_with_counts('all'));
        $aktive_count = count(WettkampfDatabase::get_competitions_with_counts('active'));
        $vergangene_count = count(WettkampfDatabase::get_competitions_with_counts('inactive'));
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager <a href="?page=wettkampf-new" class="page-title-action">Neuer Wettkampf</a></h1>
            
            <!-- Search Form -->
            <div class="wettkampf-search-form">
                <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                    <input type="hidden" name="page" value="wettkampf-manager">
                    <input type="hidden" name="filter" value="<?php echo esc_attr($current_filter); ?>">
                    
                    <label for="search" style="margin: 0; font-weight: 600;">🔍 Suche:</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo esc_attr($search_term); ?>" 
                           placeholder="Name, Ort oder Beschreibung durchsuchen..." 
                           style="min-width: 300px; padding: 8px 12px; flex: 1; max-width: 400px;">
                    
                    <button type="submit" class="button">Suchen</button>
                    
                    <?php if (!empty($search_term)): ?>
                        <a href="?page=wettkampf-manager&filter=<?php echo esc_attr($current_filter); ?>" class="button">Zurücksetzen</a>
                    <?php endif; ?>
                    
                    <div style="flex: 1; text-align: right; font-size: 13px; color: #666;">
                        <strong>Tipp:</strong> Ctrl+F für Schnellsuche
                    </div>
                </form>
                
                <?php if (!empty($search_term)): ?>
                    <div class="search-results-info">
                        <strong>Suchergebnis für:</strong> "<?php echo esc_html($search_term); ?>" 
                        - <?php echo count($wettkaempfe); ?> Wettkampf<?php echo count($wettkaempfe) != 1 ? 'e' : ''; ?> gefunden
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Filter Tabs -->
            <ul class="subsubsub">
                <li class="all">
                    <a href="?page=wettkampf-manager&filter=all<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                       class="<?php echo $current_filter === 'all' ? 'current' : ''; ?>">
                        Alle <span class="count">(<?php echo $alle_count; ?>)</span>
                    </a> |
                </li>
                <li class="active">
                    <a href="?page=wettkampf-manager&filter=active<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                       class="<?php echo $current_filter === 'active' ? 'current' : ''; ?>">
                        Aktive <span class="count">(<?php echo $aktive_count; ?>)</span>
                    </a> |
                </li>
                <li class="inactive">
                    <a href="?page=wettkampf-manager&filter=inactive<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                       class="<?php echo $current_filter === 'inactive' ? 'current' : ''; ?>">
                        Vergangene <span class="count">(<?php echo $vergangene_count; ?>)</span>
                    </a>
                </li>
            </ul>

            <?php if (empty($wettkaempfe) && !empty($search_term)): ?>
                <div class="search-no-results">
                    <h3>🔍 Keine Wettkämpfe gefunden</h3>
                    <p>Für den Suchbegriff "<strong><?php echo esc_html($search_term); ?></strong>" wurden keine Wettkämpfe gefunden.</p>
                    <a href="?page=wettkampf-manager&filter=<?php echo esc_attr($current_filter); ?>" class="button button-primary">Alle Wettkämpfe anzeigen</a>
                    <p style="margin-top: 15px; font-size: 14px; color: #666;">
                        <strong>Suchbereiche:</strong> Name, Ort, Beschreibung<br>
                        <strong>Tipp:</strong> Versuche es mit weniger spezifischen Begriffen
                    </p>
                </div>
            <?php elseif (empty($wettkaempfe)): ?>
                <div class="search-no-results">
                    <h3>🏃‍♂️ Noch keine Wettkämpfe vorhanden</h3>
                    <p>Erstelle deinen ersten Wettkampf, um loszulegen.</p>
                    <a href="?page=wettkampf-new" class="button button-primary">Neuer Wettkampf</a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped search-results">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Name</th>
                            <th style="width: 10%;">Datum</th>
                            <th style="width: 15%;">Ort</th>
                            <th style="width: 10%;">Anmeldeschluss</th>
                            <th style="width: 8%;">Anmeldungen</th>
                            <th style="width: 8%;">Lizenziert</th>
                            <th style="width: 14%;">Disziplinen</th>
                            <th style="width: 10%;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wettkaempfe as $wettkampf): ?>
                        <tr>
                            <td>
                                <strong><?php echo SecurityManager::escape_html($wettkampf->name); ?></strong>
                                <?php if ($wettkampf->beschreibung && !empty($search_term) && stripos($wettkampf->beschreibung, $search_term) !== false): ?>
                                    <br><small style="color: #666; font-style: italic;">
                                        <?php echo WettkampfHelpers::truncate_text($wettkampf->beschreibung, 80); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo WettkampfHelpers::format_german_date($wettkampf->datum); ?></td>
                            <td><?php echo SecurityManager::escape_html($wettkampf->ort); ?></td>
                            <td><?php echo WettkampfHelpers::format_german_date($wettkampf->anmeldeschluss); ?></td>
                            <td><strong><?php echo $wettkampf->anmeldungen_count; ?></strong></td>
                            <td><?php echo $wettkampf->lizenziert ? 'Ja' : 'Nein'; ?></td>
                            <td>
                                <?php echo $this->get_competition_disciplines_display($wettkampf->id); ?>
                            </td>
                            <td>
                                <a href="?page=wettkampf-new&edit=<?php echo $wettkampf->id; ?>" title="Wettkampf bearbeiten">Bearbeiten</a> |
                                <a href="?page=wettkampf-manager&copy=<?php echo $wettkampf->id; ?>&_wpnonce=<?php echo wp_create_nonce('copy_wettkampf'); ?>" 
                                   title="Wettkampf kopieren" 
                                   onclick="return confirm('Wettkampf &quot;<?php echo SecurityManager::escape_attr($wettkampf->name); ?>&quot; kopieren?\n\nEs wird eine Kopie mit allen Disziplinen erstellt, die du dann bearbeiten kannst.')" 
                                   style="color: #0073aa;">Kopieren</a> |
                                <a href="?page=wettkampf-anmeldungen&wettkampf_id=<?php echo $wettkampf->id; ?>" title="Anmeldungen verwalten">Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?>)</a> |
                                <a href="?page=wettkampf-manager&delete=<?php echo $wettkampf->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_wettkampf'); ?>" 
                                   onclick="return confirm('⚠️ ACHTUNG: Beim Löschen werden auch ALLE Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?> Stück) gelöscht!\n\nWirklich fortfahren?')" 
                                   style="color: #d63638;" title="Wettkampf löschen">Löschen</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if (!empty($search_term) && !empty($wettkaempfe)): ?>
                <div class="search-tips">
                    <strong>💡 Suchtipps:</strong> Du kannst nach Namen, Orten oder Beschreibungen suchen. 
                    <a href="?page=wettkampf-manager&filter=<?php echo esc_attr($current_filter); ?>">Alle Wettkämpfe anzeigen</a>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-submit on Enter key
            $('#search').on('keypress', function(e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
            });
            
            // Keyboard shortcut: Ctrl/Cmd + F to focus search
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    $('#search').focus().select();
                }
                
                // Escape to clear search
                if (e.key === 'Escape' && $('#search').is(':focus')) {
                    $('#search').val('');
                }
            });
            
            // Focus search field if there's a search term
            <?php if (!empty($search_term)): ?>
            $('#search').focus().get(0).setSelectionRange(<?php echo strlen($search_term); ?>, <?php echo strlen($search_term); ?>);
            <?php endif; ?>
            
            // Highlight search terms in results
            function highlightSearchTerms() {
                var searchTerm = '<?php echo esc_js($search_term); ?>';
                if (searchTerm.length > 0) {
                    var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    
                    $('.wp-list-table tbody td').each(function() {
                        var text = $(this).html();
                        var highlightedText = text.replace(regex, '<span class="search-highlight">$1</span>');
                        if (text !== highlightedText) {
                            $(this).html(highlightedText);
                        }
                    });
                }
            }
            
            // Apply highlighting
            <?php if (!empty($search_term)): ?>
            highlightSearchTerms();
            <?php endif; ?>
        });
        </script>
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
     * Copy competition with all disciplines
     */
    public function copy_wettkampf($original_id) {
        SecurityManager::check_admin_permissions();
        
        // Get original competition
        $original = WettkampfDatabase::get_competition($original_id);
        if (!$original) {
            return false;
        }
        
        // Prepare data for new competition
        $new_data = array(
            'name' => $original->name . ' (Kopie)',
            'datum' => $original->datum,
            'ort' => $original->ort,
            'beschreibung' => $original->beschreibung,
            'startberechtigte' => $original->startberechtigte,
            'anmeldeschluss' => $original->anmeldeschluss,
            'event_link' => $original->event_link,
            'lizenziert' => $original->lizenziert
        );
        
        // Get assigned disciplines
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        $discipline_assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT disziplin_id FROM {$tables['disziplin_zuordnung']} WHERE wettkampf_id = %d",
            $original_id
        ));
        
        $discipline_ids = array();
        foreach ($discipline_assignments as $assignment) {
            $discipline_ids[] = $assignment->disziplin_id;
        }
        
        $new_data['disziplinen'] = $discipline_ids;
        
        // Save new competition
        $new_id = WettkampfDatabase::save_competition($new_data);
        
        if ($new_id) {
            WettkampfHelpers::log_error('Wettkampf kopiert: Original ID ' . $original_id . ', Neue ID ' . $new_id);
        }
        
        return $new_id;
    }
    
    /**
     * Delete competition by ID
     */
    public function delete_wettkampf_by_id($id) {
        SecurityManager::check_admin_permissions();
        return WettkampfDatabase::delete_competition($id);
    }
}