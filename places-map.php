<?php

/*
 * Plugin Name: Places Map for Keyring Social Importers
 * Plugin URI: https://eric.blog
 * Description: Gives you the [places_map] shortcode which allows you to easily embed a map of all of your Instagram pictures!
 * Author: ebinnion
 * Version: 0.2
 * Author URI: https://eric.blog
 * License: GPL2+
 */

define( 'PLACES_MAP_VERSION', '0.2' );

class Places_Map {
	private static $instance = null;

	static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Places_Map;
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ), 11 );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	function register_shortcode() {
		add_shortcode( 'places_map', array( $this, 'render_shortcode' ) );
	}

	function register_rest_route() {
		register_rest_route( 'places-map/v1', '/images/(?P<id>\d+)/', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_images_for_tag' )
		) );
	}

	function get_images_for_tag( $data ) {
		$params = $data->get_params();
		$images = array();
		$posts_array = get_posts(
			array(
				'posts_per_page' => 50,
				'tax_query' => array(
					array(
						'taxonomy' => 'places',
						'field' => 'term_id',
						'terms' => intval( $params['id'] ),
					)
				)
			)
		);

		if ( empty( $posts_array ) ) {
			return array();
		}

		foreach ( (array) $posts_array as $post ) {
			$media = get_attached_media( 'image', $post->ID );
			if ( empty( $media ) ) {
				continue;
			}

			foreach ( (array) $media as $image ) {
				$images[] = $image->ID;
			}
		}

		$gallery = sprintf( '[gallery ids="%s"]', implode( ',', $images ) );
		return do_shortcode( $gallery );
	}

	function render_shortcode( $atts ) {
		if ( empty( $atts['key'] ) ) {
			return '';
		}

		if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'carousel' ) ) {
			/** This filter is already documented in core/wp-includes/media.php */
			do_action( 'post_gallery', '', '' );
		}

		if (  class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'tiled-gallery' ) ) {
			Jetpack_Tiled_Gallery::default_scripts_and_styles();
		}

		$defaults = array(
			'width'            => '100%',
			'height'           => '400px',
			'callback'         => 'initPlacesMap',
			'initialZoom'      => 2,
			'initialCenterLat' => '31.9686',
			'initialCenterLon' => '-99.9018',
			'restUrl'          => rest_url( 'places-map/v1/images/' ),
			'clusterImagePath' => plugins_url(
				'images/m',
				__FILE__
			)
		);

		$args = wp_parse_args( $atts, $defaults );

		if ( false === ( $places = get_transient( 'places_processed' ) ) ) {
			$terms = get_terms( 'places' );
			$places = array();
			foreach ( $terms as $term ) {
				$places[ $term->term_id ] = array(
					'name' => $term->name,
					'lat' => get_term_meta( $term->term_id, 'places-geo_latitude', true ),
					'lon' => get_term_meta( $term->term_id, 'places-geo_longitude', true ),
					'url' => esc_url( get_term_link( $term ) ),
				);
			}

			/**
			 * How long should the transient that holds the processed locations last? Def
			 * @since 0.1
			 *
			 * @param int
			 */
			$transient_duration = apply_filters( 'places_map_transient_duration', 6 * HOUR_IN_SECONDS );
			set_transient( 'places_processed', $places, $transient_duration );
		}

		$dependencies = ( ! empty( $places ) )
			? array( 'places-js' )
			: array();

		// Let's enqueue the js in the footer
		wp_register_script(
			'markerclusterer',
			plugins_url( 'markerclusterer.js', __FILE__ ),
			array(),
			PLACES_MAP_VERSION
		);

		wp_register_script(
			'places-js',
			plugins_url( 'places-map.js', __FILE__ ),
			array( 'markerclusterer' ),
			PLACES_MAP_VERSION
		);

		wp_localize_script(
			'places-js',
			'places',
			$places
		);

		wp_localize_script(
			'places-js',
			'placesMapConfig',
			array_intersect_key(
				$args,
				array_flip( array(
					'initialZoom',
					'initialCenterLat',
					'initialCenterLon',
					'clusterImagePath',
					'restUrl',
				) )
			)
		);

		wp_enqueue_script(
			'google-maps-api',
			esc_url_raw( sprintf(
				'https://maps.googleapis.com/maps/api/js?key=%s&callback=%s',
				$args['key'],
				$args['callback']
			) ),
			$dependencies
		);

		return sprintf(
			'<div id="map" style="margin-bottom: 1em; width: %s; height: %s;"></div><div style="margin-bottom: 1em;" id="places-map-images"></div>',
			esc_attr( $args['width'] ),
			esc_attr( $args['height'] )
		);
	}
}

Places_Map::init();
