<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class CampaignMonitorProvider extends Provider {
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			'https://api.createsend.com/api/v3.3/clients.json',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		$clients = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( array() === $clients ) {
			return array();
		}

		if (
			! $clients
			||
			empty( $clients )
			||
			! isset( $clients[0]['ClientID'] )
		) {
			return $this->get_api_error( $response, $api_key );
		}

		$response = wp_remote_get(
			'https://api.createsend.com/api/v3.3/clients/' . $clients[0]['ClientID'] . '/lists.json',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		$lists = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $lists ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		return array_map(
			function ( $mailing_list ) {
				return array(
					'name' => $mailing_list['Name'],
					'id'   => $mailing_list['ListID'],
				);
			},
			$lists
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

		$settings = $this->get_settings();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		$curl = curl_init();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL            => 'https://api.createsend.com/api/v3.3/subscribers/' . rawurlencode( $args['group'] ) . '.json',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode(
					array(
						'EmailAddress'   => $args['email'],
						'Name'           => $args['name'],
						'ConsentToTrack' => 'Yes',
					)
				),
				CURLOPT_HTTPHEADER     => array(
					'content-type: application/json',
				),
				CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
				CURLOPT_USERPWD        => $settings['api_key'] . ':x',
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$response = curl_exec( $curl );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$err = curl_error( $curl );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		curl_close( $curl );

		if ( $err ) {
			return array(
				'result' => 'no',
				'error'  => $err,
			);
		} else {
			$response = json_decode( $response, true );

			if ( isset( $response['Code'] ) ) {
				return array(
					'result'  => 'no',
					'message' => $response['Message'],
				);
			}

			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscribed_successfully(),
			);
		}
	}
}
