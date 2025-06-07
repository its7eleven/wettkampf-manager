<?php
/**
 * Frontend functionality for Wettkampf Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WettkampfFrontend {
    
    public function __construct() {
        // Constructor for frontend functionality
    }
    
    public function display_wettkampf_liste($atts) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wettkampf';
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        // Nur zukünftige Wettkämpfe und bis einen Tag nach Durchführung anzeigen
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $wettkaempfe = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM $table_name w 
            LEFT JOIN $table_anmeldung a ON w.id = a.wettkampf_id 
            WHERE w.datum >= %s 
            GROUP BY w.id 
            ORDER BY w.datum ASC
        ", $yesterday));
        
        ob_start();
        ?>
        <div class="wettkampf-liste">
            <?php if (empty($wettkaempfe)): ?>
                <p>Derzeit keine Wettkämpfe ausgeschrieben.</p>
            <?php else: ?>
                <?php foreach ($wettkaempfe as $wettkampf): ?>
                    <div class="wettkampf-card" id="wettkampf-<?php echo $wettkampf->id; ?>">
                        <div class="wettkampf-header">
                            <div class="wettkampf-summary">
                                <h3><?php echo esc_html($wettkampf->name); ?></h3>
                                <div class="wettkampf-basic-info">
                                    <span class="datum-info"><strong>📅 <?php echo date('d.m.Y', strtotime($wettkampf->datum)); ?></strong></span>
                                    <span class="ort-info"><strong>📍 <?php echo esc_html($wettkampf->ort); ?></strong></span>
                                    <?php if ($wettkampf->lizenziert): ?>
                                        <span class="lizenziert-badge">Lizenziert</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="details-toggle" data-wettkampf-id="<?php echo $wettkampf->id; ?>">
                                <span class="toggle-text">Details anzeigen</span>
                                <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                        
                        <div class="wettkampf-details" id="details-<?php echo $wettkampf->id; ?>" style="display: none;">
                            <div class="wettkampf-info">
                                <div class="info-row">
                                    <strong>Anmeldeschluss:</strong> <?php echo date('d.m.Y', strtotime($wettkampf->anmeldeschluss)); ?>
                                </div>
                                <?php if ($wettkampf->beschreibung): ?>
                                <div class="info-row">
                                    <strong>Beschreibung:</strong><br>
                                    <?php echo nl2br(esc_html($wettkampf->beschreibung)); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($wettkampf->startberechtigte): ?>
                                <div class="info-row">
                                    <strong>Startberechtigte:</strong><br>
                                    <?php echo nl2br(esc_html($wettkampf->startberechtigte)); ?>
                                </div>
                                <?php endif; ?>
                                <?php 
                                // Disziplinen für den Wettkampf anzeigen
                                $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
                                $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
                                $wettkampf_disziplinen = $wpdb->get_results($wpdb->prepare("
                                    SELECT d.name, d.beschreibung 
                                    FROM $table_zuordnung z 
                                    JOIN $table_disziplinen d ON z.disziplin_id = d.id 
                                    WHERE z.wettkampf_id = %d AND d.aktiv = 1
                                    ORDER BY d.sortierung ASC, d.name ASC
                                ", $wettkampf->id));
                                
                                if (!empty($wettkampf_disziplinen)): ?>
                                <div class="info-row">
                                    <strong>Disziplinen:</strong><br>
                                    <?php foreach ($wettkampf_disziplinen as $disziplin): ?>
                                        • <?php echo esc_html($disziplin->name); ?>
                                        <?php if ($disziplin->beschreibung): ?>
                                            <small>(<?php echo esc_html($disziplin->beschreibung); ?>)</small>
                                        <?php endif; ?><br>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($wettkampf->event_link): ?>
                                <div class="info-row">
                                    <strong>Weitere Infos:</strong> <a href="<?php echo esc_url($wettkampf->event_link); ?>" target="_blank">Link zum Event</a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="anmeldung-section">
                                <?php 
                                $anmeldeschluss_passed = strtotime($wettkampf->anmeldeschluss) < strtotime('today');
                                if ($anmeldeschluss_passed): 
                                ?>
                                    <p class="anmeldung-geschlossen">Anmeldung ist geschlossen</p>
                                <?php else: ?>
                                    <button class="anmelde-button" data-wettkampf-id="<?php echo $wettkampf->id; ?>">
                                        <span>🏃‍♂️</span> Jetzt anmelden
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($wettkampf->anmeldungen_count > 0): ?>
                            <div class="angemeldete-teilnehmer">
                                <h4>Angemeldet (<?php echo $wettkampf->anmeldungen_count; ?>)</h4>
                                <div class="teilnehmer-liste" id="teilnehmer-<?php echo $wettkampf->id; ?>">
                                    <?php 
                                    $anmeldungen = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE wettkampf_id = %d ORDER BY anmeldedatum ASC", $wettkampf->id));
                                    foreach ($anmeldungen as $anmeldung): 
                                    ?>
                                        <div class="teilnehmer-item">
                                            <span class="teilnehmer-name"><?php echo esc_html($anmeldung->vorname . ' ' . $anmeldung->name); ?></span>
                                            <div class="teilnehmer-actions">
                                                <?php 
                                                $anmeldeschluss_passed = strtotime($wettkampf->anmeldeschluss) < strtotime('today');
                                                if (!$anmeldeschluss_passed): 
                                                ?>
                                                    <button class="edit-anmeldung" data-anmeldung-id="<?php echo $anmeldung->id; ?>" title="Anmeldung bearbeiten">✏️</button>
                                                <?php else: ?>
                                                    <button class="view-anmeldung" data-anmeldung-id="<?php echo $anmeldung->id; ?>" title="Anmeldung einsehen">🔍</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php 
                    // Add expired class if registration deadline has passed
                    $anmeldeschluss_passed = strtotime($wettkampf->anmeldeschluss) < strtotime('today');
                    if ($anmeldeschluss_passed): 
                    ?>
                    <script>
                        document.querySelector('#wettkampf-<?php echo $wettkampf->id; ?>').classList.add('expired');
                    </script>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Include all modals here -->
        <?php echo $this->get_modal_html(); ?>
        
        <?php
        return ob_get_clean();
    }
    
    private function get_modal_html() {
        ob_start();
        ?>
        <!-- Anmeldung Modal -->
        <div id="anmeldung-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Wettkampf Anmeldung</h2>
                <form id="anmeldung-form">
                    <input type="hidden" id="wettkampf_id" name="wettkampf_id" value="">
                    
                    <div class="form-group">
                        <label for="vorname">Vorname *</label>
                        <input type="text" id="vorname" name="vorname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-Mail Adresse *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="geschlecht">Geschlecht *</label>
                        <select id="geschlecht" name="geschlecht" required>
                            <option value="">Bitte wählen</option>
                            <option value="männlich">Männlich</option>
                            <option value="weiblich">Weiblich</option>
                            <option value="divers">Divers</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="jahrgang" name="jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                    </div>
                    
                    <!-- Disziplinen werden dynamisch geladen - NEUE POSITION -->
                    <div class="form-group" id="disziplinen_group" style="display: none;">
                        <label>Disziplinen *</label>
                        <div id="disziplinen_container"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Können deine Eltern fahren? *</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="eltern_fahren" value="1" required>
                                <span>Ja</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="eltern_fahren" value="0" required>
                                <span>Nein</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="freie_plaetze_group" style="display: none;">
                        <label for="freie_plaetze">Anzahl freie Plätze</label>
                        <input type="number" id="freie_plaetze" name="freie_plaetze" min="0" max="10" placeholder="z.B. 2">
                    </div>
                    
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="<?php echo get_option('wettkampf_recaptcha_site_key'); ?>"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Abbrechen</button>
                        <button type="submit" class="submit-button">Anmelden</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Mutation Modal -->
        <div id="mutation-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Anmeldung bearbeiten</h2>
                <form id="mutation-verify-form">
                    <input type="hidden" id="mutation_anmeldung_id" name="anmeldung_id" value="">
                    
                    <p>Zur Verifikation bitte E-Mail und Jahrgang eingeben:</p>
                    
                    <div class="form-group">
                        <label for="verify_email">E-Mail Adresse *</label>
                        <input type="email" id="verify_email" name="verify_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="verify_jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="verify_jahrgang" name="verify_jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Abbrechen</button>
                        <button type="submit" class="submit-button">Verifizieren</button>
                    </div>
                </form>
                
                <form id="mutation-edit-form" style="display: none;">
                    <input type="hidden" id="edit_anmeldung_id" name="anmeldung_id" value="">
                    
                    <div class="form-group">
                        <label for="edit_vorname">Vorname *</label>
                        <input type="text" id="edit_vorname" name="vorname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_name">Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">E-Mail Adresse *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_geschlecht">Geschlecht *</label>
                        <select id="edit_geschlecht" name="geschlecht" required>
                            <option value="männlich">Männlich</option>
                            <option value="weiblich">Weiblich</option>
                            <option value="divers">Divers</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="edit_jahrgang" name="jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Können deine Eltern fahren? *</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="eltern_fahren" value="1" required>
                                <span>Ja</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="eltern_fahren" value="0" required>
                                <span>Nein</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="edit_freie_plaetze_group" style="display: none;">
                        <label for="edit_freie_plaetze">Anzahl freie Plätze</label>
                        <input type="number" id="edit_freie_plaetze" name="freie_plaetze" min="0" max="10" placeholder="z.B. 2">
                    </div>
                    
                    <!-- Disziplinen für Bearbeitung -->
                    <div class="form-group" id="edit_disziplinen_group" style="display: none;">
                        <label>Disziplinen *</label>
                        <div id="edit_disziplinen_container"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Abbrechen</button>
                        <button type="button" class="delete-button">Abmelden</button>
                        <button type="submit" class="submit-button">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- View-Only Modal -->
        <div id="view-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Anmeldung einsehen</h2>
                <form id="view-verify-form">
                    <input type="hidden" id="view_anmeldung_id" name="anmeldung_id" value="">
                    
                    <p>Zur Verifikation bitte E-Mail und Jahrgang eingeben:</p>
                    
                    <div class="form-group">
                        <label for="view_verify_email">E-Mail Adresse *</label>
                        <input type="email" id="view_verify_email" name="verify_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="view_verify_jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="view_verify_jahrgang" name="verify_jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Abbrechen</button>
                        <button type="submit" class="submit-button">Anzeigen</button>
                    </div>
                </form>
                
                <div id="view-display" style="display: none;">
                    <div class="view-info">
                        <h3>Anmeldedaten</h3>
                        <div class="info-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 15px;">
                            <strong>Vorname:</strong> <span id="view_vorname"></span>
                            <strong>Name:</strong> <span id="view_name"></span>
                            <strong>E-Mail:</strong> <span id="view_email"></span>
                            <strong>Geschlecht:</strong> <span id="view_geschlecht"></span>
                            <strong>Jahrgang:</strong> <span id="view_jahrgang"></span>
                            <strong>Eltern fahren:</strong> <span id="view_eltern_fahren"></span>
                            <strong>Freie Plätze:</strong> <span id="view_freie_plaetze"></span>
                            <strong>Disziplinen:</strong> <span id="view_disziplinen"></span>
                            <strong>Anmeldedatum:</strong> <span id="view_anmeldedatum"></span>
                        </div>
                        <p style="color: #666; font-style: italic; margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            ℹ️ Die Anmeldefrist ist abgelaufen. Änderungen sind nicht mehr möglich.
                        </p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function display_anmeldung_form($atts) {
        // Shortcode for standalone registration form if needed
        return '<div class="wettkampf-anmeldung-form">Anmeldungsformular wird hier angezeigt</div>';
    }
    
    public function process_anmeldung() {
        // Verify nonce and recaptcha
        if (!wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        if (!$this->verify_recaptcha($_POST['g-recaptcha-response'])) {
            wp_die(json_encode(array('success' => false, 'message' => 'reCAPTCHA Verifikation fehlgeschlagen')));
        }
        
        // Check for duplicate registrations (same email + same first name + same birth year)
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_anmeldung WHERE wettkampf_id = %d AND email = %s AND vorname = %s AND jahrgang = %d",
            intval($_POST['wettkampf_id']),
            sanitize_email($_POST['email']),
            sanitize_text_field($_POST['vorname']),
            intval($_POST['jahrgang'])
        ));
        
        if ($existing > 0) {
            wp_die(json_encode(array('success' => false, 'message' => 'Eine Person mit dieser E-Mail, diesem Vornamen und Jahrgang ist bereits angemeldet!')));
        }
        
        $data = array(
            'wettkampf_id' => intval($_POST['wettkampf_id']),
            'vorname' => sanitize_text_field($_POST['vorname']),
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'geschlecht' => sanitize_text_field($_POST['geschlecht']),
            'jahrgang' => intval($_POST['jahrgang']),
            'eltern_fahren' => intval($_POST['eltern_fahren']),
            'freie_plaetze' => (intval($_POST['eltern_fahren']) === 1) ? intval($_POST['freie_plaetze']) : 0
        );
        
        $result = $wpdb->insert($table_anmeldung, $data);
        
        if ($result) {
            $anmeldung_id = $wpdb->insert_id;
            
            // Disziplinen-Zuordnungen speichern
            if (isset($_POST['disziplinen']) && is_array($_POST['disziplinen'])) {
                $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
                foreach ($_POST['disziplinen'] as $disziplin_id) {
                    $wpdb->insert($table_anmeldung_disziplinen, array(
                        'anmeldung_id' => $anmeldung_id,
                        'disziplin_id' => intval($disziplin_id)
                    ));
                }
            }
            
            // Send confirmation email
            $this->send_confirmation_email($anmeldung_id);
            
            wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich!')));
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Fehler bei der Anmeldung')));
        }
    }
    
    public function process_mutation() {
        if (!wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        
        if (isset($_POST['action_type']) && $_POST['action_type'] === 'verify') {
            // Rate limiting: check if too many attempts from same IP
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $recent_attempts = get_transient('wettkampf_mutation_attempts_' . md5($user_ip));
            if ($recent_attempts && $recent_attempts >= 5) {
                wp_die(json_encode(array('success' => false, 'message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.')));
            }
            
            // Verify email and year only - NO CAPTCHA
            $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $anmeldung_id));
            
            if ($anmeldung && $anmeldung->email === sanitize_email($_POST['verify_email']) && $anmeldung->jahrgang == intval($_POST['verify_jahrgang'])) {
                // Success - clear attempts counter and load disciplines
                delete_transient('wettkampf_mutation_attempts_' . md5($user_ip));
                
                // Disziplinen für diese Anmeldung laden
                $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
                $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
                    SELECT disziplin_id 
                    FROM $table_anmeldung_disziplinen 
                    WHERE anmeldung_id = %d
                ", $anmeldung_id));
                $anmeldung->disziplinen = array_map(function($d) { return $d->disziplin_id; }, $anmeldung_disziplinen);
                
                wp_die(json_encode(array('success' => true, 'data' => $anmeldung)));
            } else {
                // Failed attempt - increment counter
                $attempts = $recent_attempts ? $recent_attempts + 1 : 1;
                set_transient('wettkampf_mutation_attempts_' . md5($user_ip), $attempts, 10 * MINUTE_IN_SECONDS);
                wp_die(json_encode(array('success' => false, 'message' => 'E-Mail oder Jahrgang stimmen nicht überein')));
            }
        } elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
            // Delete registration - verify ownership first
            $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $anmeldung_id));
            if (!$anmeldung) {
                wp_die(json_encode(array('success' => false, 'message' => 'Anmeldung nicht gefunden')));
            }
            
            // Erst Disziplin-Zuordnungen löschen
            $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
            $wpdb->delete($table_anmeldung_disziplinen, array('anmeldung_id' => $anmeldung_id));
            
            // Dann Anmeldung löschen
            $result = $wpdb->delete($table_anmeldung, array('id' => $anmeldung_id));
            if ($result) {
                wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich gelöscht')));
            } else {
                wp_die(json_encode(array('success' => false, 'message' => 'Fehler beim Löschen')));
            }
        } else {
            // Update registration - verify ownership first
            $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $anmeldung_id));
            if (!$anmeldung) {
                wp_die(json_encode(array('success' => false, 'message' => 'Anmeldung nicht gefunden')));
            }
            
            $data = array(
                'vorname' => sanitize_text_field($_POST['vorname']),
                'name' => sanitize_text_field($_POST['name']),
                'email' => sanitize_email($_POST['email']),
                'geschlecht' => sanitize_text_field($_POST['geschlecht']),
                'jahrgang' => intval($_POST['jahrgang']),
                'eltern_fahren' => intval($_POST['eltern_fahren']),
                'freie_plaetze' => (intval($_POST['eltern_fahren']) === 1) ? intval($_POST['freie_plaetze']) : 0
            );
            
            $result = $wpdb->update($table_anmeldung, $data, array('id' => $anmeldung_id));
            
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
                
                wp_die(json_encode(array('success' => true, 'message' => 'Anmeldung erfolgreich aktualisiert')));
            } else {
                wp_die(json_encode(array('success' => false, 'message' => 'Fehler beim Aktualisieren')));
            }
        }
    }
    
    // View-only function
    public function process_view_only() {
        if (!wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        
        $anmeldung_id = intval($_POST['anmeldung_id']);
        
        // Rate limiting
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $recent_attempts = get_transient('wettkampf_view_attempts_' . md5($user_ip));
        if ($recent_attempts && $recent_attempts >= 5) {
            wp_die(json_encode(array('success' => false, 'message' => 'Zu viele Versuche. Bitte warten Sie 10 Minuten.')));
        }
        
        // Verify email and year
        $anmeldung = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_anmeldung WHERE id = %d", $anmeldung_id));
        
        if ($anmeldung && $anmeldung->email === sanitize_email($_POST['verify_email']) && $anmeldung->jahrgang == intval($_POST['verify_jahrgang'])) {
            // Success - clear attempts counter and load disciplines
            delete_transient('wettkampf_view_attempts_' . md5($user_ip));
            
            // Disziplinen für diese Anmeldung laden
            $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
            $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
            
            $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
                SELECT d.name 
                FROM $table_anmeldung_disziplinen ad 
                JOIN $table_disziplinen d ON ad.disziplin_id = d.id 
                WHERE ad.anmeldung_id = %d 
                ORDER BY d.sortierung ASC, d.name ASC
            ", $anmeldung_id));
            
            $disziplin_names = array();
            if (is_array($anmeldung_disziplinen) && !empty($anmeldung_disziplinen)) {
                foreach ($anmeldung_disziplinen as $d) {
                    if (is_object($d) && isset($d->name) && !empty($d->name)) {
                        $disziplin_names[] = $d->name;
                    }
                }
            }
            
            $anmeldung->disziplinen_text = !empty($disziplin_names) ? implode(', ', $disziplin_names) : 'Keine';
            
            wp_die(json_encode(array('success' => true, 'data' => $anmeldung)));
        } else {
            // Failed attempt - increment counter
            $attempts = $recent_attempts ? $recent_attempts + 1 : 1;
            set_transient('wettkampf_view_attempts_' . md5($user_ip), $attempts, 10 * MINUTE_IN_SECONDS);
            wp_die(json_encode(array('success' => false, 'message' => 'E-Mail oder Jahrgang stimmen nicht überein')));
        }
    }
    
    // AJAX Handler für Disziplinen laden
    public function get_wettkampf_disziplinen() {
        if (!wp_verify_nonce($_POST['nonce'], 'wettkampf_ajax')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Sicherheitsfehler')));
        }
        
        global $wpdb;
        $wettkampf_id = intval($_POST['wettkampf_id']);
        
        // Disziplinen für den Wettkampf laden
        $table_zuordnung = $wpdb->prefix . 'wettkampf_disziplin_zuordnung';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        $disziplinen = $wpdb->get_results($wpdb->prepare("
            SELECT d.* 
            FROM $table_zuordnung z 
            JOIN $table_disziplinen d ON z.disziplin_id = d.id 
            WHERE z.wettkampf_id = %d AND d.aktiv = 1
            ORDER BY d.sortierung ASC, d.name ASC
        ", $wettkampf_id));
        
        // Nur Disziplinen zurückgeben, wenn welche für den Wettkampf definiert sind
        wp_die(json_encode(array('success' => true, 'data' => $disziplinen)));
    }
    
    private function verify_recaptcha($response) {
        $secret_key = get_option('wettkampf_recaptcha_secret_key');
        if (empty($secret_key) || empty($response)) {
            return true; // Skip verification if no key set or no response
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        );
        
        $response = wp_remote_post($url, array('body' => $data));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return isset($result['success']) && $result['success'] === true;
    }
    
    private function send_confirmation_email($anmeldung_id) {
        global $wpdb;
        $table_anmeldung = $wpdb->prefix . 'wettkampf_anmeldung';
        $table_wettkampf = $wpdb->prefix . 'wettkampf';
        
        $anmeldung = $wpdb->get_row($wpdb->prepare("
            SELECT a.*, w.name as wettkampf_name, w.datum, w.ort, w.beschreibung, w.event_link
            FROM $table_anmeldung a 
            JOIN $table_wettkampf w ON a.wettkampf_id = w.id 
            WHERE a.id = %d
        ", $anmeldung_id));
        
        if (!$anmeldung) return;
        
        // Disziplinen für die Anmeldung laden für E-Mail
        $table_anmeldung_disziplinen = $wpdb->prefix . 'wettkampf_anmeldung_disziplinen';
        $table_disziplinen = $wpdb->prefix . 'wettkampf_disziplinen';
        
        $anmeldung_disziplinen = $wpdb->get_results($wpdb->prepare("
            SELECT d.name 
            FROM $table_anmeldung_disziplinen ad 
            JOIN $table_disziplinen d ON ad.disziplin_id = d.id 
            WHERE ad.anmeldung_id = %d 
            ORDER BY d.sortierung ASC, d.name ASC
        ", $anmeldung_id));
        
        // E-Mail Inhalt erstellen
        $subject = 'Anmeldebestätigung: ' . $anmeldung->wettkampf_name;
        
        $message = "Hallo " . $anmeldung->vorname . ",\n\n";
        $message .= "deine Anmeldung für den Wettkampf wurde erfolgreich registriert.\n\n";
        $message .= "Wettkampf: " . $anmeldung->wettkampf_name . "\n";
        $message .= "Datum: " . date('d.m.Y', strtotime($anmeldung->datum)) . "\n";
        $message .= "Ort: " . $anmeldung->ort . "\n\n";
        
        $message .= "Deine Anmeldedaten:\n";
        $message .= "Name: " . $anmeldung->vorname . " " . $anmeldung->name . "\n";
        $message .= "E-Mail: " . $anmeldung->email . "\n";
        $message .= "Geschlecht: " . $anmeldung->geschlecht . "\n";
        $message .= "Jahrgang: " . $anmeldung->jahrgang . "\n";
        $message .= "Eltern können fahren: " . ($anmeldung->eltern_fahren ? 'Ja (' . $anmeldung->freie_plaetze . ' Plätze)' : 'Nein') . "\n";
        
        if (is_array($anmeldung_disziplinen) && !empty($anmeldung_disziplinen)) {
            $disziplin_names = array();
            foreach ($anmeldung_disziplinen as $d) {
                if (is_object($d) && isset($d->name) && !empty($d->name)) {
                    $disziplin_names[] = $d->name;
                }
            }
            if (!empty($disziplin_names)) {
                $message .= "Disziplinen: " . implode(', ', $disziplin_names) . "\n";
            }
        }
        $message .= "\n";
        
        if ($anmeldung->beschreibung) {
            $message .= "Beschreibung:\n" . $anmeldung->beschreibung . "\n\n";
        }
        
        if ($anmeldung->event_link) {
            $message .= "Weitere Informationen: " . $anmeldung->event_link . "\n\n";
        }
        
        $message .= "Du kannst deine Anmeldung jederzeit auf unserer Website bearbeiten oder abmelden.\n";
        $message .= "Klicke dazu einfach auf den Bleistift neben deinem Namen in der Teilnehmerliste.\n\n";
        $message .= "Viel Erfolg beim Wettkampf!\n";
        
        $sender_email = get_option('wettkampf_sender_email', get_option('admin_email'));
        $sender_name = get_option('wettkampf_sender_name', get_option('blogname'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        wp_mail($anmeldung->email, $subject, $message, $headers);
    }
}