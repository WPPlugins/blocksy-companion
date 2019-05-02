<?php

require_once dirname( __FILE__ ) . '/helpers.php';

class BlocksyExtensionCookiesConsent {
	public static function should_display_notification() {
		return !isset($_COOKIE['blocksy_cookies_consent_accepted']);
	}

	public function __construct() {
		add_filter(
			'blocksy_extensions_customizer_options',
			[$this, 'add_options_panel']
		);

		add_action(
			'customize_preview_init',
			function () {
				if (! function_exists('get_plugin_data')){
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				wp_enqueue_script(
					'blocksy-cookies-consent-customizer-sync',
					BLOCKSY_URL . 'framework/extensions/cookies-consent/static/bundle/sync.js',
					[ 'customize-preview' ],
					$data['Version'],
					true
				);
			}
		);

		add_action('wp_enqueue_scripts', function () {
			if (! function_exists('get_plugin_data')) {
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			}

			$data = get_plugin_data(BLOCKSY__FILE__);

			if (is_admin()) {
				return;
			}

			if (! BlocksyExtensionCookiesConsent::should_display_notification()) {
				if (! is_customize_preview()) {
					return;
				}
			}

			wp_enqueue_style(
				'blocksy-ext-cookies-consent-styles',
				BLOCKSY_URL . 'framework/extensions/cookies-consent/static/bundle/main.css',
				[],
				$data['Version']
			);

			wp_enqueue_script(
				'blocksy-ext-cookies-consent-scripts',
				BLOCKSY_URL . 'framework/extensions/cookies-consent/static/bundle/main.js',
				[],
				$data['Version'],
				true
			);
		});

		add_action('blocksy:global-dynamic-css:enqueue', function (
			$css, $tablet_css, $mobile_css
		) {

			blocksy_get_variables_from_file(
				dirname( __FILE__ ) . '/global.php',
				[],
				[
					'css' => $css,
					'tablet_css' => $tablet_css,
					'mobile_css' => $mobile_css
				]
			);

		}, 10, 3);
	}

	public function add_options_panel($options) {
		$options['cookie_consent_ext'] = blocksy_get_options(
			dirname( __FILE__ ) . '/customizer.php',
			[], false
		);

		return $options;
	}
}

