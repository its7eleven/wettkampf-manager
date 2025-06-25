<?php
/**
 * Test Admin Save
 * 
 * Erstelle diese Datei als test-admin-save.php im Plugin-Verzeichnis
 * Rufe sie auf über: deine-domain.de/wp-content/plugins/wettkampf-manager/test-admin-save.php?anmeldung_id=XX
 */

// WordPress laden
require_once('../../../wp-load.php');

// Nur für Admins
if (!current_user_can('manage_options')) {
    die('Keine Berechtigung');
}

$anmeldung_id = isset($_GET['anmeldung_id']) ? intval($_GET['anmeldung_id']) : 0;

if (!$anmeldung_id) {
    die('Bitte anmeldung_id in der URL angeben (z.B. ?anmeldung_id=12)');
}

// Lade Anmeldung
global $wpdb;
$table = $wpdb->prefix . 'wettkampf_anmeldung';
$anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $anmeldung_id));

if (!$anmeldung) {
    die('Anmeldung nicht gefunden');
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Test direct database update
    $update_data = array(
        'vorname' => sanitize_text_field($_POST['vorname']),
        'name' => sanitize_text_field($_POST['name']),
        'email' => sanitize_email($_POST['email']),
        'geschlecht' => sanitize_text_field($_POST['geschlecht']),
        'jahrgang' => intval($_POST['jahrgang']),
        'eltern_fahren' => sanitize_text_field($_POST['eltern_fahren']),
        'freie_plaetze' => intval($_POST['freie_plaetze'])
    );
    
    $result = $wpdb->update(
        $table,
        $update_data,
        array('id' => $anmeldung_id),
        array('%s', '%s', '%s', '%s', '%d', '%s', '%d'),
        array('%d')
    );
    
    if ($result === false) {
        $message = '<div style="color: red;">❌ Update fehlgeschlagen! DB Error: ' . $wpdb->last_error . '</div>';
    } else {
        $message = '<div style="color: green;">✅ Update erfolgreich! ' . $result . ' Zeile(n) aktualisiert.</div>';
        // Reload data
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $anmeldung_id));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Admin Save</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="email"], input[type="number"], select { width: 100%; padding: 8px; }
        .radio-group { display: flex; gap: 20px; margin: 10px 0; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        pre { background: #f1f1f1; padding: 10px; overflow: auto; }
        .current-value { color: #666; font-size: 0.9em; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Test Admin Save - Anmeldung #<?php echo $anmeldung_id; ?></h1>
    
    <?php echo $message; ?>
    
    <h2>Aktuelle Daten:</h2>
    <pre><?php print_r($anmeldung); ?></pre>
    
    <h2>Bearbeiten:</h2>
    <form method="post">
        <div class="form-group">
            <label>Vorname:</label>
            <input type="text" name="vorname" value="<?php echo esc_attr($anmeldung->vorname); ?>" required>
            <span class="current-value">Aktuell: <?php echo $anmeldung->vorname; ?></span>
        </div>
        
        <div class="form-group">
            <label>Name:</label>
            <input type="text" name="name" value="<?php echo esc_attr($anmeldung->name); ?>" required>
            <span class="current-value">Aktuell: <?php echo $anmeldung->name; ?></span>
        </div>
        
        <div class="form-group">
            <label>E-Mail:</label>
            <input type="email" name="email" value="<?php echo esc_attr($anmeldung->email); ?>" required>
            <span class="current-value">Aktuell: <?php echo $anmeldung->email; ?></span>
        </div>
        
        <div class="form-group">
            <label>Geschlecht:</label>
            <select name="geschlecht" required>
                <option value="maennlich" <?php selected($anmeldung->geschlecht, 'maennlich'); ?>>Männlich</option>
                <option value="weiblich" <?php selected($anmeldung->geschlecht, 'weiblich'); ?>>Weiblich</option>
            </select>
            <span class="current-value">Aktuell: <?php echo $anmeldung->geschlecht; ?></span>
        </div>
        
        <div class="form-group">
            <label>Jahrgang:</label>
            <input type="number" name="jahrgang" value="<?php echo esc_attr($anmeldung->jahrgang); ?>" min="1900" max="<?php echo date('Y'); ?>" required>
            <span class="current-value">Aktuell: <?php echo $anmeldung->jahrgang; ?></span>
        </div>
        
        <div class="form-group">
            <label>Transport:</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="eltern_fahren" value="ja" <?php checked($anmeldung->eltern_fahren, 'ja'); ?>>
                    Ja
                </label>
                <label>
                    <input type="radio" name="eltern_fahren" value="nein" <?php checked($anmeldung->eltern_fahren, 'nein'); ?>>
                    Nein
                </label>
                <label>
                    <input type="radio" name="eltern_fahren" value="direkt" <?php checked($anmeldung->eltern_fahren, 'direkt'); ?>>
                    Direkt
                </label>
            </div>
            <span class="current-value">Aktuell: <?php echo $anmeldung->eltern_fahren; ?></span>
        </div>
        
        <div class="form-group" id="freie_plaetze_group" style="<?php echo ($anmeldung->eltern_fahren === 'ja') ? '' : 'display: none;'; ?>">
            <label>Freie Plätze:</label>
            <input type="number" name="freie_plaetze" value="<?php echo esc_attr($anmeldung->freie_plaetze); ?>" min="0" max="10">
            <span class="current-value">Aktuell: <?php echo $anmeldung->freie_plaetze; ?></span>
        </div>
        
        <button type="submit">Speichern (Direkt in DB)</button>
    </form>
    
    <h2>Test WettkampfDatabase::save_registration:</h2>
    <?php
    if (isset($_POST['test_save_registration'])) {
        // Lade benötigte Klassen
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-database.php';
        require_once WETTKAMPF_PLUGIN_PATH . 'includes/utils/class-wettkampf-helpers.php';
        
        $test_data = array(
            'wettkampf_id' => $anmeldung->wettkampf_id,
            'vorname' => $_POST['vorname'],
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'geschlecht' => $_POST['geschlecht'],
            'jahrgang' => $_POST['jahrgang'],
            'eltern_fahren' => $_POST['eltern_fahren'],
            'freie_plaetze' => $_POST['freie_plaetze']
        );
        
        echo '<pre>Test data: ' . print_r($test_data, true) . '</pre>';
        
        $result = WettkampfDatabase::save_registration($test_data, $anmeldung_id);
        
        if ($result !== false) {
            echo '<div style="color: green;">✅ WettkampfDatabase::save_registration erfolgreich! Result: ' . $result . '</div>';
        } else {
            echo '<div style="color: red;">❌ WettkampfDatabase::save_registration fehlgeschlagen!</div>';
        }
    }
    ?>
    
    <form method="post" style="margin-top: 20px;">
        <input type="hidden" name="test_save_registration" value="1">
        <input type="hidden" name="vorname" value="<?php echo esc_attr($anmeldung->vorname); ?>">
        <input type="hidden" name="name" value="<?php echo esc_attr($anmeldung->name); ?>">
        <input type="hidden" name="email" value="<?php echo esc_attr($anmeldung->email); ?>">
        <input type="hidden" name="geschlecht" value="<?php echo esc_attr($anmeldung->geschlecht); ?>">
        <input type="hidden" name="jahrgang" value="<?php echo esc_attr($anmeldung->jahrgang); ?>">
        <input type="hidden" name="eltern_fahren" value="<?php echo esc_attr($anmeldung->eltern_fahren); ?>">
        <input type="hidden" name="freie_plaetze" value="<?php echo esc_attr($anmeldung->freie_plaetze); ?>">
        <button type="submit">Test WettkampfDatabase::save_registration</button>
    </form>
    
    <p style="margin-top: 30px;">
        <a href="<?php echo admin_url('admin.php?page=wettkampf-anmeldungen&edit=' . $anmeldung_id); ?>">Zurück zur normalen Bearbeitung</a>
    </p>
    
    <script>
    jQuery(document).ready(function($) {
        $('input[name="eltern_fahren"]').on('change', function() {
            if ($(this).val() === 'ja') {
                $('#freie_plaetze_group').show();
            } else {
                $('#freie_plaetze_group').hide();
                $('input[name="freie_plaetze"]').val('0');
            }
        });
    });
    </script>
</body>
</html>