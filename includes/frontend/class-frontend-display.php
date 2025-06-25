<?php
/**
 * Frontend display functionality - Verbessert mit sortierter Disziplinen-Anzeige
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FrontendDisplay {
    
    /**
     * Display competition list
     */
    public function display_wettkampf_liste($atts) {
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Only future competitions and up to one day after execution
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $wettkaempfe = $wpdb->get_results($wpdb->prepare("
            SELECT w.*, COUNT(a.id) as anmeldungen_count 
            FROM {$tables['wettkampf']} w 
            LEFT JOIN {$tables['anmeldung']} a ON w.id = a.wettkampf_id 
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
                    <?php echo $this->render_wettkampf_card($wettkampf); ?>
                    
                    <?php 
                    // Add expired class if registration deadline has passed
                    $anmeldeschluss_passed = SecurityManager::is_registration_deadline_passed($wettkampf->anmeldeschluss);
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
    
    /**
     * Render single competition card
     */
    private function render_wettkampf_card($wettkampf) {
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        ob_start();
        ?>
        <div class="wettkampf-card" id="wettkampf-<?php echo $wettkampf->id; ?>">
            <div class="wettkampf-header">
                <div class="wettkampf-summary">
                    <h3><?php echo SecurityManager::escape_html($wettkampf->name); ?></h3>
                    <div class="wettkampf-basic-info">
                        <span class="datum-info"><strong>📅 <?php echo WettkampfHelpers::format_german_date($wettkampf->datum); ?></strong></span>
                        <span class="ort-info"><strong>📍 <?php echo SecurityManager::escape_html($wettkampf->ort); ?></strong></span>
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
                <?php echo $this->render_wettkampf_details($wettkampf); ?>
                <?php echo $this->render_registration_section($wettkampf); ?>
                <?php echo $this->render_participants_list($wettkampf); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render competition details
     */
    private function render_wettkampf_details($wettkampf) {
        ob_start();
        ?>
        <div class="wettkampf-info">
            <div class="info-row">
                <strong>Anmeldeschluss:</strong> <?php echo WettkampfHelpers::format_german_date($wettkampf->anmeldeschluss); ?>
            </div>
            <?php if ($wettkampf->beschreibung): ?>
            <div class="info-row">
                <strong>Beschreibung:</strong><br>
                <?php echo nl2br(SecurityManager::escape_html($wettkampf->beschreibung)); ?>
            </div>
            <?php endif; ?>
            <?php if ($wettkampf->startberechtigte): ?>
            <div class="info-row">
                <strong>Startberechtigte:</strong><br>
                <?php echo nl2br(SecurityManager::escape_html($wettkampf->startberechtigte)); ?>
            </div>
            <?php endif; ?>
            <?php echo $this->render_competition_disciplines($wettkampf->id); ?>
            <?php if ($wettkampf->event_link): ?>
            <div class="info-row">
                <strong>Weitere Infos:</strong> <a href="<?php echo SecurityManager::escape_url($wettkampf->event_link); ?>" target="_blank">Link zum Event</a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render competition disciplines - MIT SORTIERUNG
     */
    private function render_competition_disciplines($wettkampf_id) {
        $wettkampf_disciplines = WettkampfDatabase::get_competition_disciplines($wettkampf_id);
        
        if (empty($wettkampf_disciplines)) {
            return '';
        }
        
        // Group disciplines by categories
        $grouped_disciplines = array();
        foreach ($wettkampf_disciplines as $discipline) {
            $category = !empty($discipline->kategorie) ? $discipline->kategorie : 'Alle';
            if (!isset($grouped_disciplines[$category])) {
                $grouped_disciplines[$category] = array();
            }
            $grouped_disciplines[$category][] = $discipline;
        }
        
        // Sortiere Disziplinen innerhalb jeder Kategorie nach Sortierung und Name
        foreach ($grouped_disciplines as $category => $disciplines) {
            usort($grouped_disciplines[$category], function($a, $b) {
                // Erst nach Sortierung sortieren (niedrigere Zahlen zuerst)
                if ($a->sortierung != $b->sortierung) {
                    return $a->sortierung - $b->sortierung;
                }
                // Dann alphabetisch nach Name
                return strcmp($a->name, $b->name);
            });
        }
        
        ob_start();
        ?>
        <div class="info-row">
            <strong>Disziplinen nach Kategorien:</strong><br>
            <div style="margin-top: 12px;">
                <?php 
                $sorted_groups = CategoryCalculator::sort_categories($grouped_disciplines);
                foreach ($sorted_groups as $category => $disciplines): 
                ?>
                    <div style="margin-bottom: 15px; padding: 12px; background: #f8fafc; border-radius: 6px; border-left: 4px solid #3b82f6;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <strong style="color: #1e40af; font-size: 1rem; margin-right: 10px;">
                                <?php echo SecurityManager::escape_html($category); ?>
                            </strong>
                        </div>
                        <div style="margin-left: 8px;">
                            <?php foreach ($disciplines as $discipline): ?>
                                <div style="margin-bottom: 4px;">
                                    <span style="color: #111827; font-weight: 500;">• <?php echo SecurityManager::escape_html($discipline->name); ?></span>
                                    <?php if ($discipline->beschreibung): ?>
                                        <small style="color: #6b7280; margin-left: 8px;">(<?php echo SecurityManager::escape_html($discipline->beschreibung); ?>)</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render registration section
     */
    private function render_registration_section($wettkampf) {
        $anmeldeschluss_passed = SecurityManager::is_registration_deadline_passed($wettkampf->anmeldeschluss);
        
        ob_start();
        ?>
        <div class="anmeldung-section">
            <?php if ($anmeldeschluss_passed): ?>
                <p class="anmeldung-geschlossen">Anmeldung ist geschlossen</p>
            <?php else: ?>
                <button class="anmelde-button" data-wettkampf-id="<?php echo $wettkampf->id; ?>">
                    <span>🏃‍♂️</span> Jetzt anmelden
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render participants list
     */
    private function render_participants_list($wettkampf) {
        if ($wettkampf->anmeldungen_count == 0) {
            return '';
        }
        
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        $anmeldungen = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['anmeldung']} WHERE wettkampf_id = %d ORDER BY anmeldedatum ASC", 
            $wettkampf->id
        ));
        
        ob_start();
        ?>
        <div class="angemeldete-teilnehmer">
            <h4>Angemeldet (<?php echo $wettkampf->anmeldungen_count; ?>)</h4>
            <div class="teilnehmer-liste" id="teilnehmer-<?php echo $wettkampf->id; ?>">
                <?php foreach ($anmeldungen as $anmeldung): ?>
                    <?php echo $this->render_participant_item($anmeldung, $wettkampf); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render single participant item - VERBESSERT mit integrierter Disziplinen-Anzeige
     */
    private function render_participant_item($anmeldung, $wettkampf) {
        $user_category = CategoryCalculator::calculate($anmeldung->jahrgang);
        $anmeldeschluss_passed = SecurityManager::is_registration_deadline_passed($wettkampf->anmeldeschluss);
        
        ob_start();
        ?>
        <div class="teilnehmer-item" id="teilnehmer-item-<?php echo $anmeldung->id; ?>">
            <div class="teilnehmer-main">
                <div class="teilnehmer-info">
                    <span class="teilnehmer-name">
                        <?php echo SecurityManager::escape_html($anmeldung->vorname . ' ' . $anmeldung->name); ?>
                    </span>
                </div>
                <div class="teilnehmer-actions">
                    <button 
                        type="button"
                        class="show-disziplinen-button" 
                        data-anmeldung-id="<?php echo $anmeldung->id; ?>" 
                        title="Disziplinen anzeigen"
                        aria-label="Disziplinen für <?php echo SecurityManager::escape_attr($anmeldung->vorname . ' ' . $anmeldung->name); ?> anzeigen"
                        tabindex="0"
                    >📋</button>
                    
                    <?php if (!$anmeldeschluss_passed): ?>
                        <button 
                            type="button"
                            class="edit-anmeldung" 
                            data-anmeldung-id="<?php echo $anmeldung->id; ?>" 
                            title="Anmeldung bearbeiten"
                            aria-label="Anmeldung von <?php echo SecurityManager::escape_attr($anmeldung->vorname . ' ' . $anmeldung->name); ?> bearbeiten"
                            tabindex="0"
                        >✏️</button>
                    <?php else: ?>
                        <button 
                            type="button"
                            class="view-anmeldung" 
                            data-anmeldung-id="<?php echo $anmeldung->id; ?>" 
                            title="Anmeldung einsehen"
                            aria-label="Anmeldung von <?php echo SecurityManager::escape_attr($anmeldung->vorname . ' ' . $anmeldung->name); ?> einsehen"
                            tabindex="0"
                        >🔍</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div 
                class="teilnehmer-disziplinen" 
                id="disziplinen-<?php echo $anmeldung->id; ?>"
                role="region"
                aria-label="Disziplinen für <?php echo SecurityManager::escape_attr($anmeldung->vorname . ' ' . $anmeldung->name); ?>"
            >
                <small>
                    <strong>Disziplinen:</strong> 
                    <?php echo $this->get_participant_disciplines($anmeldung->id); ?>
                </small>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get participant disciplines as string - MIT SORTIERUNG
     */
    private function get_participant_disciplines($anmeldung_id) {
        global $wpdb;
        $tables = WettkampfDatabase::get_table_names();
        
        // Disziplinen mit Sortierung laden
        $anmeldung_disciplines = $wpdb->get_results($wpdb->prepare("
            SELECT d.* 
            FROM {$tables['anmeldung_disziplinen']} ad 
            JOIN {$tables['disziplinen']} d ON ad.disziplin_id = d.id 
            WHERE ad.anmeldung_id = %d 
            ORDER BY d.sortierung ASC, d.name ASC
        ", $anmeldung_id));
        
        $discipline_names = array();
        if (is_array($anmeldung_disciplines) && !empty($anmeldung_disciplines)) {
            foreach ($anmeldung_disciplines as $d) {
                if (is_object($d) && isset($d->name) && !empty($d->name)) {
                    $discipline_names[] = SecurityManager::escape_html($d->name);
                }
            }
        }
        
        if (!empty($discipline_names)) {
            return implode(', ', $discipline_names);
        } else {
            return '<em>Keine Disziplinen ausgewählt</em>';
        }
    }
    
    /**
     * Get modal HTML
     */
    private function get_modal_html() {
        $forms = new FrontendForms();
        return $forms->get_all_modals_html();
    }
}