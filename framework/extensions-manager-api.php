<?php

namespace Blocksy;

class ExtensionsManagerApi {
	public function __construct() {
		$this->attach_ajax_actions();
	}

	protected $ajax_actions = [
		'blocksy_extensions_status',

		'blocksy_extension_activate',
		'blocksy_extension_deactivate',
	];

	public function blocksy_extensions_status() {
		$this->check_capability( 'edit_plugins' );

		$manager = new ExtensionsManager();
		wp_send_json_success($manager->get_extensions());
	}

	public function blocksy_extension_activate() {
		$this->check_capability( 'edit_plugins' );
		$manager = new ExtensionsManager();

		$manager->activate_extension($this->get_extension_from_request());

		wp_send_json_success();
	}

	public function blocksy_extension_deactivate() {
		$this->check_capability( 'edit_plugins' );
		$manager = new ExtensionsManager();

		$manager->deactivate_extension($this->get_extension_from_request());

		wp_send_json_success();
	}

	public function check_capability( $cap = 'install_plugins' ) {
		$manager = new ExtensionsManager();

		if ( ! $manager->can( $cap ) ) {
			wp_send_json_error();
		}

		return true;
	}

	public function get_extension_from_request() {
		if ( ! isset( $_POST['ext'] ) ) {
			wp_send_json_error();
		}

		return addslashes( $_POST['ext'] );
	}

	public function attach_ajax_actions() {
		foreach ( $this->ajax_actions as $action ) {
			add_action(
				'wp_ajax_' . $action,
				[ $this, $action ]
			);
		}
	}
}

