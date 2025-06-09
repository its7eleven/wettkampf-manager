<?php
/**
 * Email management utility
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
        $message .= "Eltern können fahren: " . ($anmeldung->eltern_fahren ? 'Ja (' . $anmeldung->freie_plaetze . ' Plätze)' : 'Nein') . "\n";
        
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
     * Send automatic export email
     */
    public function send_automatic_export($wettkampf, $recipient_email) {
        $csv_content = $this->generate_csv_export($wettkampf->id);
        
        if (!$csv_content) {
            WettkampfHelpers::log_error('Failed to generate CSV export for competition ' . $wettkampf->id);
            return false;
        }
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $safe_name = WettkampfHelpers::sanitize_filename($wettkampf->name);
        $filename = $safe_name . '_anmeldungen_' . date('Y-m-d') . '.csv';
        $temp_file = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents($temp_file, $csv_content);
        
        // Build email
        $subject = 'Anmeldungen für ' . $wettkampf->name . ' (Anmeldeschluss abgelaufen)';
        $message = $this->build_export_message($wettkampf);
        
        $result = $this->send_email($recipient_email, $subject, $message, array($temp_file));
        
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
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
        
        // Start output buffering
        ob_start();
        
        // Output UTF-8 BOM for proper encoding
        echo "\xEF\xBB\xBF";
        
        // CSV headers
        $headers = array(
            'Vorname', 'Name', 'E-Mail', 'Geschlecht', 'Jahrgang', 'Kategorie',
            'Wettkampf', 'Wettkampf Datum', 'Wettkampf Ort', 'Eltern fahren',
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
                $anmeldung->eltern_fahren ? 'Ja' : 'Nein',
                $anmeldung->eltern_fahren ? $anmeldung->freie_plaetze : '',
                WettkampfHelpers::csv_escape(!empty($discipline_names) ? implode(', ', $discipline_names) : ''),
                WettkampfHelpers::format_german_date($anmeldung->anmeldedatum, true)
            );
            
            echo implode(';', $row) . "\r\n";
        }
        
        return ob_get_clean();
    }
    
    /**
     * Send email with optional attachments
     */
    private function send_email($to, $subject, $message, $attachments = array()) {
        $sender_email = WettkampfHelpers::get_option('sender_email', get_option('admin_email'));
        $sender_name = WettkampfHelpers::get_option('sender_name', get_option('blogname'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        return wp_mail($to, $subject, $message, $headers, $attachments);
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