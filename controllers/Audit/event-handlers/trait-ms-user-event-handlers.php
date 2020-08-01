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

            return $retval;
        }

        $blog_url        = $this->sanitizeOption( get_blog_option( $blog_id, 'url', '' ), 'url' );
        $blog_name       = $this->sanitizeOption( get_blog_option( $blog_id, 'name', '' ) );
        $role_given      = $role;
        $failed_attempts = 1;

        $this->setupUserEventArgs( compact(
            'object_id', 'blog_id', 'role_given', 'blog_name', 'blog_url', 'failed_attempts'
        ) );
        $this->LogActiveEvent( 'user', __METHOD__ );

        return $retval;
    }

    /**
     * Fires immediately after a user is added to a site.
     * 
     * This event method handler is also responsible for despatching the 
     * 'add_new_user_to_blog_*' event alias
     * 
     * @since 1.0.0
     */
    public function add_user_to_blog_event( $object_id, $role, $blog_id )
    {
        if ($this->current_blog_ID == $blog_id)
        {
            $blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $blog_url  = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
            $blog_name = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
        }

        $role_given    = $this->sanitizeOption($role);

        $primary_blog  = $this->sanitizeOption( get_user_meta( $object_id, 'primary_blog', true ) );
        $source_domain = $this->sanitizeOption( get_user_meta( $object_id, 'source_domain', true ) );

        if ($this->current_blog_ID == $primary_blog) {
            $primary_blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $primary_blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $primary_blog_url  = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
            $primary_blog_name = $this->sanitizeOption(get_blog_option($primary_blog, 'blogname', ''));
        }

        $this->setupUserEventArgs( compact(
            'blog_id',
            'blog_url',
            'object_id',
            'blog_name',
            'role_given',
            'primary_blog',
            'source_domain',
            'primary_blog_url',
            'primary_blog_name'
        ) );

        /**
         * Add existing user to blog by super admins
         *
         * @see /wp-admin/user-new.php#L31
         */
        if (isset($_REQUEST['action']) && 'adduser' == $_REQUEST['action'])
        {
            // Add existing user to blog without email confirmation
            if (isset($_POST['noconfirmation']) && current_user_can('manage_network_users')) {
                $this->LogActiveEvent( 'user', __METHOD__ );
            }
        }
        elseif (isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action']) {
            /**
             * If the user is created by a super admin and does not requires confirmation,
             * let's call the hook alias
             */
            if (isset($_POST['noconfirmation']) && current_user_can('manage_network_users')) {
                $this->alm_add_new_user_to_blog_by_admin_event();
            }
        }
        else {
            if( $this->isAddNewUserAdminScreen() ) return;
                
            $this->alm_add_new_user_to_blog_by_self_event();
        }
    }

    /**
     * Fires when a new user is created by super admin without email confirmation
     * 
     * @since 1.0.0
     */
    public function alm_add_new_user_to_blog_by_admin_event()
    {
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires when a new user is created by super admin without email confirmation
     * 
     * @since 1.0.0
     */
    public function alm_add_new_user_to_blog_by_self_event()
    {
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Invite an existing user to join a site
     * 
     * @since 1.0.0
     */
    public function invite_user_event($object_id, $role, $newuser_key)
    {
        $blog_id = get_current_blog_id();

        if ($this->current_blog_ID == $blog_id) {
            $blog_url   = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $blog_name  = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $blog_url   = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
            $blog_name  = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
        }

        $role_given     = $this->sanitizeOption($role);
        $activation_key = $this->sanitizeOption($newuser_key);

        $primary_blog   = $this->sanitizeOption(get_user_meta($object_id, 'primary_blog', true));
        $source_domain  = $this->sanitizeOption(get_user_meta($object_id, 'source_domain', true));

        if ($this->current_blog_ID == $primary_blog) {
            $primary_blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $primary_blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $primary_blog_url  = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
            $primary_blog_name = $this->sanitizeOption(get_blog_option($primary_blog, 'blogname', ''));
        }

        $this->setupUserEventArgs(compact(
            'blog_id',
            'blog_url',
            'object_id',
            'blog_name',
            'role_given',
            'primary_blog',
            'source_domain',
            'activation_key',
            'primary_blog_url',
            'primary_blog_name'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires after a user is created by a super admin with email confirmation.
     * 
     * The event alias is fired when user self-registers
     * 
     * @since 1.0.0
     */
    public function after_signup_user_event($user, $user_email, $key, $meta)
    {
        $user_args          = $this->unserialize( $meta );

        $blog_id            = $this->getVar($user_args, 'add_to_blog', '');
        $blog_url           = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
        $blog_name          = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
        $user_login         = $user;
        $object_id          = 0;
        $role_given         = $this->getVar($user_args, 'new_role', '');
        $activation_key     = $this->sanitizeOption($key);
        $new_user_status    = 'Not activated';
        $_ignore_auto_setup = true;

        $this->setupUserEventArgs(compact(
            'blog_id',
            'blog_url',
            'object_id',
            'user_email',
            'user_login',
            'blog_name',
            'role_given',
            'activation_key',
            'new_user_status',
            '_ignore_auto_setup'
        ));

        if( $this->isAddNewUserAdminScreen() ) {
            $this->LogActiveEvent('user', __METHOD__);
        } else {
            $this->alm_after_signup_user_by_self_event();
        }
    }

    /**
     *  Fires after a user self-registers with email confirmation enabled
     * 
     * @since 1.0.0
     */
    public function alm_after_signup_user_by_self_event()
    {
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a new user is activated with email confirmation.
     * 
     * The event alias is fired when user self-registers
     * 
     * @since 1.0.0
     */
    public function wpmu_activate_user_event($user_id, $password, $meta)
    {
        if ($this->isAddNewUserAdminScreen()) return;

        $user_data       = get_userdata($user_id);
        $user_args       = $this->unserialize($meta);

        $blog_id         = $this->getVar($meta, 'add_to_blog', '');
        $blog_url        = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
        $blog_name       = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
        $user_login      = $this->sanitizeOption($this->getVar($user_data, 'user_login'));
        $object_id       = $user_id;
        $role_given      = $this->getVar($meta, 'new_role', '');
        $new_user_status = 'Activated';

        $primary_blog    = (int) $blog_id;
        $source_domain   = $this->sanitizeOption(get_user_meta($object_id, 'source_domain', true));

        if ($this->current_blog_ID == $primary_blog) {
            $primary_blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $primary_blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $primary_blog_url  = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
            $primary_blog_name = $this->sanitizeOption(get_blog_option($primary_blog, 'blogname', ''));
        }

        $this->setupUserEventArgs(compact(
            'blog_id',
            'blog_url',
            'object_id',
            'user_email',
            'user_login',
            'blog_name',
            'role_given',
            'primary_blog',
            'source_domain',
            'new_user_status',
            'primary_blog_url',
            'primary_blog_name'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires before a user is removed from a site.
     * 
     * @since 1.0.0
     */
    public function remove_user_from_blog_event($object_id, $blog_id, $reassign)
    {
        /**
         * When a new user is been created, the remove_user_from_blog() function is 
         * used to properly create the user, so we have to bail out
         */
        if (!empty($this->getConstant('ALM_MS_NEW_USER_CREATED')))
            return;

        // Check if the user to be removed does exists
        $object_user = get_userdata($object_id);
        if (!$object_user) return;

        /**
         * Setup the user aggregation flag
         */
        $this->setupUserLogAggregationFlag();

        if ($this->current_blog_ID == $blog_id) {
            $blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
            $blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
        } else {
            $blog_url  = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
            $blog_name = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
        }

        $primary_blog  = $this->sanitizeOption(get_user_meta($object_id, 'primary_blog', true));
        $source_domain = $this->sanitizeOption(get_user_meta($object_id, 'source_domain', true));

        /**
         * If the user is being removed from the primary blog,
         * WordPress will set a new primary blog if the user is assigned to multiple blogs
         */
        $blogs = get_blogs_of_user($object_id);
        if ($primary_blog == $blog_id) {
            foreach ((array) $blogs as $blog) {
                if ($blog->userblog_id == $blog_id) {
                    continue;
                }
                $primary_blog     = (int) $blog->userblog_id;
                $source_domain    = $this->sanitizeOption($blog->domain);
                $primary_blog_url = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
                break;
            }
        } else {
            $source_domain = $this->sanitizeOption(get_user_meta($object_id, 'source_domain', true));
        }

        // Make sure the user primary blog ID url are not set to 
        // the blog they were removed from
        if (count($blogs) == 0) {
            $primary_blog      = '';
            $source_domain     = '';
            $primary_blog_url  = '';
            $primary_blog_name = '';
        } else {
            $primary_blog_url = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
            $primary_blog_name = $this->sanitizeOption(get_blog_option($primary_blog, 'name', ''));
        }

        // Check whether the user post is reassigned to another user
        if ( !empty($reassign) ) {
            $reassign = (int) $reassign;
            $post_ids = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(ID) FROM $this->wpdb->posts WHERE post_author = %d LIMIT 1", $object_id));

            if ($post_ids > 0) {
                $reassign_user       = get_userdata($reassign);
                $reassign_user_login = $this->sanitizeOption($reassign_user->user_login);
                $reassign_post       = "{$reassign_user_login} (User ID: {$reassign})";
            } else {
                $reassign_post = 'No post found';
            }
        } else {
            $reassign_post = 'Posts was not reassigned to any user';
        }   

        $this->setupUserEventArgs(compact(
            'blog_id',
            'blog_url',
            'object_id',
            'blog_name',
            'primary_blog',
            'source_domain',
            'reassign_post',
            'primary_blog_url',
            'primary_blog_name'
        ));
        $this->LogActiveEvent('user', __METHOD__);
    }
}