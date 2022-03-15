<?php

namespace WPLocoy;

final class Plugin {
	public static function init() {
		require_once WP_LOCOY_PATH . 'inc/PublishApi.php';
		require_once WP_LOCOY_PATH . 'inc/SettingsPage.php';
		new SettingsPage();

		add_action('init', array(__CLASS__, 'load'));
	}

	public static function sanitize_settings_callback($settings) {
		$settings['check_title_empty'] = !empty($settings['check_title_empty']);
		$settings['check_title_dup'] = !empty($settings['check_title_dup']);
		$settings['post_date_interval'] = absint($settings['post_date_interval']);
		$settings['default_post_author'] = absint($settings['default_post_author']);

		return $settings;
	}

	public static function load() {
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
				'sanitize_callback' => array(__CLASS__, 'sanitize_settings_callback')
			)
		);

		if (!self::is_locoy_page()) {
			return;
		}

		if (empty($_POST)) {
			return;
		}

		$settings = get_option('wp_locoy_settings');

		$api = new PublishApi($settings);

		$r = $api->publish();

		if (is_wp_error($r)) {
			echo $r->get_error_message();
		} else {
			echo __('添加成功', '');
		}

		die;
	}

	public static function is_locoy_page() {
		return isset($_REQUEST['wp-locoy']);
	}
}
