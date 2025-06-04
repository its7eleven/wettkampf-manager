<?php
/**
 * Admin functionality for Wettkampf Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfAdmin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_save_wettkampf', array($this, 'save_wettkampf'));
        add_action('admin_post_delete_wettkampf', array($this, 'delete_wettkampf'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Wettkampf Manager',
            'Wettkämpfe',
            'manage_options',
            'wettkampf-manager',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Neuer Wettkampf',
            'Neuer Wettkampf',
            'manage_options',
            'wettkampf-new',
            array($this, 'admin_new_wettkampf')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Disziplinen',
            'Disziplinen',
            'manage_options',
            'wettkampf-disziplinen',
            array($this, 'admin_disziplinen')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Anmeldungen',
            'Anmeldungen',
            'manage_options',
            'wettkampf-anmeldungen',
            array($this, 'admin_anmeldungen')
        );
        
        add_submenu_page(
            'wettkampf-manager',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'wettkampf-settings',
            array($this, 'admin_settings')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_wettkampf')) {
            $id = intval($_GET['delete']);
            $wpdb->delete($wpdb->prefix . 'wettkampf', array('id' => $id));
            echo '<div class="notice notice-success"><p>Wettkampf gelöscht!</p></div>';
        }
        
        $table_name = $wpdb->prefix . 'wettkampf';
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $wettkaempfe = $wpdb->get_results("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM $table_name w 
            LEFT JOIN $table_anmeldung a ON w.id = a.wettkampf_id 
            GROUP BY w.id 
            ORDER BY w.datum DESC
        ");
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager <a href="?page=wettkampf-new" class="page-title-action">Neuer Wettkampf</a></h1>
            
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
                        <td><?php echo esc_html($wettkampf->name); ?></td>
                        <td><?php echo date('d.m.Y', strtotime($wettkampf->datum)); ?></td>
                        <td><?php echo esc_html($wettkampf->ort); ?></td>
                        <td><?php echo date('d.m.Y', strtotime($wettkampf->anmeldeschluss)); ?></td>
                        <td><strong><?php echo $wettkampf->anmeldungen_count; ?></strong></td>
                        <td><?php echo $wettkampf->lizenziert ? 'Ja' : 'Nein'; ?></td>
                        <td>
                            <?php
                            // Disziplinen für diesen Wettkampf anzeigen
                            $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                            $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                            
                            $disziplinen = $wpdb->get_results($wpdb->prepare("
                                SELECT d.name 
                                FROM $table_zuordnung z 
                                JOIN $table_disziplinen d ON z.disziplin_id = d.id 
                                WHERE z.wettkampf_id = %d AND d.aktiv = 1
                                ORDER BY d.sortierung ASC, d.name ASC
                            ", $wettkampf->id));
                            
                            $disziplin_names = array();
                            if (is_array($disziplinen) && !empty($disziplinen)) {
                                foreach ($disziplinen as $d) {
                                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                                        $disziplin_names[] = esc_html($d->name);
                                    }
                                }
                            }
                            
                            if (!empty($disziplin_names)) {
                                echo '<small>' . implode(', ', $disziplin_names) . '</small>';
                            } else {
                                echo '<small>Keine Disziplinen</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="?page=wettkampf-new&edit=<?php echo $wettkampf->id; ?>">Bearbeiten</a> |
                            <a href="?page=wettkampf-anmeldungen&wettkampf_id=<?php echo $wettkampf->id; ?>">Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?>)</a> |
                            <a href="?page=wettkampf-manager&delete=<?php echo $wettkampf->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_wettkampf'); ?>" onclick="return confirm('Wirklich löschen?')" style="color: #d63638;">Löschen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function admin_new_wettkampf() {
        global $wpdb;
        $wettkampf = null;
        
        if (isset($_GET['edit'])) {
            $id = intval($_GET['edit']);
            $table_name = $wpdb->prefix . 'wettkampf';
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
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
                    <tr>
                        <th><label for="name">Name</label></th>
                        <td><input type="text" id="name" name="name" value="<?php echo $wettkampf ? esc_attr($wettkampf->name) : ''; ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="datum">Datum</label></th>
                        <td><input type="date" id="datum" name="datum" value="<?php echo $wettkampf ? $wettkampf->datum : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="ort">Ort</label></th>
                        <td><input type="text" id="ort" name="ort" value="<?php echo $wettkampf ? esc_attr($wettkampf->ort) : ''; ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="beschreibung">Beschreibung</label></th>
                        <td><textarea id="beschreibung" name="beschreibung" rows="5" class="large-text"><?php echo $wettkampf ? esc_textarea($wettkampf->beschreibung) : ''; ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="startberechtigte">Startberechtigte</label></th>
                        <td><textarea id="startberechtigte" name="startberechtigte" rows="3" class="large-text"><?php echo $wettkampf ? esc_textarea($wettkampf->startberechtigte) : ''; ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="anmeldeschluss">Anmeldeschluss</label></th>
                        <td><input type="date" id="anmeldeschluss" name="anmeldeschluss" value="<?php echo $wettkampf ? $wettkampf->anmeldeschluss : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="event_link">Link zum Event</label></th>
                        <td><input type="url" id="event_link" name="event_link" value="<?php echo $wettkampf ? esc_attr($wettkampf->event_link) : ''; ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="lizenziert">Lizenzierter Event</label></th>
                        <td><input type="checkbox" id="lizenziert" name="lizenziert" value="1" <?php echo ($wettkampf && $wettkampf->lizenziert) ? 'checked' : ''; ?>></td>
                    </tr>
                    <tr>
                        <th><label for="disziplinen">Disziplinen</label></th>
                        <td>
                            <?php
                            // Alle verfügbaren Disziplinen laden
                            $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                            $alle_disziplinen = $wpdb->get_results("SELECT * FROM $table_disziplinen WHERE aktiv = 1 ORDER BY sortierung ASC, name ASC");
                            
                            // Ausgewählte Disziplinen für diesen Wettkampf laden
                            $ausgewaehlte_disziplinen = array();
                            if ($wettkampf) {
                                $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                                $zuordnungen = $wpdb->get_results($wpdb->prepare("SELECT disziplin_id FROM $table_zuordnung WHERE wettkampf_id = %d", $wettkampf->id));
                                foreach ($zuordnungen as $zuordnung) {
                                    $ausgewaehlte_disziplinen[] = $zuordnung->disziplin_id;
                                }
                            }
                            
                            if (!empty($alle_disziplinen)): ?>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                    <?php foreach ($alle_disziplinen as $disziplin): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="disziplinen[]" value="<?php echo $disziplin->id; ?>" 
                                                   <?php echo in_array($disziplin->id, $ausgewaehlte_disziplinen) ? 'checked' : ''; ?>>
                                            <?php echo esc_html($disziplin->name); ?>
                                            <?php if ($disziplin->beschreibung): ?>
                                                <small style="color: #666;">(<?php echo esc_html($disziplin->beschreibung); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small>Wähle nur die Disziplinen aus, die für diesen Wettkampf relevant sind. Lasse alle unausgewählt, wenn dieser Wettkampf keine spezifischen Disziplinen hat.</small>
                            <?php else: ?>
                                <p><em>Keine Disziplinen verfügbar. <a href="?page=wettkampf-disziplinen">Disziplinen verwalten</a></em></p>
                            <?php endif; ?>
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
    
    public function admin_disziplinen() {
        global $wpdb;
        
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_disziplin')) {
            $id = intval($_GET['delete']);
            
            // Auch Zuordnungen löschen
            $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
            $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
            
            $wpdb->delete($table_zuordnung, array('disziplin_id' => $id));
            $wpdb->delete($table_anmeldung_disziplinen, array('disziplin_id' => $id));
            $wpdb->delete($wpdb->prefix . 'wettkampf_disziplinen', array('id' => $id));
            
            echo '<div class="notice notice-success"><p>Disziplin gelöscht!</p></div>';
        }
        
        // Handle save action
        if (isset($_POST['action']) && $_POST['action'] === 'save_disziplin' && wp_verify_nonce($_POST['disziplin_nonce'], 'save_disziplin')) {
            $data = array(
                'name' => sanitize_text_field($_POST['name']),
                'beschreibung' => sanitize_textarea_field($_POST['beschreibung']),
                'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
                'sortierung' => intval($_POST['sortierung'])
            );
            
            if (isset($_POST['disziplin_id']) && !empty($_POST['disziplin_id'])) {
                // Update existing
                $wpdb->update($wpdb->prefix . 'wettkampf_disziplinen', $data, array('id' => intval($_POST['disziplin_id'])));
                echo '<div class="notice notice-success"><p>Disziplin aktualisiert!</p></div>';
            } else {
                // Insert new
                $wpdb->insert($wpdb->prefix . 'wettkampf_disziplinen', $data);
                echo '<div class="notice notice-success"><p>Disziplin erstellt!</p></div>';
            }
        }
        
        $table_name = $wpdb->prefix . 'wettkampf_disziplinen';
        $disziplinen = $wpdb->get_results("SELECT * FROM $table_name ORDER BY sortierung ASC, name ASC");
        
        $edit_disziplin = null;
        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $edit_disziplin = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
        }
        
        ?>
        <div class="wrap">
            <h1>Disziplinen verwalten</h1>
            
            <!-- Formular für neue/bearbeitung Disziplin -->
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2><?php echo $edit_disziplin ? 'Disziplin bearbeiten' : 'Neue Disziplin'; ?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="save_disziplin">
                    <?php if ($edit_disziplin): ?>
                    <input type="hidden" name="disziplin_id" value="<?php echo $edit_disziplin->id; ?>">
                    <?php endif; ?>
                    <?php wp_nonce_field('save_disziplin', 'disziplin_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Name *</label></th>
                            <td><input type="text" id="name" name="name" value="<?php echo $edit_disziplin ? esc_attr($edit_disziplin->name) : ''; ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="beschreibung">Beschreibung</label></th>
                            <td><textarea id="beschreibung" name="beschreibung" rows="3" class="large-text"><?php echo $edit_disziplin ? esc_textarea($edit_disziplin->beschreibung) : ''; ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="sortierung">Sortierung</label></th>
                            <td>
                                <input type="number" id="sortierung" name="sortierung" value="<?php echo $edit_disziplin ? $edit_disziplin->sortierung : 0; ?>" min="0" max="999">
                                <p class="description">Niedrigere Zahlen werden zuerst angezeigt</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aktiv">Aktiv</label></th>
                            <td><input type="checkbox" id="aktiv" name="aktiv" value="1" <?php echo (!$edit_disziplin || $edit_disziplin->aktiv) ? 'checked' : ''; ?>></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php echo $edit_disziplin ? 'Aktualisieren' : 'Erstellen'; ?>">
                        <?php if ($edit_disziplin): ?>
                        <a href="?page=wettkampf-disziplinen" class="button">Abbrechen</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <!-- Liste aller Disziplinen -->
            <h2>Alle Disziplinen (<?php echo count($disziplinen); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Sortierung</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($disziplinen)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666; font-style: italic;">
                                Keine Disziplinen vorhanden. Erstellen Sie die erste Disziplin mit dem Formular oben.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($disziplinen as $disziplin): ?>
                        <tr>
                            <td><strong><?php echo esc_html($disziplin->name); ?></strong></td>
                            <td><?php echo esc_html($disziplin->beschreibung); ?></td>
                            <td><?php echo $disziplin->sortierung; ?></td>
                            <td>
                                <?php if ($disziplin->aktiv): ?>
                                    <span style="color: green;">✓ Aktiv</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Inaktiv</span>
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
                <h3>💡 Hinweise zur Disziplin-Verwaltung:</h3>
                <ul>
                    <li><strong>Sortierung:</strong> Verwenden Sie Zahlen wie 10, 20, 30... um später einfach neue Disziplinen einfügen zu können</li>
                    <li><strong>Aktiv/Inaktiv:</strong> Inaktive Disziplinen werden nicht bei der Wettkampf-Erstellung angezeigt</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden alle Zuordnungen zu Wettkämpfen und Anmeldungen entfernt</li>
                    <li><strong>Standard-Disziplinen:</strong> Beim ersten Aktivieren des Plugins werden automatisch Beispiel-Disziplinen erstellt</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function admin_anmeldungen() {
        global $wpdb;
        
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
        
        // Handle CSV Export
        if (isset($_GET['export']) && $_GET['export'] === 'csv' && wp_verify_nonce($_GET['_wpnonce'], 'export_anmeldungen')) {
            $this->export_anmeldungen_csv(isset($_GET['wettkampf_id']) ? $_GET['wettkampf_id'] : null);
            return;
        }
        
        // Handle XLSX Export
        if (isset($_GET['export']) && $_GET['export'] === 'xlsx' && wp_verify_nonce($_GET['_wpnonce'], 'export_anmeldungen')) {
            $this->export_anmeldungen_xlsx(isset($_GET['wettkampf_id']) ? $_GET['wettkampf_id'] : null);
            return;
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
                        <a href="?page=wettkampf-anmeldungen&export=csv&wettkampf_id=<?php echo $wettkampf_filter; ?>&_wpnonce=<?php echo wp_create_nonce('export_anmeldungen'); ?>" 
                           class="export-button csv">📊 CSV Export</a>
                        <a href="?page=wettkampf-anmeldungen&export=xlsx&wettkampf_id=<?php echo $wettkampf_filter; ?>&_wpnonce=<?php echo wp_create_nonce('export_anmeldungen'); ?>" 
                           class="export-button xlsx">📋 Excel Export</a>
                        <?php if (!empty($wettkampf_filter)): ?>
                            <a href="?page=wettkampf-anmeldungen&export=csv&wettkampf_id=<?php echo $wettkampf_filter; ?>&_wpnonce=<?php echo wp_create_nonce('export_anmeldungen'); ?>" 
                               class="export-button">📋 Teilnehmerliste</a>
                        <?php endif; ?>
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
                    <li><strong>CSV Export:</strong> Exportiert alle gefilterten Anmeldungen als CSV-Datei für Excel</li>
                    <li><strong>Teilnehmerliste:</strong> Generiert eine druckfreundliche Liste für einen spezifischen Wettkampf</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden auch alle Disziplin-Zuordnungen entfernt</li>
                    <li><strong>Statistiken:</strong> Die Übersicht zeigt aktuelle Anmeldezahlen</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // XLSX Export function
    private function export_anmeldungen_xlsx($wettkampf_id = null) {
        global $wpdb;
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Build query
        $where_clause = '';
        $params = array();
        
        if (!empty($wettkampf_id)) {
            $where_clause = ' WHERE a.wettkampf_id = %d';
            $params[] = $wettkampf_id;
        }
        
        // Get data - use proper prepare statement
        if (empty($params)) {
            $anmeldungen = $wpdb->get_results("
                SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
                FROM $table_anmeldung a 
                JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
                ORDER BY w.datum ASC, a.anmeldedatum ASC
            ");
        } else {
            $anmeldungen = $wpdb->get_results($wpdb->prepare("
                SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
                FROM $table_anmeldung a 
                JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
                $where_clause
                ORDER BY w.datum ASC, a.anmeldedatum ASC
            ", $params));
        }
        
        // Set headers for Excel download
        $filename = 'anmeldungen_' . date('Y-m-d') . '.xls';
        if (!empty($wettkampf_id)) {
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_wettkampf WHERE id = %d", $wettkampf_id));
            if ($wettkampf) {
                $filename = sanitize_file_name(strtolower(str_replace(' ', '_', $wettkampf->name))) . '_anmeldungen_' . date('Y-m-d') . '.xls';
            }
        }
        
        // Set headers for Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Start output
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '</head>';
        echo '<body>';
        echo '<table border="1">';
        
        // Headers
        echo '<tr style="background-color: #f2f2f2; font-weight: bold;">';
        echo '<td>Vorname</td>';
        echo '<td>Name</td>';
        echo '<td>E-Mail</td>';
        echo '<td>Geschlecht</td>';
        echo '<td>Jahrgang</td>';
        echo '<td>Wettkampf</td>';
        echo '<td>Wettkampf Datum</td>';
        echo '<td>Wettkampf Ort</td>';
        echo '<td>Eltern fahren</td>';
        echo '<td>Freie Plätze</td>';
        echo '<td>Disziplinen</td>';
        echo '<td>Anmeldedatum</td>';
        echo '</tr>';
        
        // Data rows
        foreach ($anmeldungen as $anmeldung) {
            // Load disciplines
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
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
        
        exit;
    }

    private function export_anmeldungen_csv($wettkampf_id = null) {
        global $wpdb;
        
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        // Build query
        $where_clause = '';
        $params = array();
        
        if (!empty($wettkampf_id)) {
            $where_clause = ' WHERE a.wettkampf_id = %d';
            $params[] = $wettkampf_id;
        }
        
        $anmeldungen = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM $table_anmeldung a 
            JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
            $where_clause
            ORDER BY w.datum ASC, a.anmeldedatum ASC
        ", $params));
        
        // Set headers for CSV download
        $filename = 'anmeldungen_' . date('Y-m-d') . '.csv';
        if (!empty($wettkampf_id)) {
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_wettkampf WHERE id = %d", $wettkampf_id));
            if ($wettkampf) {
                $filename = sanitize_file_name(strtolower(str_replace(' ', '_', $wettkampf->name))) . '_anmeldungen_' . date('Y-m-d') . '.csv';
            }
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Create CSV output
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, array(
            'Vorname',
            'Name',
            'E-Mail',
            'Geschlecht',
            'Jahrgang',
            'Wettkampf',
            'Wettkampf Datum',
            'Wettkampf Ort',
            'Eltern fahren',
            'Freie Plätze',
            'Disziplinen',
            'Anmeldedatum'
        ), ';');
        
        // CSV Data
        foreach ($anmeldungen as $anmeldung) {
            // Load disciplines
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
            
            fputcsv($output, array(
                $anmeldung->vorname,
                $anmeldung->name,
                $anmeldung->email,
                $anmeldung->geschlecht,
                $anmeldung->jahrgang,
                $anmeldung->wettkampf_name,
                date('d.m.Y', strtotime($anmeldung->wettkampf_datum)),
                $anmeldung->wettkampf_ort,
                $anmeldung->eltern_fahren ? 'Ja' : 'Nein',
                $anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '',
                !empty($disziplin_names) ? implode(', ', $disziplin_names) : '',
                date('d.m.Y H:i:s', strtotime($anmeldung->anmeldedatum))
            ), ';');
        }
        
        fclose($output);
        exit;
    }
    
    public function admin_settings() {
        if (isset($_POST['submit'])) {
            update_option('wettkampf_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site_key']));
            update_option('wettkampf_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret_key']));
            update_option('wettkampf_sender_email', sanitize_email($_POST['sender_email']));
            update_option('wettkampf_sender_name', sanitize_text_field($_POST['sender_name']));
            
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }
        
        $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key', '');
        $recaptcha_secret_key = get_option('wettkampf_recaptcha_secret_key', '');
        $sender_email = get_option('wettkampf_sender_email', get_option('admin_email'));
        $sender_name = get_option('wettkampf_sender_name', get_option('blogname'));
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Einstellungen</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="recaptcha_site_key">reCAPTCHA Site Key</label></th>
                        <td>
                            <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="recaptcha_secret_key">reCAPTCHA Secret Key</label></th>
                        <td>
                            <input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sender_email">Absender E-Mail</label></th>
                        <td>
                            <input type="email" id="sender_email" name="sender_email" value="<?php echo esc_attr($sender_email); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sender_name">Absender Name</label></th>
                        <td>
                            <input type="text" id="sender_name" name="sender_name" value="<?php echo esc_attr($sender_name); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Einstellungen speichern">
                </p>
            </form>
        </div>
        <?php
    }
    
    public function save_wettkampf() {
        if (!wp_verify_nonce($_POST['wettkampf_nonce'], 'save_wettkampf')) {
            wp_die('Sicherheitsfehler');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf';
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'datum' => sanitize_text_field($_POST['datum']),
            'ort' => sanitize_text_field($_POST['ort']),
            'beschreibung' => sanitize_textarea_field($_POST['beschreibung']),
            'startberechtigte' => sanitize_textarea_field($_POST['startberechtigte']),
            'anmeldeschluss' => sanitize_text_field($_POST['anmeldeschluss']),
            'event_link' => esc_url_raw($_POST['event_link']),
            'lizenziert' => isset($_POST['lizenziert']) ? 1 : 0
        );
        
        if (isset($_POST['wettkampf_id']) && !empty($_POST['wettkampf_id'])) {
            // Update existing
            $wettkampf_id = intval($_POST['wettkampf_id']);
            $wpdb->update($table_name, $data, array('id' => $wettkampf_id));
        } else {
            // Insert new
            $wpdb->insert($table_name, $data);
            $wettkampf_id = $wpdb->insert_id;
        }
        
        // Disziplinen-Zuordnungen aktualisieren
        $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
        
        // Alte Zuordnungen löschen
        $wpdb->delete($table_zuordnung, array('wettkampf_id' => $wettkampf_id));
        
        // Neue Zuordnungen speichern
        if (isset($_POST['disziplinen']) && is_array($_POST['disziplinen'])) {
            foreach ($_POST['disziplinen'] as $disziplin_id) {
                $wpdb->insert($table_zuordnung, array(
                    'wettkampf_id' => $wettkampf_id,
                    'disziplin_id' => intval($disziplin_id)
                ));
            }
        }
        
        wp_redirect(admin_url('admin.php?page=wettkampf-manager'));
        exit;
    }
}