jQuery(document).ready(function($) {
    'use strict';
    
    // Admin-specific functionality for wettkampf anmeldungen page
    
    // Simple inline edit functionality for admin
    $('.edit-anmeldung-admin').on('click', function(e) {
        e.preventDefault();
        const anmeldungId = $(this).data('anmeldung-id');
        const editUrl = '?page=wettkampf-anmeldungen&edit=' + anmeldungId;
        window.location.href = editUrl;
    });
    
    // Confirmation dialogs for deletions
    $('a[href*="delete="]').on('click', function(e) {
        const href = $(this).attr('href');
        if (href.indexOf('delete=') > -1) {
            const confirmMessage = $(this).text().toLowerCase().indexOf('anmeldung') > -1 
                ? 'Anmeldung wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.'
                : 'Eintrag wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.';
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Export button loading states
    $('.export-button').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text('Export wird erstellt...');
        button.addClass('loading');
        
        // Reset after 5 seconds (export should be complete by then)
        setTimeout(function() {
            button.text(originalText);
            button.removeClass('loading');
        }, 5000);
    });
    
    // Auto-submit filter forms when dropdowns change
    $('select[name="wettkampf_id"]').on('change', function() {
        if ($(this).closest('form').find('input[name="search"]').val() === '') {
            $(this).closest('form').submit();
        }
    });
    
    // Enhanced table row highlighting
    $('.wp-list-table tbody tr').hover(
        function() {
            $(this).addClass('hover-highlight');
        },
        function() {
            $(this).removeClass('hover-highlight');
        }
    );
    
    // Add visual feedback for form submissions
    $('form').on('submit', function() {
        const submitButton = $(this).find('input[type="submit"], button[type="submit"]');
        const originalValue = submitButton.val() || submitButton.text();
        
        submitButton.prop('disabled', true);
        if (submitButton.is('input')) {
            submitButton.val('Wird gespeichert...');
        } else {
            submitButton.text('Wird gespeichert...');
        }
        
        // Reset if form validation fails (after a short delay)
        setTimeout(function() {
            if (submitButton.prop('disabled')) {
                submitButton.prop('disabled', false);
                if (submitButton.is('input')) {
                    submitButton.val(originalValue);
                } else {
                    submitButton.text(originalValue);
                }
            }
        }, 3000);
    });
    
    // Toggle functionality for freie_plaetze in edit forms
    function toggleFreePlaetze(radio) {
        const row = document.getElementById('freie_plaetze_row');
        const input = document.getElementById('freie_plaetze');
        
        if (radio && row && input) {
            if (radio.value == '1') {
                row.style.display = '';
                input.setAttribute('required', 'required');
            } else {
                row.style.display = 'none';
                input.removeAttribute('required');
                input.value = '';
            }
        }
    }
    
    // Attach toggle functionality to radio buttons
    $('input[name="eltern_fahren"]').on('change', function() {
        toggleFreePlaetze(this);
    });
    
    // Initialize toggle state on page load
    const checkedRadio = $('input[name="eltern_fahren"]:checked')[0];
    if (checkedRadio) {
        toggleFreePlaetze(checkedRadio);
    }
    
    // Add sorting functionality to tables (simple client-side)
    $('.wp-list-table th').on('click', function() {
        const table = $(this).closest('table');
        const columnIndex = $(this).index();
        const rows = table.find('tbody tr').toArray();
        
        // Skip if this is the actions column or if there are no rows
        if ($(this).text().toLowerCase().indexOf('aktionen') > -1 || rows.length === 0) {
            return;
        }
        
        const isAscending = $(this).hasClass('sort-asc');
        
        // Remove all sort classes
        table.find('th').removeClass('sort-asc sort-desc');
        
        // Sort rows
        rows.sort(function(a, b) {
            const aText = $(a).find('td').eq(columnIndex).text().trim();
            const bText = $(b).find('td').eq(columnIndex).text().trim();
            
            // Try to parse as numbers for numeric columns
            const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
            
            let comparison = 0;
            if (!isNaN(aNum) && !isNaN(bNum)) {
                comparison = aNum - bNum;
            } else {
                comparison = aText.localeCompare(bText, 'de', { numeric: true });
            }
            
            return isAscending ? -comparison : comparison;
        });
        
        // Add sort class and rebuild table
        $(this).addClass(isAscending ? 'sort-desc' : 'sort-asc');
        table.find('tbody').empty().append(rows);
    });
    
    // Search functionality enhancement
    $('input[name="search"]').on('keyup', function(e) {
        if (e.keyCode === 13) { // Enter key
            $(this).closest('form').submit();
        }
    });
    
    // Add clear search button functionality
    if ($('input[name="search"]').val()) {
        $('input[name="search"]').after('<button type="button" class="button search-clear" style="margin-left: 5px;">✖</button>');
    }
    
    $(document).on('click', '.search-clear', function() {
        const form = $(this).closest('form');
        form.find('input[name="search"]').val('');
        form.submit();
    });
    
    // Statistics card animations
    $('.stat-card').each(function(index) {
        const card = $(this);
        setTimeout(function() {
            card.addClass('animate-in');
        }, index * 100);
    });
    
    // Add tooltips for action buttons
    $('a[title]').each(function() {
        $(this).tooltip({
            show: { delay: 500 },
            hide: { delay: 100 }
        });
    });
    
    // Form validation enhancements
    $('form input[required], form select[required]').on('blur', function() {
        const field = $(this);
        const value = field.val();
        
        if (!value || value.trim() === '') {
            field.addClass('error');
        } else {
            field.removeClass('error');
            
            // Additional validation for specific fields
            if (field.attr('type') === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    field.addClass('error');
                } else {
                    field.removeClass('error');
                }
            }
            
            if (field.attr('name') === 'jahrgang') {
                const year = parseInt(value);
                const currentYear = new Date().getFullYear();
                if (year < 1900 || year > currentYear || value.length !== 4) {
                    field.addClass('error');
                } else {
                    field.removeClass('error');
                }
            }
        }
    });
    
    // Auto-save draft functionality for forms (localStorage fallback)
    const formSelector = 'form[method="post"]';
    let formChanged = false;
    
    $(formSelector + ' input, ' + formSelector + ' select, ' + formSelector + ' textarea').on('input change', function() {
        formChanged = true;
        
        // Simple auto-save to localStorage (only for longer forms)
        if ($(formSelector + ' .form-table tr').length > 5) {
            const formData = {};
            $(formSelector + ' input, ' + formSelector + ' select, ' + formSelector + ' textarea').each(function() {
                const field = $(this);
                const name = field.attr('name');
                if (name) {
                    formData[name] = field.val();
                }
            });
            
            try {
                localStorage.setItem('wettkampf_form_draft', JSON.stringify(formData));
            } catch (e) {
                // localStorage not available
            }
        }
    });
    
    // Restore draft on page load
    try {
        const savedDraft = localStorage.getItem('wettkampf_form_draft');
        if (savedDraft && !$('input[name="wettkampf_id"], input[name="anmeldung_id"]').val()) {
            const formData = JSON.parse(savedDraft);
            Object.keys(formData).forEach(function(name) {
                const field = $('input[name="' + name + '"], select[name="' + name + '"], textarea[name="' + name + '"]');
                if (field.length && formData[name]) {
                    field.val(formData[name]);
                }
            });
            
            // Show restore notification
            if (Object.keys(formData).length > 0) {
                $('<div class="notice notice-info"><p>Entwurf wiederhergestellt. <a href="#" id="clear-draft">Entwurf löschen</a></p></div>').insertAfter('h1').first();
            }
        }
    } catch (e) {
        // JSON parse error or localStorage not available
    }
    
    // Clear draft
    $(document).on('click', '#clear-draft', function(e) {
        e.preventDefault();
        try {
            localStorage.removeItem('wettkampf_form_draft');
            $(this).closest('.notice').fadeOut();
        } catch (e) {
            // localStorage not available
        }
    });
    
    // Clear draft on successful form submission - WICHTIG: formChanged auf false setzen
    $(formSelector).on('submit', function() {
        formChanged = false; // WICHTIG: Verhindert die Warnmeldung beim Submit
        try {
            localStorage.removeItem('wettkampf_form_draft');
        } catch (e) {
            // localStorage not available
        }
    });
    
    // ENTFERNT: Warn about unsaved changes - Diese Funktion verursacht das Problem
    // Die beforeunload Warnung wurde komplett entfernt
    
    // Accessibility improvements
    
    // Add ARIA labels to action links
    $('a[href*="edit="]').attr('aria-label', function() {
        const row = $(this).closest('tr');
        const name = row.find('td:first').text().trim();
        return 'Bearbeite ' + name;
    });
    
    $('a[href*="delete="]').attr('aria-label', function() {
        const row = $(this).closest('tr');
        const name = row.find('td:first').text().trim();
        return 'Lösche ' + name;
    });
    
    // Keyboard navigation for tables
    $('.wp-list-table tbody tr').attr('tabindex', '0').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            const editLink = $(this).find('a[href*="edit="]');
            if (editLink.length) {
                e.preventDefault();
                window.location.href = editLink.attr('href');
            }
        }
    });
    
    // Focus management
    if (window.location.search.indexOf('edit=') > -1) {
        // Focus first form field when editing
        setTimeout(function() {
            $('form input:first, form select:first, form textarea:first').focus();
        }, 100);
    }
    
    // Print functionality
    if ($('.export-section').length) {
        $('.export-section .export-buttons').append(
            '<button type="button" class="export-button print-button" onclick="window.print()" style="background: #6c757d;">🖨️ Drucken</button>'
        );
    }
    
    // Success message auto-hide
    $('.notice-success').delay(5000).fadeOut(500);
    
    console.log('Wettkampf Admin JS loaded successfully');
});