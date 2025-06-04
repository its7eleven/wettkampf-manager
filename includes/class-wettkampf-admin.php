public function admin_anmeldungen() {
        global $wpdb;
        
        // Handle Excel Export - MUST BE FIRST BEFORE ANY OUTPUT!
        if (isset($_GET['export']) && $_GET['export'] === 'xlsx' && wp_verify_nonce($_GET['_wpnonce'], 'export_anmeldungen')) {
            // Clean any output that might have been generated
            if (ob_get_length()) {
                ob_clean();
            }
            
            $this->export_anmeldungen_xlsx();
            exit; // Important: exit immediately after export
        }
        
        // Rest of admin_anmeldungen method stays the same...
        // [All the existing code for delete, edit, display etc. remains unchanged]
        
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_anmeldung')) {
            $id = intval($_GET['delete']);
            
            // Erst Disziplin-Zuordnungen löschen
            $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
            $wpdb->delete($table_anmeldung_disziplinen, array('anmeldung_id' => $id));
            
            // Dann Anmeldung löschen
            $wpdb->delete($wpdb->prefix . 'wettkampf_anmeldung', array('id' => $id));
            echo '<div class="notice notice-success"><p>Anmeldung gelöscht!</p></div>';
        }
        
        // Handle edit action
        if (isset($_POST['save_anmeldung']) && wp_verify_nonce($_POST['anmeldung_nonce'], 'save_anmeldung')) {
            $anmeldung_id = intval($_POST['anmeldung_id']);
            
            $data = array(
                'vorname' => sanitize_text_field($_POST['vorname']),
                'name' => sanitize_text_field($_POST['name']),
                'email' => sanitize_email($_POST['email']),
                'geschlecht' => sanitize_text_field($_POST['geschlecht']),
                'jahrgang' => intval($_POST['jahrgang']),
                'eltern_fahren' => intval($_POST['eltern_fahren']),
                'freie_plaetze' => (intval($_POST['eltern_fahren']) === 1) ? intval($_POST['freie_plaetze']) : 0
            );
            
            $result = $wpdb->update($wpdb->prefix . 'wettkampf_anmeldung', $data, array('id' => $anmeldung_id));
            
            if ($result !== false) {
                // Disziplin-Zuordnungen aktualisieren
                $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
                // Alte Zuordnungen löschen
                $wpdb->delete($table_anmeldung_disziplinen, array('anmeldung_id' => $anmeldung_id));
                // Neue Zuordnungen speichern
                if (isset($_POST['disziplinen']) && is_array($_POST['disziplinen'])) {
                    foreach ($_POST['disziplinen'] as $disziplin_id) {
                        $wpdb->insert($table_anmeldung_disziplinen, array(
                            'anmeldung_id' => $anmeldung_id,
                            'disziplin_id' => intval($disziplin_id)
                        ));
                    }
                }
                
                echo '<div class="notice notice-success"><p>Anmeldung erfolgreich aktualisiert!</p></div>';
                // Redirect to clean URL
                wp_redirect(admin_url('admin.php?page=wettkampf-anmeldungen'));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren der Anmeldung.</p></div>';
            }
        }
        
        // Check if we're in edit mode
        $edit_anmeldung = null;
        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
            $edit_anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $edit_id));
        }
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Get filter parameters
        $wettkampf_filter = isset($_GET['wettkampf_id']) ? intval($_GET['wettkampf_id']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_params = array();
        
        if (!empty($wettkampf_filter)) {
            $where_conditions[] = 'a.wettkampf_id = %d';
            $where_params[] = $wettkampf_filter;
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(a.vorname LIKE %s OR a.name LIKE %s OR a.email LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get anmeldungen with wettkampf info
        $anmeldungen = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM $table_anmeldung a 
            JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
            WHERE $where_clause
            ORDER BY w.datum DESC, a.anmeldedatum DESC
        ", $where_params));
        
        // Get all wettkämpfe for filter dropdown
        $wettkaempfe = $wpdb->get_results("SELECT id, name, datum FROM $table_wettkampf ORDER BY datum DESC");
        
        // Get statistics
        $total_anmeldungen = $wpdb->get_var("SELECT COUNT(*) FROM $table_anmeldung");
        $anmeldungen_heute = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_anmeldung WHERE DATE(anmeldedatum) = %s", date('Y-m-d')));
        $anmeldungen_woche = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_anmeldung WHERE anmeldedatum >= %s", date('Y-m-d', strtotime('-7 days'))));
        
        ?>
        <div class="wrap">
            <h1>Anmeldungen verwalten</h1>
            
            <?php if ($edit_anmeldung): ?>
                <!-- Edit Form -->
                <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2>Anmeldung bearbeiten: <?php echo esc_html($edit_anmeldung->vorname . ' ' . $edit_anmeldung->name); ?></h2>
                    <form method="post">
                        <input type="hidden" name="anmeldung_id" value="<?php echo $edit_anmeldung->id; ?>">
                        <?php wp_nonce_field('save_anmeldung', 'anmeldung_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="vorname">Vorname</label></th>
                                <td><input type="text" id="vorname" name="vorname" value="<?php echo esc_attr($edit_anmeldung->vorname); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="name">Name</label></th>
                                <td><input type="text" id="name" name="name" value="<?php echo esc_attr($edit_anmeldung->name); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="email">E-Mail</label></th>
                                <td><input type="email" id="email" name="email" value="<?php echo esc_attr($edit_anmeldung->email); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="geschlecht">Geschlecht</label></th>
                                <td>
                                    <select id="geschlecht" name="geschlecht" required>
                                        <option value="männlich" <?php selected($edit_anmeldung->geschlecht, 'männlich'); ?>>Männlich</option>
                                        <option value="weiblich" <?php selected($edit_anmeldung->geschlecht, 'weiblich'); ?>>Weiblich</option>
                                        <option value="divers" <?php selected($edit_anmeldung->geschlecht, 'divers'); ?>>Divers</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="jahrgang">Jahrgang</label></th>
                                <td><input type="number" id="jahrgang" name="jahrgang" value="<?php echo esc_attr($edit_anmeldung->jahrgang); ?>" min="1900" max="<?php echo date('Y'); ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="eltern_fahren">Eltern fahren</label></th>
                                <td>
                                    <label><input type="radio" name="eltern_fahren" value="1" <?php checked($edit_anmeldung->eltern_fahren, 1); ?> onchange="toggleFreePlaetze(this)"> Ja</label><br>
                                    <label><input type="radio" name="eltern_fahren" value="0" <?php checked($edit_anmeldung->eltern_fahren, 0); ?> onchange="toggleFreePlaetze(this)"> Nein</label>
                                </td>
                            </tr>
                            <tr id="freie_plaetze_row" style="<?php echo $edit_anmeldung->eltern_fahren ? '' : 'display: none;'; ?>">
                                <th><label for="freie_plaetze">Freie Plätze</label></th>
                                <td><input type="number" id="freie_plaetze" name="freie_plaetze" value="<?php echo esc_attr($edit_anmeldung->freie_plaetze); ?>" min="0" max="10"></td>
                            </tr>
                            <tr>
                                <th><label for="disziplinen">Disziplinen</label></th>
                                <td>
                                    <?php
                                    // Disziplinen für diesen Wettkampf laden
                                    $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                                    $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                                    
                                    $wettkampf_disziplinen = $wpdb->get_results($wpdb->prepare("
                                        SELECT d.* 
                                        FROM $table_zuordnung z 
                                        JOIN $table_disziplinen d ON z.disziplin_id = d.id 
                                        WHERE z.wettkampf_id = %d AND d.aktiv = 1
                                        ORDER BY d.sortierung ASC, d.name ASC
                                    ", $edit_anmeldung->wettkampf_id));
                                    
                                    // Bereits ausgewählte Disziplinen laden
                                    $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
                                    $selected_disziplinen = $wpdb->get_results($wpdb->prepare("
                                        SELECT disziplin_id 
                                        FROM $table_anmeldung_disziplinen 
                                        WHERE anmeldung_id = %d
                                    ", $edit_anmeldung->id));
                                    $selected_ids = array_map(function($d) { return $d->disziplin_id; }, $selected_disziplinen);
                                    
                                    if (!empty($wettkampf_disziplinen)): ?>
                                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                            <?php foreach ($wettkampf_disziplinen as $disziplin): ?>
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="checkbox" name="disziplinen[]" value="<?php echo $disziplin->id; ?>" 
                                                           <?php echo in_array($disziplin->id, $selected_ids) ? 'checked' : ''; ?>>
                                                    <?php echo esc_html($disziplin->name); ?>
                                                    <?php if ($disziplin->beschreibung): ?>
                                                        <small style="color: #666;">(<?php echo esc_html($disziplin->beschreibung); ?>)</small>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p><em>Keine Disziplinen für diesen Wettkampf definiert.</em></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="save_anmeldung" class="button-primary" value="Anmeldung aktualisieren">
                            <a href="?page=wettkampf-anmeldungen" class="button">Abbrechen</a>
                        </p>
                    </form>
                </div>
                
                <script>
                function toggleFreePlaetze(radio) {
                    var row = document.getElementById('freie_plaetze_row');
                    if (radio.value == '1') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                        document.getElementById('freie_plaetze').value = '';
                    }
                }
                </script>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="wettkampf-stats">
                <div class="stat-card">
                    <h3>Gesamt</h3>
                    <div class="stat-number"><?php echo $total_anmeldungen; ?></div>
                    <div class="stat-description">Alle Anmeldungen</div>
                </div>
                <div class="stat-card">
                    <h3>Heute</h3>
                    <div class="stat-number"><?php echo $anmeldungen_heute; ?></div>
                    <div class="stat-description">Neue Anmeldungen heute</div>
                </div>
                <div class="stat-card">
                    <h3>Diese Woche</h3>
                    <div class="stat-number"><?php echo $anmeldungen_woche; ?></div>
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
                                    <option value="<?php echo $wettkampf->id; ?>" <?php selected($wettkampf_filter, $wettkampf->id); ?>>
                                        <?php echo esc_html($wettkampf->name . ' (' . date('d.m.Y', strtotime($wettkampf->datum)) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Name oder E-Mail suchen..." style="min-width: 200px;">
                            <button type="submit" class="button">Filtern</button>
                            <?php if (!empty($search) || !empty($wettkampf_filter)): ?>
                                <a href="?page=wettkampf-anmeldungen" class="button">Zurücksetzen</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="export-buttons">
                        <a href="?page=wettkampf-anmeldungen&export=xlsx&wettkampf_id=<?php echo $wettkampf_filter; ?>&search=<?php echo urlencode($search); ?>&_wpnonce=<?php echo wp_create_nonce('export_anmeldungen'); ?>" 
                           class="export-button xlsx">📋 Excel Export</a>
                    </div>
                </div>
            </div>
            
            <!-- Results count -->
            <p><strong><?php echo count($anmeldungen); ?></strong> Anmeldung<?php echo count($anmeldungen) != 1 ? 'en' : ''; ?> gefunden</p>
            
            <!-- Anmeldungen Table -->
            <table class="wp-list-table widefat fixed striped wettkampf-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Name</th>
                        <th style="width: 200px;">E-Mail</th>
                        <th style="width: 80px;">Geschlecht</th>
                        <th style="width: 80px;">Jahrgang</th>
                        <th style="width: 200px;">Wettkampf</th>
                        <th style="width: 100px;">Datum</th>
                        <th style="width: 80px;">Eltern</th>
                        <th style="width: 80px;">Plätze</th>
                        <th style="width: 150px;">Disziplinen</th>
                        <th style="width: 120px;">Anmeldedatum</th>
                        <th style="width: 100px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($anmeldungen)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; color: #666; font-style: italic; padding: 40px;">
                                Keine Anmeldungen gefunden.
                                <?php if (!empty($search) || !empty($wettkampf_filter)): ?>
                                    <br><a href="?page=wettkampf-anmeldungen">Alle Anmeldungen anzeigen</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($anmeldungen as $anmeldung): ?>
                            <?php
                            // Load disciplines for this registration
                            $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
                                SELECT d.name 
                                FROM $table_anmeldung_disziplinen ad 
                                JOIN $table_disziplinen d ON ad.disziplin_id = d.id 
                                WHERE ad.anmeldung_id = %d 
                                ORDER BY d.sortierung ASC, d.name ASC
                            ", $anmeldung->id));
                            
                            $disziplin_names = array();
                            if (is_array($anmeldung_disziplinen) && !empty($anmeldung_disziplinen)) {
                                foreach ($anmeldung_disziplinen as $d) {
                                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                                        $disziplin_names[] = esc_html($d->name);
                                    }
                                }
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($anmeldung->vorname . ' ' . $anmeldung->name); ?></strong></td>
                                <td><?php echo esc_html($anmeldung->email); ?></td>
                                <td><?php echo esc_html($anmeldung->geschlecht); ?></td>
                                <td><?php echo esc_html($anmeldung->jahrgang); ?></td>
                                <td>
                                    <strong><?php echo esc_html($anmeldung->wettkampf_name); ?></strong><br>
                                    <small><?php echo date('d.m.Y', strtotime($anmeldung->wettkampf_datum)); ?> - <?php echo esc_html($anmeldung->wettkampf_ort); ?></small>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($anmeldung->wettkampf_datum)); ?></td>
                                <td>
                                    <?php if ($anmeldung->eltern_fahren): ?>
                                        <span style="color: green;">✓ Ja</span>
                                    <?php else: ?>
                                        <span style="color: red;">✗ Nein</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($anmeldung->eltern_fahren): ?>
                                        <strong><?php echo $anmeldung->freie_plaetze; ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($disziplin_names)): ?>
                                        <small><?php echo implode(', ', $disziplin_names); ?></small>
                                    <?php else: ?>
                                        <small style="color: #999;">Keine</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($anmeldung->anmeldedatum)); ?>
                                </td>
                                <td>
                                    <a href="?page=wettkampf-anmeldungen&edit=<?php echo $anmeldung->id; ?>" 
                                       style="color: #2271b1;" title="Bearbeiten">✏️</a> |
                                    <a href="?page=wettkampf-anmeldungen&delete=<?php echo $anmeldung->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_anmeldung'); ?>" 
                                       onclick="return confirm('Anmeldung wirklich löschen?')" 
                                       style="color: #d63638;" title="Löschen">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="help-text">
                <h4>💡 Hinweise zur Anmeldungsverwaltung:</h4>
                <ul>
                    <li><strong>Filter:</strong> Verwenden Sie die Dropdown-Filter um spezifische Wettkämpfe oder Suchbegriffe zu finden</li>
                    <li><strong>Excel Export:</strong> Exportiert alle gefilterten Anmeldungen als Excel-Datei</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden auch alle Disziplin-Zuordnungen entfernt</li>
                    <li><strong>Statistiken:</strong> Die Übersicht zeigt aktuelle Anmeldezahlen</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // COMPLETELY CLEAN Excel Export - NO WordPress admin content!
    private function export_anmeldungen_xlsx() {
        global $wpdb;
        
        // Clear any existing output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Get filter parameters
        $wettkampf_filter = isset($_GET['wettkampf_id']) ? intval($_GET['wettkampf_id']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_params = array();
        
        if (!empty($wettkampf_filter)) {
            $where_conditions[] = 'a.wettkampf_id = %d';
            $where_params[] = $wettkampf_filter;
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(a.vorname LIKE %s OR a.name LIKE %s OR a.email LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get anmeldungen with wettkampf info
        $anmeldungen = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM $table_anmeldung a 
            JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
            WHERE $where_clause
            ORDER BY w.datum DESC, a.anmeldedatum DESC
        ", $where_params));
        
        // Generate filename
        $filename = 'wettkampf_anmeldungen_' . date('Y-m-d_H-i') . '.xls';
        if (!empty($wettkampf_filter)) {
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_wettkampf WHERE id = %d", $wettkampf_filter));
            if ($wettkampf) {
                $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $wettkampf->name);
                $filename = $safe_name . '_anmeldungen_' . date('Y-m-d_H-i') . '.xls';
            }
        }
        
        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        // Start fresh output
        ob_start();
        
        // Output BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        // Start clean HTML table (Excel can read HTML tables perfectly)
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Wettkampf Anmeldungen</title>';
        echo '</head>';
        echo '<body>';
        echo '<table border="1" style="border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<th>Vorname</th>';
        echo '<th>Name</th>';
        echo '<th>E-Mail</th>';
        echo '<th>Geschlecht</th>';
        echo '<th>Jahrgang</th>';
        echo '<th>Wettkampf</th>';
        echo '<th>Wettkampf Datum</th>';
        echo '<th>Wettkampf Ort</th>';
        echo '<th>Eltern fahren</th>';
        echo '<th>Freie Plätze</th>';
        echo '<th>Disziplinen</th>';
        echo '<th>Anmeldedatum</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Data rows
        foreach ($anmeldungen as $anmeldung) {
            // Load disciplines for this registration
            $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
                SELECT d.name 
                FROM $table_anmeldung_disziplinen ad 
                JOIN $table_disziplinen d ON ad.disziplin_id = d.id 
                WHERE ad.anmeldung_id = %d 
                ORDER BY d.sortierung ASC, d.name ASC
            ", $anmeldung->id));
            
            $disziplin_names = array();
            if (is_array($anmeldung_disziplinen) && !empty($anmeldung_disziplinen)) {
                foreach ($anmeldung_disziplinen as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $disziplin_names[] = $d->name;
                    }
                }
            }
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($anmeldung->vorname, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->name, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->email, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->geschlecht, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->jahrgang, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->wettkampf_name, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . date('d.m.Y', strtotime($anmeldung->wettkampf_datum)) . '</td>';
            echo '<td>' . htmlspecialchars($anmeldung->wettkampf_ort, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . ($anmeldung->eltern_fahren ? 'Ja' : 'Nein') . '</td>';
            echo '<td>' . ($anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '') . '</td>';
            echo '<td>' . htmlspecialchars(!empty($disziplin_names) ? implode(', ', $disziplin_names) : '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . date('d.m.Y H:i:s', strtotime($anmeldung->anmeldedatum)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
        
        // Flush and exit
        ob_end_flush();
        exit;
    }