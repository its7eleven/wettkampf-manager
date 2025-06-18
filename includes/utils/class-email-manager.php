<?php
/**
 * Email management utility - KORRIGIERT für Auto-Export
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EmailManager {
    
    /**
     * Send registration confirmation email
     */
    public function send_confirmation_email($anmeldung_id) {
        global $wpdb;
        
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum, w.ort, w.beschreibung, w.event_link
            FROM {$tables['anmeldung']} a 
            JOIN {$tables['wettkampf']} w ON a.wettkampf_id = w.id 
            WHERE a.id = %d
        ", $anmeldung_id));
        
        if (!$anmeldung) {
            return false;
        }
        
        // Load disciplines for the registration
        $anmeldung_disziplinen = WettkampfDatabase::get_registration_disciplines($anmeldung_id);
        
        // Create email content
        $subject = 'Anmeldebestätigung: ' . $anmeldung->wettkampf_name;
        $message = $this->build_confirmation_message($anmeldung, $anmeldung_disziplinen);
        
        return $this->send_email($anmeldung->email, $subject, $message);
    }
    
    /**
     * Build confirmation email message
     */
    private function build_confirmation_message($anmeldung, $disciplines) {
        $message = "Hallo " . $anmeldung->vorname . ",\n\n";
        $message .= "deine Anmeldung für den Wettkampf wurde erfolgreich registriert.\n\n";
        
        // Competition details
        $message .= "Wettkampf: " . $anmeldung->wettkampf_name . "\n";
        $message .= "Datum: " . WettkampfHelpers::format_german_date($anmeldung->datum) . "\n";
        $message .= "Ort: " . $anmeldung->ort . "\n\n";
        
        // Registration details
        $message .= "Deine Anmeldedaten:\n";
        $message .= "Name: " . $anmeldung->vorname . " " . $anmeldung->name . "\n";
        $message .= "E-Mail: " . $anmeldung->email . "\n";
        $message .= "Geschlecht: " . $anmeldung->geschlecht . "\n";
        $message .= "Jahrgang: " . $anmeldung->jahrgang . "\n";
        $message .= "Kategorie: " . CategoryCalculator::calculate($anmeldung->jahrgang) . "\n";
        
        // Transport-Information
        $transport_text = $this->get_transport_text($anmeldung->eltern_fahren, $anmeldung->freie_plaetze);
        $message .= "Transport: " . $transport_text . "\n";
        
        // Disciplines
        if (is_array($disciplines) && !empty($disciplines)) {
            $discipline_names = array();
            foreach ($disciplines as $d) {
                if (is_object($d) && isset($d->name) && !empty($d->name)) {
                    $discipline_names[] = $d->name;
                }
            }
            if (!empty($discipline_names)) {
                $message .= "Disziplinen: " . implode(', ', $discipline_names) . "\n";
            }
        }
        $message .= "\n";
        
        // Additional info
        if ($anmeldung->beschreibung) {
            $message .= "Beschreibung:\n" . $anmeldung->beschreibung . "\n\n";
        }
        
        if ($anmeldung->event_link) {
            $message .= "Weitere Informationen: " . $anmeldung->event_link . "\n\n";
        }
        
        $message .= "Du kannst deine Anmeldung jederzeit auf unserer Website bearbeiten oder abmelden.\n";
        $message .= "Klicke dazu einfach auf den Bleistift neben deinem Namen in der Teilnehmerliste.\n\n";
        $message .= "Viel Erfolg beim Wettkampf!\n";
        
        return $message;
    }
    
    /**
     * Transport-Text generieren
     */
    private function get_transport_text($eltern_fahren, $freie_plaetze) {
        switch ($eltern_fahren) {
            case 'ja':
                return "Ja (" . $freie_plaetze . " Plätze)";
            case 'nein':
                return "Nein";
            case 'direkt':
                return "Wir fahren direkt";
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
     * Send automatic export email - KORRIGIERT
     */
    public function send_automatic_export($wettkampf, $recipient_email) {
        WettkampfHelpers::log_error('send_automatic_export: Start für Wettkampf ' . $wettkampf->name . ' an ' . $recipient_email);
        
        // Stelle sicher, dass WettkampfDatabase geladen ist
        if (!class_exists('WettkampfDatabase')) {
            require_once WETTKAMPF_PLUGIN_PATH . 'includes/core/class-wettkampf-database.php';
        }
        
        $csv_content = $this->generate_csv_export($wettkampf->id);
        
        if (!$csv_content) {
            WettkampfHelpers::log_error('Failed to generate CSV export for competition ' . $wettkampf->id);
            return false;
        }
        
        WettkampfHelpers::log_error('CSV-Export generiert, Länge: ' . strlen($csv_content) . ' bytes');
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['path'];
        
        // Stelle sicher, dass das Verzeichnis existiert
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $safe_name = WettkampfHelpers::sanitize_filename($wettkampf->name);
        $filename = $safe_name . '_anmeldungen_' . date('Y-m-d') . '.csv';
        $temp_file = $temp_dir . '/' . $filename;
        
        $bytes_written = file_put_contents($temp_file, $csv_content);
        
        if ($bytes_written === false) {
            WettkampfHelpers::log_error('Konnte temporäre Datei nicht schreiben: ' . $temp_file);
            return false;
        }
        
        WettkampfHelpers::log_error('Temporäre Datei geschrieben: ' . $temp_file . ' (' . $bytes_written . ' bytes)');
        
        // Build email
        $subject = 'Anmeldungen für ' . $wettkampf->name . ' (Anmeldeschluss abgelaufen)';
        $message = $this->build_export_message($wettkampf);
        
        $result = $this->send_email($recipient_email, $subject, $message, array($temp_file));
        
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
            WettkampfHelpers::log_error('Temporäre Datei gelöscht');
        }
        
        if ($result) {
            WettkampfHelpers::log_error('Automatic export sent for competition ' . $wettkampf->name . ' to ' . $recipient_email);
        } else {
            WettkampfHelpers::log_error('Failed to send automatic export for competition ' . $wettkampf->name);
        }
        
        return $result;
    }
    
    /**
     * Build export email message
     */
    private function build_export_message($wettkampf) {
        $message = "Hallo,\n\n";
        $message .= "die Anmeldefrist für den Wettkampf '" . $wettkampf->name . "' ist abgelaufen.\n\n";
        
        $message .= "Wettkampf-Details:\n";
        $message .= "- Name: " . $wettkampf->name . "\n";
        $message .= "- Datum: " . WettkampfHelpers::format_german_date($wettkampf->datum) . "\n";
        $message .= "- Ort: " . $wettkampf->ort . "\n";
        $message .= "- Anmeldeschluss: " . WettkampfHelpers::format_german_date($wettkampf->anmeldeschluss) . "\n";
        $message .= "- Anzahl Anmeldungen: " . $wettkampf->anmeldungen_count . "\n\n";
        
        $message .= "Im Anhang findest du die CSV-Datei mit allen Anmeldungen.\n";
        $message .= "Diese kann in Excel, LibreOffice oder jedem anderen Tabellenkalkulationsprogramm geöffnet werden.\n\n";
        $message .= "Tipp: In Excel verwende 'Daten > Text in Spalten' mit Semikolon als Trennzeichen für beste Formatierung.\n\n";
        $message .= "Diese E-Mail wurde automatisch vom Wettkampf Manager generiert.\n";
        
        return $message;
    }
    
    /**
     * Generate CSV export content
     */
    private function generate_csv_export($wettkampf_id) {
        global $wpdb;
        
        $tables = WettkampfDatabase::get_table_names();
        
        // Get competition info
        $wettkampf = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['wettkampf']} WHERE id = %d", $wettkampf_id));
        if (!$wettkampf) {
            WettkampfHelpers::log_error('generate_csv_export: Wettkampf nicht gefunden');
            return false;
        }
        
        // Get registrations
        $anmeldungen = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum as wettkampf_datum, w.ort as wettkampf_ort
            FROM {$tables['anmeldung']} a 
            JOIN {$tables['wettkampf']} w ON a.wettkampf_id = w.id 
            WHERE a.wettkampf_id = %d
            ORDER BY a.anmeldedatum ASC
        ", $wettkampf_id));
        
        WettkampfHelpers::log_error('generate_csv_export: ' . count($anmeldungen) . ' Anmeldungen gefunden');
        
        // Start output buffering
        ob_start();
        
        // Output UTF-8 BOM for proper encoding
        echo "\xEF\xBB\xBF";
        
        // CSV headers
        $headers = array(
            'Vorname', 'Name', 'E-Mail', 'Geschlecht', 'Jahrgang', 'Kategorie',
            'Wettkampf', 'Wettkampf Datum', 'Wettkampf Ort', 'Transport',
            'Freie Plätze', 'Disziplinen', 'Anmeldedatum'
        );
        
        // Output headers
        echo implode(';', $headers) . "\r\n";
        
        // Output data
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
            
            // Transport-Information für CSV
            $transport_text = $this->get_transport_text($anmeldung->eltern_fahren, $anmeldung->freie_plaetze);
            
            $row = array(
                WettkampfHelpers::csv_escape($anmeldung->vorname),
                WettkampfHelpers::csv_escape($anmeldung->name),
                WettkampfHelpers::csv_escape($anmeldung->email),
                WettkampfHelpers::csv_escape($anmeldung->geschlecht),
                $anmeldung->jahrgang,
                WettkampfHelpers::csv_escape($user_category),
                WettkampfHelpers::csv_escape($anmeldung->wettkampf_name),
                WettkampfHelpers::format_german_date($anmeldung->wettkampf_datum),
                WettkampfHelpers::csv_escape($anmeldung->wettkampf_ort),
                WettkampfHelpers::csv_escape($transport_text),
                ($anmeldung->eltern_fahren === 'ja') ? $anmeldung->freie_plaetze : '',
                WettkampfHelpers::csv_escape(!empty($discipline_names) ? implode(', ', $discipline_names) : ''),
                WettkampfHelpers::format_german_date($anmeldung->anmeldedatum, true)
            );
            
            echo implode(';', $row) . "\r\n";
        }
        
        $content = ob_get_clean();
        
        WettkampfHelpers::log_error('generate_csv_export: CSV generiert, Länge: ' . strlen($content));
        
        return $content;
    }
    
    /**
     * Send email with optional attachments - VERBESSERT mit mehr Debug-Info
     */
    private function send_email($to, $subject, $message, $attachments = array()) {
        $sender_email = WettkampfHelpers::get_option('sender_email', get_option('admin_email'));
        $sender_name = WettkampfHelpers::get_option('sender_name', get_option('blogname'));
        
        WettkampfHelpers::log_error('send_email: Von ' . $sender_name . ' <' . $sender_email . '> an ' . $to);
        WettkampfHelpers::log_error('send_email: Betreff: ' . $subject);
        
        if (!empty($attachments)) {
            WettkampfHelpers::log_error('send_email: Anhänge: ' . implode(', ', $attachments));
            
            // Prüfe ob Anhänge existieren
            foreach ($attachments as $attachment) {
                if (!file_exists($attachment)) {
                    WettkampfHelpers::log_error('send_email: WARNUNG - Anhang existiert nicht: ' . $attachment);
                } else {
                    $size = filesize($attachment);
                    WettkampfHelpers::log_error('send_email: Anhang OK: ' . basename($attachment) . ' (' . $size . ' bytes)');
                }
            }
        }
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        $result = wp_mail($to, $subject, $message, $headers, $attachments);
        
        if ($result) {
            WettkampfHelpers::log_error('send_email: E-Mail erfolgreich gesendet');
        } else {
            WettkampfHelpers::log_error('send_email: FEHLER beim E-Mail-Versand');
            
            // Prüfe PHP mail() Funktion
            if (!function_exists('mail')) {
                WettkampfHelpers::log_error('send_email: PHP mail() Funktion nicht verfügbar!');
            }
            
            // Prüfe wp_mail Fehler
            global $phpmailer;
            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                WettkampfHelpers::log_error('send_email: PHPMailer Fehler: ' . $phpmailer->ErrorInfo);
            }
        }
        
        return $result;
    }
    
    /**
     * Test email configuration
     */
    public function test_email_configuration() {
        $test_email = get_option('admin_email');
        $subject = 'Wettkampf Manager - Test E-Mail';
        $message = "Hallo,\n\ndies ist eine Test-E-Mail vom Wettkampf Manager.\n\nWenn du diese E-Mail erhalten hast, funktioniert die E-Mail-Konfiguration korrekt.\n\nViele Grüße\nWettkampf Manager";
        
        return $this->send_email($test_email, $subject, $message);
    }
    
    /**
     * Validate email address
     */
    public function validate_email($email) {
        return is_email($email);
    }
}