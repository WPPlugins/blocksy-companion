<?php

require_once dirname( __FILE__ ) . '/helpers.php';

class Blocksy_Widget_Ct_Instagram extends Blocksy_Widget_Factory {
	protected function get_config() {
		return [
			'name' => 'Instagram',
			'description' => 'Popular/Recent/Most Commented Posts.',
		];
	}

	public function get_path() {
		return dirname(__FILE__);
	}
}



