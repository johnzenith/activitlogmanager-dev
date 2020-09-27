<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package User Event Handlers
 * @since   1.0.0
 */

trait UserEvents
{
    /**
     * Fires before adding a user meta data
     * 
     * @see add_user_meta action hook
    */
    public function add_user_meta_event( $object_id, $meta_key, $meta_value )
    {
        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, 0, $meta_key
        );

        // Setup the old user caps/role data
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';

        if ($user_cap_meta_field == $meta_key) {
            if ( $this->User->current_user_ID == $object_id ) {
                $user_data = $this->User->current_user_data;
            } else {
                $user_data = get_userdata($object_id);
            }
            $this->old_user_caps['caps']  = $this->getVar($user_data, 'caps', []);
            $this->old_user_caps['roles'] = $this->getVar($user_data, 'roles', []);

            return;
        }

        if ( $can_ignore_user_event ) 
            return;

        $this->setupUserEventArgs(compact( 'object_id', 'meta_key', 'meta_value' ));
    }
    
    /**
     * Fires after adding a user meta data
     * 
     * @see add_user_meta action hook
    */
    public function added_user_meta_event( $meta_id, $object_id, $meta_key, $meta_value )
    {
        /**
         * Trigger the user capability event alias if the capability meta 
         * field is active
         */
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';

        // Check whether the cap was granted or not
        $new_caps     = (array) $meta_value;
        $old_caps     = (array) $this->getVar($this->old_user_caps, 'caps', []);

        $granted_caps = array_values(array_diff_key($new_caps, $old_caps));

        if ( in_array(true, $granted_caps, true) || in_array(1, $granted_caps, true) ) {
            $user_cap_event_alias = 'alm_add_user_cap_event';
        } else {
            $user_cap_event_alias = 'alm_add_user_cap_denied_event';
        }
        
        if ( $user_cap_meta_field == $meta_key ) {
            call_user_func_array(
                [$this, $user_cap_event_alias],
                [$object_id, $meta_value]
            );
            return;
        }
        
        if ($this->canIgnoreUserMetaStrictly( $meta_key ))
            return;

        /**
         * Check if the user meta field should be ignored
         */
        if ($this->isActiveMetaFieldEventIgnorable($meta_key))
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_id, $meta_key
        );

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData($meta_key, [
            'new'      => $meta_value,
            'previous' => '',
        ]);

        if ($can_ignore_user_event) 
            return;
        
        $this->setupUserEventArgs(compact( 'meta_id', 'object_id', 'meta_key', 'meta_value' ));
        $this->LogActiveEvent('user', __METHOD__);
    }
    
    /**
     * Fires before updating the user meta data
     * 
     * @see update_user_meta action hook
    */
    public function update_user_meta_event( $meta_id, $object_id, $meta_key, $meta_value )
    {
        // Setup the old user caps/role data
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';

        if ($user_cap_meta_field == $meta_key) {
            if ($this->User->current_user_ID == $object_id) {
                $user_data = $this->User->current_user_data;
            } else {
                $user_data = get_userdata($object_id);
            }

            $this->old_user_caps['caps']  = $this->getVar($user_data, 'caps', []);
            $this->old_user_caps['roles'] = $this->getVar($user_data, 'roles', []);

            return;
        }

        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_id, $meta_key
        );

        // Set the previous value
        $this->metadata_value_previous = get_user_meta( $object_id, $meta_key, true );

        if ($can_ignore_user_event) 
            return;

        $this->setupUserEventArgs(
            compact('meta_id', 'object_id', 'meta_key', 'meta_value'),
            'pre'
        );
        
        // $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Fires after updating the user meta data
     * 
     * @see update_user_meta action hook
    */
    public function updated_user_meta_event( $meta_id, $object_id, $meta_key, $meta_value )
    {
        /**
         * Trigger the user capability event alias if the capability meta 
         * field is active
         */
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';
        
        if ($user_cap_meta_field == $meta_key)
        {
            // Check whether we need to call the 'add' or 'remove' 
            // user cap event alias
            $new_caps = (array) $meta_value;
            $old_caps = (array) $this->getVar($this->old_user_caps, 'caps', []);

            $user_cap_event_alias = count($new_caps) > count($old_caps) ?
                'alm_add_user_cap_event' : 'alm_remove_user_cap_event';

            if ('alm_added_user_cap_event' == $user_cap_event_alias)
            {
                $granted_caps = array_values(array_diff_key($new_caps, $old_caps));

                if ( !in_array(true, $granted_caps, true) && !in_array(1, $granted_caps, true) )
                    $user_cap_event_alias = 'user_cap_event_alias';
            }

            call_user_func_array(
                [$this, $user_cap_event_alias],
                [$object_id, $meta_value]
            );
            return;
        }

        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
            return;

        /**
         * Check if the user meta field should be ignored
         */
        if ($this->isActiveMetaFieldEventIgnorable($meta_key))
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_id, $meta_key
        );

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData( $meta_key, [
            'new'      => $meta_value,
            'previous' => $this->metadata_value_previous,
        ]);

        if ( $can_ignore_user_event ) 
            return;

        $this->setupUserEventArgs(compact( 'meta_id', 'object_id', 'meta_key', 'meta_value'));
        $this->LogActiveEvent('user', __METHOD__);
    }
    
    /**
     * Fires before deleting a user meta
     * 
     * @see delete_user_meta action hook
    */
    public function delete_user_meta_event( $meta_ids, $object_id, $meta_key, $meta_value )
    {
        // Setup the old user caps/role data
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';

        if ($user_cap_meta_field == $meta_key) {
            if ($this->User->current_user_ID == $object_id) {
                $user_data = $this->User->current_user_data;
            } else {
                $user_data = get_userdata($object_id);
            }
            $this->old_user_caps['caps']  = $this->getVar($user_data, 'caps', []);
            $this->old_user_caps['roles'] = $this->getVar($user_data, 'roles', []);

            return;
        }

        // Set the deleted meta value
        $this->metadata_value_deleted = get_user_meta($object_id, $meta_key, true);

        if ($this->canIgnoreUserMetaStrictly( $meta_key )) 
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_ids, $meta_key
        );

        if ($can_ignore_user_event) 
            return;

        $this->setupUserEventArgs(compact('meta_id', 'object_id', 'meta_key', 'meta_value'));
    }
    
    /**
     * Fires after deleting a user meta
     * 
     * @since 1.0.0
     * 
     * @see delete_user_meta action hook
    */
    public function deleted_user_meta_event( $meta_ids, $object_id, $meta_key, $meta_value )
    {
        /**
         * Trigger the user capability event alias if the capability meta 
         * field is active
         */
        $blog_prefix         = $this->getBlogPrefix();
        $user_cap_meta_field = $blog_prefix . 'capabilities';

        if ($user_cap_meta_field == $meta_key) {
            $user_cap_event_alias = 'alm_remove_all_user_cap_event';

            call_user_func_array(
                [$this, $user_cap_event_alias],
                [$object_id, []]
            );
            return;
        }

        /**
         * Set the user meta deleted flag.
         * 
         * This is used by handle custom events like monitoring when the 
         * change of user email request has been cancelled
         */
        $this->setConstant( 'ALM_USER_META_DELETED', $meta_key );

        /**
         * Handles the user email update request cancellation which is determine by 
         * deleting the '_new_email' user meta field and the user is not updating 
         * their profile
         */
        if ( '_new_email' == $meta_key ) {
            $this->alm_profile_update_cancelled_user_email_event( $object_id, $meta_key );
            return;
        }

        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
            return;

        /**
         * Check if the user meta field should be ignored
         */
        if ($this->isActiveMetaFieldEventIgnorable($meta_key))
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_ids, $meta_key
        );

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         * 
         * This may not be used by the profile update handler,
         * but just to make sure that we are watching the changes.
         */
        $this->appendUpdatedUserProfileData( $meta_key, [
            'new'      => $this->metadata_value_deleted,
            'previous' => '',
        ]);

        if ( $can_ignore_user_event ) return;

        $this->setupUserEventArgs( compact( 'meta_ids', 'object_id', 'meta_key', 'meta_value' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Fires when the 'Edit User' is opened
     * @see edit_user_profile_update action hook
    */
    public function edit_user_profile_update_event( $object_id )
    {
        $this->setConstant( 'ALM_EDIT_USER_PROFILE_OPENED', true );

        $this->setupUserEventArgs( compact( 'object_id' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Fires before user profile update errors are returned.
     * 
     * @see user_profile_update_errors action hook
    */
    public function user_profile_update_errors_event( $errors, $update, $user )
    {
        /**
         * Get the user profile data ready for update
         */
        $this->user_profile_data_to_update = $user;

        /**
         * If not updating the user profile, then we have to 
         * Trigger the user profile update errors action hook alias,
         */
        if ( ! $update )
        {
            call_user_func_array(
                [ $this, 'alm_user_profile_update_errors_event' ],
                [ &$errors, $update, &$user ]
            );
            return;
        }

        /**
         * Don't do anything if error is not available
         */
        if ( ! is_wp_error( $errors ) ) 
            return;

        $error_list = $errors->get_error_messages();

        if ( empty( $error_list ) ) 
            return;

        $object_id  = $user->ID;
        $error_msg  = implode( $this->getEventMsgErrorChar(), $error_list );

        $_error_msg = wp_strip_all_tags( $error_msg );
        
        $this->setupUserEventArgs( compact( 'object_id', '_error_msg' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Alias of the user_profile_update_errors action hook.
     * 
     * @see user_profile_update_errors action hook
     * 
     * This event is triggered by the 'user_profile_update_errors' action hook when a 
     * new user is being created, unlike updating the user profile.
    */
    public function alm_user_profile_update_errors_event( $errors, $update, $user )
    {
        /**
         * Set the user creation flag.
         * Will be used to prevent the user profile updated event from triggering
         */
        $this->setConstant('ALM_EDIT_USER_CREATE_NEW_USER', true);

        // Discard the potential user password
        // Note: $user is passed by reference, we must not unset it directly
        $_user = clone $user;
        unset( $_user->user_pass );

        /**
         * Don't do anything if error is not available
         */
        if (!is_wp_error($errors)) return;

        $error_list = $errors->get_error_messages();

        if (empty($error_list)) return;
        
        $user_obj   = $_user;
        $object_id  = $update ? $_user->ID : 0;
        $error_msg  = implode( $this->getEventMsgErrorChar(), $error_list );

        $_error_msg = wp_strip_all_tags( $error_msg );
        
        $this->setupUserEventArgs( compact( 'object_id', '_error_msg', 'user_obj' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires after a new user has been created.
     * 
     * @see edit_user_created_user action hook
     */
    public function edit_user_created_user_event( $user_id, $notify )
    {
        $object_id = $user_id;

        $user_profile_data = '';
        if ( $this->isUserProfileDataAggregationActive() )
        {
            $user_profile_data = $this->__aggregateUserMetaFields();
            $this->setupUserEventArgs( compact( 'object_id', 'user_profile_data' ) );
        }
        else {
            $this->setupUserEventArgs( compact( 'object_id' ) );
        }

        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires immediately after a new user is registered.
     * 
     * @see register_new_user()
     * @see edit_user_created_user action hook
     */
    public function register_new_user_event( $user_id )
    {
        /**
         * We don't want the user registration event to bubble up:
         * 
         * @see wp_insert_user()
         */
        if ( ! wp_doing_ajax() 
        && ( 
            $this->is_admin 
            || $this->getConstant('ALM_EDIT_USER_PROFILE_OPENED') 
            || $this->getConstant('IS_PROFILE_PAGE') 
            || 'create' != $this->getConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION') 
            )
        ) return;

        $object_id = $user_id;

        /**
         * Get all created user meta fields if any
         */
        $user_profile_data = '';
        if ( $this->isUserProfileDataAggregationActive() )
        {
            $user_profile_data = $this->__aggregateUserMetaFields();
            $this->setupUserEventArgs( compact( 'object_id', 'user_profile_data' ) );
        }
        else {
            $this->setupUserEventArgs( compact( 'object_id' ) );
        }

        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires when submitting registration form data, before the user is created.
     * 
     * @see register_new_user()
     * @see register_post action hook
     */
    public function register_post_event( $sanitized_user_login, $user_email, $errors )
    {
        /**
         * We don't want this event to bubble up (called more than once)
         */
        if ( ! wp_doing_ajax() 
        && ( $this->is_admin || $this->is_user_admin || $this->getConstant('IS_PROFILE_PAGE') ) ) 
            return;

        /**
         * Don't do anything if error is not available
         */
        if ( ! is_wp_error( $errors ) ) 
            return;

        $error_list = $errors->get_error_messages();

        if ( empty( $error_list ) ) 
            return;
        
        $user_data  = new \stdClass;
        $object_id  = 0;
        $error_msg  = implode( $this->getEventMsgErrorChar(), $error_list );

        $_error_msg = wp_strip_all_tags( $error_msg );

        $user_data->user_login = $sanitized_user_login;
        $user_data->user_email = $this->sanitizeOption( $user_email, 'email' );

        $user_obj = $user_data;
        
        $this->setupUserEventArgs( compact( 'object_id', '_error_msg', 'user_obj' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }

    /**
     * Fires immediately after an existing user is updated.
     * 
     * @see edit_user_created_user action hook
     */
    public function profile_update_event($user_id, $old_user_data)
    {
        /**
         * Setup the user profile updated flag.
         * 
         * This is used to control custom events like monitoring the 
         * cancellation of user email request.
         */
        $this->setConstant('ALM_USER_PROFILE_UPDATED', true);

        /**
         * We don't want the profile update event to bubble up:
         * 
         * @see wp_insert_user()
         * @see wp_update_user()
         */
        if ( $this->getConstant('ALM_EDIT_USER_CREATE_NEW_USER')
        || 'update' != $this->getConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION') ) 
            return;

        $object_id     = $user_id;
        $new_user_data = $this->User->getUserData($user_id, true);

        $callback_args = [ $user_id, $new_user_data, $old_user_data ];

        /**
         * Trigger the callback alias for the profile update event
         * 
         * > user_url
         * > user_pass
         * > user_email
         * > user_login
         * > user_status
         * > display_name
         * > user_nicename
         * > user_activation_key
         */
        $user_table_fields = $this->getUserTableFields();

        foreach ( $user_table_fields as $user_table_field => $field_title )
        {
            if ( isset( $new_user_data->$user_table_field )
            && isset( $old_user_data->$user_table_field ) )
            {
                $was_user_field_updated = ( $new_user_data->$user_table_field != $old_user_data->$user_table_field );

                // User password field
                if ( 'user_pass' == $user_table_field )
                {
                    if ( ! hash_equals( $old_user_data->user_pass, $new_user_data->user_pass ) )
                    {
                        $user_profile_update_hook_alias = "alm_profile_update_{$user_table_field}_event";
                    }
                }
                // For password reset, user registration email confirmation
                elseif( 'user_activation_key' == $user_table_field )
                {
                    if ( ! empty( $new_user_data->$user_table_field ) )
                    {
                        if ( $was_user_field_updated )
                        {
                            /**
                             * List all events connected to the user_activation_key field 
                             * after successful update
                             */
                            $user_profile_update_hook_alias = [ 'alm_retrieve_password_successfully' ];
                        }
                        else {
                            /**
                             * List all events connected to the user_activation_key field 
                             * after unsuccessful update
                             */
                            $user_profile_update_hook_alias = [ 'alm_retrieve_password_unsuccessful' ];
                        }
                    }
                }
                elseif ( $was_user_field_updated )
                {
                    $user_profile_update_hook_alias = "alm_profile_update_{$user_table_field}_event";
                }
                /**
                 * A confirmation by email/sms maybe requested for:
                 *  [user_email], [user_login]
                 * 
                 * So we have to setup a pre-update request handler for monitoring the 
                 * changes in those user fields that requires confirmation.
                 * 
                 * Important:
                 * The only exception here is that, if a user is editing another user,
                 * the changes are committed without confirmation, so we have to 
                 * check for that too.
                 */
                elseif ( $this->userProfileFieldUpdateRequiresConfirmation( $user_table_field, $new_user_data, $old_user_data ) )
                {
                    $user_profile_update_hook_alias = "alm_profile_update_pre_{$user_table_field}_event";
                }
                else {
                    // Reset the variable to avoid repetitive callback execution
                    $user_profile_update_hook_alias = '';
                }

                if ( is_array( $user_profile_update_hook_alias ) )
                {
                    foreach ( $user_profile_update_hook_alias as $profile_hook_alias )
                    {
                        if ( is_string( $profile_hook_alias ) 
                        && ! empty( $profile_hook_alias ) 
                        && method_exists( $this, $profile_hook_alias ) ) 
                        {
                            call_user_func_array(
                                [ $this, $profile_hook_alias ],
                                $callback_args
                            );
                        }
                    }
                }
                elseif ( is_string( $user_profile_update_hook_alias )  
                && ! empty( $user_profile_update_hook_alias ) 
                && method_exists( $this, $user_profile_update_hook_alias ) )
                {
                    call_user_func_array(
                        [ $this, $user_profile_update_hook_alias ],
                        $callback_args
                    );
                }
            }
        } // foreach ( $user_table_fields as $user_table_field )

        $user_post_request_meta = isset( $_POST[ $this->user_profile_meta_field_name ] ) ? 
            sanitize_text_field( $_POST[ $this->user_profile_meta_field_name ] ) : '';

        if ( ! empty( $user_post_request_meta ) )
        {
            $user_metadata = [];
            parse_str(
                str_replace( $this->user_profile_meta_field_val_splitter, '&', $user_post_request_meta ),
                $user_metadata
            );

            foreach ( $user_metadata as $k => $v )
            {
                $_v = $this->sanitizeOption( $v );
                if ( ! isset( $this->user_data_aggregation[ $k ] ) 
                && ( 
                    is_object( $this->user_profile_data_to_update ) 
                    && isset( $this->user_profile_data_to_update->$k ) 
                    && $this->user_profile_data_to_update->$k != $_v ) 
                )
                {
                    $this->user_data_aggregation[ $k ] = [
                        'new'      => $this->user_profile_data_to_update->$k,
                        'previous' => $_v,
                    ];
                }
            }
            unset( $_v, $v, $k );
        }

        $user_profile_data = '';
        if ( $this->isUserProfileDataAggregationActive() )
        {
            $user_profile_data = $this->__aggregateUserMetaFields();
            $this->setupUserEventArgs(compact( 'object_id', 'user_profile_data' ));
        }
        else {
            $this->setupUserEventArgs(compact( 'object_id' ));
        }

        if ($this->isLogAggregatable() && empty( $user_profile_data ))
            return;

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User display name update handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_display_name_event( $object_id, $new_user_data, $old_user_data )
    {
        $display_name_new      = $new_user_data->display_name;
        $display_name_previous = $old_user_data->display_name;

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData( 'display_name', [
            'new'      => $display_name_new,
            'previous' => $display_name_previous,
        ]);

        if ( $this->isUserProfileDataAggregationActive() ) 
            return;

        $this->setupUserEventArgs(compact( 'object_id', 'display_name_new', 'display_name_previous' ));
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User email pre update request handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_pre_user_email_event( $object_id, $new_user_data, $old_user_data )
    {
        $user_email_current   = $old_user_data->user_email;
        $user_email_requested = $this->getVar( get_user_meta( $object_id, '_new_email', true ), 'newemail' );

        /**
         * Bail out if the current user email is the same with the requested one,
         * or if email confirmation var ($_GET['newuseremail']) is set
         */
        if (empty( $user_email_requested )) 
            return;

        if (0 === strcasecmp( $user_email_requested, $user_email_current ))
            return;

        if ( $this->getConstant('IS_PROFILE_PAGE') 
        && isset( $_GET['newuseremail'] ) 
        && $this->User->current_user_ID )
            return;

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         * 
         * For now, we will disable profile update aggregation on this event because 
         * its update context is entirely different
         */
        $allow_profile_update_aggregation = false;
        if ( $allow_profile_update_aggregation ) 
        {
            $this->appendUpdatedUserProfileData( 'user_email', [
                'current'   => $user_email_current,
                'requested' => $user_email_requested,
            ]);
            
            // Trying to modify the user email should be a critical event
            $this->overrideActiveEventData('severity', 'critical');

            if ( $this->isUserProfileDataAggregationActive() ) 
                return;
        }

        $this->setupUserEventArgs(compact( 'object_id', 'user_email_current', 'user_email_requested' ));
        $this->LogActiveEvent('user', __METHOD__);
    }  

    /**
     * User email update confirmed handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_user_email_event( $object_id, $new_user_data, $old_user_data )
    {
        $user_email_new      = $new_user_data->user_email;
        $user_email_previous = $old_user_data->user_email;

        if ( 0 === strcasecmp( $user_email_new, $user_email_previous ) )
        {
            $user_email_new = $this->getVar(
                get_user_meta( $object_id, '_new_email', true ), 'newemail', ''
            );
        }

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         * 
         * For now, the user email event aggregation is disabled because the 
         * event is too sensitive and should be standing alone.
         */
        $allow_profile_update_aggregation = false;
        if ($allow_profile_update_aggregation) {
            $this->appendUpdatedUserProfileData('user_email', [
                'new'      => $user_email_new,
                'previous' => $user_email_previous,
            ]);

            // Modifying the user email should be a critical event
            $this->overrideActiveEventData('severity', 'critical');

            if ($this->isUserProfileDataAggregationActive())
                return;
        }

        $this->setupUserEventArgs( compact( 'object_id', 'user_email_new', 'user_email_previous' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User email update request cancellation handler
     * 
     * @since 1.0.0
     * 
     * @see UserEvents::deleted_user_meta_event()
     */
    public function alm_profile_update_cancelled_user_email_event( $object_id, $meta_key )
    {
        /**
         * We have to make sure that this is really an user email cancellation event.
         * 
         * Cancellation is not connected with the user profile update.
         */
        if ( $this->getConstant('ALM_USER_PROFILE_UPDATED') ) 
            return;

        $user_data            = $this->User->getUserData( $object_id );

        $user_email_current   = $user_data->user_email;
        $user_email_requested = $this->getVar( $this->metadata_value_deleted, 'newemail', '' );

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         * 
         * For now, we will disable profile update aggregation on this event because 
         * its update context is entirely different
         */
        $allow_profile_update_aggregation = false;
        if ( $allow_profile_update_aggregation )
        {
            $this->appendUpdatedUserProfileData( 'user_email', [
                'current'   => $user_email_current,
                'requested' => $user_email_requested,
            ]);

            if ( $this->isUserProfileDataAggregationActive() ) 
                return;
        }

        $this->setupUserEventArgs( compact( 'object_id', 'user_email_current', 'user_email_requested' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User url update handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_user_url_event( $object_id, $new_user_data, $old_user_data )
    {
        $user_url_new      = $new_user_data->user_url;
        $user_url_previous = $old_user_data->user_url;

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData('user_url', [
            'new'      => $user_url_new,
            'previous' => $user_url_previous,
        ]);

        if ( $this->isUserProfileDataAggregationActive() ) 
            return;

        $this->setupUserEventArgs( compact( 'object_id', 'user_url_new', 'user_url_previous' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User nicename update handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_user_nicename_event( $object_id, $new_user_data, $old_user_data )
    {
        $user_nicename_new      = $new_user_data->user_nicename;
        $user_nicename_previous = $old_user_data->user_nicename;

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData('user_nicename', [
            'new'      => $user_nicename_new,
            'previous' => $user_nicename_previous,
        ]);

        if ( $this->isUserProfileDataAggregationActive() ) 
            return;

        $this->setupUserEventArgs( compact( 'object_id', 'user_nicename_new', 'user_nicename_previous' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User status update handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_user_status_event( $object_id, $new_user_data, $old_user_data )
    {
        $user_status_new      = $new_user_data->user_status;
        $user_status_previous = $old_user_data->user_status;

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData('user_status', [
            'new'      => $user_status_new,
            'previous' => $user_status_previous,
        ]);

        if ( $this->isUserProfileDataAggregationActive() ) 
            return;

        $this->setupUserEventArgs( compact( 'object_id', 'user_status_new', 'user_status_previous' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User password update handler
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_profile_update_user_pass_event( $object_id, $new_user_data, $old_user_data )
    {
        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         * 
         * For now, the user password event aggregation is disabled because the 
         * event is too sensitive and should be standing alone.
         */
        $allow_profile_update_aggregation = false;
        if ( $allow_profile_update_aggregation )
        {
            $this->appendUpdatedUserProfileData('user_password', [
                'new'      => '',
                'previous' => '',
            ]);

            // Modifying the user email should be a critical event
            $this->overrideActiveEventData('severity', 'critical');

            if ( $this->isUserProfileDataAggregationActive() ) 
                return;
        }

        $this->setupUserEventArgs( compact( 'object_id' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires before errors are returned from a password reset request.
     * 
     * @since 1.0.0
     */
    public function lostpassword_post_event( $errors, $_user_data )
    {
        /**
         * Don't do anything if error is not available
         */
        if ( ! is_wp_error( $errors ) ) 
            return;

        $error_list = $errors->get_error_messages();

        if ( empty( $error_list ) ) 
            return;
        
        $user_data  = new \stdClass;
        $error_msg  = implode( $this->getEventMsgErrorChar(), $error_list );
        
        $_error_msg = wp_strip_all_tags( $error_msg );

        $object_id             = (int) $this->getVar( $_user_data, 'ID', 0 );
        $user_data->user_login = $this->getVar( $_user_data, 'user_login', '' );
        $user_data->user_email = $this->sanitizeOption( $this->getVar( $_user_data, 'user_email', '' ) );

        $user_obj = $user_data;
        
        $this->setupUserEventArgs( compact( 'object_id', '_error_msg', 'user_obj' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Check whether user password reset is allowed or not
     */
    public function allow_password_reset_event( $allow, $object_id )
    {
        /**
         * Don't listen for this event if the password reset flag is not set
         */
        if ( empty( $this->getConstant('ALM_USER_PASSWORD_RESET_STARTED') ) ) 
            return $allow;

        /**
         *  Run if password reset is not allowed on the user account
         */
        if ( ! $allow || empty( $allow ) )
        {
            $this->setupUserEventArgs( compact( 'object_id' ) );
            $this->LogActiveEvent('user', __METHOD__);
        }
    }

    /**
     * Monitor whether the password reset request was successful
     * 
     * @since 1.0.0
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_retrieve_password_successfully( $object_id )
    {
        /**
         * Don't listen for this event if the password reset flag is not set
         */
        if ( empty( $this->getConstant('ALM_USER_PASSWORD_RESET_STARTED') ) 
        || empty( $this->getConstant('ALM_USER_PASSWORD_RESET_KEY') ) ) 
            return;

        // A simple hack to retrieve the password reset expiration time
        $expiration_time          = '@' . apply_filters( 'password_reset_expiration', DAY_IN_SECONDS );

        $password_reset_key       = $this->getConstant( 'ALM_USER_PASSWORD_RESET_KEY' );
        $password_reset_url       = $this->sanitizeOption( wp_lostpassword_url(), 'url' );
        $password_expiration_time = $this->getDataInPluginFormat( $expiration_time );

        $this->setupUserEventArgs( compact(
            'object_id', 'password_reset_key', 'password_reset_url', 'password_expiration_time'
        ) );

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Monitor whether the password reset request was unsuccessful
     * 
     * @since 1.0.0
     * 
     * @see UserEvents::profile_update_event()
     */
    public function alm_retrieve_password_unsuccessful( $object_id )
    {
        /**
         * Don't listen for this event if the password reset flag is not set
         */
        if ( empty( $this->getConstant('ALM_USER_PASSWORD_RESET_STARTED') ) 
        || empty( $this->getConstant('ALM_USER_PASSWORD_RESET_KEY') ) ) 
            return;

        $password_reset_url           = $this->sanitizeOption( wp_lostpassword_url(), 'url' );
        $generated_password_reset_key = $this->getConstant( 'ALM_USER_PASSWORD_RESET_KEY' );

        $this->setupUserEventArgs( compact(
            'object_id', 'generated_password_reset_key', 'password_reset_url'
        ) );

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Monitors user's password reset
     * 
     * @since 1.0.0
     * 
     * @see UserEvents::profile_update_event()
     */
    public function after_password_reset_event( $user, $new_pass )
    {
        $object_id          = $user->ID;
        $password_reset_url = $this->sanitizeOption( wp_lostpassword_url(), 'url' );

        $this->setupUserEventArgs( compact( 'object_id', 'password_reset_url' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Failed login attempts
     * 
     * @since 1.0.0
     */
    public function wp_login_failed_event( $username, $errors )
    {
        $user = get_user_by('slug', $username);
        if ( !$user && false !== strpos($username, '@') )
            $user = get_user_by('email', $username);

        $object_id           = (int) $this->getVar($user, 'ID');
        $login_url           = $this->sanitizeOption(wp_login_url(), 'url');
        $_error_msg          = '';
        $failed_attempts     = 1;
        $user_account_exists = $object_id > 0 ? 'Yes' : 'No';

        $attempted_password  = $this->getVar(
            $this->_user_event_log_bin, 'failed_login_attempted_password', str_repeat('*', 8)
        );

        if ( is_wp_error( $errors ) )
        {
            $error_list = $errors->get_error_messages();

            if ( ! empty( $error_list ) ) {
                $error_msg  = implode( $this->getEventMsgErrorChar(), $error_list );
                $_error_msg = wp_strip_all_tags( $error_msg );
            }
        }

        /**
         * Maybe we should bail the user data if the username/email doesn't exists
         */
        $user_obj = null;
        if ($object_id < 1) {
            $user_obj = [];
            if (is_email($username)) {
                $user_obj['user_login'] = 'Unknown';
                $user_obj['user_email'] = $username;
            } else {
                $user_obj['user_login'] = $username;
                $user_obj['user_email'] = 'Unknown';
            }
            
            $user_obj['email_address'] = $user_obj['user_email'];

            $user_obj = (object) $user_obj;
        }

        $this->setupUserEventArgs(compact(
            'user_obj',
            'object_id',
            'failed_attempts',
            'login_url',
            '_error_msg',
            'user_account_exists',
            'attempted_password'
        ));
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User logged in successfully
     * 
     * @since 1.0.0
     */
    public function wp_login_event( $username, $user )
    {
        $object_id        = $user->ID;
        $login_url        = $this->sanitizeOption( wp_login_url(), 'url' );
        $_current_user_id = $object_id;

        $this->setupUserEventArgs( compact( 'object_id', 'login_url', '_current_user_id' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * User logged out successfully
     * 
     * @since 1.0.0
     */
    public function wp_logout_event()
    {
        $object_id = $this->User->getCurrentUserId();

        $this->setupUserEventArgs( compact( 'object_id' ) );
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a user is deleted from the database.
     * 
     * @since 1.0.0
     */
    public function deleted_user_event($object_id, $reassign)
    {
        $blog_id                 = get_current_blog_id();
        $blog_url                = '';
        $blog_name               = '';
        $primary_blog            = '';
        $source_domain           = '';
        $primary_blog_url        = '';
        $primary_blog_name       = '';
        $deleted_user_statistics = '';

        /**
         * Setup the user aggregation flag
         */
        $this->setupUserLogAggregationFlag();

        if ($this->is_multisite)
        {
            if ($this->current_blog_ID == $blog_id) {
                $blog_url  = $this->sanitizeOption($this->getVar($this->blog_data, 'url'), 'url');
                $blog_name = $this->sanitizeOption($this->getVar($this->blog_data, 'name'));
            } else {
                $blog_url  = $this->sanitizeOption(get_blog_option($blog_id, 'siteurl', ''), 'url');
                $blog_name = $this->sanitizeOption(get_blog_option($blog_id, 'blogname', ''));
            }

            $primary_blog      = $this->sanitizeOption(get_user_meta($object_id, 'primary_blog', true));
            $source_domain     = $this->sanitizeOption(get_user_meta($object_id, 'source_domain', true));
            $primary_blog_url  = $this->sanitizeOption(get_blog_option($primary_blog, 'siteurl', ''), 'url');
            $primary_blog_name = $this->sanitizeOption(get_blog_option($primary_blog, 'name', ''));
        }

        // Check whether the user post is reassigned to another user
        if ( !empty($reassign) ) {
            $reassign = (int) $reassign;
            $post_ids = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(ID) FROM $this->wpdb->posts WHERE post_author = %d LIMIT 1", $object_id));

            if ($post_ids > 0) {
                $reassign_user       = get_userdata($reassign);
                $reassign_user_login = $this->sanitizeOption($reassign_user->user_login);
                $reassign_post       = "{$reassign}_{$reassign_user_login}";
            } else {
                $reassign_post = 'No post found';
            }
        } else {
            $reassign_post = 'Posts was not reassigned to any user';
        }

        // Set the user data object since we may not be able to retrieve the user 
        // data prior to calling this action
        $user_obj = $this->user_data_ref;

        $this->setupUserEventArgs(compact(
            'blog_id',
            'blog_url',
            'user_obj',
            'object_id',
            'blog_name',
            'primary_blog',
            'source_domain',
            'reassign_post',
            'primary_blog_url',
            'primary_blog_name',
            'deleted_user_statistics'
        ));

        /**
         * Fire the deleted user event alias
         */
        if ($this->getConstant('ALM_MS_USER_DELETE_USER')) {
            $this->alm_deleted_user_from_network($object_id, $reassign);
            return;
        }
        
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a user is deleted from the network
     * 
     * This event is an alias of the {@see deleted_user action}
     * 
     * @see wpmu_delete_user()
     */
    public function alm_deleted_user_from_network($object_id, $reassign)
    {
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires after the user's role has changed.
     * 
     * @see WP_User::set_role()
     */
    public function set_user_role_event($object_id, $role, $old_roles)
    {
        $user = $this->User->getUserData($object_id, true);
        
        // Setup the user's roles properties correctly
        $user->get_role_caps();
        
        $user_roles    = $user->roles;
        $role_new      = $this->parseValueForDb($user_roles);
        $role_previous = $this->parseValueForDb($old_roles);

        $no_role_found = 'None';
        if ('' === $role_new) {
            $role_new = $no_role_found;
        }
        if ('' === $role_previous) {
            $role_previous = $no_role_found;
        }

        $this->setupUserEventArgs(compact(
            'role_new',
            'object_id',
            'role_previous'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires after the user is given a new role
     * 
     * @see WP_User::add_role()
     */
    public function add_user_role_event($object_id, $role)
    {
        $user = $this->User->getUserData($object_id, true);

        // Setup the user's roles properties correctly
        $user->get_role_caps();

        $user_roles = $user->roles;

        // Set the role info if the added role is not what WordPress recognized
        $role_info = '_ignore_';
        if ( !in_array($role, $user_roles, true) ) {
            // $user_roles[] = $role;
            $role_info = sprintf('(%s) is a custom role (behaving like a capability) and may not be listed with the actual roles of the user. Please use the inspect user role button to see a full overview of all the roles given to the user.', $role);
        }

        $role_new      = $this->parseValueForDb($user_roles);
        $added_role    = $role;

        $prev_roles    = empty($user_roles) ? '' : array_diff($user_roles, [$role]);
        $role_previous = $this->parseValueForDb($prev_roles);

        $no_role_found = 'None';
        if ('' === $role_previous) {
            $role_previous = $no_role_found;
        }

        $this->setupUserEventArgs(compact(
            'role_new',
            'object_id',
            'role_info',
            'added_role',
            'role_previous' 
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a role as been removed from a user.
     * 
     * @see WP_User::remove_role()
     */
    public function remove_user_role_event($object_id, $role)
    {
        $user = $this->User->getUserData($object_id, true);

        // Setup the user's roles properties correctly
        $user->get_role_caps();

        $user_roles = $user->roles;

        // Make sure the removed role is not in the new user roles array
        if (in_array($role, $user_roles, true)) {
            $all_roles = array_flip($user_roles);
            unset($all_roles[$role]);

            $user_roles = $all_roles;
        }

        $role_new      = $this->parseValueForDb($user_roles);
        $removed_role  = $role;

        $prev_roles    = array_merge($user_roles, [$role]);
        $role_previous = $this->parseValueForDb($prev_roles);

        $no_role_found = 'None';
        if ('' === $role_new) {
            $role_new = $no_role_found;
        }
        if ('' === $role_previous) {
            $role_previous = $no_role_found;
        }

        $this->setupUserEventArgs(compact(
            'role_new',
            'object_id',
            'removed_role',
            'role_previous'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a capability has been given to a user
     * 
     * @see WP_User::add_cap()
     */
    public function alm_add_user_cap_event($object_id, $new_caps)
    {
        $user = $this->User->getUserData($object_id, true);

        // Setup the user's caps correctly
        $user->get_role_caps();

        $new_user_caps       = $user->caps;
        $old_user_caps       = $this->getVar($this->old_user_caps, 'caps', []);
        
        $capability_new      = $this->parseValueForDb($new_user_caps, true);
        
        $cap_added           = empty($old_user_caps) ? 
            $new_user_caps : array_diff_key($old_user_caps, $new_user_caps);

        $capability_added    = $this->parseValueForDb($cap_added, true);

        $prev_caps           = array_diff_key($new_user_caps, $old_user_caps);
        $capability_previous = $this->parseValueForDb($prev_caps, true);

        $no_cap_found = 'None';
        if ('' === $capability_new) {
            $capability_new = $no_cap_found;
        }
        if ('' === $capability_previous) {
            $capability_previous = $no_cap_found;
        }

        $this->setupUserEventArgs(compact(
            'object_id',
            'capability_new',
            'capability_added',
            'capability_previous'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after a capability has been removed from a user
     * 
     * @see WP_User::remove_cap()
     */
    public function alm_remove_user_cap_event($object_id, $new_caps)
    {
        $user = $this->User->getUserData($object_id, true);

        // Setup the user's caps correctly
        $user->get_role_caps();

        $new_user_caps       = $user->caps;
        $old_user_caps       = $this->getVar($this->old_user_caps, 'caps', []);

        $capability_new      = $this->parseValueForDb($new_user_caps, true);

        $caps_removed        = array_diff_key($old_user_caps, $new_user_caps);
        $removed_capability  = $this->parseValueForDb($caps_removed, true);

        $prev_caps           = array_diff_key($new_user_caps, $old_user_caps);
        $capability_previous = $this->parseValueForDb($prev_caps, true);

        $no_cap_found = 'None';
        if ('' === $capability_new) {
            $capability_new = $no_cap_found;
        }
        if ('' === $capability_previous) {
            $capability_previous = $no_cap_found;
        }

        $this->setupUserEventArgs(compact(
            'object_id',
            'removed_capability',
            'capability_new',
            'capability_previous'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires immediately after all the user capabilities have been removed
     * 
     * @see WP_User::add_cap()
     */
    public function alm_remove_all_user_cap_event($object_id, $new_caps)
    {
        $capability_new      = 'None';
        $capability_previous = $this->getVar($this->old_user_caps, 'caps', []);

        $no_cap_found = 'None';
        if ('' === $capability_previous) {
            $capability_previous = $no_cap_found;
        }

        $this->setupUserEventArgs(compact(
            'object_id',
            'capability_new',
            'capability_previous'
        ));

        $this->LogActiveEvent('user', __METHOD__);
    }
}