<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class ActiveCampaignProvider extends Provider {
	public function fetch_lists( $api_key, $api_url ) {

		if ( ! $api_url ) {
			return new \WP_Error( 'api_url_missing', __( 'Please enter an API URL.', 'blocksy-companion' ) );
		}

		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			"{$api_url}/api/3/lists",
			array(
				'headers' => array(
					'Api-Token' => $api_key,
					'accept'    => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $body || ! isset( $body['lists'] ) || ! is_array( $body['lists'] ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			return array_map(
				function ( $mailing_list ) {
					return array(
						'name' => $mailing_list['name'],
						'id'   => $mailing_list['id'],
					);
				},
				$body['lists']
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
				CURLOPT_URL            => "{$settings['api_url']}/api/3/contacts",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode(
					array(
						'contact' => array(
							'email'     => $args['email'],
							'firstName' => $args['name'],
						),
					)
				),
				CURLOPT_HTTPHEADER     => array(
					'Api-Token: ' . $settings['api_key'],
					'accept: application/json',
					'content-type: application/json; charset=utf-8',
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

			if ( isset( $response['errors'][0] ) ) {
				return array(
					'result'  => 'no',
					'message' => $response['errors'][0]['title'],
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
			$curl = curl_init();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => "{$settings['api_url']}/api/3/contactLists",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING       => '',
					CURLOPT_MAXREDIRS      => 10,
					CURLOPT_TIMEOUT        => 30,
					CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST  => 'POST',
					CURLOPT_POSTFIELDS     => json_encode(
						array(
							'contactList' => array(
								'list'    => $args['group'],
								'contact' => $response['contact']['id'],
								'status'  => 1,
							),
						)
					),
					CURLOPT_HTTPHEADER     => array(
						'Api-Token: ' . $settings['api_key'],
						'accept: application/json',
						'content-type: application/json; charset=utf-8',
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

				if ( isset( $response['errors'][0] ) ) {
					return array(
						'result'  => 'no',
						'message' => $response['errors'][0]['title'],
					);
				}
			}

			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscribed_successfully(),
			);
		}
	}
}
