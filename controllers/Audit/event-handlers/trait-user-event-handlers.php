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

        if ( $can_ignore_user_event ) 
            return;

        $this->setupUserEventArgs( compact( 'object_id', 'meta_key', 'meta_value' ) );
    }
    
    /**
     * Fires after adding a user meta data
     * 
     * @see add_user_meta action hook
    */
    public function added_user_meta_event( $meta_id, $object_id, $meta_key, $meta_value )
    {
        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
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
            'previous' => '',
        ]);

        if ( $can_ignore_user_event ) 
            return;
        
        $this->setupUserEventArgs( compact( 'meta_id', 'object_id', 'meta_key', 'meta_value' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Fires before updating the user meta data
     * 
     * @see update_user_meta action hook
    */
    public function update_user_meta_event( $meta_id, $object_id, $meta_key, $meta_value )
    {
        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_id, $meta_key
        );

        // Set the previous value
        $this->metadata_value_previous = get_user_meta( $object_id, $meta_key, true );

        if ( $can_ignore_user_event ) 
            return;

        $this->setupUserEventArgs(
            compact( 'meta_id', 'object_id', 'meta_key', 'meta_value' ),
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
        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
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

        $this->setupUserEventArgs( compact( 'meta_id', 'object_id', 'meta_key', 'meta_value' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
    
    /**
     * Fires before deleting a user meta
     * 
     * @see delete_user_meta action hook
    */
    public function delete_user_meta_event( $meta_ids, $object_id, $meta_key, $meta_value )
    {
        // Set the deleted meta value
        $this->metadata_value_deleted = get_user_meta( $object_id, $meta_key, true );

        if ( $this->canIgnoreUserMetaStrictly( $meta_key ) ) 
            return;

        $can_ignore_user_event = apply_filters(
            'alm/event/user/custom_field/ignore',
            false, $meta_ids, $meta_key
        );

        if ( $can_ignore_user_event ) 
            return;

        $this->setupUserEventArgs( compact( 'meta_id', 'object_id', 'meta_key', 'meta_value' ) );

        // var_dump( $meta_ids, $object_id, $meta_key, $meta_value );
        // wp_die();
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

        if ( $can_ignore_user_event ) 
            return;

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
            \call_user_func_array(
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
        if ( ! is_wp_error( $errors ) ) 
            return;

        $error_list = $errors->get_error_messages();

        if ( empty( $error_list ) ) 
            return;
        
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
    public function profile_update_event( $user_id, $old_user_data )
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
        $new_user_data = get_userdata( $user_id );

        // var_dump( $old_user_data, $new_user_data );
        // wp_die();

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
         */
        $user_table_fields = $this->getUserTableFields();

        foreach ( $user_table_fields as $user_table_field => $field_title )
        {
            if ( isset( $new_user_data->$user_table_field ) 
            && isset( $old_user_data->$user_table_field ) )
            {
                /**
                 * A confirmation by email/sms maybe requested for:
                 *  [user_email], [user_login]
                 * 
                 * So we have to setup a pre-update request handler for monitoring the 
                 * changes in those user fields which requires confirmation.
                 * 
                 * Important:
                 * The only exception here is that, if a user is editing another user,
                 * the changes are committed without confirmation, so we have to 
                 * check for that too.
                 */
                if ( $this->userProfileFieldUpdateRequiresConfirmation( $user_table_field, $new_user_data, $old_user_data ) )
                {
                    $user_profile_update_hook_alias = "alm_profile_update_pre_{$user_table_field}_event";
                }
                elseif ( $new_user_data->$user_table_field != $old_user_data->$user_table_field )
                {
                    $user_profile_update_hook_alias = "alm_profile_update_{$user_table_field}_event";
                }
                else {
                    // Reset the variable to avoid repetitive callback execution
                    $user_profile_update_hook_alias = '';
                }

                if ( ! empty( $user_profile_update_hook_alias ) 
                && method_exists( $this, $user_profile_update_hook_alias ) )
                {
                    \call_user_func_array(
                        [ $this, $user_profile_update_hook_alias   ],
                        [ $user_id, $new_user_data, $old_user_data ]
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
            $this->setupUserEventArgs( compact( 'object_id', 'user_profile_data' ) );
        }
        else {
            $this->setupUserEventArgs( compact( 'object_id' ) );
        }

        if ( $this->isLogAggregatable() && empty( $user_profile_data ) ) 
            return;

        $this->LogActiveEvent( 'user', __METHOD__ );
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

        $this->setupUserEventArgs( compact( 'object_id', 'display_name_new', 'display_name_previous' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
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
            $this->active_event_alt['severity'] = 'critical';

            if ( $this->isUserProfileDataAggregationActive() ) 
                return;
        }

        $this->setupUserEventArgs( compact( 'object_id', 'user_email_current', 'user_email_requested' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
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
            $user_email_new = $this->getVar(
                get_user_meta( $object_id, '_new_email', true ), 'newemail', ''
            );

        /**
         * Append the current user custom field data if user profile data 
         * aggregation is active
         */
        $this->appendUpdatedUserProfileData( 'user_email', [
            'new'      => $user_email_new,
            'previous' => $user_email_previous,
        ]);

        // Modifying the user email should be a critical event
        $this->active_event_alt['severity'] = 'critical';

        if ( $this->isUserProfileDataAggregationActive() ) 
            return;

        $this->setupUserEventArgs( compact( 'object_id', 'user_email_new', 'user_email_previous' ) );
        $this->LogActiveEvent( 'user', __METHOD__ );
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
        $this->LogActiveEvent( 'user', __METHOD__ );
    }
}