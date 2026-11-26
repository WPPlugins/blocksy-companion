<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class MailerliteClassicProvider extends Provider {
	private function request( $method, $path, $api_key, $body = null ) {
		$args = array(
			'timeout' => 30,
			'method'  => $method,
			'headers' => array(
				'X-MailerLite-ApiKey' => $api_key,
				'accept'              => 'application/json',
				'content-type'        => 'application/json',
			),
		);

		if ( ! is_null( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request(
			'https://api.mailerlite.com/api/v2/' . ltrim( $path, '/' ),
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

		$cache_key = 'blocksy_ml_classic_doi_' . md5( $api_key );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$response   = $this->request( 'GET', 'settings/double_optin', $api_key );
		$is_enabled = false;

		if ( ! $response['error'] && 200 === $response['code'] ) {
			$body = $response['body'];

			if ( isset( $body['enabled'] ) ) {
				$is_enabled = (bool) $body['enabled'];
			} elseif ( isset( $body['double_optin'] ) ) {
				$is_enabled = (bool) $body['double_optin'];
			} elseif ( isset( $body['data']['enabled'] ) ) {
				$is_enabled = (bool) $body['data']['enabled'];
			} elseif ( isset( $body['data']['double_optin'] ) ) {
				$is_enabled = (bool) $body['data']['double_optin'];
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

		if ( ! is_array( $response['body'] ) ) {
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
			$response['body']
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

		$response = $this->request(
			'POST',
			'groups/' . rawurlencode( $args['group'] ) . '/subscribers',
			$settings['api_key'],
			array(
				'email' => $args['email'],
				'name'  => $args['name'],
			)
		);

		if ( $response['error'] || ! in_array( $response['code'], array( 200, 201 ), true ) ) {
			return array(
				'result'  => 'no',
				'message' => isset( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : NewsletterMessages::unable_to_subscribe(),
				'error'   => $response['error'],
			);
		}

		$status = $response['body']['type'] ?? '';

		if ( 'unconfirmed' === $status || $args['double_optin'] ) {
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
