<?php
/**
 * Debug-Script für AJAX-Handler
 * Lege diese Datei im Plugin-Hauptverzeichnis ab und rufe sie auf
 */

// WordPress Bootstrap
require_once('../../../wp-load.php');

// Nur Admin darf dieses Script ausführen
if (!current_user_can('manage_options')) {
    die('Keine Berechtigung.');
}

global $wpdb;

echo "<h1>Wettkampf Manager - AJAX Debug</h1>";

// 1. Prüfe ob alle benötigten Klassen existieren
echo "<h2>1. Klassen-Check:</h2>";
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

// 2. Prüfe Datenbank-Struktur
echo "<h2>2. Datenbank-Struktur:</h2>";
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
    }
} else {
    echo "❌ Spalte 'eltern_fahren' nicht gefunden!<br>";
}

// 3. Test-Anmeldung simulieren
echo "<h2>3. Test-Anmeldung:</h2>";

// Hole einen Wettkampf
$wettkampf = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wettkampf WHERE datum >= CURDATE() LIMIT 1");

if ($wettkampf) {
    echo "Test-Wettkampf: {$wettkampf->name} (ID: {$wettkampf->id})<br>";
    
    // Simuliere Anmeldedaten
    $test_data = array(
        'wettkampf_id' => $wettkampf->id,
        'vorname' => 'Test',
        'name' => 'User',
        'email' => 'test@example.com',
        'geschlecht' => 'maennlich',
        'jahrgang' => 2016,
        'eltern_fahren' => 'ja',
        'freie_plaetze' => 2,
        'disziplinen' => array()
    );
    
    echo "<h3>Test-Daten:</h3>";
    echo "<pre>" . print_r($test_data, true) . "</pre>";
    
    // Versuche zu speichern
    if (class_exists('WettkampfDatabase')) {
        try {
            // Aktiviere Error Reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            echo "<h3>Speichere Test-Anmeldung...</h3>";
            $result = WettkampfDatabase::save_registration($test_data);
            
            if ($result) {
                echo "✅ Test-Anmeldung erfolgreich gespeichert (ID: $result)<br>";
                
                // Lösche Test-Anmeldung wieder
                WettkampfDatabase::delete_registration($result);
                echo "✅ Test-Anmeldung wieder gelöscht<br>";
            } else {
                echo "❌ Fehler beim Speichern der Test-Anmeldung<br>";
                echo "Letzter DB-Fehler: " . $wpdb->last_error . "<br>";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "<br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    } else {
        echo "❌ WettkampfDatabase Klasse nicht gefunden<br>";
    }
} else {
    echo "❌ Kein aktiver Wettkampf gefunden<br>";
}

// 4. Prüfe AJAX-Handler
echo "<h2>4. AJAX-Handler Check:</h2>";

// Prüfe ob AJAX Actions registriert sind
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

// 5. PHP Error Log
echo "<h2>5. Letzte PHP Fehler:</h2>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $lines = array_slice(file($error_log), -20);
    echo "<pre>";
    foreach ($lines as $line) {
        if (strpos($line, 'wettkampf') !== false || strpos($line, 'Wettkampf') !== false) {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "Kein Zugriff auf Error Log<br>";
}

// 6. Test direkte AJAX-Response
echo "<h2>6. Test AJAX-Response:</h2>";
echo '<button onclick="testAjax()">Test AJAX-Aufruf</button>';
echo '<div id="ajax-result" style="margin-top: 20px; padding: 10px; background: #f0f0f0;"></div>';

?>

<script>
function testAjax() {
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'wettkampf_anmeldung',
            nonce: '<?php echo wp_create_nonce('wettkampf_ajax'); ?>',
            wettkampf_id: <?php echo $wettkampf ? $wettkampf->id : 0; ?>,
            vorname: 'Test',
            name: 'User',
            email: 'test@example.com',
            geschlecht: 'maennlich',
            jahrgang: 2016,
            eltern_fahren: 'ja',
            freie_plaetze: 2,
            disziplinen: []
        },
        dataType: 'text', // Erstmal als Text um zu sehen was zurückkommt
        success: function(response) {
            document.getElementById('ajax-result').innerHTML = '<h3>Response:</h3><pre>' + response + '</pre>';
            console.log('Raw response:', response);
            
            // Versuche als JSON zu parsen
            try {
                var json = JSON.parse(response);
                console.log('Parsed JSON:', json);
            } catch(e) {
                console.error('JSON Parse Error:', e);
            }
        },
        error: function(xhr, status, error) {
            document.getElementById('ajax-result').innerHTML = '<h3>Error:</h3><pre>Status: ' + status + '\nError: ' + error + '\nResponse: ' + xhr.responseText + '</pre>';
            console.error('AJAX Error:', {status, error, response: xhr.responseText});
        }
    });
}
</script>

<?php
echo "<hr>";
echo "<p><a href='" . admin_url('admin.php?page=wettkampf-anmeldungen') . "'>Zurück zur Anmeldungsverwaltung</a></p>";
?>