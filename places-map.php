<?php

/*
 * Plugin Name: Places Map for Keyring Social Importers
 * Plugin URI: https://eric.blog
 * Description: Gives you the [places_map] shortcode which allows you to easily embed a map of all of your Instagram pictures!
 * Author: ebinnion
 * Version: 0.1
 * Author URI: https://eric.blog
 * License: GPL2+
 */

define( 'PLACES_MAP_VERSION', '0.1' );

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
	}

	function register_shortcode() {
		add_shortcode( 'places_map', array( $this, 'render_shortcode' ) );
	}

	function render_shortcode( $atts ) {
		if ( empty( $atts['key'] ) ) {
			return '';
		}

		$defaults = array(
			'width'            => '100%',
			'height'           => '400px',
			'callback'         => 'initPlacesMap',
			'initialZoom'      => 2,
			'initialCenterLat' => '31.9686',
			'initialCenterLon' => '-99.9018',
		);

		$args = wp_parse_args( $atts, $defaults );

		if ( false === ( $places = get_transient( 'places_processed' ) ) ) {
			$terms = get_terms( 'places' );
			$places = array();
			foreach ( $terms as $term ) {
				$places[ $term->slug ] = array(
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
			'<div id="map" style="width: %s; height: %s;"></div>',
			esc_attr( $args['width'] ),
			esc_attr( $args['height'] )
		);
	}
}

Places_Map::init();
