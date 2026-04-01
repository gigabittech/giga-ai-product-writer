<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'giga_apw_generations';

// Check if table exists first
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

$total_generations = 0;
$today_generations = 0;
$avg_quality = 0;
$recent_generations = [];

if ($table_exists) {
    // Fetch Statistics
    $total_generations = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $today_generations = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(generated_at) = CURDATE()");
    $avg_score = $wpdb->get_var("SELECT AVG(quality_score) FROM $table_name WHERE status = 'published'") ?: 0;
    $avg_quality = round((float)$avg_score, 1);

    // Fetch recent with join
    $recent_generations = $wpdb->get_results("SELECT g.*, p.post_title FROM $table_name g JOIN {$wpdb->posts} p ON g.product_id = p.ID ORDER BY generated_at DESC LIMIT 5");
}

$license = Giga_APW_License::get_instance();
$is_pro = $license->is_pro();
$limit = $license->get_monthly_limit();
$remaining = $license->get_monthly_remaining();
$usage_percent = $limit > 0 ? round((($limit - $remaining) / $limit) * 100) : 0;

?>
<div class="giga-apw-main-container">
    <div id="giga-apw-notices-handler"></div>
    
    <div class="wrap giga-apw-wrap">
        <div class="giga-apw-dashboard">
        <div class="giga-apw-header">
            <div class="giga-apw-logo-section">
                <span class="giga-apw-logo">⚡</span>
                <div class="giga-apw-title-text">
                    <h1>Dashboard · Overview</h1>
                    <p class="description">Monitor your AI content generation performance and plan status.</p>
                </div>
            </div>
        </div>

        <!-- Notification Center (for Toasts) -->
        <div id="giga-apw-notification-center"></div>

        <!-- Stats Grid -->
        <div class="giga-apw-stats-grid">
            <div class="giga-apw-stat-card">
                <div class="giga-apw-stat-icon stat-icon-total">✨</div>
                <div class="giga-apw-stat-content">
                    <span class="giga-apw-stat-label"><?php _e('Total Generated', 'giga-ai-product-writer'); ?></span>
                    <span class="giga-apw-stat-value"><?php echo number_format($total_generations); ?></span>
                </div>
            </div>
            
            <div class="giga-apw-stat-card">
                <div class="giga-apw-stat-icon stat-icon-today">📅</div>
                <div class="giga-apw-stat-content">
                    <span class="giga-apw-stat-label"><?php _e('Generated Today', 'giga-ai-product-writer'); ?></span>
                    <span class="giga-apw-stat-value"><?php echo number_format($today_generations); ?></span>
                </div>
            </div>
            
            <div class="giga-apw-stat-card">
                <div class="giga-apw-stat-icon stat-icon-quality">⭐</div>
                <div class="giga-apw-stat-content">
                    <span class="giga-apw-stat-label"><?php _e('Avg. Quality', 'giga-ai-product-writer'); ?></span>
                    <span class="giga-apw-stat-value"><?php echo $avg_quality; ?>%</span>
                </div>
            </div>
            
            <div class="giga-apw-stat-card">
                <div class="giga-apw-stat-icon stat-icon-plan">🚀</div>
                <div class="giga-apw-stat-content">
                    <span class="giga-apw-stat-label"><?php _e('Plan Type', 'giga-ai-product-writer'); ?></span>
                    <span class="giga-apw-stat-value"><?php echo $is_pro ? 'PRO VERSION' : 'FREE VERSION'; ?></span>
                </div>
            </div>
        </div>

        <div class="giga-apw-dashboard-grid">
            <!-- Usage Card -->
            <div class="giga-apw-card giga-apw-usage-card">
                <div class="giga-apw-card-header">
                    <h3>Current Usage</h3>
                    <span class="giga-apw-badge <?php echo $is_pro ? 'pro' : ''; ?>"><?php echo $is_pro ? 'Active' : 'Limited'; ?></span>
                </div>
                <div class="giga-apw-usage-visual">
                    <div class="usage-progress-bar">
                        <div class="usage-progress-fill" style="width: <?php echo $usage_percent; ?>%;"></div>
                    </div>
                </div>
                <div class="giga-apw-usage-details">
                    <div class="detail-item">
                        <span>Used this month</span>
                        <strong><?php echo ($limit - $remaining); ?> / <?php echo $limit; ?></strong>
                    </div>
                    <div class="detail-item">
                        <span>Remaining</span>
                        <strong><?php echo $remaining; ?></strong>
                    </div>
                </div>
                <?php if (!$is_pro): ?>
                <div class="usage-upgrade-promo">
                    <p>Unlock unlimited generations and bulk processing!</p>
                    <a href="<?php echo admin_url('admin.php?page=giga-apw-settings#plan'); ?>" class="giga-apw-btn-outline">Upgrade to PRO</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="giga-apw-card giga-apw-activity-card">
                <div class="giga-apw-card-header">
                    <h3>Recent Generates</h3>
                </div>
                <div class="giga-apw-activity-list">
                    <?php if ($recent_generations): ?>
                        <?php foreach ($recent_generations as $gen): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <strong><?php echo esc_html($gen->post_title); ?></strong>
                                    <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gen->generated_at)); ?></span>
                                </div>
                                <div class="activity-meta">
                                    <span class="stat-badge score-<?php echo $gen->quality_score >= 80 ? 'high' : ($gen->quality_score >= 60 ? 'mid' : 'low'); ?>">
                                        Score: <?php echo $gen->quality_score; ?>%
                                    </span>
                                    <span class="status-badge status-<?php echo $gen->status; ?>"><?php echo ucfirst($gen->status); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-state">No activity yet. Start generating content for your products!</p>
                    <?php endif; ?>
                </div>
                <div class="activity-footer">
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="view-all">Open WooCommerce Products →</a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="giga-apw-quick-nav">
            <a href="<?php echo admin_url('admin.php?page=giga-apw-bulk'); ?>" class="nav-item">
                <span class="icon">📦</span>
                <div class="nav-text">
                    <h4>Bulk Generate</h4>
                    <p>Generate for all products</p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=giga-apw-voice'); ?>" class="nav-item">
                <span class="icon">🎙️</span>
                <div class="nav-text">
                    <h4>Brand Voice</h4>
                    <p>Teach AI your style</p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=giga-apw-settings'); ?>" class="nav-item">
                <span class="icon">⚙️</span>
                <div class="nav-text">
                    <h4>Configuration</h4>
                    <p>Setup API & Models</p>
                </div>
            </a>
            <a href="https://gigaverse.io/docs" target="_blank" class="nav-item">
                <span class="icon">📖</span>
                <div class="nav-text">
                    <h4>Documentation</h4>
                    <p>Learn how to use</p>
                </div>
            </a>
        </div>
        </div>
    </div>
</div>
