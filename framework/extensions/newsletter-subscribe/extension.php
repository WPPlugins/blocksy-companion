<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/helpers.php';

class BlocksyExtensionNewsletterSubscribe {
	public function __construct() {
		add_action(
			'enqueue_block_editor_assets',
			function () {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data( BLOCKSY__FILE__ );

				wp_enqueue_script(
					'blocksy-ext-newsletter-subscribe-admin-scripts',
					BLOCKSY_URL .
					'framework/extensions/newsletter-subscribe/admin-static/bundle/main.js',
					array( 'ct-options-scripts' ),
					$data['Version'],
					false
				);

				wp_localize_script(
					'blocksy-ext-newsletter-subscribe-admin-scripts',
					'blocksy_ext_newsletter_subscribe_localization',
					array(
						'public_url' =>
							BLOCKSY_URL .
							'framework/extensions/newsletter-subscribe/admin-static/bundle/',
					)
				);
			}
		);

		add_action(
			'customize_controls_enqueue_scripts',
			function () {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data( BLOCKSY__FILE__ );

				wp_register_script(
					'blocksy-ext-newsletter-subscribe-admin-scripts',
					BLOCKSY_URL . 'framework/extensions/newsletter-subscribe/admin-static/bundle/main.js',
					array(),
					$data['Version'],
					true
				);

				wp_localize_script(
					'blocksy-ext-newsletter-subscribe-admin-scripts',
					'blocksy_ext_newsletter_subscribe_localization',
					array(
						'public_url' => BLOCKSY_URL . 'framework/extensions/newsletter-subscribe/admin-static/bundle/',
					)
				);
			}
		);

		add_filter(
			'do_shortcode_tag',
			function ( $output, $tag ) {
				if ( 'blocksy_newsletter_subscribe' === $tag ) {
					wp_enqueue_style( 'blocksy-block-newsletter-styles' );
				}

				return $output;
			},
			10,
			2
		);

		add_filter(
			'render_block',
			function ( $block_content, $block ) {
				if ( 'blocksy/newsletter' === $block['blockName'] ) {
					wp_enqueue_style( 'blocksy-block-newsletter-styles' );
				}

				return $block_content;
			},
			10,
			2
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data( BLOCKSY__FILE__ );

				if ( is_admin() ) {
					return;
				}

				wp_register_style(
					'blocksy-block-newsletter-styles',
					BLOCKSY_URL . 'framework/extensions/newsletter-subscribe/static/bundle/main.min.css',
					array( 'ct-main-styles' ),
					$data['Version']
				);

				if (
					blocksy_companion_theme_functions()->blocksy_get_theme_mod(
						'newsletter_subscribe_single_post_enabled',
						'yes'
					) === 'yes'
					&&
					get_post_type() === 'post'
				) {
					wp_enqueue_style( 'blocksy-block-newsletter-styles' );
				}
			},
			45
		);

		add_filter(
			'blocksy:frontend:dynamic-js-chunks',
			function ( $chunks ) {
				$chunks[] = array(
					'id'          => 'blocksy_ext_newsletter_subscribe',
					'selector'    => '.ct-newsletter-subscribe-form',
					'url'         => blocksy_companion_theme_functions()->blocksy_cdn_url(
						BLOCKSY_URL .
							'framework/extensions/newsletter-subscribe/static/bundle/main.js'
					),
					'trigger'     => 'submit',
					'global_data' => array(
						array(
							'var'  => 'blocksy_newsletter_subscribe',
							'data' => array(
								'errorMessage' => \Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::unable_to_subscribe(),
							),
						),
					),
					'version'     => blocksy_companion_get_version(),
				);

				return $chunks;
			}
		);

		add_filter(
			'blocksy_single_posts_end_customizer_options',
			function ( $opts, $prefix ) {
				if ( 'single_blog_post' !== $prefix ) {
					return $opts;
				}

				$opts['newsletter_subscribe_single_post_enabled'] = blocksy_companion_get_options(
					__DIR__ . '/customizer.php',
					array(),
					false
				);

				return $opts;
			},
			10,
			2
		);

		add_filter(
			'blocksy_extensions_metabox_post:elements:before',
			function ( $opts ) {
				$opts['disable_subscribe_form'] = array(
					'label' => __(
						'Disable Subscribe Form',
						'blocksy-companion'
					),
					'type'  => 'ct-switch',
					'value' => 'no',
				);

				return $opts;
			},
			5
		);

		add_action(
			'customize_preview_init',
			function () {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data( BLOCKSY__FILE__ );

				wp_enqueue_script(
					'blocksy-newsletter-subscribe-customizer-sync',
					BLOCKSY_URL .
					'framework/extensions/newsletter-subscribe/admin-static/bundle/sync.js',
					array( 'customize-preview', 'ct-customizer' ),
					$data['Version'],
					true
				);
			}
		);

		add_action(
			'wp_ajax_blc_newsletter_subscribe_process_ajax_subscribe',
			array(
				$this,
				'newsletter_subscribe_process_ajax_subscribe',
			)
		);

		add_action(
			'wp_ajax_nopriv_blc_newsletter_subscribe_process_ajax_subscribe',
			array( $this, 'newsletter_subscribe_process_ajax_subscribe' )
		);

		add_shortcode(
			'blocksy_newsletter_subscribe',
			function ( $args ) {
				$args = wp_parse_args(
					$args,
					array(
						'has_title'       => false,
						'has_description' => false,

						'button_text'     => __( 'Subscribe', 'blocksy-companion' ),

						// no | yes
						'has_name'        => 'no',

						'name_label'      => __( 'Your name', 'blocksy-companion' ),
						'email_label'     => __( 'Your email', 'blocksy-companion' ),
						'list_id'         => '',
						'class'           => '',

						'container_style' => 'default',
						'form_style'      => 'inline',
					)
				);

				$args['class'] = implode(
					' ',
					array(
						'ct-newsletter-subscribe-shortcode',
						$args['class'],
					)
				);

				return blocksy_companion_ext_newsletter_subscribe_output_form( $args );
			}
		);

