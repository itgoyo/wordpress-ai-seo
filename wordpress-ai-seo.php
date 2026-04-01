<?php
/**
 * Plugin Name: WordPress AI SEO
 * Plugin URI:  https://github.com/itgoyo/wordpress-ai-seo
 * Description: 利用 DeepSeek / NVIDIA GLM 大模型，一键生成 SEO 标题、关键词、描述、标签及图文并茂的正文内容。
 * Version:     1.0.0
 * Author:      itgoyo
 * License:     MIT
 * Text Domain: wp-ai-seo
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_AI_SEO_VERSION', '1.0.0' );
define( 'WP_AI_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once WP_AI_SEO_DIR . 'includes/class-settings.php';
require_once WP_AI_SEO_DIR . 'includes/class-api-client.php';
require_once WP_AI_SEO_DIR . 'includes/class-meta-box.php';
require_once WP_AI_SEO_DIR . 'includes/class-ajax-handler.php';

add_action( 'plugins_loaded', array( 'WP_AI_SEO_Settings', 'init' ) );
add_action( 'plugins_loaded', array( 'WP_AI_SEO_Meta_Box', 'init' ) );
add_action( 'plugins_loaded', array( 'WP_AI_SEO_Ajax_Handler', 'init' ) );
