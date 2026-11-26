<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class MoosendProvider extends Provider {
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {
		if ( ! $api_key ) {
			return new \WP_Error( 'api_key_missing', __( 'Please enter an API key.', 'blocksy-companion' ) );
		}

		$lists = array();
		$page  = 1;

		do {
			$response = $this->request( 'lists/' . $page . '/100.json?WithStatistics=false', $api_key );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! isset( $response['MailingLists'] ) || ! is_array( $response['MailingLists'] ) ) {
				return new \WP_Error( 'invalid_response', __( 'The newsletter provider returned an unexpected response. Please try again.', 'blocksy-companion' ) );
			}

			foreach ( $response['MailingLists'] as $list ) {
				if ( ! isset( $list['ID'], $list['Name'] ) ) {
					return new \WP_Error( 'invalid_response', __( 'The newsletter provider returned an unexpected response. Please try again.', 'blocksy-companion' ) );
				}

				$lists[] = array(
					'id'   => $list['ID'],
					'name' => $list['Name'],
				);
			}

			++$page;
		} while ( $page <= (int) ( $response['Paging']['TotalPageCount'] ?? 1 ) );

		return $lists;
	}

	public function get_list( $list_id ) {
		$settings = $this->get_settings();

		if ( ! $list_id || ! $settings['api_key'] ) {
			return null;
		}

		$list = $this->request( 'lists/' . rawurlencode( $list_id ) . '/details.json?WithStatistics=false', $settings['api_key'] );

		if ( is_wp_error( $list ) || ! isset( $list['ID'], $list['Name'], $list['Status'] ) ) {
			return null;
		}

		if ( $list['ID'] !== $list_id || ! in_array( $list['Status'], array( 0, 1, 2 ), true ) ) {
			return null;
		}

		return array(
			'id'   => $list['ID'],
			'name' => $list['Name'],
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

		$error = array(
			'result'  => 'no',
			'message' => NewsletterMessages::unable_to_subscribe(),
		);

		$settings = $this->get_settings();
		$path     = 'subscribers/' . rawurlencode( $args['group'] );
		$member   = $this->request( $path . '/view.json?Email=' . rawurlencode( $args['email'] ), $settings['api_key'] );

		if ( ! is_wp_error( $member ) ) {
			if ( 1 !== ( $member['SubscribeType'] ?? null ) ) {
				return $error;
			}

			return array(
				'result'  => 'yes',
				'message' => NewsletterMessages::subscription_received(),
			);
		}

		if ( '300' !== ( $member->get_error_data()['provider_code'] ?? '' ) ) {
			return $error;
		}

		$body = array(
			'Email'                  => $args['email'],
			'HasExternalDoubleOptIn' => false,
		);

		if ( $args['name'] ) {
			$body['Name'] = $args['name'];
		}

		$subscriber = $this->request(
			$path . '/subscribe.json',
			$settings['api_key'],
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $subscriber ) || empty( $subscriber['ID'] ) || 1 !== ( $subscriber['SubscribeType'] ?? null ) ) {
			return $error;
		}

		return array(
			'result'  => 'yes',
			'message' => NewsletterMessages::subscription_received(),
		);
	}

	private function request( $path, $api_key, $args = array() ) {
		$response = wp_remote_request(
			add_query_arg( 'apikey', rawurlencode( $api_key ), 'https://api.moosend.com/v3/' . $path ),
			wp_parse_args(
				$args,
				array(
					'timeout' => 30,
					'headers' => array(
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					),
				)
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['Code'] ) || 0 !== $body['Code'] || ! empty( $body['Error'] ) || ! isset( $body['Context'] ) || ! is_array( $body['Context'] ) ) {
			return $this->get_api_error( $response, $api_key );
		}

		return $body['Context'];
	}
}
