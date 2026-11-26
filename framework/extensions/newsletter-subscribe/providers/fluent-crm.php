<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class FluentCRMProvider extends Provider {
	public function __construct() {
	}

	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function fetch_lists( $api_key, $api_url = '' ) {

		if ( ! class_exists( \FluentCrm\App\Models\Lists::class ) ) {
			return new \WP_Error( 'provider_unavailable', __( 'Activate FluentCRM to fetch your lists.', 'blocksy-companion' ) );
		}

		$lists = \FluentCrm\App\Models\Lists::orderBy( 'title', 'ASC' )->get();

		$formatted_lists = array();
		foreach ( $lists as $mailing_list ) {
			$formatted_lists[] = array(
				'name' => $mailing_list->title,
				'id'   => strval( $mailing_list->id ),
			);
		}

		return $formatted_lists;
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

		if ( ! class_exists( \FluentCrm\App\Services\Funnel\FunnelHelper::class ) ) {
			return array(
				'result' => 'no',
				'error'  => 'FluentCrm API not found',
			);
		}

		$subscriber = array(
			'email'     => $args['email'],
			'full_name' => $args['name'],
		);

		try {
			$get_subscriber = \FluentCrm\App\Services\Funnel\FunnelHelper::getSubscriber( $args['email'] );
		} catch ( \Exception $e ) {
			return array(
				'result'  => 'no',
				'message' => $e->getMessage(),
			);
		}

		if ( $get_subscriber ) {
			return array(
				'result'  => 'no',
				'message' => 'Subscriber already exists',
			);
		}

		$subscriber['lists'] = array( intval( $args['group'] ) );

		try {
			\FluentCrm\App\Services\Funnel\FunnelHelper::createOrUpdateContact( $subscriber );
		} catch ( \Exception $e ) {
			return array(
				'result'  => 'no',
				'message' => $e->getMessage(),
			);
		}

		return array(
			'list'    => $args,
			'result'  => 'yes',
			'message' => NewsletterMessages::subscribed_successfully(),
		);
	}
}
