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
        const autoModelStatus = document.getElementById('giga-apw-auto-model-status');
        
        if (providerSelect) {
            providerSelect.addEventListener('change', function() {
                const provider = this.value;
                console.log('Provider changed to:', provider);
                currentProvider = provider;
                
                // Clear any previous model selection
                const modelInput = document.getElementById('giga_ai_model');
                if (modelInput) modelInput.value = '';
                
                // Update auto model status
                if (autoModelStatus) {
                    autoModelStatus.querySelector('.giga-apw-auto-model-status-text').textContent = 'Ready to connect';
                    autoModelStatus.querySelector('.giga-apw-status-dot').className = 'giga-apw-status-dot';
                }
                
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
        
        const paidOnlyFields = document.querySelectorAll('.provider-paid-only');
        const ollamaOnlyFields = document.querySelectorAll('.provider-ollama-only');
        const apiKeyHelp = document.getElementById('giga-apw-api-key-help');
        
        if (provider === 'ollama') {
            paidOnlyFields.forEach(f => f.style.display = 'none');
            ollamaOnlyFields.forEach(f => f.style.display = 'block');
        } else {
            paidOnlyFields.forEach(f => f.style.display = 'block');
            ollamaOnlyFields.forEach(f => f.style.display = 'none');
            
            // Update help text for API key
            if (apiKeyHelp) {
                let helpText = 'Configure your API key for content generation';
                switch (provider) {
                    case 'claude': helpText = 'Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>'; break;
                    case 'openai': helpText = 'Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a>'; break;
                    case 'gemini': helpText = 'Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>'; break;
                    case 'groq': helpText = 'Get your API key from <a href="https://console.groq.com/" target="_blank">Groq Console</a>'; break;
                    case 'zai': helpText = 'Get your API key from <a href="https://console.z.ai/" target="_blank">Z.ai Console</a>'; break;
                }
                apiKeyHelp.innerHTML = helpText;
            }
        }
    }
    
    /**
     * Update model dropdown options for selected provider
     */
    function updateModelDropdown(provider) {
        console.log('Updating model dropdown for provider:', provider);
        
        const modelSelect = document.getElementById('giga_ai_model');
        if (!modelSelect) return;
        
        // Store current selection if it exists
        const currentValue = modelSelect.value;
        
        // Clear existing options
        modelSelect.innerHTML = '';
        
        // Add loading option
        const loadingOption = document.createElement('option');
        loadingOption.value = '';
        loadingOption.textContent = 'Loading models...';
        modelSelect.appendChild(loadingOption);
        
        // Fetch models for the selected provider
        const formData = new FormData();
        formData.append('action', 'giga_get_models');
        formData.append('nonce', giga_apw_data.settings_nonce);
        formData.append('provider', provider);
        
        fetch(giga_apw_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear loading option
                modelSelect.innerHTML = '';
                
                // Add model options - no "Select a model" option needed
                data.models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.name;
                    option.textContent = model.label;
                    
                    // Restore selection if this model was previously selected
                    if (model.name === currentValue) {
                        option.selected = true;
                    }
                    
                    modelSelect.appendChild(option);
                });
                
                // Auto-select first available model if none is selected
                if (!currentValue && data.models.length > 0) {
                    modelSelect.value = data.models[0].name;
                    console.log('Auto-selected model:', data.models[0].name);
                }
                
                // Hide model selection dropdown - system will handle automatically
                if (data.models.length > 0) {
                    modelSelect.style.display = 'none';
                    console.log('Model selection hidden - using automatic selection');
                } else {
                    modelSelect.style.display = 'block';
                    console.log('No models available - showing selection dropdown');
                }
            } else {
                // Handle error
                modelSelect.innerHTML = '';
                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Failed to load models';
                modelSelect.appendChild(errorOption);
                modelSelect.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading models:', error);
            modelSelect.innerHTML = '';
            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Error loading models';
            modelSelect.appendChild(errorOption);
            modelSelect.style.display = 'block';
        });
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
     * Test AI connection with smart fallback logic
     */
    async function testConnection() {
        const btn = document.getElementById('giga-apw-test-connection');
        const btnText = btn?.querySelector('.giga-apw-btn-text');
        const spinner = btn?.querySelector('.giga-apw-spinner');
        const statusDiv = document.getElementById('giga-apw-connection-status');
        const autoModelStatus = document.getElementById('giga-apw-auto-model-status');
        
        if (!btn || !btnText || !spinner || !statusDiv) {
            console.error('Test connection elements not found');
            return;
        }
        
        console.log('Testing connection with automatic model selection...');
        
        // Show loading state
        btn.classList.add('loading');
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        btnText.textContent = 'Testing...';
        statusDiv.className = 'giga-apw-connection-status loading';
        statusDiv.textContent = 'Testing connection...';
        
        if (autoModelStatus) {
            const statusText = autoModelStatus.querySelector('.giga-apw-auto-model-status-text');
            const statusDot = autoModelStatus.querySelector('.giga-apw-status-dot');
            statusText.textContent = 'Connecting...';
            statusDot.className = 'giga-apw-status-dot loading';
        }
        
        const provider = document.getElementById('giga_ai_provider')?.value;
        const apiKey = document.getElementById('giga_ai_api_key')?.value;
        const ollamaUrl = document.getElementById('giga_ollama_base_url')?.value;
        
        // Test connection using the backend smart fallback logic
        try {
            const formData = new FormData();
            formData.append('action', 'giga_test_connection');
            formData.append('nonce', giga_apw_data.settings_nonce);
            formData.append('provider', provider);
            formData.append('api_key', apiKey);
            if (ollamaUrl) {
                formData.append('ollama_base_url', ollamaUrl);
            }
            
            const response = await fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Connection successful:', result);
                const msg = result.message || result.data?.message || 'Connection successful';
                statusDiv.className = 'giga-apw-connection-status success';
                statusDiv.textContent = msg;
                showSuccessToast('Connection successful: ' + msg);
                
                // Update auto model status
                if (autoModelStatus) {
                    const statusText = autoModelStatus.querySelector('.giga-apw-auto-model-status-text');
                    const statusDot = autoModelStatus.querySelector('.giga-apw-status-dot');
                    const connectedModel = result.model || result.data?.model;
                    statusText.textContent = 'Connected: ' + connectedModel;
                    statusDot.className = 'giga-apw-status-dot success';
                }
            } else {
                const errorMsg = result.error || result.data?.error || result.data?.message || 'Unknown error';
                console.error('Connection failed:', errorMsg);
                statusDiv.className = 'giga-apw-connection-status error';
                statusDiv.textContent = errorMsg;
                showErrorToast('Connection failed: ' + errorMsg);
                
                if (autoModelStatus) {
                    const statusText = autoModelStatus.querySelector('.giga-apw-auto-model-status-text');
                    const statusDot = autoModelStatus.querySelector('.giga-apw-status-dot');
                    statusText.textContent = 'Connection failed';
                    statusDot.className = 'giga-apw-status-dot error';
                }
            }
        } catch (error) {
            console.error('Connection test error:', error);
            statusDiv.className = 'giga-apw-connection-status error';
            statusDiv.textContent = 'Network error. Please try again.';
            showErrorToast('Network error. Please check your connection and try again.');
            
            if (autoModelStatus) {
                const statusText = autoModelStatus.querySelector('.giga-apw-auto-model-status-text');
                const statusDot = autoModelStatus.querySelector('.giga-apw-status-dot');
                statusText.textContent = 'Connection failed';
                statusDot.className = 'giga-apw-status-dot error';
            }
        }
        
        // Reset button state
        btn.classList.remove('loading');
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Test Connection';
    }
    
    /**
     * Test a single connection with specific model
     */
    async function testSingleConnection(provider, apiKey, model, ollamaUrl) {
        const formData = new FormData();
        formData.append('action', 'giga_test_connection');
        formData.append('nonce', giga_apw_data.settings_nonce);
        formData.append('provider', provider);
        formData.append('api_key', apiKey);
        formData.append('model', model);
        formData.append('ollama_base_url', ollamaUrl);
        
        try {
            const response = await fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            console.error('Single connection test error:', error);
            return { success: false, error: 'Network error occurred' };
        }
    }
    
    /**
     * Get available models for provider
     */
    async function getAvailableModels(provider) {
        const formData = new FormData();
        formData.append('action', 'giga_get_models');
        formData.append('nonce', giga_apw_data.settings_nonce);
        formData.append('provider', provider);
        
        try {
            const response = await fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            return data.success ? data.models.map(model => model.name) : [];
        } catch (error) {
            console.error('Error getting available models:', error);
            return [];
        }
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
                    // Show model switching information if applicable
                    if (data.data.model_was_switched) {
                        showToast(`Settings saved! Using ${data.data.model} (model was auto-corrected)`, 'success');
                    } else {
                        showToast('Settings saved successfully!', 'success');
                    }
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
            toastIcon.textContent = '✓';
        } else if (type === 'error') {
            toastIcon.textContent = '✕';
        } else {
            toastIcon.textContent = 'ℹ';
        }
        
        // Add or update close button
        let closeBtn = toast.querySelector('.giga-apw-toast-close');
        if (!closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.className = 'giga-apw-toast-close';
            closeBtn.innerHTML = '×';
            closeBtn.addEventListener('click', hideToast);
            toast.appendChild(closeBtn);
        }
        
        // Show toast
        toast.style.display = 'block';
        
        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Hide after 5 seconds (increased from 3 for better readability)
        const timeoutId = setTimeout(() => {
            hideToast();
        }, 5000);
        
        // Store timeout ID for potential manual close
        toast.dataset.timeoutId = timeoutId;
        
        // Add hover effect to pause auto-hide
        toast.addEventListener('mouseenter', () => {
            clearTimeout(parseInt(toast.dataset.timeoutId));
            toast.dataset.paused = 'true';
        });
        
        toast.addEventListener('mouseleave', () => {
            if (toast.dataset.paused === 'true') {
                toast.dataset.paused = 'false';
                // Restart auto-hide after 3 seconds on mouse leave
                const newTimeoutId = setTimeout(() => {
                    hideToast();
                }, 3000);
                toast.dataset.timeoutId = newTimeoutId;
            }
        });
        
        return toast; // Return toast for potential manual control
    }
    
    /**
     * Hide toast notification
     */
    function hideToast() {
        const toast = document.getElementById('giga-apw-toast');
        if (!toast) return;
        
        // Clear any existing timeout
        if (toast.dataset.timeoutId) {
            clearTimeout(parseInt(toast.dataset.timeoutId));
            delete toast.dataset.timeoutId;
        }
        
        // Animate out
        toast.classList.add('hiding');
        setTimeout(() => {
            toast.style.display = 'none';
            toast.classList.remove('hiding');
        }, 300);
    }
    
    /**
     * Show error toast with auto-hide and manual close
     */
    function showErrorToast(message) {
        const toast = showToast(message, 'error');
        
        // Auto-hide after 7 seconds for errors
        setTimeout(() => {
            hideToast();
        }, 7000);
        
        return toast;
    }
    
    /**
     * Show success toast with auto-hide and manual close
     */
    function showSuccessToast(message) {
        const toast = showToast(message, 'success');
        
        // Auto-hide after 3 seconds for success messages
        setTimeout(() => {
            hideToast();
        }, 3000);
        
        return toast;
    }
    
    /**
     * Update model status indicator
     */
    function updateModelStatus(data) {
        const autoModelStatus = document.getElementById('giga-apw-auto-model-status');
        if (!autoModelStatus) return;
        
        const statusDot = autoModelStatus.querySelector('.giga-apw-status-dot');
        const statusText = autoModelStatus.querySelector('.giga-apw-auto-model-status-text');
        
        if (data.success) {
            statusDot.className = 'giga-apw-status-dot success';
            if (data.model_was_switched) {
                statusText.textContent = 'Connected: ' + data.model + ' (auto-switched)';
            } else {
                statusText.textContent = 'Connected: ' + data.model;
            }
        } else {
            statusDot.className = 'giga-apw-status-dot error';
            statusText.textContent = 'Connection failed';
        }
    }
    
    console.log('Giga APW Settings: All initialization complete');
});