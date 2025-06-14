<?php
/**
 * Debug page for admin
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
        
        ?>
        <div class="wrap">
            <h1>Wettkampf Manager - Debug Information</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                
                <h2>1. Klassen-Check:</h2>
                <?php
                $required_classes = array(
                    'WettkampfDatabase',
                    'SecurityManager',
                    'EmailManager',
                    'CategoryCalculator',
                    'WettkampfHelpers',
                    'FrontendAjax'
                );
                
                foreach ($required_classes as $class) {
                    if (class_exists($class)) {
                        echo "✅ $class existiert<br>";
                    } else {
                        echo "❌ $class FEHLT!<br>";
                    }
                }
                ?>
                
                <h2>2. Datenbank-Struktur:</h2>
                <?php
                $table_name = $wpdb->prefix . 'wettkampf_anmeldung';
                $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'eltern_fahren'");
                
                if ($column_info) {
                    echo "<pre>";
                    echo "Spalte: eltern_fahren\n";
                    echo "Type: " . $column_info->Type . "\n";
                    echo "Default: " . $column_info->Default . "\n";
                    echo "</pre>";
                    
                    if (strpos($column_info->Type, "enum('ja','nein','direkt')") !== false) {
                        echo "✅ Spalte ist korrekt als ENUM konfiguriert<br>";
                    } else {
                        echo "❌ Spalte ist NICHT korrekt konfiguriert!<br>";
                        echo "<p><strong>Aktuelle Konfiguration:</strong> " . $column_info->Type . "</p>";
                        echo "<p><strong>Erwartet:</strong> enum('ja','nein','direkt')</p>";
                    }
                } else {
                    echo "❌ Spalte 'eltern_fahren' nicht gefunden!<br>";
                }
                
                // Zeige aktuelle Daten
                $sample_data = $wpdb->get_results("SELECT id, vorname, name, eltern_fahren FROM $table_name LIMIT 5");
                if ($sample_data) {
                    echo "<h3>Beispiel-Daten:</h3>";
                    echo "<table class='wp-list-table widefat'>";
                    echo "<tr><th>ID</th><th>Name</th><th>eltern_fahren</th></tr>";
                    foreach ($sample_data as $row) {
                        echo "<tr>";
                        echo "<td>{$row->id}</td>";
                        echo "<td>{$row->vorname} {$row->name}</td>";
                        echo "<td>{$row->eltern_fahren}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                ?>
                
                <h2>3. AJAX-Handler Check:</h2>
                <?php
                $ajax_actions = array(
                    'wettkampf_anmeldung',
                    'wettkampf_mutation',
                    'get_wettkampf_disziplinen',
                    'wettkampf_view_only'
                );
                
                foreach ($ajax_actions as $action) {
                    if (has_action('wp_ajax_' . $action)) {
                        echo "✅ wp_ajax_$action ist registriert<br>";
                    } else {
                        echo "❌ wp_ajax_$action ist NICHT registriert<br>";
                    }
                    
                    if (has_action('wp_ajax_nopriv_' . $action)) {
                        echo "✅ wp_ajax_nopriv_$action ist registriert<br>";
                    } else {
                        echo "❌ wp_ajax_nopriv_$action ist NICHT registriert<br>";
                    }
                }
                ?>
                
                <h2>4. Test AJAX-Response:</h2>
                <button id="test-ajax" class="button button-primary">Test AJAX-Aufruf</button>
                <div id="ajax-result" style="margin-top: 20px; padding: 10px; background: #f0f0f0; display: none;"></div>
                
                <h2>5. Plugin-Status:</h2>
                <?php
                echo "Plugin Version: " . WETTKAMPF_VERSION . "<br>";
                echo "DB Version: " . WETTKAMPF_DB_VERSION . "<br>";
                echo "Gespeicherte DB Version: " . get_option('wettkampf_db_version', 'nicht gesetzt') . "<br>";
                echo "PHP Version: " . PHP_VERSION . "<br>";
                echo "WordPress Version: " . get_bloginfo('version') . "<br>";
                ?>
                
                <h2>6. Fehler-Log (letzte Wettkampf-Einträge):</h2>
                <?php
                $upload_dir = wp_upload_dir();
                $debug_log = WP_CONTENT_DIR . '/debug.log';
                
                if (file_exists($debug_log) && is_readable($debug_log)) {
                    $lines = array_slice(file($debug_log), -50);
                    $wettkampf_errors = array();
                    
                    foreach ($lines as $line) {
                        if (stripos($line, 'wettkampf') !== false) {
                            $wettkampf_errors[] = $line;
                        }
                    }
                    
                    if (!empty($wettkampf_errors)) {
                        echo "<pre style='background: #f0f0f0; padding: 10px; overflow: auto; max-height: 300px;'>";
                        foreach (array_slice($wettkampf_errors, -10) as $error) {
                            echo htmlspecialchars($error);
                        }
                        echo "</pre>";
                    } else {
                        echo "<p>Keine Wettkampf-bezogenen Fehler gefunden.</p>";
                    }
                } else {
                    echo "<p>Debug-Log nicht verfügbar. Aktiviere WP_DEBUG_LOG in wp-config.php</p>";
                }
                ?>
                
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h2>Datenbank-Korrektur</h2>
                <?php if ($column_info && strpos($column_info->Type, "enum('ja','nein','direkt')") === false): ?>
                    <p>Die Spalte 'eltern_fahren' muss korrigiert werden.</p>
                    <form method="post">
                        <?php wp_nonce_field('fix_database', 'fix_nonce'); ?>
                        <button type="submit" name="fix_database" class="button button-primary">Datenbank korrigieren</button>
                    </form>
                    
                    <?php
                    if (isset($_POST['fix_database']) && wp_verify_nonce($_POST['fix_nonce'], 'fix_database')) {
                        $this->fix_database_column();
                    }
                    ?>
                <?php else: ?>
                    <p>✅ Die Datenbank-Struktur ist korrekt.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-ajax').on('click', function() {
                $('#ajax-result').show().html('<p>Teste AJAX-Aufruf...</p>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wettkampf_anmeldung',
                        nonce: '<?php echo wp_create_nonce('wettkampf_ajax'); ?>',
                        wettkampf_id: 1,
                        vorname: 'Test',
                        name: 'User',
                        email: 'test@example.com',
                        geschlecht: 'maennlich',
                        jahrgang: 2016,
                        eltern_fahren: 'ja',
                        freie_plaetze: 2,
                        disziplinen: []
                    },
                    dataType: 'text',
                    success: function(response) {
                        $('#ajax-result').html('<h3>Response:</h3><pre>' + response + '</pre>');
                        console.log('Raw response:', response);
                        
                        try {
                            var json = JSON.parse(response);
                            $('#ajax-result').append('<h3>Parsed JSON:</h3><pre>' + JSON.stringify(json, null, 2) + '</pre>');
                        } catch(e) {
                            $('#ajax-result').append('<h3>JSON Parse Error:</h3><pre>' + e.toString() + '</pre>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#ajax-result').html('<h3>Error:</h3><pre>Status: ' + status + '\nError: ' + error + '\nResponse: ' + xhr.responseText + '</pre>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Fix database column
     */
    private function fix_database_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf_anmeldung';
        
        try {
            // Create temporary column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN eltern_fahren_temp VARCHAR(20) AFTER eltern_fahren");
            
            // Copy and convert data
            $wpdb->query("UPDATE $table_name SET eltern_fahren_temp = 
                CASE 
                    WHEN eltern_fahren = '1' THEN 'ja'
                    WHEN eltern_fahren = '0' THEN 'nein'
                    WHEN eltern_fahren = 1 THEN 'ja'
                    WHEN eltern_fahren = 0 THEN 'nein'
                    WHEN eltern_fahren = 'ja' THEN 'ja'
                    WHEN eltern_fahren = 'nein' THEN 'nein'
                    WHEN eltern_fahren = 'direkt' THEN 'direkt'
                    ELSE 'nein'
                END
            ");
            
            // Drop old column
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN eltern_fahren");
            
            // Create new ENUM column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN eltern_fahren ENUM('ja','nein','direkt') NOT NULL DEFAULT 'nein' AFTER jahrgang");
            
            // Copy data back
            $wpdb->query("UPDATE $table_name SET eltern_fahren = eltern_fahren_temp");
            
            // Drop temporary column
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN eltern_fahren_temp");
            
            echo '<div class="notice notice-success"><p>✅ Datenbank erfolgreich korrigiert!</p></div>';
            
            // Reload page
            echo '<script>setTimeout(function() { location.reload(); }, 2000);</script>';
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>❌ Fehler: ' . $e->getMessage() . '</p></div>';
        }
    }
}