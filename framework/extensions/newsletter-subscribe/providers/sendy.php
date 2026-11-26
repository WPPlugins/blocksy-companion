<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class SendyProvider extends Provider {
	private function request( $path, $api_url, $body = array() ) {
		$response = wp_remote_post(
			trailingslashit( $api_url ) . ltrim( $path, '/' ),
			array(
				'timeout' => 30,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => $response->get_error_message(),
				'code'  => 0,
				'body'  => null,
			);
		}

		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $raw_body, true );

		return array(
			'error' => null,
			'code'  => wp_remote_retrieve_response_code( $response ),
			'body'  => is_array( $decoded_body ) ? $decoded_body : $raw_body,
		);
	}

	private function is_empty_response( $response, $empty_messages ) {
		if (
			$response['error']
			||
			200 !== $response['code']
			||
			! is_string( $response['body'] )
		) {
			return false;
		}

		return in_array( trim( $response['body'] ), $empty_messages, true );
	}

	public function fetch_lists( $api_key, $api_url ) {
		if ( ! $api_url ) {
			return new \WP_Error( 'api_url_missing', __( 'Please enter an API URL.', 'blocksy-companion' ) );
		}

		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$brands_response = $this->request(
			'/api/brands/get-brands.php',
			$api_url,
			array(
				'api_key' => $api_key,
			)
		);

		if ( $this->is_empty_response( $brands_response, array( 'No brands found' ) ) ) {
			return array();
		}

		if (
			$brands_response['error']
			||
			200 !== $brands_response['code']
			||
			! is_array( $brands_response['body'] )
		) {
			return $this->get_api_error( $brands_response, $api_key );
		}

		$brands = array_values(
			array_filter(
				$brands_response['body'],
				function ( $brand ) {
					return isset( $brand['id'], $brand['name'] );
				}
			)
		);

		if ( empty( $brands ) && ! empty( $brands_response['body'] ) ) {
			return $this->get_api_error( $brands_response, $api_key );
		}

		$has_multiple_brands = count( $brands ) > 1;
		$lists               = array();

		foreach ( $brands as $brand ) {
			$lists_response = $this->request(
				'/api/lists/get-lists.php',
				$api_url,
				array(
					'api_key'  => $api_key,
					'brand_id' => $brand['id'],
				)
			);

			$empty_lists = array( 'No lists found', 'Brand does not exist' );

			if ( $this->is_empty_response( $lists_response, $empty_lists ) ) {
				continue;
			}

			if (
				$lists_response['error']
				||
				200 !== $lists_response['code']
				||
				! is_array( $lists_response['body'] )
			) {
				return $this->get_api_error( $lists_response, $api_key );
			}

			foreach ( $lists_response['body'] as $mailing_list ) {
				if ( ! isset( $mailing_list['id'], $mailing_list['name'] ) ) {
					continue;
				}

				$lists[] = array(
					'name' => $has_multiple_brands ? $brand['name'] . ' / ' . $mailing_list['name'] : $mailing_list['name'],
					'id'   => $mailing_list['id'],
				);
			}
		}

		return $lists;
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

		$response = $this->request(
			'/subscribe',
			$settings['api_url'],
			array(
				'api_key' => $settings['api_key'],
				'name'    => $args['name'],
				'email'   => $args['email'],
				'list'    => $args['group'],
				'boolean' => 'true',
			)
		);

		if ( $response['error'] || 200 !== $response['code'] ) {
			return array(
				'result'  => 'no',
				'message' => NewsletterMessages::unable_to_subscribe(),
				'error'   => $response['error'],
			);
		}

		$body = is_string( $response['body'] ) ? trim( $response['body'] ) : '';

		if ( '1' !== $body && strtolower( $body ) !== 'true' ) {
			return array(
				'result'  => 'no',
				'message' => $body ? $body : NewsletterMessages::unable_to_subscribe(),
			);
		}

		return array(
			'result'  => 'yes',
			'message' => NewsletterMessages::subscribed_successfully(),
		);
	}
}
