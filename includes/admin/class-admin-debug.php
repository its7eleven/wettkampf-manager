<?php
/**
 * Debug page for admin - ERWEITERT für Auto-Export Testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AdminDebug {
    
    /**
     * Display debug page
     */
    public function display_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }
        
        global $wpdb;
        
        // Handle Test Actions
        $test_result = '';
        if (isset($_POST['test_auto_export']) && wp_verify_nonce($_POST['test_nonce'], 'test_auto_export')) {
            $test_result = $this->test_auto_export();
        }
        
        if (isset($_POST['test_cron_now']) && wp_verify_nonce($_POST['test_nonce'], 'test_cron_now')) {
            $test_result = $this->run_cron_now();
        }
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager - Debug Information</h1>
            
            <?php if ($test_result): ?>
                <div class="notice notice-info">
                    <p><strong>Test-Ergebnis:</strong> <?php echo esc_html($test_result); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                
                <h2>1. Auto-Export Status:</h2>
                <?php
                $export_email = get_option('wettkampf_export_email', '');
                $sender_email = get_option('wettkampf_sender_email', get_option('admin_email'));
                $sender_name = get_option('wettkampf_sender_name', get_option('blogname'));
                
                echo "Export E-Mail: " . ($export_email ? "✅ " . $export_email : "❌ NICHT konfiguriert") . "<br>";
                echo "Sender E-Mail: " . ($sender_email ? "✅ " . $sender_email : "❌ NICHT konfiguriert") . "<br>";
                echo "Sender Name: " . ($sender_name ? "✅ " . $sender_name : "❌ NICHT konfiguriert") . "<br>";
                
                // Cron Status
                $next_cron = wp_next_scheduled('wettkampf_check_expired_registrations');
                if ($next_cron) {
                    echo "Nächster Cron-Lauf: ✅ " . date('d.m.Y H:i:s', $next_cron + (get_option('gmt_offset') * HOUR_IN_SECONDS)) . " Uhr<br>";
                } else {
                    echo "Cron-Job: ❌ NICHT geplant!<br>";
                }
                
                // WordPress Cron Status
                if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                    echo "<p style='color: red;'><strong>⚠️ WARNUNG:</strong> WordPress Cron ist deaktiviert (DISABLE_WP_CRON = true). ";
                    echo "Auto-Export funktioniert nur mit externem Cron-Job!</p>";
                } else {
                    echo "WordPress Cron: ✅ Aktiviert<br>";
                }
                ?>
                
                <h2>2. E-Mail System Check:</h2>
                <?php
                // PHP mail() Funktion
                if (function_exists('mail')) {
                    echo "✅ PHP mail() Funktion ist verfügbar<br>";
                } else {
                    echo "❌ PHP mail() Funktion ist NICHT verfügbar!<br>";
                }
                
                // WordPress E-Mail Test
                echo "<h3>WordPress E-Mail Test:</h3>";
                ?>
                <form method="post" style="margin: 10px 0;">
                    <?php wp_nonce_field('test_wp_mail', 'test_nonce'); ?>
                    <button type="submit" name="test_wp_mail" class="button button-primary">Test E-Mail senden</button>
                </form>
                
                <?php
                if (isset($_POST['test_wp_mail']) && wp_verify_nonce($_POST['test_nonce'], 'test_wp_mail')) {
                    $test_result = wp_mail(
                        get_option('admin_email'),
                        'Wettkampf Manager - WordPress E-Mail Test',
                        'Diese Test-E-Mail wurde von WordPress gesendet.',
                        array('Content-Type: text/plain; charset=UTF-8')
                    );
                    
                    if ($test_result) {
                        echo "<p style='color: green;'>✅ Test-E-Mail wurde gesendet an " . get_option('admin_email') . "</p>";
                    } else {
                        echo "<p style='color: red;'>❌ E-Mail konnte NICHT gesendet werden!</p>";
                        
                        // Erweiterte Fehlerdiagnose
                        global $phpmailer;
                        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                            echo "<p>PHPMailer Fehler: " . esc_html($phpmailer->ErrorInfo) . "</p>";
                        }
                    }
                }
                ?>
                
                <h2>3. Abgelaufene Wettkämpfe für Auto-Export:</h2>
                <?php
                $tables = WettkampfDatabase::get_table_names();
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $today = date('Y-m-d');
                
                // Wettkämpfe die gestern abgelaufen sind
                $yesterday_expired = $wpdb->get_results($wpdb->prepare("
                    SELECT w.*, COUNT(a.id) as anmeldungen_count 
                    FROM {$tables['wettkampf']} w 
                    LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
                    WHERE w.anmeldeschluss = %s
                    GROUP BY w.id
                ", $yesterday));
                
                echo "<h4>Gestern abgelaufen (werden um 2 Uhr exportiert):</h4>";
                if ($yesterday_expired) {
                    echo "<table class='wp-list-table widefat'>";
                    echo "<tr><th>Wettkampf</th><th>Anmeldungen</th><th>Export Status</th></tr>";
                    foreach ($yesterday_expired as $w) {
                        $sent = get_option('wettkampf_export_sent_' . $w->id);
                        echo "<tr>";
                        echo "<td>" . esc_html($w->name) . "</td>";
                        echo "<td>" . $w->anmeldungen_count . "</td>";
                        echo "<td>" . ($sent ? "✅ Gesendet am " . $sent : "⏳ Ausstehend") . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Keine Wettkämpfe</p>";
                }
                
                // Alle kürzlich abgelaufenen
                $recent_expired = $wpdb->get_results($wpdb->prepare("
                    SELECT w.*, COUNT(a.id) as anmeldungen_count 
                    FROM {$tables['wettkampf']} w 
                    LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
                    WHERE w.anmeldeschluss < %s AND w.anmeldeschluss >= %s
                    GROUP BY w.id
                    HAVING anmeldungen_count > 0
                    ORDER BY w.anmeldeschluss DESC
                ", $today, date('Y-m-d', strtotime('-7 days'))));
                
                echo "<h4>Letzte 7 Tage abgelaufen:</h4>";
                if ($recent_expired) {
                    echo "<table class='wp-list-table widefat'>";
                    echo "<tr><th>Wettkampf</th><th>Anmeldeschluss</th><th>Anmeldungen</th><th>Export Status</th><th>Aktion</th></tr>";
                    foreach ($recent_expired as $w) {
                        $sent = get_option('wettkampf_export_sent_' . $w->id);
                        echo "<tr>";
                        echo "<td>" . esc_html($w->name) . "</td>";
                        echo "<td>" . WettkampfHelpers::format_german_date($w->anmeldeschluss) . "</td>";
                        echo "<td>" . $w->anmeldungen_count . "</td>";
                        echo "<td>" . ($sent ? "✅ " . $sent : "❌ Nicht gesendet") . "</td>";
                        echo "<td>";
                        ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('force_export', 'test_nonce'); ?>
                            <input type="hidden" name="wettkampf_id" value="<?php echo $w->id; ?>">
                            <button type="submit" name="force_export" class="button button-small">Export erzwingen</button>
                        </form>
                        <?php
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    // Handle forced export
                    if (isset($_POST['force_export']) && wp_verify_nonce($_POST['test_nonce'], 'force_export')) {
                        $wettkampf_id = intval($_POST['wettkampf_id']);
                        $wettkampf = $wpdb->get_row($wpdb->prepare("
                            SELECT w.*, COUNT(a.id) as anmeldungen_count 
                            FROM {$tables['wettkampf']} w 
                            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
                            WHERE w.id = %d
                            GROUP BY w.id
                        ", $wettkampf_id));
                        
                        if ($wettkampf && $export_email) {
                            // Lösche alte Markierung
                            delete_option('wettkampf_export_sent_' . $wettkampf_id);
                            
                            // Sende Export
                            $cron = new WettkampfCron();
                            $result = $cron->send_automatic_export($wettkampf);
                            
                            if ($result) {
                                update_option('wettkampf_export_sent_' . $wettkampf_id, current_time('mysql'));
                                echo "<div class='notice notice-success'><p>✅ Export erfolgreich gesendet!</p></div>";
                            } else {
                                echo "<div class='notice notice-error'><p>❌ Export fehlgeschlagen!</p></div>";
                            }
                            
                            // Seite neu laden
                            echo "<script>setTimeout(function() { location.reload(); }, 2000);</script>";
                        }
                    }
                } else {
                    echo "<p>Keine abgelaufenen Wettkämpfe mit Anmeldungen</p>";
                }
                ?>
                
                <h2>4. Auto-Export Test-Funktionen:</h2>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1; padding: 15px; background: #f0f0f0; border-radius: 5px;">
                        <h3>Test Auto-Export (simuliert Cron)</h3>
                        <p>Testet den Auto-Export mit dem neuesten abgelaufenen Wettkampf.</p>
                        <form method="post">
                            <?php wp_nonce_field('test_auto_export', 'test_nonce'); ?>
                            <button type="submit" name="test_auto_export" class="button button-primary">Auto-Export testen</button>
                        </form>
                    </div>
                    
                    <div style="flex: 1; padding: 15px; background: #f0f0f0; border-radius: 5px;">
                        <h3>Cron jetzt ausführen</h3>
                        <p>Führt den Cron-Job sofort aus (ignoriert Zeitfenster).</p>
                        <form method="post">
                            <?php wp_nonce_field('test_cron_now', 'test_nonce'); ?>
                            <button type="submit" name="test_cron_now" class="button button-primary">Cron jetzt ausführen</button>
                        </form>
                    </div>
                </div>
                
                <h2>5. WordPress Cron Jobs:</h2>
                <?php
                $crons = _get_cron_array();
                $wettkampf_crons = array();
                
                foreach ($crons as $timestamp => $cron) {
                    foreach ($cron as $hook => $dings) {
                        if (strpos($hook, 'wettkampf') !== false) {
                            $wettkampf_crons[] = array(
                                'hook' => $hook,
                                'next_run' => date('d.m.Y H:i:s', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS))
                            );
                        }
                    }
                }
                
                if ($wettkampf_crons) {
                    echo "<table class='wp-list-table widefat'>";
                    echo "<tr><th>Hook</th><th>Nächste Ausführung</th></tr>";
                    foreach ($wettkampf_crons as $cron) {
                        echo "<tr>";
                        echo "<td>" . esc_html($cron['hook']) . "</td>";
                        echo "<td>" . esc_html($cron['next_run']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>❌ Keine Wettkampf Cron-Jobs gefunden!</p>";
                }
                ?>
                
                <h2>6. Debug-Log (Wettkampf-Einträge):</h2>
                <?php
                $debug_log = WP_CONTENT_DIR . '/debug.log';
                
                if (file_exists($debug_log) && is_readable($debug_log)) {
                    // Nur die letzten 100 Zeilen lesen
                    $lines = array_slice(file($debug_log), -100);
                    $wettkampf_errors = array();
                    
                    foreach ($lines as $line) {
                        if (stripos($line, 'wettkampf') !== false || 
                            stripos($line, 'cron') !== false ||
                            stripos($line, 'export') !== false ||
                            stripos($line, 'email') !== false ||
                            stripos($line, 'mail') !== false) {
                            $wettkampf_errors[] = $line;
                        }
                    }
                    
                    if (!empty($wettkampf_errors)) {
                        echo "<pre style='background: #f0f0f0; padding: 10px; overflow: auto; max-height: 400px;'>";
                        foreach (array_slice($wettkampf_errors, -20) as $error) {
                            echo htmlspecialchars($error);
                        }
                        echo "</pre>";
                    } else {
                        echo "<p>Keine relevanten Log-Einträge gefunden.</p>";
                    }
                } else {
                    echo "<p>Debug-Log nicht verfügbar. Aktiviere WP_DEBUG und WP_DEBUG_LOG in wp-config.php:</p>";
                    echo "<pre>define('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);\ndefine('WP_DEBUG_DISPLAY', false);</pre>";
                }
                ?>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Test Auto-Export
     */
    private function test_auto_export() {
        if (class_exists('WettkampfCron')) {
            return WettkampfCron::test_cron_manually();
        } else {
            return 'WettkampfCron Klasse nicht gefunden';
        }
    }
    
    /**
     * Run Cron Now
     */
    private function run_cron_now() {
        // Temporär aktuelle Stunde auf 2 Uhr setzen für den Test
        add_filter('current_time', function($time, $type) {
            if ($type === 'timestamp') {
                return mktime(2, 0, 0);
            }
            return $time;
        }, 10, 2);
        
        // Cron ausführen
        do_action('wettkampf_check_expired_registrations');
        
        return 'Cron-Job wurde ausgeführt. Prüfe das Debug-Log für Details.';
    }
}