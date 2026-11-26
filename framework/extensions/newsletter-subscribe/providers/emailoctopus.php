<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class EmailOctopusProvider extends Provider {
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			'https://api.emailoctopus.com/lists',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'accept'        => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $body || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			return array_map(
				function ( $mailing_list ) {
					return array(
						'name'         => $mailing_list['name'],
						'id'           => $mailing_list['id'],

						'double_optin' => $mailing_list['double_opt_in'],
					);
				},
				$body['data']
			);
		} else {
			return $this->get_api_error( $response, $api_key );
		}
	}

	public function subscribe_form( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'email'        => '',
				'name'         => '',
				'group'        => '',
				'double_optin' => false,
			)
		);

		$settings      = $this->get_settings();
		$target_status = $args['double_optin'] ? 'pending' : 'subscribed';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		$curl = curl_init();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL            => 'https://api.emailoctopus.com/lists/' . rawurlencode( $args['group'] ) . '/contacts',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode(
					array(
						'email_address' => $args['email'],
						'fields'        => array(
							'FirstName' => $args['name'],
						),
						'tags'          => array(),
						'status'        => $target_status,
					)
				),
				CURLOPT_HTTPHEADER     => array(
					"Authorization: Bearer {$settings['api_key']}",
					'content-type: application/json',
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		curl_exec( $curl );
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
			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscribed_successfully(),
			);
		}
	}
}
