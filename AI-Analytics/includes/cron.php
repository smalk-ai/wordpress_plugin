<?php

// Custom Intervals

function smalk_add_cron_intervals($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300
    );

    return $schedules;
}

add_filter('cron_schedules', 'smalk_add_cron_intervals');

// Starting

function smalk_start_cron_jobs_if_needed() {
    if (!wp_next_scheduled(SMALK_AI_DAILY_CRON_EVENT)) {
        wp_schedule_event(time(), 'daily', SMALK_AI_DAILY_CRON_EVENT);
    }

    if (!wp_next_scheduled(SMALK_AI_EVERY_FIVE_MINUTES_CRON_EVENT)) {
        wp_schedule_event(time(), 'every_five_minutes', SMALK_AI_EVERY_FIVE_MINUTES_CRON_EVENT);
    }
}

add_action('init', 'smalk_start_cron_jobs_if_needed');

// Stopping

function smalk_stop_cron_jobs() {
    wp_clear_scheduled_hook(SMALK_AI_DAILY_CRON_EVENT);
    wp_clear_scheduled_hook(SMALK_AI_EVERY_FIVE_MINUTES_CRON_EVENT);
}

register_deactivation_hook(SMALK_AI_PLUGIN_FILE, 'smalk_stop_cron_jobs');