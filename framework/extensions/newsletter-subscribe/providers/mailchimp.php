<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class MailchimpProvider extends Provider {
	public function __construct() {
	}

	private function get_subscriber_hash( $email ) {
		return md5( strtolower( trim( $email ) ) );
	}

	private function is_compliance_status( $status ) {
		return in_array( $status, array( 'unsubscribed', 'cleaned' ), true );
	}

	private function is_member_exists_error( $response ) {
		if ( ! is_array( $response ) ) {
			return false;
		}

		if ( isset( $response['title'] ) && 'Member Exists' === $response['title'] ) {
			return true;
		}

		if (
			isset( $response['detail'] )
			&&
			is_string( $response['detail'] )
			&&
			strpos( strtolower( $response['detail'] ), 'already a list member' ) !== false
		) {
			return true;
		}

		return false;
	}

	private function is_compliance_error( $response ) {
		if ( ! is_array( $response ) ) {
			return false;
		}

		$keywords = array( 'compliance', 'forgotten', 'resubscribe' );

		foreach ( array( 'title', 'detail' ) as $key ) {
			if ( ! isset( $response[ $key ] ) || ! is_string( $response[ $key ] ) ) {
				continue;
			}

			$value = strtolower( $response[ $key ] );

			foreach ( $keywords as $keyword ) {
				if ( strpos( $value, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	private function map_error_response_to_message( $response, $email = '' ) {
		if ( $this->is_member_exists_error( $response ) ) {
			return blocksy_companion_safe_sprintf(
				// translators: %s is the email address
				__( '%s is already a list member.', 'blocksy-companion' ),
				$email
			);
		}

		if ( $this->is_compliance_error( $response ) ) {
			if ( isset( $response['detail'] ) && is_string( $response['detail'] ) ) {
				return $response['detail'];
			}

			return NewsletterMessages::confirm_subscription();
		}

		return NewsletterMessages::unable_to_subscribe();
	}

	private function request( $api_key, $region, $method, $path, $body = null ) {
		$maybe_args = array(
			'timeout' => 30,
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);

		if ( ! is_null( $body ) ) {
			$maybe_args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request(
			'https://' . $region . '.api.mailchimp.com/3.0/' . ltrim( $path, '/' ),
			$maybe_args
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

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		if ( strpos( $api_key, '-' ) === false ) {
			return new \WP_Error( 'api_key_invalid', __( 'The Mailchimp API key format is invalid.', 'blocksy-companion' ) );
		}

		$region = explode( '-', $api_key );

		if ( strpos( $region[1], '.' ) !== false ) {
			return new \WP_Error( 'api_key_invalid', __( 'The Mailchimp API key format is invalid.', 'blocksy-companion' ) );
		}

		$response = wp_remote_get(
			'https://' . $region[1] . '.api.mailchimp.com/3.0/lists?count=1000',
			array(
				'timeout' => 2,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode(
						'asd:' . $api_key
					),
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $body ) {
				return $this->get_api_error( $response, $api_key );
			}

			if ( ! isset( $body['lists'] ) || ! is_array( $body['lists'] ) ) {
				return $this->get_api_error( $response, $api_key );
			}

			return array_map(
				function ( $mailing_list ) {
					return array(
						'name'                    => $mailing_list['name'],
						'id'                      => $mailing_list['id'],
						'subscribe_url_long'      => $mailing_list['subscribe_url_long'],

						'subscribe_url_long_json' => str_replace(
							'subscribe',
							'subscribe/post-json',
							$mailing_list['subscribe_url_long'] . '&c=callback'
						),

						'has_gdpr_fields'         => $mailing_list['marketing_permissions'],
						'double_optin'            => $mailing_list['double_optin'],
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
				'email'        => '',
				'name'         => '',
				'group'        => '',
				'double_optin' => false,
			)
		);

		$settings = $this->get_settings();

		$api_key       = $settings['api_key'];
		$target_status = $args['double_optin'] ? 'pending' : 'subscribed';

		if ( strpos( $api_key, '-' ) === false ) {
			return 'api_key_invalid';
		}

		$region      = explode( '-', $api_key );
		$region_name = $region[1];

		if ( strpos( $region_name, '.' ) !== false ) {
			return 'api_key_invalid';
		}

		$name_parts      = $this->maybe_split_name( $args['name'] );
		$fname           = $name_parts['first_name'];
		$lname           = $name_parts['last_name'];
		$subscriber_hash = $this->get_subscriber_hash( $args['email'] );
		$list_path       = 'lists/' . rawurlencode( $args['group'] ) . '/members';

		$member_check = $this->request(
			$api_key,
			$region_name,
			'GET',
			$list_path . '/' . $subscriber_hash
		);

		if ( $member_check['error'] ) {
			return array(
				'result'  => 'no',
				'message' => NewsletterMessages::unable_to_subscribe(),
				'error'   => $member_check['error'],
			);
		}

		if ( ! in_array( $member_check['code'], array( 200, 404 ), true ) ) {
			return array(
				'result'  => 'no',
				'message' => $this->map_error_response_to_message( $member_check['body'], $args['email'] ),
			);
		}

		$has_existing_member = 200 === $member_check['code'];
		$existing_status     = $has_existing_member ? $member_check['body']['status'] ?? '' : '';

		if ( $this->is_compliance_status( $existing_status ) ) {
			$target_status = 'pending';
		}

		if ( 'archived' === $existing_status ) {
			$target_status       = 'subscribed';
			$has_existing_member = false;
		}

		$request_body = array(
			'email_address' => $args['email'],
			'status'        => $target_status,
			'status_if_new' => $target_status,
			'merge_fields'  => array_merge(
				array(
					'FNAME' => $fname,
				),
				( ! empty( $lname ) ? array( 'LNAME' => $lname ) : array() )
			),
		);

		if ( $has_existing_member && ! $this->is_compliance_status( $existing_status ) && 'archived' !== $existing_status ) {
			return array(
				'result'  => 'no',
				'message' => NewsletterMessages::already_subscribed( $args['email'] ),
			);
		}

		$subscribe_response = $this->request(
			$api_key,
			$region_name,
			$has_existing_member ? 'PUT' : 'POST',
			$has_existing_member ? $list_path . '/' . $subscriber_hash : $list_path,
			$request_body
		);

		// Safety fallback for compliance errors that may still come from POST/PUT.
		if (
			400 === $subscribe_response['code']
			&&
			'pending' !== $target_status
			&&
			$this->is_compliance_error( $subscribe_response['body'] )
		) {
			$subscribe_response = $this->request(
				$api_key,
				$region_name,
				'PUT',
				$list_path . '/' . $subscriber_hash,
				array_merge(
					$request_body,
					array(
						'status'        => 'pending',
						'status_if_new' => 'pending',
					)
				)
			);
		}

		if ( in_array( $subscribe_response['code'], array( 200, 201 ), true ) ) {
			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscribed_successfully(),
			);
		} else {
			return array(
				'result'  => 'no',
				'message' => $this->map_error_response_to_message( $subscribe_response['body'], $args['email'] ),
			);
		}
	}
}
