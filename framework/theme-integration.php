<?php

namespace Blocksy;

class ThemeIntegration {
	public function __construct() {
		add_filter(
			'blocksy_add_menu_page',
			function ($res, $options) {
				add_menu_page(
					$options['title'],
					$options['menu-title'],
					$options['permision'],
					$options['top-level-handle'],
					$options['callback'],
					$options['icon-url'],
					2
				);

				return true;
			},
			10, 2
		);
	}
}
