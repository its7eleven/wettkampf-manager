<?php
/**
 * Admin functionality for Wettkampf Manager with Categories
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
        
        // Enqueue admin scripts for anmeldungen page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'wettkampf') !== false) {
            // Load admin-specific CSS
            wp_enqueue_style('wettkampf-admin', WETTKAMPF_PLUGIN_URL . 'assets/admin.css', array(), WETTKAMPF_VERSION);
            
            // Load admin-specific JS for anmeldungen page
            if (isset($_GET['page']) && $_GET['page'] === 'wettkampf-anmeldungen') {
                wp_enqueue_script('wettkampf-admin-js', WETTKAMPF_PLUGIN_URL . 'assets/admin.js', array('jquery'), WETTKAMPF_VERSION, true);
                wp_localize_script('wettkampf-admin-js', 'wettkampf_admin_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wettkampf_admin_ajax')
                ));
            }
        }
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
        
        add_submenu_page(
            'wettkampf-manager',
            'Auto-Export Status',
            'Auto-Export Status',
            'manage_options',
            'wettkampf-export-status',
            array($this, 'admin_export_status')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Handle delete action
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_wettkampf')) {
            $id = intval($_GET['delete']);
            $this->delete_wettkampf_cascade($id);
            echo '<div class="notice notice-success"><p>Wettkampf und alle Anmeldungen gelöscht!</p></div>';
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
        
        // Get current filter
        $current_filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';

        // Calculate counts
        $alle_count = count($wettkaempfe);
        $aktive_count = count(array_filter($wettkaempfe, function($w) { return strtotime($w->datum) >= strtotime('today'); }));
        $vergangene_count = count(array_filter($wettkaempfe, function($w) { return strtotime($w->datum) < strtotime('today'); }));

        // Filter wettkämpfe based on selection
        if ($current_filter === 'active') {
            $wettkaempfe = array_filter($wettkaempfe, function($w) { return strtotime($w->datum) >= strtotime('today'); });
        } elseif ($current_filter === 'inactive') {
            $wettkaempfe = array_filter($wettkaempfe, function($w) { return strtotime($w->datum) < strtotime('today'); });
        }
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager <a href="?page=wettkampf-new" class="page-title-action">Neuer Wettkampf</a></h1>
            
            <!-- WordPress-style Filter Tabs -->
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
                                SELECT d.name, d.kategorie 
                                FROM $table_zuordnung z 
                                JOIN $table_disziplinen d ON z.disziplin_id = d.id 
                                WHERE z.wettkampf_id = %d AND d.aktiv = 1
                                ORDER BY d.sortierung ASC, d.name ASC
                            ", $wettkampf->id));
                            
                            $disziplin_info = array();
                            if (is_array($disziplinen) && !empty($disziplinen)) {
                                foreach ($disziplinen as $d) {
                                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                                        $info = esc_html($d->name);
                                        if (!empty($d->kategorie) && $d->kategorie !== 'Alle') {
                                            $info .= ' <span style="font-size: 10px; background: #e5e7eb; padding: 1px 4px; border-radius: 3px;">' . esc_html($d->kategorie) . '</span>';
                                        }
                                        $disziplin_info[] = $info;
                                    }
                                }
                            }
                            
                            if (!empty($disziplin_info)) {
                                echo '<small>' . implode(', ', $disziplin_info) . '</small>';
                            } else {
                                echo '<small>Keine Disziplinen</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="?page=wettkampf-new&edit=<?php echo $wettkampf->id; ?>">Bearbeiten</a> |
                            <a href="?page=wettkampf-anmeldungen&wettkampf_id=<?php echo $wettkampf->id; ?>">Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?>)</a> |
                            <a href="?page=wettkampf-manager&delete=<?php echo $wettkampf->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_wettkampf'); ?>" onclick="return confirm('⚠️ ACHTUNG: Beim Löschen werden auch ALLE Anmeldungen (<?php echo $wettkampf->anmeldungen_count; ?> Stück) gelöscht!\n\nWirklich fortfahren?')" style="color: #d63638;">Löschen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function admin_export_status() {
        global $wpdb;
        
        // Handle manual test
        if (isset($_POST['test_export']) && wp_verify_nonce($_POST['test_nonce'], 'test_export')) {
            $wettkampf_id = intval($_POST['wettkampf_id']);
            
            // Get competition
            $table_wettkampf = $wpdb->prefix . 'wettkampf';
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_wettkampf WHERE id = %d", $wettkampf_id));
            
            if ($wettkampf) {
                // Create WettkampfManager instance to access private method
                $manager = new WettkampfManager();
                $manager->send_automatic_export($wettkampf);
                echo '<div class="notice notice-success"><p>Test-Export für "' . esc_html($wettkampf->name) . '" wurde versendet!</p></div>';
            }
        }
        
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        // Get competitions with their export status
        $competitions = $wpdb->get_results("
            SELECT w.*, COUNT(a.id) as anmeldungen_count,
                   CASE WHEN o.option_value IS NOT NULL THEN o.option_value ELSE NULL END as export_sent_date
            FROM $table_wettkampf w 
            LEFT JOIN $table_anmeldung a ON w.id = a.wettkampf_id 
            LEFT JOIN {$wpdb->prefix}options o ON o.option_name = CONCAT('wettkampf_export_sent_', w.id)
            WHERE w.anmeldeschluss <= CURDATE()
            GROUP BY w.id 
            ORDER BY w.datum DESC
        ");
        
        $export_email = get_option('wettkampf_export_email', '');
        
        ?>
        <div class="wrap">
            <h1>Auto-Export Status</h1>
            
            <?php if (empty($export_email)): ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ Warnung:</strong> Keine E-Mail-Adresse für automatische Exports konfiguriert. 
                    <a href="?page=wettkampf-settings">Jetzt konfigurieren</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ Auto-Export aktiviert</strong> für: <?php echo esc_html($export_email); ?></p>
                </div>
            <?php endif; ?>
            
            <h2>Wettkämpfe mit abgelaufener Anmeldefrist</h2>
            
            <?php if (empty($competitions)): ?>
                <p>Keine Wettkämpfe mit abgelaufener Anmeldefrist gefunden.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Wettkampf</th>
                            <th>Datum</th>
                            <th>Anmeldeschluss</th>
                            <th>Anmeldungen</th>
                            <th>Export Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitions as $comp): ?>
                        <tr>
                            <td><strong><?php echo esc_html($comp->name); ?></strong></td>
                            <td><?php echo date('d.m.Y', strtotime($comp->datum)); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($comp->anmeldeschluss)); ?></td>
                            <td><?php echo $comp->anmeldungen_count; ?></td>
                            <td>
                                <?php if ($comp->export_sent_date): ?>
                                    <span style="color: #46b450;">✅ Gesendet</span><br>
                                    <small><?php echo date('d.m.Y H:i', strtotime($comp->export_sent_date)); ?></small>
                                <?php elseif ($comp->anmeldungen_count > 0): ?>
                                    <span style="color: #dc3232;">⏳ Ausstehend</span>
                                <?php else: ?>
                                    <span style="color: #666;">➖ Keine Anmeldungen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($comp->anmeldungen_count > 0 && !empty($export_email)): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="wettkampf_id" value="<?php echo $comp->id; ?>">
                                        <?php wp_nonce_field('test_export', 'test_nonce'); ?>
                                        <button type="submit" name="test_export" class="button button-small" 
                                                onclick="return confirm('Test-Export für &quot;<?php echo esc_js($comp->name); ?>&quot; senden?')">
                                            📧 Test-Export
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="?page=wettkampf-anmeldungen&wettkampf_id=<?php echo $comp->id; ?>" class="button button-small">
                                    📋 Anmeldungen
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                <h3>ℹ️ Informationen zum Auto-Export</h3>
                <ul>
                    <li><strong>Zeitpunkt:</strong> 2 Stunden nach Mitternacht des Anmeldeschlusstages (02:00 Uhr)</li>
                    <li><strong>Bedingung:</strong> Nur Wettkämpfe mit mindestens einer Anmeldung</li>
                    <li><strong>Häufigkeit:</strong> Pro Wettkampf wird nur einmal ein Export versendet</li>
                    <li><strong>Test:</strong> Mit "Test-Export" kannst du manuell einen Export senden</li>
                    <li><strong>Format:</strong> CSV-Dateien für beste Kompatibilität mit allen E-Mail-Clients</li>
                </ul>
                
                <?php
                $next_cron = wp_next_scheduled('wettkampf_check_expired_registrations');
                if ($next_cron):
                ?>
                <p><strong>Nächste automatische Prüfung:</strong> 
                   <?php echo date('d.m.Y H:i:s', $next_cron + (get_option('gmt_offset') * HOUR_IN_SECONDS)); ?> Uhr</p>
                <?php endif; ?>
            </div>
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
                            // Alle verfügbaren Disziplinen laden - GRUPPIERT nach Kategorien
                            $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                            $alle_disziplinen = $wpdb->get_results("SELECT * FROM $table_disziplinen WHERE aktiv = 1 ORDER BY kategorie ASC, sortierung ASC, name ASC");
                            
                            // Ausgewählte Disziplinen für diesen Wettkampf laden
                            $ausgewaehlte_disziplinen = array();
                            if ($wettkampf) {
                                $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                                $zuordnungen = $wpdb->get_results($wpdb->prepare("SELECT disziplin_id FROM $table_zuordnung WHERE wettkampf_id = %d", $wettkampf->id));
                                foreach ($zuordnungen as $zuordnung) {
                                    $ausgewaehlte_disziplinen[] = $zuordnung->disziplin_id;
                                }
                            }
                            
                            if (!empty($alle_disziplinen)): 
                                // Gruppiere Disziplinen nach Kategorien
                                $grouped_disziplinen = array();
                                foreach ($alle_disziplinen as $disziplin) {
                                    $kategorie = !empty($disziplin->kategorie) ? $disziplin->kategorie : 'Alle';
                                    if (!isset($grouped_disziplinen[$kategorie])) {
                                        $grouped_disziplinen[$kategorie] = array();
                                    }
                                    $grouped_disziplinen[$kategorie][] = $disziplin;
                                }
                                ?>
                                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                                    <div style="margin-bottom: 10px; padding: 5px 10px; background: #e0f2fe; border-radius: 5px; font-size: 12px; color: #0891b2;">
                                        <strong>💡 Tipp:</strong> Wähle nur die Disziplinen aus, die für diesen Wettkampf relevant sind. 
                                        Die Teilnehmer sehen nur Disziplinen ihrer Alterskategorie.
                                    </div>
                                    
                                    <?php foreach ($grouped_disziplinen as $kategorie => $disziplinen): ?>
                                        <div style="margin-bottom: 20px;">
                                            <h4 style="margin: 0 0 10px 0; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px;">
                                                <span class="kategorie-badge kategorie-<?php echo strtolower($kategorie); ?>">
                                                    <?php echo esc_html($kategorie); ?>
                                                </span>
                                            </h4>
                                            <div style="margin-left: 10px;">
                                                <?php foreach ($disziplinen as $disziplin): ?>
                                                    <label style="display: block; margin-bottom: 8px; padding: 5px; border-radius: 3px; transition: background-color 0.2s;">
                                                        <input type="checkbox" name="disziplinen[]" value="<?php echo $disziplin->id; ?>" 
                                                               <?php echo in_array($disziplin->id, $ausgewaehlte_disziplinen) ? 'checked' : ''; ?>>
                                                        <strong><?php echo esc_html($disziplin->name); ?></strong>
                                                        <?php if ($disziplin->beschreibung): ?>
                                                            <small style="color: #666; margin-left: 10px;">(<?php echo esc_html($disziplin->beschreibung); ?>)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small>Lasse alle unausgewählt, wenn dieser Wettkampf keine spezifischen Disziplinen hat.</small>
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
                'kategorie' => sanitize_text_field($_POST['kategorie']),
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
        $disziplinen = $wpdb->get_results("SELECT * FROM $table_name ORDER BY kategorie ASC, sortierung ASC, name ASC");
        
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
                            <th><label for="kategorie">Alterskategorie *</label></th>
                            <td>
                                <select id="kategorie" name="kategorie" required>
                                    <option value="">Bitte wählen</option>
                                    <option value="U10" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'U10') ? 'selected' : ''; ?>>U10 (unter 10 Jahre)</option>
                                    <option value="U12" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'U12') ? 'selected' : ''; ?>>U12 (unter 12 Jahre)</option>
                                    <option value="U14" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'U14') ? 'selected' : ''; ?>>U14 (unter 14 Jahre)</option>
                                    <option value="U16" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'U16') ? 'selected' : ''; ?>>U16 (unter 16 Jahre)</option>
                                    <option value="U18" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'U18') ? 'selected' : ''; ?>>U18 (unter 18 Jahre)</option>
                                    <option value="Alle" <?php echo ($edit_disziplin && $edit_disziplin->kategorie === 'Alle') ? 'selected' : ''; ?>>Alle Kategorien</option>
                                </select>
                                <p class="description">Wähle die Alterskategorie, für die diese Disziplin verfügbar ist. Maßgebend ist das Alter, das im aktuellen Jahr erreicht wird.</p>
                            </td>
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
                            <td><strong><?php echo esc_html($disziplin->name); ?></strong></td>
                            <td><?php echo esc_html($disziplin->beschreibung); ?></td>
                            <td>
                                <span class="kategorie-badge kategorie-<?php echo strtolower($disziplin->kategorie); ?>">
                                    <?php echo esc_html($disziplin->kategorie ?: 'Nicht gesetzt'); ?>
                                </span>
                            </td>
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
    
    public function admin_anmeldungen() {
        global $wpdb;
        
        // Handle Excel Export - MUST BE FIRST BEFORE ANY OUTPUT!
        if (isset($_GET['export']) && $_GET['export'] === 'xlsx' && wp_verify_nonce($_GET['_wpnonce'], 'export_anmeldungen')) {
            // Clean any output that might have been generated
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            $this->export_anmeldungen_xlsx();
            exit; // Important: exit immediately after export
        }
        
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
                                <td>
                                    <input type="number" id="jahrgang" name="jahrgang" value="<?php echo esc_attr($edit_anmeldung->jahrgang); ?>" min="1900" max="<?php echo date('Y'); ?>" required>
                                    <p class="description">
                                        Alterskategorie: <strong><?php echo WettkampfManager::calculateAgeCategory($edit_anmeldung->jahrgang); ?></strong>
                                    </p>
                                </td>
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
                                    // Disziplinen für diesen Wettkampf laden mit Kategorie-Filter
                                    $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                                    $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                                    
                                    $user_category = WettkampfManager::calculateAgeCategory($edit_anmeldung->jahrgang);
                                    
                                    $wettkampf_disziplinen = $wpdb->get_results($wpdb->prepare("
                                        SELECT d.* 
                                        FROM $table_zuordnung z 
                                        JOIN $table_disziplinen d ON z.disziplin_id = d.id 
                                        WHERE z.wettkampf_id = %d AND d.aktiv = 1 
                                        AND (d.kategorie = %s OR d.kategorie = 'Alle')
                                        ORDER BY d.sortierung ASC, d.name ASC
                                    ", $edit_anmeldung->wettkampf_id, $user_category));
                                    
                                    // Bereits ausgewählte Disziplinen laden
                                    $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
                                    $selected_disziplinen = $wpdb->get_results($wpdb->prepare("
                                        SELECT disziplin_id 
                                        FROM $table_anmeldung_disziplinen 
                                        WHERE anmeldung_id = %d
                                    ", $edit_anmeldung->id));
                                    $selected_ids = array_map(function($d) { return $d->disziplin_id; }, $selected_disziplinen);
                                    
                                    if (!empty($wettkampf_disziplinen)): ?>
                                        <div style="background: #f0f6fc; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                                            <small><strong>Verfügbare Disziplinen für Kategorie <?php echo $user_category; ?>:</strong></small>
                                        </div>
                                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                            <?php foreach ($wettkampf_disziplinen as $disziplin): ?>
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="checkbox" name="disziplinen[]" value="<?php echo $disziplin->id; ?>" 
                                                           <?php echo in_array($disziplin->id, $selected_ids) ? 'checked' : ''; ?>>
                                                    <?php echo esc_html($disziplin->name); ?>
                                                    <span class="kategorie-badge kategorie-<?php echo strtolower($disziplin->kategorie); ?>" style="margin-left: 5px; font-size: 9px;">
                                                        <?php echo esc_html($disziplin->kategorie); ?>
                                                    </span>
                                                    <?php if ($disziplin->beschreibung): ?>
                                                        <small style="color: #666;">(<?php echo esc_html($disziplin->beschreibung); ?>)</small>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p><em>Keine Disziplinen für Kategorie <?php echo $user_category; ?> bei diesem Wettkampf definiert.</em></p>
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
            
            <!-- Anmeldungen Table -->
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
                            <td colspan="12" style="text-align: center; color: #666; font-style: italic; padding: 40px;">
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
                            
                            $user_category = WettkampfManager::calculateAgeCategory($anmeldung->jahrgang);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($anmeldung->vorname . ' ' . $anmeldung->name); ?></strong></td>
                                <td><?php echo esc_html($anmeldung->email); ?></td>
                                <td><?php echo esc_html($anmeldung->geschlecht); ?></td>
                                <td><?php echo esc_html($anmeldung->jahrgang); ?></td>
                                <td>
                                    <span class="kategorie-badge kategorie-<?php echo strtolower($user_category); ?>">
                                        <?php echo esc_html($user_category); ?>
                                    </span>
                                </td>
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
                    <li><strong>Disziplinen:</strong> Beim Bearbeiten werden nur Disziplinen der entsprechenden Kategorie angezeigt</li>
                    <li><strong>Filter:</strong> Verwende die Dropdown-Filter um spezifische Wettkämpfe oder Suchbegriffe zu finden</li>
                    <li><strong>Export:</strong> Exportiert alle gefilterten Anmeldungen als Excel/CSV-Datei (automatisch optimiert für dein Gerät)</li>
                    <li><strong>Löschen:</strong> Beim Löschen werden auch alle Disziplin-Zuordnungen entfernt</li>
                </ul>
            </div>
        </div>
        
        <style>
        /* Export button styling */
        .export-button.xlsx {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            display: inline-block;
            text-align: center;
            min-width: 120px;
        }

        .export-button.xlsx:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .export-button.xlsx.loading {
            background: #6b7280;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .export-button.xlsx small {
            opacity: 0.85;
            font-weight: normal;
        }

        /* Responsive Anpassungen für Export-Info */
        @media (max-width: 768px) {
            .export-section {
                padding: 15px;
            }
            
            .export-section > div:first-child {
                flex-direction: column;
                align-items: stretch;
            }
            
            .export-section form {
                flex-direction: column;
                gap: 10px;
            }
            
            .export-section input[type="text"] {
                min-width: auto;
                width: 100%;
            }
            
            .export-buttons {
                margin-top: 10px;
                text-align: center;
            }
            
            .export-button.xlsx {
                width: 100%;
                padding: 12px 16px;
            }
        }
        </style>
        <?php
    }
    
    // Excel Export and other methods
    private function export_anmeldungen_xlsx() {
        global $wpdb;
        
        // Clean any existing output
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
        $timestamp = date('Y-m-d_H-i');
        $filename = 'wettkampf_anmeldungen_' . $timestamp;
        
        if (!empty($wettkampf_filter)) {
            $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_wettkampf WHERE id = %d", $wettkampf_filter));
            if ($wettkampf) {
                $safe_name = $this->sanitize_filename($wettkampf->name);
                $filename = $safe_name . '_anmeldungen_' . $timestamp;
            }
        }
        
        // Detect user agent for better compatibility
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $is_mobile = preg_match('/Mobile|Android|iPhone|iPad/', $user_agent);
        
        if ($is_mobile) {
            // For mobile: Use CSV format which is more reliable
            $this->export_as_csv($anmeldungen, $filename, $table_anmeldung_disziplinen, $table_disziplinen);
        } else {
            // For desktop: Use improved Excel format
            $this->export_as_excel($anmeldungen, $filename, $table_anmeldung_disziplinen, $table_disziplinen);
        }
    }

    /**
     * CSV Export für bessere Mobile-Kompatibilität
     */
    private function export_as_csv($anmeldungen, $filename, $table_anmeldung_disziplinen, $table_disziplinen) {
        global $wpdb;
        
        // CSV Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        // Output UTF-8 BOM for proper encoding
        echo "\xEF\xBB\xBF";
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // CSV column headers
        $headers = array(
            'Vorname',
            'Name', 
            'E-Mail',
            'Geschlecht',
            'Jahrgang',
            'Kategorie',
            'Wettkampf',
            'Wettkampf Datum',
            'Wettkampf Ort',
            'Eltern fahren',
            'Freie Plätze',
            'Disziplinen',
            'Anmeldedatum'
        );
        
        fputcsv($output, $headers, ';'); // Use semicolon for better Excel compatibility
        
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
            
            $user_category = WettkampfManager::calculateAgeCategory($anmeldung->jahrgang);
            
            $row = array(
                $anmeldung->vorname,
                $anmeldung->name,
                $anmeldung->email,
                $anmeldung->geschlecht,
                $anmeldung->jahrgang,
                $user_category,
                $anmeldung->wettkampf_name,
                date('d.m.Y', strtotime($anmeldung->wettkampf_datum)),
                $anmeldung->wettkampf_ort,
                $anmeldung->eltern_fahren ? 'Ja' : 'Nein',
                $anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '',
                !empty($disziplin_names) ? implode(', ', $disziplin_names) : '',
                date('d.m.Y H:i:s', strtotime($anmeldung->anmeldedatum))
            );
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Verbesserter Excel Export für Desktop
     */
    private function export_as_excel($anmeldungen, $filename, $table_anmeldung_disziplinen, $table_disziplinen) {
        global $wpdb;
        
        // Better Excel headers
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        // Output UTF-8 BOM
        echo "\xEF\xBB\xBF";
        
        // Improved Excel XML format
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        // Document properties
        echo '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
        echo '<Title>Wettkampf Anmeldungen</Title>' . "\n";
        echo '<Author>Wettkampf Manager</Author>' . "\n";
        echo '<Created>' . date('Y-m-d\TH:i:s\Z') . '</Created>' . "\n";
        echo '</DocumentProperties>' . "\n";
        
        // Styles
        echo '<Styles>' . "\n";
        echo '<Style ss:ID="Header">' . "\n";
        echo '<Font ss:Bold="1"/>' . "\n";
        echo '<Interior ss:Color="#CCE5FF" ss:Pattern="Solid"/>' . "\n";
        echo '<Borders>' . "\n";
        echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        echo '</Borders>' . "\n";
        echo '</Style>' . "\n";
        echo '<Style ss:ID="Data">' . "\n";
        echo '<Borders>' . "\n";
        echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>' . "\n";
        echo '</Borders>' . "\n";
        echo '</Style>' . "\n";
        echo '</Styles>' . "\n";
        
        // Worksheet
        echo '<Worksheet ss:Name="Anmeldungen">' . "\n";
        echo '<Table>' . "\n";
        
        // Column widths
        echo '<Column ss:Width="80"/>' . "\n";  // Vorname
        echo '<Column ss:Width="80"/>' . "\n";  // Name
        echo '<Column ss:Width="120"/>' . "\n"; // E-Mail
        echo '<Column ss:Width="70"/>' . "\n";  // Geschlecht
        echo '<Column ss:Width="60"/>' . "\n";  // Jahrgang
        echo '<Column ss:Width="70"/>' . "\n";  // Kategorie
        echo '<Column ss:Width="150"/>' . "\n"; // Wettkampf
        echo '<Column ss:Width="80"/>' . "\n";  // Datum
        echo '<Column ss:Width="100"/>' . "\n"; // Ort
        echo '<Column ss:Width="80"/>' . "\n";  // Eltern fahren
        echo '<Column ss:Width="70"/>' . "\n";  // Freie Plätze
        echo '<Column ss:Width="200"/>' . "\n"; // Disziplinen
        echo '<Column ss:Width="120"/>' . "\n"; // Anmeldedatum
        
        // Header row
        echo '<Row>' . "\n";
        $headers = array(
            'Vorname', 'Name', 'E-Mail', 'Geschlecht', 'Jahrgang', 'Kategorie',
            'Wettkampf', 'Wettkampf Datum', 'Wettkampf Ort', 'Eltern fahren',
            'Freie Plätze', 'Disziplinen', 'Anmeldedatum'
        );
        
        foreach ($headers as $header) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
        
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
            
            $user_category = WettkampfManager::calculateAgeCategory($anmeldung->jahrgang);
            
            echo '<Row>' . "\n";
            
            // Data cells
            $data = array(
                $anmeldung->vorname,
                $anmeldung->name,
                $anmeldung->email,
                $anmeldung->geschlecht,
                $anmeldung->jahrgang,
                $user_category,
                $anmeldung->wettkampf_name,
                date('d.m.Y', strtotime($anmeldung->wettkampf_datum)),
                $anmeldung->wettkampf_ort,
                $anmeldung->eltern_fahren ? 'Ja' : 'Nein',
                $anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '',
                !empty($disziplin_names) ? implode(', ', $disziplin_names) : '',
                date('d.m.Y H:i:s', strtotime($anmeldung->anmeldedatum))
            );
            
            foreach ($data as $cell) {
                echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($cell, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
            }
            
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
        
        exit;
    }

    /**
     * Sanitize filename for different operating systems
     */
    private function sanitize_filename($filename) {
        // Remove/replace problematic characters
        $filename = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß_-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename); // Remove multiple underscores
        $filename = trim($filename, '_');
        
        // Limit length
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }
        
        return $filename;
    }
    
    public function admin_settings() {
        if (isset($_POST['submit'])) {
            update_option('wettkampf_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site_key']));
            update_option('wettkampf_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret_key']));
            update_option('wettkampf_sender_email', sanitize_email($_POST['sender_email']));
            update_option('wettkampf_sender_name', sanitize_text_field($_POST['sender_name']));
            update_option('wettkampf_export_email', sanitize_email($_POST['export_email']));
            
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }
        
        $recaptcha_site_key = get_option('wettkampf_recaptcha_site_key', '');
        $recaptcha_secret_key = get_option('wettkampf_recaptcha_secret_key', '');
        $sender_email = get_option('wettkampf_sender_email', get_option('admin_email'));
        $sender_name = get_option('wettkampf_sender_name', get_option('blogname'));
        $export_email = get_option('wettkampf_export_email', '');
        
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
                            <p class="description">E-Mail-Adresse für ausgehende Nachrichten (Bestätigungen, etc.)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sender_name">Absender Name</label></th>
                        <td>
                            <input type="text" id="sender_name" name="sender_name" value="<?php echo esc_attr($sender_name); ?>" class="regular-text">
                            <p class="description">Name für ausgehende Nachrichten</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="export_email">Automatischer Export E-Mail</label></th>
                        <td>
                            <input type="email" id="export_email" name="export_email" value="<?php echo esc_attr($export_email); ?>" class="regular-text">
                            <p class="description">E-Mail-Adresse für automatische CSV-Exporte nach Anmeldeschluss (2 Stunden nach Mitternacht)</p>
                            <?php if (!empty($export_email)): ?>
                                <p style="color: #46b450; font-weight: 500;">✓ Automatische Exports sind aktiviert</p>
                            <?php else: ?>
                                <p style="color: #dc3232; font-weight: 500;">⚠ Automatische Exports sind deaktiviert (keine E-Mail-Adresse hinterlegt)</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Einstellungen speichern">
                </p>
            </form>
            
            <!-- Informationsbox für Cron-Jobs -->
            <div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                <h3>🤖 Automatische CSV-Exports</h3>
                <p><strong>Funktionsweise:</strong></p>
                <ul>
                    <li>Das System prüft stündlich, ob Anmeldefristen abgelaufen sind</li>
                    <li>2 Stunden nach Mitternacht des Anmeldeschlusstages wird automatisch ein CSV-Export generiert</li>
                    <li>Der Export wird an die oben konfigurierte E-Mail-Adresse gesendet</li>
                    <li>Pro Wettkampf wird nur einmal ein automatischer Export versendet</li>
                    <li>CSV-Format für beste Kompatibilität mit allen E-Mail-Clients und Mobilgeräten</li>
                </ul>
                
                <p><strong>Technische Details:</strong></p>
                <ul>
                    <li>WordPress Cron-Job läuft stündlich</li>
                    <li>Export-Zeitfenster: 02:00 - 03:00 Uhr</li>
                    <li>Nur Wettkämpfe mit Anmeldungen werden exportiert</li>
                    <li>UTF-8 Kodierung mit BOM für korrekte Umlaute</li>
                </ul>
                
                <?php
                // Show next scheduled cron run
                $next_cron = wp_next_scheduled('wettkampf_check_expired_registrations');
                if ($next_cron):
                ?>
                <p><strong>Nächste Prüfung:</strong> <?php echo date('d.m.Y H:i:s', $next_cron + (get_option('gmt_offset') * HOUR_IN_SECONDS)); ?> Uhr</p>
                <?php endif; ?>
            </div>
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
    
    private function delete_wettkampf_cascade($wettkampf_id) {
        global $wpdb;
        
        // Get all anmeldung IDs
        $anmeldung_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wettkampf_anmeldung WHERE wettkampf_id = %d", 
            $wettkampf_id
        ));
        
        // Delete anmeldung-disziplin relations
        if (!empty($anmeldung_ids)) {
            $placeholders = implode(',', array_fill(0, count($anmeldung_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}wettkampf_anmeldung_disziplinen WHERE anmeldung_id IN ($placeholders)", 
                ...$anmeldung_ids
            ));
        }
        
        // Delete anmeldungen
        $wpdb->delete($wpdb->prefix . 'wettkampf_anmeldung', array('wettkampf_id' => $wettkampf_id));
        
        // Delete wettkampf-disziplin relations
        $wpdb->delete($wpdb->prefix . 'wettkampf_disziplin_zuordnung', array('wettkampf_id' => $wettkampf_id));
        
        // Delete wettkampf
        $wpdb->delete($wpdb->prefix . 'wettkampf', array('id' => $wettkampf_id));
    }
}