<?php

add_action( 'wp_ajax_blocksy_widget_instagram', 'blocksy_widget_instagram_generate_html' );
add_action( 'wp_ajax_nopriv_blocksy_widget_instagram', 'blocksy_widget_instagram_generate_html' );

function blocksy_widget_instagram_generate_html() {
	$instagram_photos = blocksy_get_instagram_photos(
		sanitize_text_field( $_GET['username'] ),
		intval( sanitize_text_field( $_GET['limit'] ) )
	);

	?>
		<?php foreach ( $instagram_photos as $photo ) { ?>
			<li
			<?php
			if ( $photo['is_video'] ) {
				echo ' class="ct-is-video"';}
			?>
			>
				<?php
				echo blocksy_simple_image(
					$photo['img'],
					[
						'tag_name' => 'a',
						'html_atts' => [
							'target' => '_blank',
							'href' => 'https://instagram.com/p/' . $photo['code'],
						],
						'img_atts' => [
							'alt' => esc_attr( $photo['description'] ),
							'title' => esc_attr( $photo['description'] ),
						],
					]
				)
				?>
			</li>
		<?php } ?>

	<?php

	wp_die();
}

function blocksy_get_instagram_photos( $username, $items = 10 ) {
	$transient_name = 'blocksy_widget_instagram_' . md5(
		json_encode(
			[ $username, $items ]
		)
	);

	if ( $maybe_transient_data = get_transient( $transient_name ) ) {
		return $maybe_transient_data;
	}

	$username = trim( $username );

	if ( ! $username ) {
		return false;
	}
	// Instagram page source.
	$source = wp_remote_get( 'http://instagram.com/' . $username );

	$instagram_photos = [];

	$remote_response = true;

	if ( is_wp_error( $source ) ) {
		$remote_response = null;
	}

	if ( 200 !== wp_remote_retrieve_response_code( $source ) ) {
		$remote_response = null;
	}

	if ( ! $remote_response ) {
		return $instagram_photos;
	}

	// Get json info about the user.
	preg_match( '|window._sharedData = {(.+?)};</script>|', $source['body'], $matches );

	if ( ! isset( $matches[1] ) ) {
		return null;
	}

	$json_data = "{{$matches[1]}}";
	$json_data = json_decode( $json_data, true );

	if ( ! $json_data ) {
		return new WP_Error( 'bad_json', __( 'Instagram has returned invalid data.', 'blocksy' ) );
	}

	// Get instagram images.
	if ( isset( $json_data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
		$images = $json_data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];
	} else {
		return $instagram_photos;
	}

	if ( count( $images ) ) {
		foreach ( $images as $image ) {
			if ( count( $instagram_photos ) >= $items ) {
				break;
			}

			$image = $image['node'];

			if ( ! isset( $image['edge_media_to_caption'] ) ) {
				$image['edge_media_to_caption'] = array(
					'edges' => array(
						array(
							'node' => array(
								'text' => '',
							),
						),
					),
				);
			}

			if ( empty( $iamge['edge_media_to_caption']['edges'] ) ) {
				$image['edge_media_to_caption'] = array(
					'edges' => array(
						array(
							'node' => array(
								'text' => '',
							),
						),
					),
				);
			}

			$instagram_photos[] = array(
				'code'  => $image['shortcode'],
				'img'   => $image['thumbnail_src'],
				'likes' => $image['edge_liked_by']['count'],
				'description' => $image['edge_media_to_caption']['edges'][0]['node']['text'],
				'is_video'    => $image['is_video'],
			);
		}
	}

	set_transient( $transient_name, $instagram_photos, 7 * 24 * HOUR_IN_SECONDS );

	return $instagram_photos;
}