		add_action(
			'blocksy:global-dynamic-css:enqueue',
			'BlocksyExtensionNewsletterSubscribe::add_global_styles',
			10
		);

		add_action( 'init', array( $this, 'blocksy_newsletter_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_admin' ) );

		add_filter(
			'blocksy:block-editor:localized_data',
			function ( $data ) {
				$options_file =
					BLOCKSY_PATH .
					'framework/extensions/newsletter-subscribe/ct-newsletter-subscribe/options.php';

				$options = blocksy_companion_akg(
					'options',
					blocksy_companion_get_variables_from_file(
						$options_file,
						array( 'options' => array() )
					)
				);

				$data['newsletter'] = $options;

				return $data;
			}
		);

		add_action(
			'blocksy:single:page-elements:contained:before',
			function () {
				if ( get_post_type() === 'post' ) {
					/**
					 * Note to code reviewers: This line doesn't need to be escaped.
					 * Function blocksy_companion_ext_newsletter_subscribe_form() used here escapes the value properly.
					 */
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo blocksy_companion_ext_newsletter_subscribe_form();
				}
			}
		);
	}

	public function render_block( $attributes ) {
		$file_path = BLOCKSY_PATH . 'framework/extensions/newsletter-subscribe/ct-newsletter-subscribe/view.php';

		if ( ! file_exists( $file_path ) ) {
			return '<p>Default widget view. Please create a <i>view.php</i> file.</p>';
		}

		return blocksy_companion_render_view(
			$file_path,
			array(
				'atts' => $attributes,
			)
		);
	}

	public function blocksy_newsletter_block() {
		register_block_type(
			BLOCKSY_PATH . '/framework/extensions/newsletter-subscribe/admin-static/js/newsletter-block/block.json',
			array(
				'render_callback'      => array( $this, 'render_block' ),
				'editor_style_handles' => array(
					'blocksy/newsletter',
				),
			)
		);
	}

	public function enqueue_admin() {
		$deps = array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
		);

		global $wp_customize;

		if ( $wp_customize ) {
			$deps[] = 'ct-customizer-controls';
		} else {
			$deps[] = 'ct-options-scripts';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( BLOCKSY__FILE__ );

		wp_enqueue_script(
			'blocksy/newsletter',
			BLOCKSY_URL .
				'framework/extensions/newsletter-subscribe/admin-static/bundle/newsletter-block.js',
			$deps,
			$plugin_data['Version'],
			false
		);

		$data = array(
			'has_cookies_checkbox' => function_exists( 'blocksy_companion_ext_cookies_checkbox' ),
		);

		wp_localize_script(
			'blocksy/newsletter',
			'blc_newsletter_data',
			$data
		);

		wp_register_style(
			'blocksy/newsletter',
			BLOCKSY_URL .
				'framework/extensions/newsletter-subscribe/admin-static/bundle/admin.min.css',
			array(),
			$plugin_data['Version']
		);
	}

	public static function add_global_styles( $args ) {
		blocksy_companion_theme_functions()->blocksy_theme_get_dynamic_styles(
			array_merge(
				array(
					'path'  => __DIR__ . '/global.php',
					'chunk' => 'global',
				),
				$args
			)
		);
	}

	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	public static function onDeactivation() {
		remove_action(
			'blocksy:global-dynamic-css:enqueue',
			'BlocksyExtensionNewsletterSubscribe::add_global_styles',
			10
		);
	}

	public function newsletter_subscribe_process_ajax_subscribe() {
		$error_response = array(
			'result'  => 'no',
			'message' => \Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::unable_to_subscribe(),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['GROUP'] ) ) {
			wp_send_json_error( $error_response );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$group = sanitize_text_field( wp_unslash( $_POST['GROUP'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['EMAIL'] ) ) {
			wp_send_json_error( $error_response );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = sanitize_email( wp_unslash( $_POST['EMAIL'] ) );
		$name  = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['FNAME'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$name = sanitize_text_field( wp_unslash( $_POST['FNAME'] ) );
		}

		$manager = \Blocksy\Extensions\NewsletterSubscribe\Provider::get_for_settings();
		$list    = $manager->get_list( $group );

		if ( ! $list ) {
			wp_send_json_error( $error_response );
		}

		if ( ! empty( $list['has_gdpr_fields'] ) ) {
			$form_url = isset( $list['subscribe_url_long'] ) ? esc_url_raw( $list['subscribe_url_long'] ) : '';

			if ( ! $form_url ) {
				wp_send_json_error( $error_response );
			}

			wp_send_json_success(
				array(
					'form_url' => $form_url,
				)
			);
		}

		$result = $manager->subscribe_form(
			array(
				'email'        => $email,
				'name'         => $name,
				'group'        => $group,
				'double_optin' => ! empty( $list['double_optin'] ),
			)
		);

		$messages = array(
			\Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::unable_to_subscribe(),
			\Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::confirm_subscription(),
			\Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::subscribed_successfully(),
			\Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::subscription_received(),
			\Blocksy\Extensions\NewsletterSubscribe\NewsletterMessages::already_subscribed( $email ),
		);

		$message = $result['message'] ?? $messages[0];

		if ( ! in_array( $message, $messages, true ) ) {
			$message = $messages[0];
		}

		wp_send_json_success(
			array(
				'result'  => $result['result'] ?? 'no',
				'message' => $message,
			)
		);
	}
}
