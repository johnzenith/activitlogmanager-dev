<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package User Event Handlers (for multisite installation)
 * @since   1.0.0
 */

trait MS_UserEvents
{
    /**
     *Fires after the user is marked as a SPAM user.
     * 
     * @since 1.0.0
     */
    public function make_spam_user_event( $object_id )
    {
        $this->setupUserEventArgs( compact( 'object_id' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     *Fires after the user is marked as a SPAM user.
     * 
     * @since 1.0.0
     */
    public function make_ham_user_event( $object_id )
    {
        $this->setupUserEventArgs( compact( 'object_id' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Filters whether a user should be added to a site.
     * 
     * @since 1.0.0
     */
    public function can_add_user_to_blog_event( $retval, $object_id, $role, $blog_id )
    {
        // Ignore if the user can be added to the site
        if ( true === $retval )
        {
            /**
             * We have to aggregate the user meta primary blog and source domain 
             * fields if they are updated
             * 
             * This constant flag specifies whether aggregation is active or not
             */
            $this->setupUserLogAggregationFlag( 'add_user_to_blog' );

            return;
        }

        $blog_url        = $this->sanitizeOption( get_blog_option( $blog_id, 'url', '' ), 'url' );
        $blog_name       = $this->sanitizeOption( get_blog_option( $blog_id, 'name', '' ) );
        $role_given      = $role;
        $failed_attempts = 1;

        $this->setupUserEventArgs( compact(
            'object_id', 'blog_id', 'role_given', 'blog_name', 'blog_url', 'failed_attempts'
        ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires immediately after a user is added to a site.
     * 
     * @since 1.0.0
     */
    public function add_user_to_blog_event( $object_id, $role, $blog_id )
    {
        $blog_url      = $this->sanitizeOption( get_blog_option( $blog_id, 'url', '' ), 'url' );
        $blog_name     = $this->sanitizeOption( get_blog_option( $blog_id, 'name', '' ) );
        $role_given    = $role;
        $primary_blog  = $this->sanitizeOption( get_user_meta( $object_id, 'primary_blog', true ) );
        $source_domain = $this->sanitizeOption( get_user_meta( $object_id, 'source_domain', true ) );

        $primary_blog_name = $this->sanitizeOption( get_blog_option( $primary_blog, 'name', '' ) );

        $this->setupUserEventArgs( compact(
            'blog_id',
            'blog_url',
            'object_id',
            'blog_name',
            'role_given',
            'primary_blog',
            'source_domain',
            'primary_blog_name'
        ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires before a user is removed from a site.
     * 
     * @since 1.0.0
     */
    public function remove_user_from_blog_event( $object_id, $blog_id, $reassign )
    {
        // Check if the user to be removed does exists
        $object_user = get_userdata( $object_id );
        if ( ! $object_user ) return;

        /**
         * Setup the user aggregation flag
         */
        $this->setupUserLogAggregationFlag();

        $blogs     = get_blogs_of_user( $object_id );
        $blog_url  = $this->sanitizeOption( get_blog_option( $blog_id, 'url', '' ), 'url' );
        $blog_name = $this->sanitizeOption( get_blog_option( $blog_id, 'name', '' ) );

        $primary_blog = $this->sanitizeOption( get_user_meta( $object_id, 'primary_blog', true ) );
        /**
         * If the user is being removed from the primary blog, 
         * WordPress will set a new primary if the user is assigned to multiple blogs
         */
        if ( $primary_blog == $blog_id )
        {
            foreach ( (array) $blogs as $blog )
            {
                if ( $blog->userblog_id == $blog_id ) {
                    continue;
                }
                $primary_blog  = (int) $blog->userblog_id;
                $source_domain = $this->sanitizeOption( $blog->domain );
                break;
            }
        }
        else {
            $source_domain = $this->sanitizeOption( get_user_meta( $object_id, 'source_domain', true ) );
        }

        // Make sure the user primary blog and source domain is not set to 
        // the blog they were removed from
        if ( count( $blogs ) == 0 ) {
            $primary_blog  = '';
            $source_domain = '';
        }

        $primary_blog_name = $this->sanitizeOption( get_blog_option( $primary_blog, 'name', '' ) );

        // Check whether the user post is reassigned to another user
        if ( $reassign )
        {
            $reassign = (int) $reassign;
            $post_ids = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(ID) FROM $this->wpdb->posts WHERE post_author = %d LIMIT 1", $object_id ) );

            if ( $post_ids > 0 ) {
                $reassign_user       = get_userdata( $reassign );
                $reassign_user_login = $this->sanitizeOption( $reassign_user->user_login );
                $reassign_post       = "{$reassign}_{$reassign_user_login}";
            } else {
                $reassign_post = 'No post found';
            }
        }
        else {
            $reassign_post = 'Posts was not reassigned';
        }

        $this->setupUserEventArgs( compact(
            'blog_id',
            'blog_url',
            'object_id',
            'blog_name',
            'primary_blog',
            'source_domain',
            'reassign_post',
            'primary_blog_name'
        ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
}