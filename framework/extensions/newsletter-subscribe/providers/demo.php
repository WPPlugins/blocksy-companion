<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class DemoProvider extends Provider {
	public function __construct() {
	}

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		return array(
			array(
				'name' => __( 'Demo List', 'blocksy-companion' ),
				'id'   => 'demolist',
			),
		);
	}

	public function subscribe_form( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'email' => '',
				'name'  => '',
				'group' => '',
			)
		);

		return array(
			// 'response' => $response,
			'result'  => 'yes',
			'message' => NewsletterMessages::subscribed_successfully(),
		);
	}
}
