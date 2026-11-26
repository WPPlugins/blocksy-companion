<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class Provider {
	public static function get_for_settings() {
		$m        = new Provider();
		$settings = $m->get_settings();

		return Provider::get_for_provider(
			$settings['provider']
		);
	}

	public static function get_for_provider( $provider ) {
		if ( 'moosend' === $provider ) {
			return new MoosendProvider();
		}

		if ( 'mailchimp' === $provider ) {
			return new MailchimpProvider();
		}

		if ( 'brevo' === $provider ) {
			return new BrevoProvider();
		}

		if ( 'campaignmonitor' === $provider ) {
			return new CampaignMonitorProvider();
		}

		if ( 'mailerlite-new' === $provider ) {
			return new MailerliteNewProvider();
		}

		if ( 'convertkit' === $provider ) {
			return new ConvertKitProvider();
		}

		if ( 'convertkit-new' === $provider ) {
			return new ConvertKitNewProvider();
		}

		if ( 'demo' === $provider ) {
			return new DemoProvider();
		}

		if ( 'mailpoet' === $provider ) {
			return new MailPoetProvider();
		}

		if ( 'activecampaign' === $provider ) {
			return new ActiveCampaignProvider();
		}

		if ( 'fluentcrm' === $provider ) {
			return new FluentCRMProvider();
		}

		if ( 'emailoctopus' === $provider ) {
			return new EmailOctopusProvider();
		}

		if ( 'klaviyo' === $provider ) {
			return new KlaviyoProvider();
		}

		if ( 'sendy' === $provider ) {
			return new SendyProvider();
		}

		return new MailerliteClassicProvider();
	}

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url ) {
		return array();
	}

	public function get_list( $list_id ) {
		$settings = $this->get_settings();
		$lists    = $this->fetch_lists(
			$settings['api_key'],
			$settings['api_url'] ?? ''
		);

		if ( ! is_array( $lists ) ) {
			return null;
		}

		foreach ( $lists as $list ) {
			if (
				isset( $list['id'] )
				&&
				(string) $list['id'] === (string) $list_id
			) {
				return $list;
			}
		}

		return null;
	}

	public function get_settings() {
		$option = get_option( 'blocksy_ext_mailchimp_credentials', array() );

		if ( empty( $option ) ) {
			$option = array();
		}

		$free_providers = array( 'mailchimp', 'demo' );

		if (
			isset( $option['provider'] )
			&&
			! in_array( $option['provider'], $free_providers, true )
			&&
			blocksy_companion_get_capabilities()->get_plan() === 'free'
		) {
			$option['provider'] = $free_providers[0];
		}

		return array_merge(
			array(
				'provider' => 'mailchimp',
				'api_key'  => null,
				'list_id'  => null,
			),
			$option
		);
	}

	public function set_settings( $vals ) {
		update_option(
			'blocksy_ext_mailchimp_credentials',
			array_merge(
				array(
					'provider' => 'mailchimp',
					'api_key'  => null,
					'list_id'  => null,
				),
				$vals
			)
		);
	}

	public function can( $capability = 'manage_options' ) {
		if ( is_multisite() ) {
			// Only network admin can change files that affects the entire network.
			$can = current_user_can_for_site( get_current_blog_id(), $capability );
		} else {
			$can = current_user_can( $capability );
		}

		if ( $can ) {
			// Also you can use this method to get the capability.
			$can = $capability;
		}

		return $can;
	}

	public function maybe_split_name( $full_name ) {
		$lname = '';
		$fname = '';

		if ( ! empty( $full_name ) ) {
			$parts = explode( ' ', $full_name );

			if ( count( $parts ) > 1 ) {
				$lname = array_pop( $parts );
				$fname = implode( ' ', $parts );
			} else {
				$fname = $full_name;
			}
		}

		return array(
			'first_name' => $fname,
			'last_name'  => $lname,
		);
	}

	protected function get_api_error( $response, $api_key ) {
		if ( is_wp_error( $response ) ) {
			$status        = 0;
			$body          = array( 'message' => $response->get_error_message() );
			$provider_code = $response->get_error_code();
		} elseif ( isset( $response['code'] ) ) {
			$status        = (int) $response['code'];
			$body          = $response['error'] ? array( 'message' => $response['error'] ) : $response['body'];
			$provider_code = '';
		} else {
			$status        = (int) wp_remote_retrieve_response_code( $response );
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$provider_code = '';
		}

		$code     = 'provider_error';
		$fallback = __( 'Unable to fetch lists from the newsletter provider. Please try again.', 'blocksy-companion' );

		if ( ! $status ) {
			$code     = 'connection_failed';
			$fallback = __( 'Unable to connect to the newsletter provider. Please try again.', 'blocksy-companion' );
		} elseif ( 200 === $status ) {
			$code     = 'invalid_response';
			$fallback = __( 'The newsletter provider returned an unexpected response. Please try again.', 'blocksy-companion' );
		} elseif ( 429 === $status ) {
			$code     = 'rate_limited';
			$fallback = __( 'The newsletter provider is receiving too many requests. Please try again later.', 'blocksy-companion' );
		}

		$message = '';
		$errors  = is_array( $body ) ? array( $body ) : array();

		if ( isset( $body['error'] ) && is_array( $body['error'] ) ) {
			array_unshift( $errors, $body['error'] );
		}

		if ( isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
			$errors = array_merge( $body['errors'], $errors );
		}

		foreach ( $errors as $error ) {
			if ( is_string( $error ) ) {
				$message = $error;
			} elseif ( is_array( $error ) ) {
				foreach ( array( 'detail', 'message', 'Message', 'error', 'Error', 'title' ) as $key ) {
					if ( ! empty( $error[ $key ] ) && is_string( $error[ $key ] ) ) {
						$message = $error[ $key ];
						break;
					}
				}

				$provider_code = $error['code'] ?? $error['Code'] ?? $provider_code;
			}

			if ( $message ) {
				break;
			}
		}

		if ( is_string( $body ) && false === strpos( $body, '<' ) ) {
			$message = $body;
		}

		$message       = sanitize_text_field( $message );
		$provider_code = is_scalar( $provider_code ) ? sanitize_text_field( (string) $provider_code ) : '';

		if ( 200 === $status && ! empty( $provider_code ) ) {
			$code = 'provider_error';
		}

		if ( $api_key ) {
			$secrets       = array( $api_key, rawurlencode( $api_key ) );
			$message       = str_replace( $secrets, '[redacted]', $message );
			$provider_code = str_replace( $secrets, '[redacted]', $provider_code );
		}

		return new \WP_Error(
			$code,
			$message ? $message : $fallback,
			array(
				'status'        => $status,
				'provider_code' => $provider_code,
			)
		);
	}
}
