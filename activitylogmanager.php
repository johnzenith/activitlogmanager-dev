<?php
/**
 * @package   Activity Log Manager
 * @version   1.0
 * @author    ViewPact
 * @copyright 2020 ViewPact Team 
 * @license   GPL-2.0+ [http://www.gnu.org/licenses/gpl-2.0.txt]
 * @link      https://activitylogmanager.com
 *
 * Plugin Name: Activity Log Manager
 * Plugin URI:  https://activitylogmanager.com
 * Description: <strong>Never leave any site activity untraced</strong>. See what your users are doing, control who is logged in, monitor content modifications, watch file integrity status, and <strong>revert the changes you don't like!</strong>. Activity Log Manager enables you to view all recorded activities on your site and get instant notifications as activity unfolds so you can easily troubleshoot your site and prevent malicious attack before they become a security problem. To get started, install the plugin, run the setup wizard and take charge!
 * Version:     1.0
 * Author:      ViewPact Team
 * Author URI:  https://viewpact.com
 * Text Domain: activitylogmanager
 * Domain Path: /languages/
 */


/**
 * Test
 */

// add_action( 'plugin_loaded', 'load_me' );
// add_action( 'plugins_loaded', 'test_me' );

function load_me( $plugin_file )
{
    include_once plugin_dir_path( __FILE__ ) . 'test.php';
}

function alm_get_user_roles($user = 0)
{
    if ( $user instanceof WP_User && isset($user->roles) ) {
        $roles = $user->roles;
    } else {
        $user  = (0 < (int) $user) ? $user : get_current_user_id();
        $roles = get_userdata($user)->roles ?? [];
    }
    return $roles;
}

function test_me()
{
    echo '<pre>';

    // print_r( get_post_types() );
    var_dump( post_type_exists( 'wp_block' ) );
    
    // $_object_type = get_object_subtype( $object_type, $object_id );
    $post_typ_obj = get_post_type_object( 'attachment' );
    $terms = get_taxonomies();

    var_dump( get_post_type( 1 ) );

    global $wp_registered_widgets;
    var_dump( get_taxonomies() );

    var_dump( taxonomy_exists( 'category' ) );

    echo '<hr>';
    
    // var_dump( $terms );

    $post_types = get_post_types();
    print_r( $post_types );

    // wp_die();

    global $wpdb;
    $user = new \WP_User;
    $user_id = get_current_user_id();
    $user_data = wp_get_current_user();
    $user->init( $user_data );
    // var_dump( $user );
    
    $table = _get_meta_table( 'user' );

    $a = update_user_meta(
        $user_id, 'first_name', 'John-Zenith'
    );

    // var_dump( $table, $user_id );
    // $fields = $wpdb->get_results( "SELECT * FROM $table" );

    // var_dump( $fields );
    // var_dump( $a );

    $user_meta = get_user_meta( $user_id );
    print_r( $user_meta );

    echo '<hr>';
    // print_r( $user->__get('caps') );

    echo '<hr>';

    var_dump( get_user_meta( $user_id, $wpdb->prefix . 'capabilities', true ) );

    echo '<hr>';

    print_r( alm_get_user_roles() );
    // var_dump( array_map( 'translate_user_role', alm_get_user_roles() ) );

    echo '<hr>';

    // $new_role = $user->add_cap( 'staff', false );
    // $new_role = $user->add_role( 'worker' );
    // $user->add_cap( 'runner' );
    
    echo '</hr>';

    print_r( $user->get_role_caps() );

    echo '<hr>';

    var_dump( get_user_option( $wpdb->prefix . 'user-settings-time' ) );
    echo '<hr>';
    // print_r( get_all_user_settings(  ) );

    // echo date( 'Y-m-d H:i:s A', get_user_option( $wpdb->prefix . 'user-settings-time') );
    // echo human_time_diff( get_user_option( $wpdb->prefix . 'user-settings-time') );

    // var_dump(get_user_meta($user_id));

    $a = get_user_option('user-settings');
    // print_r($a);

    echo '<hr>';

    // wp_set_current_user( 2 );
    // var_dump( $user_data->first_name );

    add_action( 'init', function()
    {
        global $user_id;

        // $a = update_user_meta(
        //     get_current_user_id(), 'first_name', '@John-Zenith'
        // );
    
        $user_data = get_userdata( get_current_user_id() );
        print_r( $user_data );
        // $user_data = get_user_meta( get_current_user_id(), '', true );

        // var_dump( $user_data->first_name );

        wp_die();
    });
}




// add_action( 'plugins_loaded', '__wait' );
function __wait()
{
    $post_type    = get_object_subtype( 'post', 5 );
    $post_typ_obj = get_post_type_object( 'page');

    $support = get_all_post_type_supports('post');
    echo '<pre>';
    // print_r( $type );
    print_r( $post_typ_obj );
    print_r( $support );

    // print_r( get_the_terms( 1, 'category' ) );

    // print_r( get_term( 1, 'category' ) );
    print_r( get_taxonomies() );
    print_r( get_object_taxonomies('post') );
    
    $terms = wp_get_object_terms( 1, ['category'], []);
    print_r( $terms );
    wp_die();
};
//---------------------------------------------------------------------------------------------




// Make sure this plugin file is not accessed directly
if ( !defined('ABSPATH') || !function_exists('add_action') ) {
    exit( "Hi there, you just called me directly and am not allowed to run in such mode, hanging up!" );
}

define( 'ALM_PLUGIN_FILE', __FILE__ );
require_once plugin_dir_path( __FILE__ ) . 'core/bootstrap.php';