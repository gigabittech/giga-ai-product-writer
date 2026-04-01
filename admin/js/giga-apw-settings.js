document.addEventListener('DOMContentLoaded', function() {
    console.log('Giga APW Settings: DOM loaded');
    
    // Initialize global variables
    let formDirty = false;
    let currentProvider = document.getElementById('giga_ai_provider')?.value || 'claude';
    
    // Initialize everything
    initializeSettings();
    initializeNavigation();
    initializeProviderSelection();
    initializeFormInteractions();
    initializeRangeSliders();
    initializeToggleSwitches();
    initializeFormSubmission();
    initializeUtilities();
    initializeNoticeMover();

    /**
     * Initialize settings from current values
     */
    function initializeSettings() {
        console.log('Giga APW Settings: Initializing settings');
        
        // Set initial provider
        updateProviderFields(currentProvider);
        
        // Set initial tone selection
        const selectedTone = document.querySelector('.giga-apw-tone-card.selected');
        if (selectedTone) {
            document.getElementById('giga_apw_settings[default_tone]').value = selectedTone.dataset.tone;
        }
        
        // Update range slider displays
        updateRangeDisplay('giga_apw_settings[min_words]', 'giga_apw_settings[max_words]', 'words-range-display');
        updateRangeDisplay('giga_apw_settings[short_min_words]', 'giga_apw_settings[short_max_words]', 'short-words-range-display');
        
        // Update temperature display
        const tempSlider = document.getElementById('giga_apw_settings[temperature]');
        if (tempSlider) {
            document.getElementById('temperature-value').textContent = tempSlider.value;
        }
        
        // Update quality gate threshold display
        const qualitySlider = document.getElementById('giga_apw_settings[quality_gate_threshold]');
        if (qualitySlider) {
            document.getElementById('quality-threshold-value').textContent = qualitySlider.value;
        }
        
        // Show/hide sub-options based on toggle states
        updateSubOptions();
        
        console.log('Giga APW Settings: Settings initialized');
    }

    /**
     * Initialize navigation (tabs)
     */
    function initializeNavigation() {
        console.log('Giga APW Settings: Initializing navigation');
        
        const tabButtons = document.querySelectorAll('.giga-apw-tab-button');
        const tabContents = document.querySelectorAll('.giga-apw-tab-content');
        
        console.log('Found tab buttons:', tabButtons.length);
        console.log('Found tab contents:', tabContents.length);
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Tab clicked:', this.dataset.tab);
                
                // Update active tab button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding tab content
                const targetTab = this.dataset.tab;
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === targetTab) {
                        content.classList.add('active');
                        console.log('Activated tab:', targetTab);
                    }
                });
            });
        });
        
        console.log('Giga APW Settings: Navigation initialized');
    }

    /**
     * Initialize provider selection
     */
    function initializeProviderSelection() {
        console.log('Giga APW Settings: Initializing provider selection');
        
        const providerSelect = document.getElementById('giga_ai_provider');
        
        if (providerSelect) {
            providerSelect.addEventListener('change', function() {
                const provider = this.value;
                console.log('Provider changed to:', provider);
                currentProvider = provider;
                
                // Clear model hidden input so PHP selects the best default for the new provider
                const modelInput = document.getElementById('giga_ai_model');
                if (modelInput) modelInput.value = '';
                
                updateProviderFields(provider);
                markFormDirty();
            });
        }
        
        console.log('Giga APW Settings: Provider selection initialized');
    }

    /**
     * Update provider-specific fields
     */
    function updateProviderFields(provider) {
        console.log('Updating provider fields for:', provider);
        
        const apiKeyField = document.querySelector('#giga_ai_api_key')?.closest('.giga-apw-field');
        const baseUrlField = document.querySelector('#giga_ollama_base_url')?.closest('.giga-apw-field');
        const modelSelect = document.getElementById('giga_ai_model');
        
        // Base URL field is handled by CSS or this logic if needed
        if (provider === 'ollama') {
            if (apiKeyField) apiKeyField.style.display = 'none';
            if (baseUrlField) baseUrlField.style.display = 'block';
        } else {
            if (apiKeyField) apiKeyField.style.display = 'block';
            if (baseUrlField) baseUrlField.style.display = 'none';
        }
    }


    /**
     * Initialize form interactions
     */
    function initializeFormInteractions() {
        console.log('Giga APW Settings: Initializing form interactions');
        
        // Password toggle
        const passwordToggles = document.querySelectorAll('.giga-apw-toggle-password');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const passwordInput = this.parentElement.querySelector('input[type="password"]');
                const showIcon = this.querySelector('.giga-apw-eye-icon');
                
                if (passwordInput && showIcon) {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        showIcon.textContent = '🙈';
                    } else {
                        passwordInput.type = 'password';
                        showIcon.textContent = '👁️';
                    }
                }
            });
        });
        
        // Test connection button
        const testConnectionBtn = document.getElementById('giga-apw-test-connection');
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', testConnection);
        }
        
        // License activation
        const activateLicenseBtn = document.getElementById('giga-apw-activate-license');
        if (activateLicenseBtn) {
            activateLicenseBtn.addEventListener('click', activateLicense);
        }
        
        // Reset form
        const resetFormBtn = document.getElementById('giga-apw-reset-form');
        if (resetFormBtn) {
            resetFormBtn.addEventListener('click', resetForm);
        }
        
        console.log('Giga APW Settings: Form interactions initialized');
    }

    /**
     * Test AI connection
     */
    function testConnection() {
        const btn = document.getElementById('giga-apw-test-connection');
        const btnText = btn?.querySelector('.giga-apw-btn-text');
        const spinner = btn?.querySelector('.giga-apw-spinner');
        const statusDiv = document.getElementById('giga-apw-connection-status');
        
        if (!btn || !btnText || !spinner || !statusDiv) {
            console.error('Test connection elements not found');
            return;
        }
        
        console.log('Testing connection...');
        
        // Show loading state
        btn.classList.add('loading');
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        btnText.textContent = 'Testing...';
        statusDiv.className = 'giga-apw-connection-status loading';
        statusDiv.textContent = 'Testing connection...';
        
        const formData = new FormData();
        formData.append('action', 'giga_test_connection');
        formData.append('nonce', giga_apw_data.settings_nonce);
        
        // Include current form data so we can test before saving
        formData.append('provider', document.getElementById('giga_ai_provider')?.value);
        formData.append('api_key', document.getElementById('giga_ai_api_key')?.value);
        formData.append('model', document.getElementById('giga_ai_model')?.value);
        formData.append('ollama_base_url', document.getElementById('giga_ollama_base_url')?.value);
        
        fetch(giga_apw_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Connection test result:', data);
            btn.classList.remove('loading');
            btn.disabled = false;
            spinner.style.display = 'none';
            btnText.textContent = 'Test Connection';
            
            if (data.success) {
                statusDiv.className = 'giga-apw-connection-status success';
                statusDiv.textContent = `✅ Connected · Model: ${data.data.model} · Response: ${data.data.latency}`;
                showToast('Connection successful!', 'success');
            } else {
                statusDiv.className = 'giga-apw-connection-status error';
                statusDiv.textContent = `❌ ${data.data.message}`;
                showToast('Connection failed: ' + data.data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Connection test error:', error);
            btn.classList.remove('loading');
            btn.disabled = false;
            spinner.style.display = 'none';
            btnText.textContent = 'Test Connection';
            statusDiv.className = 'giga-apw-connection-status error';
            statusDiv.textContent = '❌ Connection error';
            showToast('Connection error. Please try again.', 'error');
        });
    }

    /**
     * Activate license
     */
    function activateLicense() {
        const keyInput = document.getElementById('giga_apw_license_key');
        const key = keyInput?.value.trim();
        
        if (!key) {
            showToast('Please enter a license key.', 'error');
            return;
        }
        
        const btn = document.getElementById('giga-apw-activate-license');
        if (!btn) return;
        
        console.log('Activating license...');
        
        btn.classList.add('loading');
        btn.disabled = true;
        btn.textContent = 'Activating...';
        
        const formData = new FormData();
        formData.append('action', 'giga_activate_license');
        formData.append('nonce', giga_apw_data.nonce);
        formData.append('license_key', key);
        
        fetch(giga_apw_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('License activation result:', data);
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.textContent = 'Activate';
            
            if (data.success) {
                showToast(data.data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.data.message, 'error');
            }
        })
        .catch(error => {
            console.error('License activation error:', error);
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.textContent = 'Activate';
            showToast('Activation failed. Please try again.', 'error');
        });
    }

    /**
     * Initialize range sliders
     */
    function initializeRangeSliders() {
        console.log('Giga APW Settings: Initializing range sliders');
        
        // Word count range sliders
        const wordRangeInputs = document.querySelectorAll('#giga_apw_settings[min_words], #giga_apw_settings[max_words]');
        wordRangeInputs.forEach(input => {
            input.addEventListener('input', function() {
                updateRangeDisplay('giga_apw_settings[min_words]', 'giga_apw_settings[max_words]', 'words-range-display');
                validateWordRange('min_words', 'max_words');
                markFormDirty();
            });
        });
        
        // Short word count range sliders
        const shortWordRangeInputs = document.querySelectorAll('#giga_apw_settings[short_min_words], #giga_apw_settings[short_max_words]');
        shortWordRangeInputs.forEach(input => {
            input.addEventListener('input', function() {
                updateRangeDisplay('giga_apw_settings[short_min_words]', 'giga_apw_settings[short_max_words]', 'short-words-range-display');
                validateWordRange('short_min_words', 'short_max_words');
                markFormDirty();
            });
        });
        
        // Temperature slider
        const tempSlider = document.getElementById('giga_apw_settings[temperature]');
        if (tempSlider) {
            tempSlider.addEventListener('input', function() {
                document.getElementById('temperature-value').textContent = this.value;
                markFormDirty();
            });
        }
        
        // Quality gate threshold slider
        const qualitySlider = document.getElementById('giga_apw_settings[quality_gate_threshold]');
        if (qualitySlider) {
            qualitySlider.addEventListener('input', function() {
                document.getElementById('quality-threshold-value').textContent = this.value;
                markFormDirty();
            });
        }
        
        console.log('Giga APW Settings: Range sliders initialized');
    }

    /**
     * Validate word range (min cannot exceed max)
     */
    function validateWordRange(minId, maxId) {
        const minInput = document.getElementById(`giga_apw_settings[${minId}]`);
        const maxInput = document.getElementById(`giga_apw_settings[${maxId}]`);
        
        if (minInput && maxInput) {
            const min = parseInt(minInput.value) || 0;
            const max = parseInt(maxInput.value) || 0;
            
            if (min > max) {
                if (minId === 'min_words') {
                    minInput.value = max;
                } else {
                    maxInput.value = min;
                }
                updateRangeDisplay(minId, maxId, minId === 'min_words' ? 'words-range-display' : 'short-words-range-display');
            }
        }
    }

    /**
     * Update range slider display
     */
    function updateRangeDisplay(minId, maxId, displayId) {
        const minInput = document.getElementById(`giga_apw_settings[${minId}]`);
        const maxInput = document.getElementById(`giga_apw_settings[${maxId}]`);
        const display = document.getElementById(displayId);
        
        if (minInput && maxInput && display) {
            const min = parseInt(minInput.value) || 0;
            const max = parseInt(maxInput.value) || 0;
            display.textContent = `${min} – ${max} words`;
        }
    }

    /**
     * Initialize toggle switches
     */
    function initializeToggleSwitches() {
        console.log('Giga APW Settings: Initializing toggle switches');
        
        const toggles = document.querySelectorAll('.giga-apw-toggle-switch input[type="checkbox"]');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                // Handle sub-options
                const field = this.closest('.giga-apw-field');
                const subOptions = field?.querySelector('.giga-apw-sub-option');
                
                if (subOptions) {
                    if (this.checked) {
                        subOptions.classList.add('show');
                    } else {
                        subOptions.classList.remove('show');
                    }
                }
                
                markFormDirty();
            });
        });
        
        // Tone selection
        const toneCards = document.querySelectorAll('.giga-apw-tone-card');
        toneCards.forEach(card => {
            card.addEventListener('click', function() {
                toneCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                const toneInput = document.getElementById('giga_apw_settings[default_tone]');
                if (toneInput) {
                    toneInput.value = this.dataset.tone;
                }
                markFormDirty();
            });
        });
        
        console.log('Giga APW Settings: Toggle switches initialized');
    }

    /**
     * Update sub-options visibility based on toggle states
     */
    function updateSubOptions() {
        const toggles = document.querySelectorAll('.giga-apw-toggle-switch input[type="checkbox"]');
        
        toggles.forEach(toggle => {
            const field = toggle.closest('.giga-apw-field');
            const subOptions = field?.querySelector('.giga-apw-sub-option');
            
            if (subOptions) {
                if (toggle.checked) {
                    subOptions.classList.add('show');
                } else {
                    subOptions.classList.remove('show');
                }
            }
        });
    }

    /**
     * Initialize form submission
     */
    function initializeFormSubmission() {
        const form = document.getElementById('giga-apw-settings-form');
        
        if (!form) {
            console.error('Settings form not found');
            return;
        }
        
        console.log('Giga APW Settings: Initializing form submission');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            const originalText = submitBtn.textContent;
            submitBtn.innerHTML = '<span class="giga-apw-spinner" style="display: inline-block;">⏳</span> Saving...';
            
            // Collect form data
            const formData = new FormData(form);
            
            // action is needed for WordPress AJAX
            formData.append('action', 'giga_save_settings');
            
            // currentProvider should already be in the form, but we ensure it's up to date
            if (currentProvider) {
                formData.set('giga_ai_provider', currentProvider);
            }
            
            // Send AJAX request
            fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Form submission result:', data);
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                
                if (data.success) {
                    showToast('Settings saved successfully!', 'success');
                    markFormClean();
                    
                    // Update current provider if changed
                    if (data.data.provider !== currentProvider) {
                        currentProvider = data.data.provider;
                        updateProviderFields(currentProvider);
                    }
                } else {
                    showToast('Error saving settings: ' + data.data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                showToast('Error saving settings. Please try again.', 'error');
            });
        });
        
        console.log('Giga APW Settings: Form submission initialized');
    }

    /**
     * Reset form to defaults
     */
    function resetForm() {
        if (!confirm('Are you sure you want to reset all settings to defaults?')) {
            return;
        }
        
        console.log('Resetting form...');
        
        // Reset form fields
        const form = document.getElementById('giga-apw-settings-form');
        if (form) form.reset();
        
        // Reset provider selection
        currentProvider = 'claude';
        const providerSelect = document.getElementById('giga_ai_provider');
        if (providerSelect) {
            providerSelect.value = 'claude';
            updateProviderFields('claude');
        }
        
        // Reset tone selection
        document.querySelectorAll('.giga-apw-tone-card').forEach(card => {
            card.classList.remove('selected');
            if (card.dataset.tone === 'Professional') {
                card.classList.add('selected');
            }
        });
        
        // Reset displays
        updateRangeDisplay('giga_apw_settings[min_words]', 'giga_apw_settings[max_words]', 'words-range-display');
        updateRangeDisplay('giga_apw_settings[short_min_words]', 'giga_apw_settings[short_max_words]', 'short-words-range-display');
        
        // Update temperature display
        const tempSlider = document.getElementById('giga_apw_settings[temperature]');
        if (tempSlider) {
            document.getElementById('temperature-value').textContent = tempSlider.value;
        }
        
        // Update quality gate threshold display
        const qualitySlider = document.getElementById('giga_apw_settings[quality_gate_threshold]');
        if (qualitySlider) {
            document.getElementById('quality-threshold-value').textContent = qualitySlider.value;
        }
        
        // Reset provider fields
        updateProviderFields(currentProvider);
        
        // Reset toggle states and sub-options
        document.querySelectorAll('.giga-apw-toggle-switch input[type="checkbox"]').forEach(toggle => {
            toggle.checked = false;
        });
        updateSubOptions();
        
        markFormClean();
        showToast('Settings reset to defaults.', 'success');
    }

    /**
     * Initialize utility functions
     */
    function initializeUtilities() {
        console.log('Giga APW Settings: Initializing utilities');
        
        // Track form changes
        const formInputs = document.querySelectorAll('#giga-apw-settings-form input, #giga-apw-settings-form select');
        formInputs.forEach(input => {
            input.addEventListener('change', markFormDirty);
            input.addEventListener('input', markFormDirty);
        });
        
        // Hide unsaved badge when form is clean
        window.addEventListener('beforeunload', function(e) {
            if (formDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        console.log('Giga APW Settings: Utilities initialized');
    }

    /**
     * Mark form as dirty (has unsaved changes)
     */
    function markFormDirty() {
        if (!formDirty) {
            formDirty = true;
            const unsavedBadge = document.getElementById('giga-apw-unsaved');
            if (unsavedBadge) {
                unsavedBadge.style.display = 'inline-block';
            }
        }
    }

    /**
     * Mark form as clean (no unsaved changes)
     */
    function markFormClean() {
        formDirty = false;
        const unsavedBadge = document.getElementById('giga-apw-unsaved');
        if (unsavedBadge) {
            unsavedBadge.style.display = 'none';
        }
    }

    /**
     * Move WordPress system notices to our custom handler
     */
    function initializeNoticeMover() {
        const handler = document.getElementById('giga-apw-notices-handler');
        if (!handler) return;

        const moveNotices = () => {
            // Target any native WP notice classes OR WooCommerce alerts
            const notices = document.querySelectorAll('#wpbody-content .notice, #wpbody-content .error, #wpbody-content .updated, #wpbody-content .notice-warning, #wpbody-content .woocommerce-message, #wpbody-content .woocommerce-info, #wpbody-content .woocommerce-error');
            
            notices.forEach(notice => {
                // If the notice is not already in our handler
                if (!notice.closest('#giga-apw-notices-handler')) {
                    handler.appendChild(notice);
                    console.log('Relocated a WordPress/WooCommerce notice to premium handler');
                }
            });
        };

        // Aggressive initial move
        setTimeout(moveNotices, 100);
        setTimeout(moveNotices, 1000); // Catch delayed notices

        // Handle dynamic notices (Ajax/React injected)
        const observer = new MutationObserver((mutations) => {
            moveNotices();
        });

        const target = document.querySelector('#wpbody-content');
        if (target) {
            observer.observe(target, { childList: true, subtree: true });
        }
        
        // Also listen for WooCommerce events if possible
        document.addEventListener('updated_checkout', moveNotices);
        document.addEventListener('updated_cart_totals', moveNotices);
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        const toast = document.getElementById('giga-apw-toast');
        if (!toast) return;
        
        const toastMessage = toast.querySelector('.giga-apw-toast-message');
        const toastIcon = toast.querySelector('.giga-apw-toast-icon');
        
        // Set message
        toastMessage.textContent = message;
        
        // Set type styling
        toast.className = 'giga-apw-toast ' + type;
        
        // Set icon
        if (type === 'success') {
            toastIcon.textContent = '✅';
        } else if (type === 'error') {
            toastIcon.textContent = '❌';
        } else {
            toastIcon.textContent = 'ℹ️';
        }
        
        // Show toast
        toast.style.display = 'block';
        
        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.style.display = 'none';
            }, 300);
        }, 3000);
    }
    
    console.log('Giga APW Settings: All initialization complete');
});