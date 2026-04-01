<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="giga-apw-main-container">
    <div id="giga-apw-notices-handler"></div>
    
    <div class="wrap giga-apw-wrap">
        <div class="giga-apw-header">
        <div class="giga-apw-logo-section">
            <span class="giga-apw-logo">📖</span>
            <div class="giga-apw-title-text">
                <h1>Documentation · Guide</h1>
                <p class="description">Learn how to master the Giga AI Product Writer.</p>
            </div>
        </div>
    </div>

    <div class="giga-apw-dashboard">
        <div class="giga-apw-card">
            <div class="giga-apw-section-title">
                <h3>🚀 Quick Start Guide</h3>
                <p>Follow these steps to generate your first AI product content.</p>
            </div>

            <div class="giga-apw-docs-steps">
                <div class="doc-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>API Configuration</h4>
                        <p>Go to <strong>Settings</strong> and enter your API Key. If you are using Z.ai, ensure you select it from the provider list. Click "Test Connection" to verify.</p>
                    </div>
                </div>

                <div class="doc-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Global Settings</h4>
                        <p>Set your default <strong>Target Language</strong> (e.g., English or Bengali) and <strong>Tone</strong>. These settings will be the baseline for all generations.</p>
                    </div>
                </div>

                <div class="doc-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Individual Generation</h4>
                        <p>Go to any <strong>Product Edit</strong> page. You will see the "Giga AI Writer" meta box on the right. Click "Generate" to create a description, SEO meta, and alt text for that specific product.</p>
                    </div>
                </div>

                <div class="doc-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Bulk Generation (Pro)</h4>
                        <p>If you have hundreds of products, use the <strong>Bulk Generate</strong> menu. Select a category and criteria (like "No descriptions") and let the AI process everything in the background.</p>
                    </div>
                </div>

                <div class="doc-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h4>Brand Voice (Pro)</h4>
                        <p>Train the AI with your unique style in the <strong>Brand Voice</strong> section. Provide examples of your best writing to make all generated content sound like your brand.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="giga-apw-card">
            <h3>❓ Frequently Asked Questions</h3>
            <div class="giga-apw-faq">
                <div class="faq-item">
                    <strong>Can I edit the generated content?</strong>
                    <p>Yes! All generated content is saved as a draft or updated content that you can manually refine before publishing.</p>
                </div>
                <div class="faq-item">
                    <strong>Is it SEO friendly?</strong>
                    <p>Absolutely. The plugin automatically generates meta titles, descriptions, and image alt tags specifically optimized for search engines.</p>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<style>
.giga-apw-docs-steps {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-top: 32px;
}

.doc-step {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 16px;
    border: 1px solid #f1f5f9;
}

.step-number {
    width: 40px;
    height: 40px;
    background: var(--giga-primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}

.step-content h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #0f172a;
}

.step-content p {
    margin: 0;
    color: #64748b;
    line-height: 1.6;
}

.giga-apw-faq {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.faq-item strong {
    display: block;
    margin-bottom: 8px;
    color: #0f172a;
}

.faq-item p {
    color: #64748b;
    margin: 0;
}
</style>
