<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove all field data stored in post, term, user and comment meta.
$wpdb->delete($wpdb->postmeta,    ['meta_key' => '_fieldforge_data']);
$wpdb->delete($wpdb->termmeta,    ['meta_key' => '_fieldforge_data']);
$wpdb->delete($wpdb->usermeta,    ['meta_key' => '_fieldforge_data']);
$wpdb->delete($wpdb->commentmeta, ['meta_key' => '_fieldforge_data']);

// Remove all indexed meta keys (_fieldforge_idx_*).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_fieldforge_idx_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_fieldforge_idx_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_fieldforge_idx_') . '%'
    )
);

// Remove options page data (_fieldforge_options_*).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_fieldforge_options_') . '%'
    )
);
