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

		add_filter('upload_mimes', function ($mimes) {
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		});

		add_action(
			'elementor/element/section/section_layout/before_section_end',
			function ($element, $args) {

				$element->add_control('fix_columns_alignment', [
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label' => esc_html__( 'Columns Alignment Fix', 'blc' ),
					// 'description' => esc_html__( 'It will remove the "weird" columns gap added by Elementor on the left and right side of each section (when `Columns Gap` is active). This helps you to have consistent content width without having to manually readjust it everytime you create sections with `Columns Gap`', 'blc' ),
					'return_value' => 'fix',
					'default' => '',
					'separator' => 'before',
					'prefix_class' => 'ct-columns-alignment-',
				]);

			},
			10, 2
		);
	}
}
