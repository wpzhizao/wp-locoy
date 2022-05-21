<?php

namespace WPLocoy;

class Settings {

    public static function init() {
        add_action('init', array(__CLASS__, 'register'));
    }

    public static function register() {
        // delete_option('wp_locoy_settings');
        register_setting(
            'wp-locoy-settings',
            'wp_locoy_settings',
            array(
                'type' => 'object',
                'default' => array(
                    'secret' => wp_generate_password(12, false),
                    'check_title_empty' => true,
                    'check_title_dup' => true,
                    'default_post_author' => 0,
                    'post_date_interval' => 0
                ),
                'sanitize_callback' => array(__CLASS__, 'sanitize'),
                'properites' => array(
                    'secret' => array(
                        'type' => 'string'
                    ),
                    'check_title_empty' => array(
                        'type' => 'boolean'
                    ),
                    'check_title_dup' => array(
                        'type' => 'boolean'
                    ),
                    'default_post_author' => array(
                        'type' => 'integer'
                    ),
                    'post_date_interval' => array(
                        'type' => 'integer'
                    )
                )
            )
        );
    }

    public static function sanitize($settings) {
        $settings['check_title_empty'] = !empty($settings['check_title_empty']);
        $settings['check_title_dup'] = !empty($settings['check_title_dup']);
        $settings['post_date_interval'] = absint($settings['post_date_interval']);
        $settings['default_post_author'] = absint($settings['default_post_author']);

        return $settings;
    }
}
