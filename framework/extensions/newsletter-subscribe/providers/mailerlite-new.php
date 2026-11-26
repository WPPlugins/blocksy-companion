<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class MailerliteNewProvider extends Provider {
	private function get_double_optin_status_from_body( $body ) {
		if ( ! is_array( $body ) ) {
			return null;
		}

		if ( isset( $body['enabled'] ) ) {
			return (bool) $body['enabled'];
		}

		if ( isset( $body['double_optin'] ) ) {
			return (bool) $body['double_optin'];
		}

		if ( isset( $body['data']['enabled'] ) ) {
			return (bool) $body['data']['enabled'];
		}

		if ( isset( $body['data']['double_optin'] ) ) {
			return (bool) $body['data']['double_optin'];
		}

		if ( isset( $body['settings']['double_optin'] ) ) {
			return (bool) $body['settings']['double_optin'];
		}

		if ( isset( $body['data']['settings']['double_optin'] ) ) {
			return (bool) $body['data']['settings']['double_optin'];
		}

		return null;
	}

	private function request( $method, $path, $api_key, $body = null ) {
		$args = array(
			'timeout'    => 30,
			'method'     => $method,
			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:87.0) Gecko/20100101 Firefox/87.0',
			'headers'    => array(
				'Authorization' => 'Bearer ' . $api_key,
				'accept'        => 'application/json',
				'content-type'  => 'application/json',
			),
		);

		if ( ! is_null( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request(
			'https://connect.mailerlite.com/api/' . ltrim( $path, '/' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => $response->get_error_message(),
				'code'  => 0,
				'body'  => array(),
			);
		}

		$decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded_body ) ) {
			$decoded_body = array();
		}

		return array(
			'error' => null,
			'code'  => wp_remote_retrieve_response_code( $response ),
			'body'  => $decoded_body,
		);
	}

	private function fetch_double_optin_status( $api_key ) {
		if ( ! $api_key ) {
			return false;
		}

		$cache_key = 'blocksy_ml_new_doi_' . md5( $api_key );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$response   = $this->request( 'GET', 'settings/double_optin', $api_key );
		$is_enabled = false;

		if ( ! $response['error'] && 200 === $response['code'] ) {
			$detected = $this->get_double_optin_status_from_body( $response['body'] );

			if ( ! is_null( $detected ) ) {
				$is_enabled = $detected;
			}
		}

		if ( ! $response['error'] && 404 === $response['code'] ) {
			$response = $this->request( 'GET', 'account', $api_key );
			$detected = $this->get_double_optin_status_from_body( $response['body'] );

			if (
				! $response['error']
				&&
				200 === $response['code']
				&&
				! is_null( $detected )
			) {
				$is_enabled = $detected;
			}
		}

		set_transient( $cache_key, $is_enabled ? '1' : '0', 5 * MINUTE_IN_SECONDS );

		return $is_enabled;
	}

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$response = $this->request( 'GET', 'groups', $api_key );

		if ( $response['error'] || 200 !== $response['code'] ) {
			return $this->get_api_error( $response, $api_key );
		}

		if ( ! isset( $response['body']['data'] ) || ! is_array( $response['body']['data'] ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		$double_optin = $this->fetch_double_optin_status( $api_key );

		return array_map(
			function ( $mailing_list ) use ( $double_optin ) {
				return array(
					'name'         => $mailing_list['name'],
					'id'           => $mailing_list['id'],
					'double_optin' => $double_optin,
				);
			},
			$response['body']['data']
		);
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

		$settings = $this->get_settings();

		$payload = array(
			'email'  => $args['email'],
			'fields' => array(
				'name' => $args['name'],
			),
			'groups' => array(
				$args['group'],
			),
		);

		if ( $args['double_optin'] ) {
			$payload['status'] = 'unconfirmed';
		}

		$response = $this->request(
			'POST',
			'subscribers',
			$settings['api_key'],
			$payload
		);

		if ( $response['error'] || ! in_array( $response['code'], array( 200, 201 ), true ) ) {
			return array(
				'result'  => 'no',
				'message' => isset( $response['body']['message'] ) ? $response['body']['message'] : NewsletterMessages::unable_to_subscribe(),
				'error'   => $response['error'],
			);
		}

		$status = $response['body']['data']['status'] ?? '';

		if ( 'unconfirmed' === $status ) {
			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::confirm_subscription(),
			);
		}

		return array(
			'result'  => 'yes',
			'message' => NewsletterMessages::subscribed_successfully(),
		);
	}
}
