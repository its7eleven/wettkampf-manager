jQuery(document).ready(function($) {
    'use strict';
    
    // Modal elements
    const anmeldungModal = $('#anmeldung-modal');
    const mutationModal = $('#mutation-modal');
    const viewModal = $('#view-modal');
    const anmeldungForm = $('#anmeldung-form');
    const mutationVerifyForm = $('#mutation-verify-form');
    const mutationEditForm = $('#mutation-edit-form');
    const viewVerifyForm = $('#view-verify-form');
    
    // Calculate and set header height for modal positioning
    function updateModalPositioning() {
        let headerHeight = 0;
        
        // Common header selectors to check
        const headerSelectors = [
            'header',
            '.site-header', 
            '#header',
            '#masthead',
            '.header',
            '.main-header',
            'nav.navbar',
            '.navbar-fixed-top',
            '.fixed-header'
        ];
        
        // Find the main header element
        for (let selector of headerSelectors) {
            const headerEl = $(selector).first();
            if (headerEl.length && headerEl.is(':visible')) {
                const position = headerEl.css('position');
                if (position === 'fixed' || position === 'sticky') {
                    headerHeight = Math.max(headerHeight, headerEl.outerHeight());
                }
            }
        }
        
        // Set CSS custom property for modal positioning
        document.documentElement.style.setProperty('--header-height', headerHeight + 'px');
    }
    
    // Update modal positioning on load and resize
    updateModalPositioning();
    $(window).on('resize', updateModalPositioning);
    
    // KORRIGIERTE Kategorie-Berechnung Funktion - nur U10, U12, U14, U16, U18
    function calculateAgeCategory(jahrgang) {
        const currentYear = new Date().getFullYear();
        const age = currentYear - jahrgang;
        
        // Nur diese 5 Kategorien verwenden, immer nächst passende wählen
        if (age < 10) return 'U10';  // Alle unter 10 Jahren → U10
        if (age < 12) return 'U12';  // 10-11 Jahre → U12
        if (age < 14) return 'U14';  // 12-13 Jahre → U14
        if (age < 16) return 'U16';  // 14-15 Jahre → U16
        if (age < 18) return 'U18';  // 16-17 Jahre → U18
        
        return 'U18'; // Alle 18+ Jahre bleiben in U18
    }
    
    // Kategorie-Anzeige aktualisieren
    function updateCategoryDisplay(jahrgang, categoryTextId, categoryDisplayId) {
        const category = calculateAgeCategory(jahrgang);
        const categoryText = $('#' + categoryTextId);
        const categoryDisplay = $('#' + categoryDisplayId);
        
        if (jahrgang && jahrgang.toString().length === 4) {
            categoryText.text(category);
            categoryDisplay.show();
        } else {
            categoryDisplay.hide();
        }
        
        return category;
    }
    
    // Toggle details accordion
    $(document).on('click', '.details-toggle', function(e) {
        e.preventDefault();
        const wettkampfId = $(this).data('wettkampf-id');
        const detailsDiv = $('#details-' + wettkampfId);
        const toggleButton = $(this);
        const toggleText = toggleButton.find('.toggle-text');
        
        console.log('Accordion clicked for wettkampf:', wettkampfId);
        console.log('Details div found:', detailsDiv.length);
        
        if (detailsDiv.hasClass('show') || detailsDiv.is(':visible')) {
            detailsDiv.removeClass('show').slideUp(300);
            toggleText.text('Details anzeigen');
            toggleButton.removeClass('active');
        } else {
            detailsDiv.addClass('show').slideDown(300);
            toggleText.text('Details ausblenden');
            toggleButton.addClass('active');
        }
    });
    
    // FIXED: Toggle Disziplinen für Teilnehmer - bessere Event-Delegation
    $(document).on('click', '.show-disziplinen-button', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const anmeldungId = $(this).data('anmeldung-id');
        const disziplinenDiv = $('#disziplinen-' + anmeldungId);
        const button = $(this);
        
        console.log('Disziplinen toggle clicked for anmeldung:', anmeldungId);
        console.log('Button element:', button);
        console.log('Disziplinen div found:', disziplinenDiv.length);
        console.log('Current visibility:', disziplinenDiv.is(':visible'));
        
        // Fallback falls das Disziplinen-Div nicht gefunden wird
        if (disziplinenDiv.length === 0) {
            console.warn('Disziplinen div not found for anmeldung:', anmeldungId);
            return;
        }
        
        if (disziplinenDiv.is(':visible')) {
            disziplinenDiv.slideUp(200);
            button.text('📋');
            button.attr('title', 'Disziplinen anzeigen');
            console.log('Hiding disziplinen for:', anmeldungId);
        } else {
            // Erst alle anderen schließen
            $('.teilnehmer-disziplinen:visible').slideUp(200);
            $('.show-disziplinen-button').text('📋').attr('title', 'Disziplinen anzeigen');
            
            // Dann diese öffnen
            disziplinenDiv.slideDown(200);
            button.text('📋');
            button.attr('title', 'Disziplinen ausblenden');
            console.log('Showing disziplinen for:', anmeldungId);
        }
    });
    
    // ZUSÄTZLICHER FIX: Alternative Event-Handler für Desktop-spezifische Issues
    $(document).on('mousedown touchstart', '.show-disziplinen-button', function(e) {
        // Backup handler für den Fall dass click nicht funktioniert
        const originalHandler = $(this).data('backup-handler-executed');
        if (originalHandler) {
            return; // Verhindere doppelte Ausführung
        }
        
        $(this).data('backup-handler-executed', true);
        
        // Reset nach kurzer Zeit
        setTimeout(() => {
            $(this).removeData('backup-handler-executed');
        }, 500);
        
        // Trigger den ursprünglichen Handler
        $(this).trigger('click');
    });
    
    // DEBUG: Event-Handler für alle Clicks auf Teilnehmer-Items
    $(document).on('click', '.teilnehmer-item', function(e) {
        console.log('Click on teilnehmer-item detected');
        console.log('Target element:', e.target);
        console.log('Has show-disziplinen-button class:', $(e.target).hasClass('show-disziplinen-button'));
        
        // Prüfe ob der Klick auf das Disziplinen-Button war
        if ($(e.target).hasClass('show-disziplinen-button')) {
            console.log('Click was on disziplinen button');
        }
    });
    
    // Event Handler für Jahrgang-Eingabe in Anmeldeformular
    $(document).on('input change', '#jahrgang', function() {
        const jahrgang = parseInt($(this).val());
        const wettkampfId = $('#wettkampf_id').val();
        
        // Kategorie-Anzeige aktualisieren
        const category = updateCategoryDisplay(jahrgang, 'kategorie-text', 'kategorie-anzeige');
        
        // Disziplinen neu laden wenn Jahrgang vollständig und Wettkampf ausgewählt
        if (jahrgang && jahrgang.toString().length === 4 && wettkampfId) {
            console.log('Loading disciplines for year:', jahrgang, 'category:', category);
            loadWettkampfDisziplinenWithCategory(wettkampfId, jahrgang, 'disziplinen_container', 'disziplinen_group');
        } else {
            // Disziplinen ausblenden wenn Jahrgang unvollständig
            $('#disziplinen_group').hide();
        }
    });
    
    // Event Handler für Jahrgang-Eingabe in Edit-Formular
    $(document).on('input change', '#edit_jahrgang', function() {
        const jahrgang = parseInt($(this).val());
        const wettkampfId = $('#edit_anmeldung_id').length ? 
            $('#edit_anmeldung_id').data('wettkampf-id') : null;
        
        // Kategorie-Anzeige aktualisieren
        updateCategoryDisplay(jahrgang, 'edit-kategorie-text', 'edit-kategorie-anzeige');
        
        // Disziplinen neu laden wenn möglich
        if (jahrgang && jahrgang.toString().length === 4 && wettkampfId) {
            loadWettkampfDisziplinenWithCategory(wettkampfId, jahrgang, 'edit_disziplinen_container', 'edit_disziplinen_group');
        }
    });
    
    // Open registration modal
    $(document).on('click', '.anmelde-button', function() {
        const wettkampfId = $(this).data('wettkampf-id');
        $('#wettkampf_id').val(wettkampfId);
        updateModalPositioning();
        anmeldungModal.show();
        resetForm(anmeldungForm);
        
        // Disziplinen NICHT sofort laden - warten auf Jahrgang
        console.log('Registration modal opened for wettkampf:', wettkampfId);
    });
    
    // Open mutation modal - FIXED with event delegation
    $(document).on('click', '.edit-anmeldung', function() {
        const anmeldungId = $(this).data('anmeldung-id');
        console.log('Edit button clicked for anmeldung:', anmeldungId);
        $('#mutation_anmeldung_id').val(anmeldungId);
        updateModalPositioning();
        mutationModal.show();
        mutationVerifyForm.show();
        mutationEditForm.hide();
        resetForm(mutationVerifyForm);
        resetForm(mutationEditForm);
    });
    
    // Open view-only modal - FIXED with event delegation
    $(document).on('click', '.view-anmeldung', function() {
        const anmeldungId = $(this).data('anmeldung-id');
        console.log('View button clicked for anmeldung:', anmeldungId);
        $('#view_anmeldung_id').val(anmeldungId);
        updateModalPositioning();
        viewModal.show();
        viewVerifyForm.show();
        $('#view-display').hide();
        resetForm(viewVerifyForm);
    });
    
    // Close modals
    $('.close, .cancel-button').on('click', function() {
        anmeldungModal.hide();
        mutationModal.hide();
        viewModal.hide();
        clearMessages();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if (event.target === anmeldungModal[0]) {
            anmeldungModal.hide();
            clearMessages();
        }
        if (event.target === mutationModal[0]) {
            mutationModal.hide();
            clearMessages();
        }
        if (event.target === viewModal[0]) {
            viewModal.hide();
            clearMessages();
        }
    });
    
    // Toggle free seats field for radio buttons
    $(document).on('change', 'input[name="eltern_fahren"]', function() {
        const value = $(this).val();
        const form = $(this).closest('form');
        
        if (value === '1') {
            form.find('#freie_plaetze_group, #edit_freie_plaetze_group').show();
            form.find('#freie_plaetze, #edit_freie_plaetze').attr('required', true);
        } else {
            form.find('#freie_plaetze_group, #edit_freie_plaetze_group').hide();
            form.find('#freie_plaetze, #edit_freie_plaetze').attr('required', false).val('');
        }
    });
    
    // Handle registration form submission (with reCAPTCHA)
    anmeldungForm.on('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm(anmeldungForm)) {
            return;
        }
        
        // Validate discipline selection
        const disziplinenContainer = $('#disziplinen_container');
        if (disziplinenContainer.is(':visible') && disziplinenContainer.find('input[type="checkbox"]').length > 0) {
            const checkedDisziplinen = disziplinenContainer.find('input[type="checkbox"]:checked');
            if (checkedDisziplinen.length === 0) {
                showMessage('error', 'Bitte wähle mindestens eine Disziplin aus.', anmeldungForm);
                return;
            }
        }
        
        const formData = new FormData(this);
        formData.append('action', 'wettkampf_anmeldung');
        formData.append('nonce', wettkampf_ajax.nonce);
        
        // Add reCAPTCHA response if available for registration only
        const recaptcha_site_key = $('div[data-sitekey]').data('sitekey');
        if (recaptcha_site_key && typeof grecaptcha !== 'undefined') {
            try {
                const recaptchaResponse = grecaptcha.getResponse();
                if (recaptchaResponse && recaptchaResponse.length > 0) {
                    formData.append('g-recaptcha-response', recaptchaResponse);
                } else {
                    showMessage('error', 'Bitte bestätige das reCAPTCHA.', anmeldungForm);
                    return;
                }
            } catch (error) {
                console.log('reCAPTCHA error:', error);
            }
        }
        
        submitForm(formData, anmeldungForm, function(response) {
            if (response.success) {
                showMessage('success', '✅ Anmeldung erfolgreich! Die Seite wird in 3 Sekunden aktualisiert...', anmeldungForm);
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                showMessage('error', response.message, anmeldungForm);
                resetRecaptcha();
            }
        });
    });
    
    // Handle mutation verification (NO CAPTCHA)
    mutationVerifyForm.on('submit', function(e) {
        e.preventDefault();
        
        // Clear any existing messages
        $('.success-message, .error-message').remove();
        
        // Simple validation - only email and year
        const email = $('#verify_email').val() || '';
        const jahrgang = $('#verify_jahrgang').val() || '';
        
        if (!email.trim()) {
            showMessage('error', 'Bitte E-Mail eingeben.', mutationVerifyForm);
            return;
        }
        
        if (!jahrgang.trim()) {
            showMessage('error', 'Bitte Jahrgang eingeben.', mutationVerifyForm);
            return;
        }
        
        // Submit AJAX request
        $.ajax({
            url: wettkampf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wettkampf_mutation',
                action_type: 'verify',
                nonce: wettkampf_ajax.nonce,
                anmeldung_id: $('#mutation_anmeldung_id').val(),
                verify_email: email.trim(),
                verify_jahrgang: jahrgang.trim()
            },
            dataType: 'json',
            beforeSend: function() {
                const submitButton = mutationVerifyForm.find('.submit-button');
                submitButton.prop('disabled', true).text('Wird verarbeitet...');
            },
            success: function(response) {
                if (response.success) {
                    // Fill edit form with existing data
                    const data = response.data;
                    $('#edit_anmeldung_id').val(data.id);
                    $('#edit_anmeldung_id').data('wettkampf-id', data.wettkampf_id);
                    $('#edit_vorname').val(data.vorname);
                    $('#edit_name').val(data.name);
                    $('#edit_email').val(data.email);
                    $('#edit_geschlecht').val(data.geschlecht);
                    $('#edit_jahrgang').val(data.jahrgang);
                    
                    // Kategorie sofort anzeigen
                    updateCategoryDisplay(parseInt(data.jahrgang), 'edit-kategorie-text', 'edit-kategorie-anzeige');
                    
                    // Set radio button for eltern_fahren in edit form
                    const editForm = mutationEditForm;
                    editForm.find('input[name="eltern_fahren"][value="' + data.eltern_fahren + '"]').prop('checked', true);
                    
                    if (data.eltern_fahren == 1) {
                        editForm.find('#edit_freie_plaetze_group').show();
                        editForm.find('#edit_freie_plaetze').val(data.freie_plaetze).attr('required', true);
                    } else {
                        editForm.find('#edit_freie_plaetze_group').hide();
                        editForm.find('#edit_freie_plaetze').attr('required', false);
                    }
                    
                    // Load disciplines with category filter
                    loadWettkampfDisziplinenWithCategory(data.wettkampf_id, parseInt(data.jahrgang), 'edit_disziplinen_container', 'edit_disziplinen_group', data.disziplinen);
                    
                    // Switch to edit form
                    mutationVerifyForm.hide();
                    mutationEditForm.show();
                    clearMessages();
                } else {
                    showMessage('error', response.message, mutationVerifyForm);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { status, error, responseText: xhr.responseText });
                showMessage('error', 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.', mutationVerifyForm);
            },
            complete: function() {
                const submitButton = mutationVerifyForm.find('.submit-button');
                submitButton.prop('disabled', false).text('Verifizieren');
            }
        });
    });
    
    // Handle view-only verification (NO CAPTCHA)
    viewVerifyForm.on('submit', function(e) {
        e.preventDefault();
        
        // Clear any existing messages
        $('.success-message, .error-message').remove();
        
        // Simple validation - only email and year
        const email = $('#view_verify_email').val() || '';
        const jahrgang = $('#view_verify_jahrgang').val() || '';
        
        if (!email.trim()) {
            showMessage('error', 'Bitte E-Mail eingeben.', viewVerifyForm);
            return;
        }
        
        if (!jahrgang.trim()) {
            showMessage('error', 'Bitte Jahrgang eingeben.', viewVerifyForm);
            return;
        }
        
        // Submit AJAX request
        $.ajax({
            url: wettkampf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wettkampf_view_only',
                nonce: wettkampf_ajax.nonce,
                anmeldung_id: $('#view_anmeldung_id').val(),
                verify_email: email.trim(),
                verify_jahrgang: jahrgang.trim()
            },
            dataType: 'json',
            beforeSend: function() {
                const submitButton = viewVerifyForm.find('.submit-button');
                submitButton.prop('disabled', true).text('Wird verarbeitet...');
            },
            success: function(response) {
                if (response.success) {
                    // Fill view display with data
                    const data = response.data;
                    const category = calculateAgeCategory(parseInt(data.jahrgang));
                    
                    $('#view_vorname').text(data.vorname);
                    $('#view_name').text(data.name);
                    $('#view_email').text(data.email);
                    $('#view_geschlecht').text(data.geschlecht);
                    $('#view_jahrgang').text(data.jahrgang);
                    $('#view_kategorie').text(category);
                    $('#view_eltern_fahren').text(data.eltern_fahren == 1 ? 'Ja' : 'Nein');
                    $('#view_freie_plaetze').text(data.eltern_fahren == 1 ? data.freie_plaetze : '-');
                    $('#view_disziplinen').text(data.disziplinen_text || 'Keine');
                    $('#view_anmeldedatum').text(new Date(data.anmeldedatum).toLocaleString('de-DE'));
                    
                    // Switch to view display
                    viewVerifyForm.hide();
                    $('#view-display').show();
                    clearMessages();
                } else {
                    showMessage('error', response.message, viewVerifyForm);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { status, error, responseText: xhr.responseText });
                showMessage('error', 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.', viewVerifyForm);
            },
            complete: function() {
                const submitButton = viewVerifyForm.find('.submit-button');
                submitButton.prop('disabled', false).text('Anzeigen');
            }
        });
    });
    
    // Handle mutation edit form submission (NO reCAPTCHA)
    mutationEditForm.on('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm(mutationEditForm)) {
            return;
        }
        
        // Validate discipline selection for edit form
        const editDisziplinenContainer = $('#edit_disziplinen_container');
        if (editDisziplinenContainer.is(':visible') && editDisziplinenContainer.find('input[type="checkbox"]').length > 0) {
            const checkedDisziplinen = editDisziplinenContainer.find('input[type="checkbox"]:checked');
            if (checkedDisziplinen.length === 0) {
                showMessage('error', 'Bitte wähle mindestens eine Disziplin aus.', mutationEditForm);
                return;
            }
        }
        
        const formData = new FormData(this);
        formData.append('action', 'wettkampf_mutation');
        formData.append('action_type', 'update');
        formData.append('nonce', wettkampf_ajax.nonce);
        
        submitForm(formData, mutationEditForm, function(response) {
            if (response.success) {
                showMessage('success', '✅ Anmeldung erfolgreich aktualisiert! Die Seite wird in 3 Sekunden aktualisiert...', mutationEditForm);
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                showMessage('error', response.message, mutationEditForm);
            }
        });
    });
    
    // Handle deletion
    $('.delete-button').on('click', function() {
        if (!confirm('Möchtest du deine Anmeldung wirklich löschen?')) {
            return;
        }
        
        const anmeldungId = $('#edit_anmeldung_id').val();
        const formData = new FormData();
        formData.append('action', 'wettkampf_mutation');
        formData.append('action_type', 'delete');
        formData.append('anmeldung_id', anmeldungId);
        formData.append('nonce', wettkampf_ajax.nonce);
        
        submitForm(formData, mutationEditForm, function(response) {
            if (response.success) {
                showMessage('success', '✅ Anmeldung erfolgreich gelöscht! Die Seite wird in 3 Sekunden aktualisiert...', mutationEditForm);
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                showMessage('error', response.message, mutationEditForm);
            }
        });
    });
    
    // Erweiterte Funktion zum Laden von Disziplinen mit Kategorie-Filter
    function loadWettkampfDisziplinenWithCategory(wettkampfId, jahrgang, containerId, groupId, selectedDisziplinen = []) {
        console.log('Loading disciplines with category filter:', {wettkampfId, jahrgang, containerId, groupId});
        
        $.ajax({
            url: wettkampf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_wettkampf_disziplinen',
                wettkampf_id: wettkampfId,
                jahrgang: jahrgang, // WICHTIG: Jahrgang für Kategorie-Filter
                nonce: wettkampf_ajax.nonce
            },
            dataType: 'json',
            beforeSend: function() {
                // Loading-Indikator anzeigen
                $('#' + containerId).html('<div style="text-align: center; padding: 20px;"><span class="spinner"></span> Lade passende Disziplinen...</div>');
                $('#' + groupId).show();
            },
            success: function(response) {
                console.log('Disciplines AJAX response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    const container = $('#' + containerId);
                    const group = $('#' + groupId);
                    
                    // Clear container
                    container.empty();
                    
                    // Kategorie-Info anzeigen
                    let html = '';
                    if (response.user_category) {
                        html += '<div style="margin-bottom: 15px; padding: 12px; background: #e0f2fe; border: 1px solid #0891b2; border-radius: 6px; font-size: 14px;">';
                        html += '<strong>📋 Verfügbare Disziplinen für Kategorie ' + response.user_category + ':</strong>';
                        html += '</div>';
                        
                        console.log('User category:', response.user_category, 'Disciplines count:', response.data.length);
                    }
                    
                    // Create discipline checkboxes
                    html += '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #d1d5db; padding: 15px; background: #f9fafb; border-radius: 8px;">';
                    
                    response.data.forEach(function(disziplin) {
                        const isChecked = selectedDisziplinen.includes(disziplin.id.toString()) || selectedDisziplinen.includes(parseInt(disziplin.id));
                        html += '<label style="display: block; margin-bottom: 10px; cursor: pointer; padding: 10px; border-radius: 6px; transition: background-color 0.15s ease; border: 1px solid #e5e7eb;">';
                        html += '<input type="checkbox" name="disziplinen[]" value="' + disziplin.id + '" ' + (isChecked ? 'checked' : '') + ' style="margin-right: 12px; transform: scale(1.1);">';
                        html += '<span style="font-weight: 500; color: #111827;">' + escapeHtml(disziplin.name) + '</span>';
                        
                        // Kategorie-Badge hinzufügen
                        if (disziplin.kategorie) {
                            html += ' <span style="display: inline-block; margin-left: 8px; padding: 2px 6px; background: #e5e7eb; color: #374151; border-radius: 10px; font-size: 10px; font-weight: 600;">' + escapeHtml(disziplin.kategorie) + '</span>';
                        }
                        
                        if (disziplin.beschreibung) {
                            html += '<br><small style="color: #6b7280; margin-left: 24px; font-style: italic;">' + escapeHtml(disziplin.beschreibung) + '</small>';
                        }
                        html += '</label>';
                    });
                    
                    html += '</div>';
                    html += '<small style="color: #6b7280; font-style: italic; margin-top: 8px; display: block;">Wähle die Disziplinen aus, für die du dich anmelden möchtest. Mindestens eine Disziplin muss ausgewählt werden.</small>';
                    
                    container.html(html);
                    group.show();
                    
                    // Hover-Effekte für Labels hinzufügen
                    container.find('label').hover(
                        function() { $(this).css('background-color', '#f0f9ff'); },
                        function() { $(this).css('background-color', 'transparent'); }
                    );
                    
                } else {
                    // Keine passenden Disziplinen
                    const container = $('#' + containerId);
                    const group = $('#' + groupId);
                    
                    let message = '<div style="text-align: center; padding: 20px; color: #6b7280; font-style: italic;">';
                    if (response.user_category) {
                        message += '⚠️ Für deine Alterskategorie ' + response.user_category + ' sind bei diesem Wettkampf keine Disziplinen verfügbar.';
                        console.log('No disciplines found for category:', response.user_category);
                    } else {
                        message += 'Für diesen Wettkampf sind keine spezifischen Disziplinen definiert.';
                    }
                    message += '</div>';
                    
                    container.html(message);
                    group.show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading disciplines:', error, xhr.responseText);
                const container = $('#' + containerId);
                container.html('<div style="text-align: center; padding: 20px; color: #dc2626;">❌ Fehler beim Laden der Disziplinen. Bitte versuche es erneut.</div>');
                $('#' + groupId).show();
            }
        });
    }
    
    // Angepasste ursprüngliche loadWettkampfDisziplinen Funktion für Rückwärtskompatibilität
    function loadWettkampfDisziplinen(wettkampfId, containerId, groupId, selectedDisziplinen = []) {
        // Prüfen ob Jahrgang verfügbar ist
        const jahrgang = $('#jahrgang').val() || $('#edit_jahrgang').val();
        
        if (jahrgang && jahrgang.length === 4) {
            // Mit Kategorie-Filter laden
            loadWettkampfDisziplinenWithCategory(wettkampfId, parseInt(jahrgang), containerId, groupId, selectedDisziplinen);
        } else {
            // Ohne Kategorie-Filter laden (alte Methode)
            loadWettkampfDisziplinenWithCategory(wettkampfId, null, containerId, groupId, selectedDisziplinen);
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Helper functions
    function validateForm(form) {
        let isValid = true;
        const requiredFields = form.find('[required]');
        
        // Skip reCAPTCHA validation for mutation forms
        const formId = form.attr('id');
        const isMutationForm = formId === 'mutation-verify-form' || formId === 'mutation-edit-form' || formId === 'view-verify-form';
        
        requiredFields.each(function() {
            const field = $(this);
            
            // Special handling for radio buttons
            if (field.attr('type') === 'radio') {
                const radioName = field.attr('name');
                const isRadioChecked = form.find('input[name="' + radioName + '"]:checked').length > 0;
                
                form.find('input[name="' + radioName + '"]').each(function() {
                    if (!isRadioChecked) {
                        $(this).addClass('error');
                        isValid = false;
                    } else {
                        $(this).removeClass('error');
                    }
                });
                return true; // Skip to next iteration
            }
            
            const value = field.val() ? field.val().trim() : '';
            
            if (!value) {
                field.addClass('error');
                isValid = false;
            } else {
                field.removeClass('error');
                
                // Additional validation
                if (field.attr('type') === 'email' && !isValidEmail(value)) {
                    field.addClass('error');
                    isValid = false;
                }
                
                if (field.attr('name') === 'jahrgang' || field.attr('name') === 'verify_jahrgang' || field.attr('name') === 'view_verify_jahrgang') {
                    const year = parseInt(value);
                    const currentYear = new Date().getFullYear();
                    if (year < 1900 || year > currentYear || value.length !== 4) {
                        field.addClass('error');
                        isValid = false;
                    }
                }
            }
        });
        
        // Spezielle Validierung für Disziplinen - nur wenn Disziplinen-Container sichtbar ist
        const formPrefix = formId === 'mutation-edit-form' ? 'edit_' : '';
        const disziplinenContainer = $('#' + formPrefix + 'disziplinen_container');
        if (disziplinenContainer.is(':visible') && disziplinenContainer.find('input[type="checkbox"]').length > 0) {
            const checkedDisziplinen = disziplinenContainer.find('input[type="checkbox"]:checked');
            if (checkedDisziplinen.length === 0) {
                isValid = false;
                // Visuelles Feedback für Disziplinen-Fehler
                disziplinenContainer.css('border-color', '#dc2626');
                if (!isMutationForm) {
                    showMessage('error', 'Bitte wähle mindestens eine Disziplin aus.', form);
                }
            } else {
                disziplinenContainer.css('border-color', '#d1d5db');
            }
        }
        
        if (!isValid && !isMutationForm) {
            showMessage('error', 'Bitte fülle alle Pflichtfelder korrekt aus.', form);
        } else if (!isValid && isMutationForm) {
            showMessage('error', 'Bitte fülle alle Felder korrekt aus.', form);
        }
        
        return isValid;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function submitForm(formData, form, callback) {
        const submitButton = form.find('.submit-button');
        const originalText = submitButton.text();
        
        // Show loading state
        submitButton.prop('disabled', true).html('<span class="spinner"></span> Wird verarbeitet...');
        form.addClass('loading');
        clearMessages();
        
        $.ajax({
            url: wettkampf_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                callback(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showMessage('error', 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es erneut.', form);
            },
            complete: function() {
                // Reset loading state
                submitButton.prop('disabled', false).text(originalText);
                form.removeClass('loading');
            }
        });
    }
    
    function showMessage(type, message, form) {
        clearMessages();
        
        const messageClass = type === 'success' ? 'success-message' : 'error-message';
        const messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
        
        form.prepend(messageHtml);
    }
    
    function clearMessages() {
        $('.success-message, .error-message').remove();
        $('.error').removeClass('error');
    }
    
    function resetForm(form) {
        form[0].reset();
        clearMessages();
        form.find('#freie_plaetze_group, #edit_freie_plaetze_group').hide();
        form.find('#freie_plaetze, #edit_freie_plaetze').attr('required', false);
        form.find('#disziplinen_group, #edit_disziplinen_group').hide();
        form.find('#kategorie-anzeige, #edit-kategorie-anzeige').hide(); // Kategorie-Anzeigen ausblenden
        // Reset radio buttons
        form.find('input[type="radio"]').prop('checked', false);
        
        // Reset view display
        if (form.attr('id') === 'view-verify-form') {
            $('#view-display').hide();
            form.show();
        }
        
        // Only reset reCAPTCHA for registration form
        if (form.attr('id') === 'anmeldung-form') {
            resetRecaptcha();
        }
    }
    
    function resetRecaptcha() {
        if (typeof grecaptcha !== 'undefined') {
            try {
                setTimeout(function() {
                    grecaptcha.reset();
                }, 100);
            } catch (error) {
                console.log('reCAPTCHA reset error:', error);
            }
        }
    }
    
    // Add input event listeners for real-time validation feedback
    $('input[required], select[required], input[type="radio"][required]').on('input change', function() {
        const field = $(this);
        const value = field.val() ? field.val().trim() : '';
        
        // Special handling for radio buttons
        if (field.attr('type') === 'radio') {
            const radioName = field.attr('name');
            const isRadioChecked = $('input[name="' + radioName + '"]:checked').length > 0;
            
            $('input[name="' + radioName + '"]').each(function() {
                if (isRadioChecked) {
                    $(this).removeClass('error');
                } else {
                    $(this).addClass('error');
                }
            });
            return;
        }
        
        if (value) {
            field.removeClass('error');
            
            // Email validation
            if (field.attr('type') === 'email' && !isValidEmail(value)) {
                field.addClass('error');
            }
            
            // Year validation
            if (field.attr('name') === 'jahrgang' || field.attr('name') === 'verify_jahrgang' || field.attr('name') === 'view_verify_jahrgang') {
                const year = parseInt(value);
                const currentYear = new Date().getFullYear();
                if (year < 1900 || year > currentYear || value.length !== 4) {
                    field.addClass('error');
                }
            }
        }
    });
    
    // Form field formatting
    $('input[name="jahrgang"], input[name="verify_jahrgang"], input[name="edit_jahrgang"], input[name="view_verify_jahrgang"]').on('input', function() {
        // Only allow numbers, exactly 4 digits
        let value = this.value.replace(/[^0-9]/g, '');
        if (value.length > 4) {
            value = value.substring(0, 4);
        }
        this.value = value;
    });
    
    $('input[name="freie_plaetze"], input[name="edit_freie_plaetze"]').on('input', function() {
        // Only allow numbers, max 10
        let value = this.value.replace(/[^0-9]/g, '');
        if (parseInt(value) > 10) {
            value = '10';
        }
        this.value = value;
    });
    
    // Auto-capitalize names
    $('input[name="vorname"], input[name="name"], input[name="edit_vorname"], input[name="edit_name"]').on('input', function() {
        const words = this.value.split(' ');
        for (let i = 0; i < words.length; i++) {
            if (words[i].length > 0) {
                words[i] = words[i][0].toUpperCase() + words[i].slice(1).toLowerCase();
            }
        }
        this.value = words.join(' ');
    });
    
    // Check for unsaved changes
    let formChanged = false;
    
    $('form input, form select, form textarea').on('change input', function() {
        formChanged = true;
    });
    
    $('form').on('submit', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged && (anmeldungModal.is(':visible') || mutationModal.is(':visible') || viewModal.is(':visible'))) {
            return 'Du hast ungespeicherte Änderungen. Möchtest du die Seite wirklich verlassen?';
        }
    });
    
    // Custom event for modal show/hide
    $.fn.extend({
        show: function() {
            this.css('display', 'block');
            this.trigger('show');
            return this;
        },
        hide: function() {
            this.css('display', 'none');
            this.trigger('hide');
            return this;
        }
    });
    
    // Keyboard accessibility
    $(document).on('keydown', function(e) {
        // Close modal with Escape key
        if (e.key === 'Escape') {
            if (anmeldungModal.is(':visible')) {
                anmeldungModal.hide();
                clearMessages();
            }
            if (mutationModal.is(':visible')) {
                mutationModal.hide();
                clearMessages();
            }
            if (viewModal.is(':visible')) {
                viewModal.hide();
                clearMessages();
            }
        }
    });
    
    // Focus management for modals
    anmeldungModal.on('show', function() {
        setTimeout(function() {
            $('#vorname').focus();
        }, 100);
    });
    
    mutationModal.on('show', function() {
        setTimeout(function() {
            $('#verify_email').focus();
        }, 100);
    });
    
    viewModal.on('show', function() {
        setTimeout(function() {
            $('#view_verify_email').focus();
        }, 100);
    });
    
    console.log('Wettkampf Frontend JS with FIXED Disziplinen Toggle and corrected Categories (U10, U12, U14, U16, U18) loaded successfully');
});