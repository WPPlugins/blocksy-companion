<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class MailPoetProvider extends Provider {
	public function __construct() {
	}

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {

		if ( ! class_exists( \MailPoet\API\API::class ) ) {
			return new \WP_Error( 'provider_unavailable', __( 'Activate MailPoet to fetch your lists.', 'blocksy-companion' ) );
		}

		$mailpoet_api = \MailPoet\API\API::MP( 'v1' );

		return array_map(
			function ( $mailing_list ) {
				return array(
					'name' => $mailing_list['name'],
					'id'   => $mailing_list['id'],
				);
			},
			$mailpoet_api->getLists()
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

		if ( ! class_exists( \MailPoet\API\API::class ) ) {
			return array(
				'result' => 'no',
				'error'  => 'MailPoet API not found',
			);
		}

		$mailpoet_api = \MailPoet\API\API::MP( 'v1' );

		$name_parts = $this->maybe_split_name( $args['name'] );
		$fname      = $name_parts['first_name'];
		$lname      = $name_parts['last_name'];

		$list_ids = array( $args['group'] );

		$subscriber = array(
			'email'      => $args['email'],
			'first_name' => $fname,
		);

		if ( ! empty( $lname ) ) {
			$subscriber['last_name'] = $lname;
		}

		try {
			$get_subscriber = $mailpoet_api->getSubscriber( $subscriber['email'] );
		} catch ( \Exception $e ) {
			$get_subscriber = false;
		}

		try {
			if ( ! $get_subscriber ) {
				$mailpoet_api->addSubscriber( $subscriber, $list_ids );
			} else {
				$mailpoet_api->subscribeToLists( $subscriber['email'], $list_ids );
			}
		} catch ( \Exception $e ) {
			return array(
				'result'  => 'no',
				'message' => $e->getMessage(),
			);
		}

		return array(
			'result'  => 'yes',
			'message' => NewsletterMessages::subscribed_successfully(),
		);
	}
}
