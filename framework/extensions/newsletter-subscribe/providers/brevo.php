<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class BrevoProvider extends Provider {
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			'https://api.brevo.com/v3/contacts/lists?limit=50&offset=0&sort=desc',
			array(
				'headers' => array(
					'api-key' => $api_key,
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
						'id'   => (string) $mailing_list['id'],
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

		$name_parts = $this->maybe_split_name( $args['name'] );
		$fname      = $name_parts['first_name'];
		$lname      = $name_parts['last_name'];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		curl_setopt_array(
			$curl,
			array(
				// CURLOPT_URL => "https://api.mailerlite.com/api/v2/groups/" . $args['group'] . "/subscribers",
				CURLOPT_URL            => 'https://api.brevo.com/v3/contacts',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode(
					array(
						'email'      => $args['email'],
						'attributes' => array_merge(
							array(
								'FIRSTNAME' => $fname,
							),
							( ! empty( $lname ) ? array( 'LASTNAME' => $lname ) : array() )
						),
						'listIds'    => array( intval( $args['group'] ) ),
					)
				),
				CURLOPT_HTTPHEADER     => array(
					'content-type: application/json',
					'api-key: ' . $settings['api_key'],
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

			if ( isset( $response['code'] ) ) {
				return array(
					'result'  => 'no',
					'message' => $response['message'],
				);
			}

			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscribed_successfully(),
			);
		}
	}
}
