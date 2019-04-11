<?php

namespace Blocksy;

class Dashboard {
	public function __construct() {
		add_action(
			'admin_enqueue_scripts',
			[ $this, 'enqueue_static' ]
		);
	}

	public function enqueue_static() {
		if (! function_exists('blocksy_is_dashboard_page')) return;
		if (! blocksy_is_dashboard_page()) return;

		$data = get_plugin_data(BLOCKSY__FILE__);

		wp_enqueue_script(
			'blocksy-dashboard-scripts',
			BLOCKSY_URL . 'static/bundle/dashboard.js',
			['wp-i18n', 'ct-dashboard-scripts'],
			$data['Version'],
			true
		);

		wp_enqueue_style(
			'blocksy-dashboard-styles',
			BLOCKSY_URL . 'static/bundle/dashboard.css',
			[],
			$data['Version']
		);
	}
}
