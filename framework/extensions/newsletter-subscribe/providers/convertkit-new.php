<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class ConvertKitNewProvider extends Provider {
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {

		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			'https://api.kit.com/v4/forms/',
			array(
				'headers' => array(
					'X-Kit-Api-Key' => $api_key,
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $body || ! isset( $body['forms'] ) || ! is_array( $body['forms'] ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			return array_map(
				function ( $mailing_list ) {
					return array(
						'name' => $mailing_list['name'],
						'id'   => $mailing_list['id'],
					);
				},
				$body['forms']
			);
		} else {
			return $this->get_api_error( $response, $api_key );
		}
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
				CURLOPT_URL            => 'https://api.kit.com/v4/subscribers',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode(
					array(
						'email_address' => $args['email'],
						'first_name'    => $args['name'],
					)
				),
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					'X-Kit-Api-Key: ' . $settings['api_key'],
				),
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

			if ( isset( $response['errors'] ) ) {
				return array(
					'result'  => 'no',
					'message' => implode( ', ', $response['errors'] ),
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
			$curl = curl_init();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => 'https://api.kit.com/v4/forms/' . rawurlencode( $args['group'] ) . '/subscribers',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING       => '',
					CURLOPT_MAXREDIRS      => 10,
					CURLOPT_TIMEOUT        => 30,
					CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST  => 'POST',
					CURLOPT_POSTFIELDS     => json_encode(
						array(
							'email_address' => $args['email'],
						)
					),
					CURLOPT_HTTPHEADER     => array(
						'content-type: application/json; charset=utf-8',
						'X-Kit-Api-Key: ' . $settings['api_key'],
					),
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

				if ( isset( $response['errors'] ) ) {
					return array(
						'result'  => 'no',
						'message' => implode( ', ', $response['errors'] ),
					);
				}

				return array(
					'result'  => 'yes',
					'message' => NewsletterMessages::subscribed_successfully(),
				);
			}
		}
	}
}
