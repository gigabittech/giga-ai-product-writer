document.addEventListener('DOMContentLoaded', function() {
    function getProductId() {
        let productId = document.getElementById('post_ID')?.value;
        if (!productId && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            productId = wp.data.select('core/editor').getCurrentPostId();
        }
        return productId || 0;
    }

    const testBtn = document.getElementById('giga-apw-test-connection');
    if (testBtn) {
        testBtn.addEventListener('click', function() {
            const resultSpan = document.getElementById('giga-apw-test-result');
            const apiKey = document.getElementById('giga_ai_api_key')?.value;
            
            resultSpan.textContent = ' Testing...';
            resultSpan.className = '';
            
            const formData = new FormData();
            formData.append('action', 'giga_apw_test_connection');
            formData.append('nonce', giga_apw_data.nonce);

            fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultSpan.textContent = ' ✅ ' + data.data.message;
                    resultSpan.style.color = 'green';
                } else {
                    resultSpan.textContent = ' ❌ ' + data.data.message;
                    resultSpan.style.color = 'red';
                }
            })
            .catch(error => {
                resultSpan.textContent = ' ❌ Connection error.';
                resultSpan.style.color = 'red';
            });
        });
    }

    const activateLicenseBtn = document.getElementById('giga-apw-activate-license');
    if (activateLicenseBtn) {
        activateLicenseBtn.addEventListener('click', function() {
            const key = document.getElementById('giga_apw_license_key').value;
            if (!key) return;

            activateLicenseBtn.disabled = true;
            activateLicenseBtn.textContent = 'Activating...';

            const formData = new FormData();
            formData.append('action', 'giga_apw_activate_license');
            formData.append('nonce', giga_apw_data.nonce);
            formData.append('license_key', key);

            fetch(giga_apw_data.ajax_url, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message);
                    location.reload();
                } else {
                    alert(data.data.message);
                    activateLicenseBtn.disabled = false;
                    activateLicenseBtn.textContent = 'Activate';
                }
            });
        });
    }

    // Generator Flow
    const generateBtn = document.getElementById('giga-apw-generate-btn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            const btnText = generateBtn.querySelector('.giga-apw-btn-text');
            const spinner = generateBtn.querySelector('.giga-apw-spinner');
            
            generateBtn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = giga_apw_data.strings.generating;

            const targetKeywords = document.getElementById('giga_apw_keywords')?.value || '';
            const tone = document.getElementById('giga_apw_tone')?.value || '';
            const language = document.getElementById('giga_apw_language')?.value || '';
            const additionalInstructions = document.getElementById('giga_apw_instructions')?.value || '';
            
            const productId = getProductId();

            const formData = new FormData();
            formData.append('action', 'giga_apw_generate');
            formData.append('nonce', giga_apw_data.nonce);
            formData.append('product_id', productId);
            formData.append('target_keywords', targetKeywords);
            formData.append('tone', tone);
            formData.append('language', language);
            formData.append('additional_instructions', additionalInstructions);

            fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                generateBtn.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = '✨ Generate All Content';

                if (data.success) {
                    document.getElementById('giga-apw-options').style.display = 'none'; // Collapse options
                    
                    const score = data.data.scores;
                    document.getElementById('giga-apw-score-section').style.display = 'block';
                    document.getElementById('giga-apw-total-score').querySelector('.score-number').textContent = score.total;
                    const badge = document.getElementById('giga-apw-total-score');
                    badge.style.borderColor = score.grade_color;
                    badge.style.color = score.grade_color;
                    document.getElementById('giga-apw-score-label').textContent = score.grade_label;
                    document.getElementById('giga-apw-score-label').style.color = score.grade_color;

                    document.getElementById('giga-score-readability').textContent = score.readability + '/25';
                    document.getElementById('giga-score-seo').textContent = score.seo + '/25';
                    document.getElementById('giga-score-uniqueness').textContent = score.uniqueness + '/25';
                    document.getElementById('giga-score-benefits').textContent = score.benefits + '/15';
                    document.getElementById('giga-score-length').textContent = score.length + '/10';

                    // Add warning notice if score is low (< 70)
                    const warningBox = document.getElementById('giga-apw-quality-warning');
                    if (score.total < 70) {
                        if (!warningBox) {
                            const newWarning = document.createElement('div');
                            newWarning.id = 'giga-apw-quality-warning';
                            newWarning.className = 'giga-apw-notice warning';
                            newWarning.style.marginTop = '15px';
                            newWarning.innerHTML = `<strong>⚠️ Warning:</strong> Quality score is below 70. We recommend regenerating for better results.`;
                            document.getElementById('giga-apw-score-section').appendChild(newWarning);
                        }
                    } else if (warningBox) {
                        warningBox.remove();
                    }

                    document.getElementById('giga-apw-preview-section').style.display = 'block';
                    document.getElementById('giga-apw-generation-id').value = data.data.generation_id;

                    renderPanels(data.data.content);
                } else {
                    alert('Error: ' + (data.data?.message || 'Failed to generate.'));
                }
            })
            .catch(err => {
                generateBtn.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = '✨ Generate All Content';
                alert('An error occurred during generation.');
            });
        });
    }

    function simpleDiff(oldText, newText) {
        if (!oldText) return newText;
        if (!newText) return '';

        const oldWords = oldText.split(/\s+/);
        const newWords = newText.split(/\s+/);
        let result = '';

        const oldSet = new Set(oldWords);
        
        newWords.forEach(word => {
            if (oldSet.has(word)) {
                result += word + ' ';
            } else {
                result += `<span class="giga-diff-add">${word}</span> `;
            }
        });

        return result.trim();
    }

    function renderPanels(content) {
        const container = document.getElementById('giga-apw-panels-container');
        container.innerHTML = '';

        const productId = document.getElementById('post_ID')?.value;
        const formData = new FormData();
        formData.append('action', 'giga_apw_get_current_content');
        formData.append('nonce', giga_apw_data.nonce);
        formData.append('product_id', productId);

        fetch(giga_apw_data.ajax_url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(currentData => {
            const current = currentData.data || {};
            const fields = [
                { id: 'long_description', label: 'Long Description', content: content.long_description, current: current.description },
                { id: 'short_description', label: 'Short Description', content: content.short_description, current: current.short_description },
                { id: 'meta_title', label: 'Meta Title', content: content.meta_title, current: current.meta_title },
                { id: 'meta_description', label: 'Meta Description', content: content.meta_description, current: current.meta_description },
                { id: 'tags', label: 'Tags', content: (content.tags || []).join(', '), current: (current.tags || []).join(', ') },
                { id: 'alt_text', label: 'Image Alts', content: (content.alt_texts || []).join(', '), current: '' }
            ];

            let count = 0;
            fields.forEach(f => {
                if (!f.content) return;
                count++;
                
                const div = document.createElement('div');
                div.className = 'giga-apw-preview-box';
                
                const header = document.createElement('div');
                header.className = 'giga-apw-preview-header';
                header.innerHTML = `
                    <label>
                        <input type="checkbox" class="giga-apw-field-checkbox" value="${f.id}" checked>
                        <strong>${f.label}</strong>
                    </label>
                `;
                
                const grid = document.createElement('div');
                grid.className = 'giga-apw-preview-grid';
                
                const currentCol = document.createElement('div');
                currentCol.className = 'giga-apw-preview-col current';
                const currentHtml = f.current ? f.current.substring(0, 300) + (f.current.length > 300 ? '...' : '') : '<em style="color:#94a3b8">Empty</em>';
                currentCol.innerHTML = `
                    <div class="col-label">CURRENT</div>
                    <div class="col-content">${currentHtml}</div>
                `;
                
                const generatedCol = document.createElement('div');
                generatedCol.className = 'giga-apw-preview-col generated';
                
                // For long description, we don't do full word-diff as it might be messy with HTML
                let generatedHtml = f.content;
                if (f.id !== 'long_description' && f.current) {
                    generatedHtml = simpleDiff(f.current, f.content);
                }

                generatedCol.innerHTML = `
                    <div class="col-label">GENERATED (NEW)</div>
                    <div class="col-content">${generatedHtml}</div>
                `;
                
                grid.appendChild(currentCol);
                grid.appendChild(generatedCol);
                
                div.appendChild(header);
                div.appendChild(grid);
                container.appendChild(div);
            });

            document.getElementById('giga-apw-approved-count').textContent = count;
            document.querySelectorAll('.giga-apw-field-checkbox').forEach(cb => {
                cb.addEventListener('change', updateApprovedCount);
            });
        });
    }

    function updateApprovedCount() {
        const checked = document.querySelectorAll('.giga-apw-field-checkbox:checked').length;
        document.getElementById('giga-apw-approved-count').textContent = checked;
    }

    const selectAllBtn = document.getElementById('giga-apw-select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.giga-apw-field-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            updateApprovedCount();
        });
    }

    const toggleBreakdownBtn = document.getElementById('giga-apw-toggle-breakdown');
    if (toggleBreakdownBtn) {
        toggleBreakdownBtn.addEventListener('click', function() {
            const el = document.getElementById('giga-apw-score-breakdown');
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        });
    }

    // Publish Actions
    const publishBtn = document.getElementById('giga-apw-publish-btn');
    if (publishBtn) {
        publishBtn.addEventListener('click', function() {
            const generationId = document.getElementById('giga-apw-generation-id').value;
            const checkedBoxes = document.querySelectorAll('.giga-apw-field-checkbox:checked');
            const approvedFields = Array.from(checkedBoxes).map(cb => cb.value);

            if (approvedFields.length === 0) {
                alert('Please select at least one field to publish.');
                return;
            }

            publishBtn.disabled = true;
            publishBtn.textContent = 'Publishing...';

            const formData = new FormData();
            formData.append('action', 'giga_apw_publish');
            formData.append('nonce', giga_apw_data.nonce);
            formData.append('generation_id', generationId);
            approvedFields.forEach(f => formData.append('approved_fields[]', f));

            fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                publishBtn.disabled = false;
                publishBtn.innerHTML = `Publish Approved (<span id="giga-apw-approved-count">${approvedFields.length}</span>)`;
                if (data.success) {
                    alert('Published successfully!');
                    // Visually mark as published
                    checkedBoxes.forEach(cb => {
                        const panel = cb.closest('.giga-apw-panel');
                        panel.style.borderLeft = '4px solid #7C3AED';
                        panel.style.background = '#f3f0ff';
                    });
                } else {
                    alert('Error: ' + (data.data?.message || 'Failed to publish'));
                }
            })
            .catch(err => {
                publishBtn.disabled = false;
                publishBtn.innerHTML = `Publish Approved (<span id="giga-apw-approved-count">${approvedFields.length}</span>)`;
                alert('Failed to publish.');
            });
        });
    }

    // History Actions
    const historyBtn = document.getElementById('giga-apw-history-btn');
    const historyModal = document.getElementById('giga-apw-history-modal');
    if (historyBtn && historyModal) {
        historyBtn.addEventListener('click', function() {
            const productId = getProductId();
            
            const formData = new FormData();
            formData.append('action', 'giga_apw_get_history');
            formData.append('nonce', giga_apw_data.nonce);
            formData.append('product_id', productId);

            fetch(giga_apw_data.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('giga-apw-history-tbody');
                    tbody.innerHTML = '';
                    
                    data.data.history.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${row.generated_at}</td>
                            <td>${row.quality_score}/100</td>
                            <td>${row.status}</td>
                            <td>
                                <button class="button giga-apw-revert-btn" data-id="${row.id}">Revert</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    historyModal.style.display = 'block';

                    document.querySelectorAll('.giga-apw-revert-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (!confirm('Are you sure you want to revert to this version? Current post data will be replaced.')) return;
                            
                            const genId = this.dataset.id;
                            const rData = new FormData();
                            rData.append('action', 'giga_apw_revert');
                            rData.append('nonce', giga_apw_data.nonce);
                            rData.append('generation_id', genId);

                            fetch(giga_apw_data.ajax_url, {
                                method: 'POST',
                                body: rData
                            }).then(r => r.json()).then(res => {
                                if (res.success) {
                                    alert('Successfully reverted! Please reload the page to see changes.');
                                    location.reload();
                                } else {
                                    alert('Error reverting: ' + (res.data?.message || ''));
                                }
                            });
                        });
                    });
                }
            });
        });

        historyModal.querySelector('.giga-apw-close').addEventListener('click', function() {
            historyModal.style.display = 'none';
        });
    }

    const regenBtn = document.getElementById('giga-apw-regen-btn');
    if (regenBtn) {
        regenBtn.addEventListener('click', function() {
            document.getElementById('giga-apw-options').style.display = 'block';
            document.getElementById('giga-apw-score-section').style.display = 'none';
            document.getElementById('giga-apw-preview-section').style.display = 'none';
            document.getElementById('giga-apw-generate-btn').scrollIntoView({ behavior: 'smooth' });
        });
    }

    // --- BULK GENERATION (Refactored for Cron) ---
    const bulkStartBtn = document.getElementById('giga-apw-bulk-start');
    let pollingInterval = null;

    if (bulkStartBtn) {
        bulkStartBtn.addEventListener('click', function() {
            const categoryId = document.getElementById('giga_apw_bulk_category').value;
            const filterType = document.getElementById('giga_apw_bulk_filter').value;
            const autoPublish = document.getElementById('giga_apw_bulk_auto_publish').checked;
            const tone = document.getElementById('giga_apw_bulk_tone').value;
            const language = document.getElementById('giga_apw_bulk_language').value;
            const useBrandVoice = document.getElementById('giga_apw_bulk_brand_voice').checked;

            bulkStartBtn.disabled = true;
            bulkStartBtn.textContent = 'Starting Job...';

            // 1. Get IDs first
            const getIdsForm = new FormData();
            getIdsForm.append('action', 'giga_apw_bulk_get_counts');
            getIdsForm.append('nonce', giga_apw_data.nonce);
            getIdsForm.append('category_id', categoryId);
            getIdsForm.append('filter_type', filterType);

            fetch(giga_apw_data.ajax_url, { method: 'POST', body: getIdsForm })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.ids.length > 0) {
                    // 2. Start Cron Job
                    const startData = new FormData();
                    startData.append('action', 'giga_apw_bulk_start_job');
                    startData.append('nonce', giga_apw_data.nonce);
                    data.data.ids.forEach(id => startData.append('product_ids[]', id));
                    startData.append('filter_type', filterType);
                    startData.append('auto_publish', autoPublish);
                    startData.append('tone', tone);
                    startData.append('language', language);
                    startData.append('use_brand_voice', useBrandVoice);

                    fetch(giga_apw_data.ajax_url, { method: 'POST', body: startData })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            document.getElementById('giga-apw-bulk-progress-wrap').style.display = 'block';
                            document.getElementById('giga-apw-bulk-total').textContent = data.data.ids.length;
                            document.getElementById('giga-apw-bulk-stop').style.display = 'inline-block';
                            startPolling();
                        } else {
                            alert(res.data.message);
                            bulkStartBtn.disabled = false;
                        }
                    });
                } else {
                    alert('No products found.');
                    bulkStartBtn.disabled = false;
                }
            });
        });
    }

    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => {
            const formData = new FormData();
            formData.append('action', 'giga_apw_bulk_get_progress');
            formData.append('nonce', giga_apw_data.nonce);

            fetch(giga_apw_data.ajax_url, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                const job = data.data.job;
                if (!job) return;

                const total = job.total;
                const processed = job.completed + job.failed;
                const percent = Math.round((processed / total) * 100);

                document.getElementById('giga-apw-bulk-percentage').textContent = percent + '%';
                document.getElementById('giga-apw-bulk-bar').style.width = percent + '%';
                document.getElementById('giga-apw-bulk-count').textContent = processed;
                
                logBulk(`[Cron] Processed ${processed}/${total}...`);

                if (job.status !== 'running') {
                    clearInterval(pollingInterval);
                    bulkStartBtn.disabled = false;
                    bulkStartBtn.textContent = '🚀 Start Bulk Process';
                    document.getElementById('giga-apw-bulk-status').textContent = job.status === 'completed' ? 'Completed!' : 'Cancelled';
                    document.getElementById('giga-apw-bulk-stop').style.display = 'none';
                }
            });
        }, 5000);
    }

    function logBulk(msg) {
        const logs = document.getElementById('giga-apw-bulk-logs');
        if (!logs) return;
        const entry = document.createElement('div');
        entry.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
        logs.prepend(entry);
    }

    const bulkStopBtn = document.getElementById('giga-apw-bulk-stop');
    if (bulkStopBtn) {
        bulkStopBtn.addEventListener('click', function() {
            if (!confirm('Cancel this job? Progress won\'t be lost for already processed items.')) return;
            const formData = new FormData();
            formData.append('action', 'giga_apw_bulk_cancel');
            formData.append('nonce', giga_apw_data.nonce);
            fetch(giga_apw_data.ajax_url, { method: 'POST', body: formData }).then(() => {
                clearInterval(pollingInterval);
                location.reload();
            });
        });
    }

    // --- AUTO-RESUME BULK UI ---
    if (document.getElementById('giga-apw-bulk-progress-wrap')) {
        const checkRunning = new FormData();
        checkRunning.append('action', 'giga_apw_bulk_get_progress');
        checkRunning.append('nonce', giga_apw_data.nonce);
        fetch(giga_apw_data.ajax_url, { method: 'POST', body: checkRunning })
        .then(r => r.json())
        .then(data => {
            if (data.data.job && data.data.job.status === 'running') {
                document.getElementById('giga-apw-bulk-progress-wrap').style.display = 'block';
                document.getElementById('giga-apw-bulk-total').textContent = data.data.job.total;
                document.getElementById('giga-apw-bulk-stop').style.display = 'inline-block';
                startPolling();
            }
        });
    }
});
