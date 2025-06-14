<?php
/**
 * Export functionality admin class - E-Mail-Versand korrigiert
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminExport {
    
    /**
     * Handle export requests
     */
    public function handle_export() {
        // Clean any output that might have been generated
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $export_type = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';
        
        switch ($export_type) {
            case 'xlsx':
                $this->export_registrations();
                break;
            default:
                wp_die('Unknown export type');
        }
    }
    
    /**
     * Export registrations as Excel/CSV
     */
    private function export_registrations() {
        // Get filter parameters
        $filters = array(
            'wettkampf_id' => isset($_GET['wettkampf_id']) ? intval($_GET['wettkampf_id']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        );
        
        $anmeldungen = WettkampfDatabase::get_registrations($filters);
        
        // Generate filename
        $timestamp = date('Y-m-d_H-i');
        $filename = 'wettkampf_anmeldungen_' . $timestamp;
        
        if (!empty($filters['wettkampf_id'])) {
            $wettkampf = WettkampfDatabase::get_competition($filters['wettkampf_id']);
            if ($wettkampf) {
                $safe_name = WettkampfHelpers::sanitize_filename($wettkampf->name);
                $filename = $safe_name . '_anmeldungen_' . $timestamp;
            }
        }
        
        // Detect user agent for better compatibility
        if (WettkampfHelpers::is_mobile_user_agent()) {
            $this->export_as_csv($anmeldungen, $filename);
        } else {
            $this->export_as_excel($anmeldungen, $filename);
        }
    }
    
    /**
     * CSV Export for better mobile compatibility
     */
    private function export_as_csv($anmeldungen, $filename) {
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
            'Transport',
            'Freie Plätze',
            'Disziplinen',
            'Anmeldedatum'
        );
        
        fputcsv($output, $headers, ';');
        
        // Data rows
        foreach ($anmeldungen as $anmeldung) {
            $disciplines = WettkampfDatabase::get_registration_disciplines($anmeldung->id);
            
            $discipline_names = array();
            if (is_array($disciplines) && !empty($disciplines)) {
                foreach ($disciplines as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $discipline_names[] = $d->name;
                    }
                }
            }
            
            $user_category = CategoryCalculator::calculate($anmeldung->jahrgang);
            
            // Transport-Text generieren
            $transport_text = $this->get_transport_text($anmeldung->eltern_fahren, $anmeldung->freie_plaetze);
            
            $row = array(
                $anmeldung->vorname,
                $anmeldung->name,
                $anmeldung->email,
                $anmeldung->geschlecht,
                $anmeldung->jahrgang,
                $user_category,
                $anmeldung->wettkampf_name,
                WettkampfHelpers::format_german_date($anmeldung->wettkampf_datum),
                $anmeldung->wettkampf_ort,
                $transport_text,
                ($anmeldung->eltern_fahren === 'ja') ? $anmeldung->freie_plaetze : '',
                !empty($discipline_names) ? implode(', ', $discipline_names) : '',
                WettkampfHelpers::format_german_date($anmeldung->anmeldedatum, true)
            );
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get transport text for export
     */
    private function get_transport_text($eltern_fahren, $freie_plaetze) {
        switch ($eltern_fahren) {
            case 'ja':
                return "Ja (" . $freie_plaetze . " Plätze)";
            case 'nein':
                return "Nein";
            case 'direkt':
                return "Fahren direkt";
            default:
                // Fallback für alte Einträge
                if ($eltern_fahren == 1) {
                    return "Ja (" . $freie_plaetze . " Plätze)";
                } else {
                    return "Nein";
                }
        }
    }
    
    /**
     * Improved Excel export for desktop
     */
    private function export_as_excel($anmeldungen, $filename) {
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
        $column_widths = array(80, 80, 120, 70, 60, 70, 150, 80, 100, 120, 70, 200, 120);
        foreach ($column_widths as $width) {
            echo '<Column ss:Width="' . $width . '"/>' . "\n";
        }
        
        // Header row
        echo '<Row>' . "\n";
        $headers = array(
            'Vorname', 'Name', 'E-Mail', 'Geschlecht', 'Jahrgang', 'Kategorie',
            'Wettkampf', 'Wettkampf Datum', 'Wettkampf Ort', 'Transport',
            'Freie Plätze', 'Disziplinen', 'Anmeldedatum'
        );
        
        foreach ($headers as $header) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
        
        // Data rows
        foreach ($anmeldungen as $anmeldung) {
            $disciplines = WettkampfDatabase::get_registration_disciplines($anmeldung->id);
            
            $discipline_names = array();
            if (is_array($disciplines) && !empty($disciplines)) {
                foreach ($disciplines as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $discipline_names[] = $d->name;
                    }
                }
            }
            
            $user_category = CategoryCalculator::calculate($anmeldung->jahrgang);
            $transport_text = $this->get_transport_text($anmeldung->eltern_fahren, $anmeldung->freie_plaetze);
            
            echo '<Row>' . "\n";
            
            $data = array(
                $anmeldung->vorname,
                $anmeldung->name,
                $anmeldung->email,
                $anmeldung->geschlecht,
                $anmeldung->jahrgang,
                $user_category,
                $anmeldung->wettkampf_name,
                WettkampfHelpers::format_german_date($anmeldung->wettkampf_datum),
                $anmeldung->wettkampf_ort,
                $transport_text,
                ($anmeldung->eltern_fahren === 'ja') ? $anmeldung->freie_plaetze : '',
                !empty($discipline_names) ? implode(', ', $discipline_names) : '',
                WettkampfHelpers::format_german_date($anmeldung->anmeldedatum, true)
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
     * Display export status page
     */
    public function display_status_page() {
        // Handle manual test
        if (isset($_POST['test_export']) && wp_verify_nonce($_POST['test_nonce'], 'test_export')) {
            $wettkampf_id = intval($_POST['wettkampf_id']);
            
            // Lade komplette Wettkampf-Daten mit Anmeldungen
            global $wpdb;
            $tables = WettkampfDatabase::get_table_names();
            
            $wettkampf = $wpdb->get_row($wpdb->prepare("
                SELECT w.*, COUNT(a.id) as anmeldungen_count 
                FROM {$tables['wettkampf']} w 
                LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
                WHERE w.id = %d
                GROUP BY w.id
            ", $wettkampf_id));
            
            if ($wettkampf) {
                $cron = new WettkampfCron();
                $result = $cron->send_automatic_export($wettkampf);
                
                if ($result) {
                    WettkampfHelpers::add_admin_notice('Test-Export für "' . $wettkampf->name . '" wurde versendet!');
                } else {
                    WettkampfHelpers::add_admin_notice('Fehler beim Versenden des Test-Exports. Bitte überprüfe die E-Mail-Einstellungen.', 'error');
                }
            } else {
                WettkampfHelpers::add_admin_notice('Wettkampf nicht gefunden.', 'error');
            }
        }
        
        $competitions = $this->get_competitions_for_export_status();
        $export_email = WettkampfHelpers::get_option('export_email', '');
        
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
                    <p><strong>✅ Auto-Export aktiviert</strong> für: <?php echo SecurityManager::escape_html($export_email); ?></p>
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
                            <td><strong><?php echo SecurityManager::escape_html($comp->name); ?></strong></td>
                            <td><?php echo WettkampfHelpers::format_german_date($comp->datum); ?></td>
                            <td><?php echo WettkampfHelpers::format_german_date($comp->anmeldeschluss); ?></td>
                            <td><?php echo $comp->anmeldungen_count; ?></td>
                            <td>
                                <?php if ($comp->export_sent_date): ?>
                                    <?php echo WettkampfHelpers::get_status_badge('active', '✅ Gesendet'); ?><br>
                                    <small><?php echo WettkampfHelpers::format_german_date($comp->export_sent_date, true); ?></small>
                                <?php elseif ($comp->anmeldungen_count > 0): ?>
                                    <?php echo WettkampfHelpers::get_status_badge('inactive', '⏳ Ausstehend'); ?>
                                <?php else: ?>
                                    <?php echo WettkampfHelpers::get_status_badge('expired', '➖ Keine Anmeldungen'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($comp->anmeldungen_count > 0 && !empty($export_email)): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="wettkampf_id" value="<?php echo $comp->id; ?>">
                                        <?php wp_nonce_field('test_export', 'test_nonce'); ?>
                                        <button type="submit" name="test_export" class="button button-small" 
                                                onclick="return confirm('Test-Export für &quot;<?php echo SecurityManager::escape_attr($comp->name); ?>&quot; senden?')">
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
    
    /**
     * Get competitions for export status display
     */
    private function get_competitions_for_export_status() {
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        return $wpdb->get_results("
            SELECT w.*, COUNT(a.id) as anmeldungen_count,
                   CASE WHEN o.option_value IS NOT NULL THEN o.option_value ELSE NULL END as export_sent_date
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
            LEFT JOIN {$wpdb->prefix}options o ON o.option_name = CONCAT('wettkampf_export_sent_', w.id)
            WHERE w.anmeldeschluss <= CURDATE()
            GROUP BY w.id 
            ORDER BY w.datum DESC
        ");
    }
}