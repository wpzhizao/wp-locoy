<?php

namespace WPLocoy;

use WPLocoy\Settings;
use WPLocoy\SettingsPage;

final class Plugin
{
	public static function init()
	{
		Settings::init();

		new SettingsPage();

		add_action('init', array(__CLASS__, 'load'));
	}



	public static function load()
	{

		if (!self::is_locoy_page()) {
			return;
		}

		if (empty($_POST)) {
			return;
		}

		$settings = get_option('wp_locoy_settings');

		$api = new PublishApi($settings);

		$post_id = $api->publish();

		if (is_wp_error($post_id)) {
			$message = $post_id->get_error_message();
		} else {
			$message = sprintf(
				__('发布成功: ID为%s %s %s', 'wp-locoy'),
				$post_id,
				'<a href="' . get_permalink($post_id) . '">查看</a>',
				'<a href="' . get_edit_post_link($post_id) . '">编辑</a>'
			);
		}

		die($message);
	}

	public static function is_locoy_page()
	{
		return isset($_REQUEST['wp-locoy']);
	}
}
