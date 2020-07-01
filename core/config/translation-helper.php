<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Wrapper for translations
 * @package Translation Helper
 * @see /wp-includes/I10n.php
 * @since 1.0.0
 */
function alm__( $text ) {
	return __( $text, 'activitylogmanager' );
}

function alm_esc_attr__( $text ) {
	return esc_attr__( $text, 'activitylogmanager' );
}

function alm_esc_html__( $text ) {
	return esc_html__( $text, 'activitylogmanager' );
}

function alm_e( $text ) {
	_e( $text, 'activitylogmanager' );
}

function alm_esc_attr_e( $text ) {
	esc_attr_e( $text, 'activitylogmanager' );
}

function alm_esc_html_e( $text ) {
	esc_html_e( $text, 'activitylogmanager' );
}

function alm_x( $text, $context ) {
	return _x( $text, $context, 'activitylogmanager' );
}

function alm_ex( $text, $context ) {
	return _ex( $text, $context, 'activitylogmanager' );
}

function alm_esc_attr_x( $text, $context ) {
	return esc_attr_x( $text, $context, 'activitylogmanager' );
}

function alm_esc_html_x( $text, $context ) {
	return esc_html_x( $text, $context, 'activitylogmanager' );
}

function alm_n( $single, $plural, $number ) {
	return _n( $single, $plural, $number, 'activitylogmanager' );
}

function alm_nx( $single, $plural, $number, $context ) {
	return _n( $single, $plural, $number, $context, 'activitylogmanager' );
}

function alm_n_noop( $singular, $plural ) {
	return _n_noop( $singular, $plural, 'activitylogmanager' );
}

function alm_nx_noop_nx_noop( $singular, $plural, $context ) {
	return _nx_noop( $singular, $plural, $context, 'activitylogmanager' );
}

function alm_translate_user_role( $name ) {
	return translate_user_role( $name, 'activitylogmanager' );
}

function alm_translate_nooped_plural( $nooped_plural, $count ) {
	return translate_nooped_plural( $nooped_plural, $count, 'activitylogmanager' );
}

function alm_load_plugin_textdomain()
{
	$domain_dir = defined( 'ALM_DOMAIN_DIR' ) ? 
		ALM_DOMAIN_DIR : plugin_dir_path( wp_normalize_path( ALM_PLUGIN_FILE ) ) . '/languages/';

	load_plugin_textdomain( 'activitylogmanager', false, $domain_dir );
}