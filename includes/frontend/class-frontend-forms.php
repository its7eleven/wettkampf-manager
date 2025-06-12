<?php
/**
 * Frontend forms functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FrontendForms {
    
    /**
     * Get all modals HTML
     */
    public function get_all_modals_html() {
        ob_start();
        echo $this->get_registration_modal();
        echo $this->get_mutation_modal();
        echo $this->get_view_modal();
        return ob_get_clean();
    }
    
    /**
     * Get registration modal HTML
     */
    private function get_registration_modal() {
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
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="jahrgang" name="jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                        <div id="kategorie-anzeige" style="display: none; margin-top: 8px; padding: 8px 12px; background: #f0f6fc; border: 1px solid #3b82f6; border-radius: 5px;">
                            <strong style="color: #1e40af;">Deine Kategorie: <span id="kategorie-text"></span></strong>
                        </div>
                    </div>
                    
                    <!-- Disziplinen werden dynamisch geladen -->
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
                        <label for="freie_plaetze">Anzahl freie Plätze (inkl. eigenen Kind/ern)</label>
                        <input type="number" id="freie_plaetze" name="freie_plaetze" min="0" max="10" placeholder="z.B. 2">
                    </div>
                    
                    <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="<?php echo WettkampfHelpers::get_option('recaptcha_site_key'); ?>"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-button">Abbrechen</button>
                        <button type="submit" class="submit-button">Anmelden</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get mutation modal HTML
     */
    private function get_mutation_modal() {
        ob_start();
        ?>
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
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_jahrgang">Jahrgang (4-stellig) *</label>
                        <input type="number" id="edit_jahrgang" name="jahrgang" min="1900" max="<?php echo date('Y'); ?>" required placeholder="z.B. 2010">
                        <small style="color: #666; display: block; margin-top: 5px;">Bitte gib dein Geburtsjahr 4-stellig ein (z.B. 2010)</small>
                        <div id="edit-kategorie-anzeige" style="display: none; margin-top: 8px; padding: 8px 12px; background: #f0f6fc; border: 1px solid #3b82f6; border-radius: 5px;">
                            <strong style="color: #1e40af;">Deine Kategorie: <span id="edit-kategorie-text"></span></strong>
                        </div>
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
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get view-only modal HTML
     */
    private function get_view_modal() {
        ob_start();
        ?>
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
                            <strong>Kategorie:</strong> <span id="view_kategorie"></span>
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
    
    /**
     * Display standalone registration form (if needed)
     */
    public function display_anmeldung_form($atts) {
        return '<div class="wettkampf-anmeldung-form">Anmeldungsformular wird hier angezeigt</div>';
    }
}