<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove all field data stored in post, term, user and comment meta.
$wpdb->delete($wpdb->postmeta,    ['meta_key' => '_ctrlfield_data']);
$wpdb->delete($wpdb->termmeta,    ['meta_key' => '_ctrlfield_data']);
$wpdb->delete($wpdb->usermeta,    ['meta_key' => '_ctrlfield_data']);
$wpdb->delete($wpdb->commentmeta, ['meta_key' => '_ctrlfield_data']);

// Remove all indexed meta keys (_ctrlfield_idx_*).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_ctrlfield_idx_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_ctrlfield_idx_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_ctrlfield_idx_') . '%'
    )
);

// Remove options page data (_ctrlfield_options_*).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_ctrlfield_options_') . '%'
    )
);

// Pro: license, webhook and audit-log options.
delete_option('_ctrlfield_pro_license_key');
delete_option('_ctrlfield_pro_instance_id');
delete_option('_ctrlfield_pro_license_cache');
delete_option('_ctrlfield_pro_webhook_secret');
delete_option('_ctrlf_pro_audit_log_db_version');

// Pro: drop all tables created by CtrlField (audit log + all CCT tables).
// Table names come from the DB itself (not user input), but we validate format before use.
$ctrlf_prefix = $wpdb->prefix . 'ctrlf_';
$tables       = $wpdb->get_col(
    $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($ctrlf_prefix) . '%')
);

foreach ($tables as $table) {
    if (is_string($table) && preg_match('/^[a-zA-Z0-9_$]+$/', $table)) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
}
