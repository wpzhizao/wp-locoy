<?php

/**
 * Plugin Name: WP Locoy - WordPress火车头发布接口
 * Description: 火车头采集器的WordPress免登录发布接口插件。支持自定义字段和自定义分类，本地文件上传等。符合WordPress插入文章的流程规范，方便二次开发。
 * Author: Cloud Stone
 * Version: 1.0.0
 * Requires PHP: 7.4.0
 * Requires at least: 5.6
 * License: GPLv2 or later
 */

namespace WPLocoy;

define('WP_LOCOY_PATH', plugin_dir_path(__FILE__));
define('WP_LOCOY_URL', plugin_dir_url(__FILE__));

require_once WP_LOCOY_PATH . 'inc/Plugin.php';
Plugin::init();
