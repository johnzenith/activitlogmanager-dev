<?php
namespace ALM\Controllers\Audit\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Event Conditional Parser
 * @since   1.0.0
 */

trait EventConditionalParser
{
    /**
             * Event conditional arguments
             */
    protected $event_conditional_args = [];

    /**
     * Initialize the event conditional arguments
     */
    protected function initEventConditionalArgs()
    {
        $this->event_conditional_args = [
            /**
             * Specifies whether to force 'disabled state' on the event.
             * This is useful if a particular event should be monitored  
             * only when a given condition evaluates to true.
             * 
             * Setting this to {false} will turn off the event.
             * 
             * Default: true
             */
            'can_enable' => true,

            /**
             * Specifies where to load event watcher:
             *
             *     'all'      : for all screens (front-end and admin)
             *     'admin'    : for admin screens
             *     'user'     : for non-admin user dashboard
             *     'public'   : for front end (non-admin) screens
             *     'network'  : for network admin screens
             * 
             *     // Accepts: 'main_site' | 'mainsite'
             *     'main_site': for the main site in WordPress network (multisite) installation
             * 
             *     'multisite': for WordPress network (multisite) installation
             * 
             *     'not-multisite': for non-multisite WordPress installation
             *     'not_multisite': alias for 'not-multisite'
             *
             * Note: An empty array will load the event where it is applicable.
             * 
             * @since 1.0.0
             */
            'screen' => [],

            /**
             * Specifies the page to load and watch the event on.
             * This is equivalent to the {@see $pagenow} global var
             * @since 1.0.0
             * 
             * Value can either be a string or an array
             */
            'pagenow' => [],

            /**
             * Controls whether to watch only logged in users, visitors or both.
             * 
             * Accepted arguments: 'logged_in' | 'visitor' | 'both'
             * 
             * @since 1.0.0
             */
            'user_state' => 'both',

            /**
             * Specifies list of capabilities to check for on the logged in user 
             * before loading the event.
             * 
             * The event will not be loaded (watched) if the current user does not 
             * have any of the specified capability
             * 
             * @since 1.0.0
             */
            'logged_in_user_caps' => [],

            /**
             * Specifies event IDs to watch
             * 
             * @since 1.0.0
             */
            'event_id__in' => [],

            /**
             * Specifies event IDs not to watch
             * 
             * @since 1.0.0
             */
            'event_id__not_in' => [],

            /**
             * Specifies event slugs to watch
             * 
             * @since 1.0.0
             */
            'event_slug__in' => [],

            /**
             * Specifies event slugs not to watch
             * 
             * @since 1.0.0
             */
            'event_slug__not_in' => [],
            
            /**
             * Specifies client IP addresses to watch
             * 
             * @since 1.0.0
             */
            'client_ip__in' => [],
            
            /**
             * Specifies client IP addresses not to watch
             * 
             * @since 1.0.0
             */
            'client_ip__not_in' => [],
            
            /**
             * Specifies page error codes (404, 403, etc.) to watch
             * 
             * @since 1.0.0
             */
            'page_error_code__in' => [],
            
            /**
             * Specifies page error codes not to watch
             * 
             * @since 1.0.0
             */
            'page_error_code__not_in' => [],
            
            /**
             * Specifies event severities to watch
             * 
             * @since 1.0.0
             */
            'event_severity__in' => [],

            /**
             * Specifies event severities not to watch
             * 
             * @since 1.0.0
             */
            'event_severity__not_in' => [],

            /**
             * Specifies weekdays to watch
             * 
             * @since 1.0.0
             */
            'weekdays__in' => [],

            /**
             * Specifies weekdays not to watch
             * 
             * @since 1.0.0
             */
            'weekdays__not_in' => [],

            /**
             * Controls users that should be monitored by the event watcher.
             * 
             * Specifies one or more capabilities to check for on user before 
             * loading the event.
             * 
             * @since 1.0.0
             */
            'user_caps__in' => [],

            /**
             * Does the opposite of 'caps__in' parameter.
             * Specifies user with capabilities not to watch.
             * 
             * @since 1.0.0
             */
            'user_caps__not_in' => [],

            /**
             * Specifies user roles to watch.
             * 
             * @since 1.0.0
             */
            'roles__in' => [],

            /**
             * Specifies user roles not to watch.
             * 
             * @since 1.0.0
             */
            'roles__not_in' => [],

            /**
             * Specifies term IDs to watch
             * 
             * @since 1.0.0
             */
            'term_id__in' => [],

            /**
             * Specifies term IDs not to watch.
             * 
             * @since 1.0.0
             */
            'term_id__not_in' => [],

            /**
             * Specifies term slugs to watch.
             * 
             * @since 1.0.0
             */
            'term_slug__in' => [],

            /**
             * Specifies term slugs not to watch.
             * 
             * @since 1.0.0
             */
            'term_slug__not_in' => [],

            /**
             * Specifies term names to watch.
             * 
             * @since 1.0.0
             */
            'term_name__in' => [],

            /**
             * Specifies term names not to watch.
             * 
             * @since 1.0.0
             */
            'term_name__not_in' => [],

            /**
             * Specifies taxonomies to watch.
             * 
             * @since 1.0.0
             */
            'taxonomy__in' => [],

            /**
             * Specifies taxonomies not to watch.
             * 
             * @since 1.0.0
             */
            'taxonomy__not_in' => [],

            /**
             * Specifies taxonomy IDs to watch.
             * 
             * @since 1.0.0
             */
            'taxonomy_id__in' => [],

            /**
             * Specifies taxonomy IDs not to watch.
             * 
             * @since 1.0.0
             */
            'taxonomy_id__not_in' => [],

            /**
             * Specifies post IDs to watch.
             * 
             * @since 1.0.0
             */
            'post_id__in' => [],

            /**
             * Specifies post IDs not to watch.
             * 
             * @since 1.0.0
             */
            'post_id__not_in' => [],

            /**
             * Specifies post IDs not to watch.
             * 
             * @since 1.0.0
             */
            'post_id__not_in' => [],

            /**
             * Specifies post names not to watch.
             * 
             * @since 1.0.0
             */
            'post_name__in' => [],

            /**
             * Specifies post names not to watch.
             * 
             * @since 1.0.0
             */
            'post_name__not_in' => [],

            /**
             * Specifies tag IDs to watch
             * 
             * @since 1.0.0
             */
            'tag_id__in' => [],

            /**
             * Specifies tag IDs not to watch
             * 
             * @since 1.0.0
             */
            'tag_id__not_in' => [],
            
            /**
             * Specifies tag slugs to watch
             * 
             * @since 1.0.0
             */
            'tag_slug__in' => [],
            
            /**
             * Specifies tag slugs not to watch
             * 
             * @since 1.0.0
             */
            'tag_slug__not_in' => [],
            
            /**
             * Specifies post parent to watch
             * 
             * @since 1.0.0
             */
            'post_parent__in' => [],
            
            /**
             * Specifies parent not to watch
             * 
             * @since 1.0.0
             */
            'post_parent__not_in' => [],

            /**
             * Specifies post statuses to watch
             * 
             * @since 1.0.0
             */
            'post_status__in' => [],

            /**
             * Specifies post statuses not to watch
             * 
             * @since 1.0.0
             */
            'post_status__not_in' => [],

            /**
             * Specifies post types to watch
             * 
             * @since 1.0.0
             */
            'post_type__in' => [],

            /**
             * Specifies post types to watch
             * 
             * @since 1.0.0
             */
            'post_type__not_in' => [],
            
            /**
             * Specifies attachment IDs to watch
             * 
             * @since 1.0.0
             */
            'attachment_id__in' => [],

            /**
             * Specifies attachment IDs not to watch
             * 
             * @since 1.0.0
             */
            'attachment_id__not_in' => [],

            /**
             * Specifies post attachment mime types to watch
             * 
             * @since 1.0.0
             */
            'post_mime_type__in' => [],

            /**
             * Specifies post attachment mime types not to watch
             * 
             * @since 1.0.0
             */
            'post_mime_type__not_in' => [],

            /**
             * Specifies comment IDs to watch
             * 
             * @since 1.0.0
             */
            'comment_id__in' => [],

            /**
             * Specifies comment IDs not to watch
             * 
             * @since 1.0.0
             */
            'comment_id__not_in' => [],

            /**
             * Specifies comment status to watch
             * 
             * @since 1.0.0
             */
            'comment_status__in' => [],

            /**
             * Specifies comment status not to watch
             * 
             * @since 1.0.0
             */
            'comment_status__not_in' => [],
            
            /**
             * Specifies author IDs to watch
             * 
             * @since 1.0.0
             */
            'author_id__in' => [],
            
            /**
             * Specifies author IDs not to watch
             * 
             * @since 1.0.0
             */
			'author_id__not_in' => [],
        ];

        if ( $this->is_multisite )
        {
            $this->event_conditional_args['site_id__in']       = [];
            $this->event_conditional_args['site_id__not_in']   = [];

            $this->event_conditional_args['site_name__in']     = [];
            $this->event_conditional_args['site_name__not_in'] = [];
        }
    }

    /**
     * Retrieve the event conditional argument from its method handler (helper).
     * 
     * @param string $method_name Specifies the method name. This is equivalent to the 
     *                            __METHOD__ constant.
     * 
     * @return string The event conditional argument name
     */
    protected function __getConditionArgName( $method_name )
    {
        $split_method_name = explode( '::', $method_name );
        if ( empty( $split_method_name ) )
            return '';

        $_method_name = end( $split_method_name );

        return preg_replace( '/^is_event_|_valid$/', '', $_method_name );
    }

    /**
     * Get the event conditional data
     * @see EventConditionalParser::__getConditionArgName()
     */
    protected function getEventConditionalData( $method_name, array $event = [] )
    {
        $helper = $this->__getConditionArgName( $method_name );
        if ( ! isset( $event[ $helper ] ) || empty( $event[ $helper ] ) ) 
            return [];

        return $event[ $helper ];
    }

    /**
     * Get list of event conditional arguments that can be run before event is fired.
     * This is used to auto register the event conditional helper method.
     * 
     * For example: if the event conditional argument is 'screen', then the helper will be
     * formatted like so: is_event_{screen}_valid
     * 
     * The format is is_event_{$conditional_arg}_valid
     */
    protected function preEventConditionalArgsHelper()
    {
        $args = [
            'screen',
            'pagenow',
            'can_enable',
            'user_state',
            'event_id__in',
            'event_id__not_in',
            'event_slug__in',
            'event_slug__not_in',
            'client_ip__in',
            'client_ip__not_in',
            'weekdays__in',
            'weekdays__not_in',
            'event_severity__in',
            'event_severity__not_in',
            'logged_in_user_caps',
        ];

        $helper = [];
        foreach ( $args as $arg ) {
            $helper[ $arg ] = "is_event_{$arg}_valid";
        }

        return $helper;
    }

    /**
     * Run the pre event conditional argument helper
     * 
     * @since 1.0.0
     * 
     * Note: when checking the '__in' condition, it is referred to as 'included'
     * Note: when checking the '__not_in' condition, it is referred to as 'excluded'
     * 
     * @param  string $event_id   Specifies the event ID
     * @param  string $event_name Specifies the event name (event action/filter hook)
     * @param  array  $event      Specifies the event arguments list
     * @param  array  $log_data   Specifies the event log data to be saved in the log table
     * 
     * @return bool True if the event conditional helper method is valid. Otherwise false.
     */
    protected function preIsEventValid( $event_id, $event_name, $event, $log_data = [] )
    {
        /**
         * All event conditional helpers used within this method don't have access 
         * to the event log data because the event is not active (not triggered).
         * So we have to provide usable default values for the log data
         */
        $log_data_defaults = [
            'user_id'    => $this->User->current_user_ID,
            'object_id'  => 0,
            'user_login' => $this->User->current_user_login,
        ];

        if ( $this->is_multisite ) {
            $log_data_defaults['blog_id']   = $this->current_blog_ID;
            $log_data_defaults['site_name'] = $this->getBlogName();
        }

        $_log_data        = array_merge( $log_data_defaults, $log_data );
        $_is_event_valid  = true;
        $conditional_args = $this->preEventConditionalArgsHelper();

        foreach ( $conditional_args as $filter_hook => $helper )
        {
            /**
             * Graciously bail out undefined helpers
             */
            if ( \method_exists( $this, $helper ) )
            {
                $is_event_valid = $this->$helper( $event_id, $event_name, $event, $_log_data );

                /**
                 * Filters the event conditional helper.
                 * The filter hook name is the same with the event conditional query parameter 
                 * as used during when the event is being registered.
                 * 
                 * It accepts 5 arguments.
                 * 
                 * @since 1.0.0
                 * 
                 * Example: event_id_in, event_id__not_in, etc.
                 * 
                 *  
                 * add_filter( 'event_id_in', 'callback_func', 10, 5 );
                 * 
                 * function callback_func( $is_event_valid, $event_id, $event_name, $event, $log_data )
                 * {
                 *     // Note: Returning false will disable the event
                 *     // Check some conditions here if needed
                 * 
                 *     return $is_event_valid;
                 * }
                 * 
                 * @param bool   $is_event_valid   Specifies whether the event should be disabled.
                 *                                 Setting it to False will disable the event.
                 * 
                 * @param string $event_id         Specifies the event ID
                 * 
                 * @param string $event_name       Specifies the event name (event action/filter hook)
                 * 
                 * @param array  $event            Specifies the event arguments list
                 * 
                 * @param  array  $log_data        Specifies the event log data to be saved in the log table
                 */
                $_is_event_valid = apply_filters(
                    $filter_hook,
                    $is_event_valid, $event_id, $event_name, $event, $_log_data
                );
            }
            else {
                /**
                 * Auto create a filter for the conditional helper that doesn't exists yet
                 */
                $_is_event_valid = apply_filters(
                    $filter_hook,
                    true, $event_id, $event_name, $event, $_log_data
                );
            }

            if ( ! $_is_event_valid ) 
                return false;
        } // foreach()

        return $_is_event_valid;
    }

    /**
     * Get the event object (user) ID
     * @return int The event object (user) ID
     */
    protected function getEventObjectUserId( $log_data )
    {
        return isset( $log_data['object_id'] ) ? $log_data['object_id'] : 0;
    }

    /**
     * Check if the event is excluded or ignorable (won't be logged)
     * 
     * @see \ALM\Controllers\Audit\Auditor::prepareLogData()
     * 
     * @param  string $event_id   Specifies the event ID
     * @param  string $event_name Specifies the event name (event action/filter hook)
     * @param  array  $event      Specifies the main event arguments list
     * @param  array  $log_data   Specifies the event log data to be saved in the log table
     * 
     * @return bool   True if the event should be ignored. Otherwise false.
     */
    protected function isEventIgnorable( $event_id, $event_name, $event, $log_data )
    {
        // List of event conditions that was pre-checked before loading the event
        $pre_event_conditional_args = $this->preEventConditionalArgsHelper();

        $_is_event_ignorable = false;
        foreach ( $this->event_conditional_args as $filter_hook => $empty_val )
        {
            // We have to ignore all the pre-event conditions since they've been evaluated
            if ( array_key_exists( $filter_hook, $pre_event_conditional_args ) ) 
                continue;
            
            $helper = "is_event_{$filter_hook}_valid";
            
            /**
             * Graciously bail out undefined helpers (conditional methods)
             */
            if ( \method_exists( $this, $helper ) )
            {
                /**
                 * Note: Within this event conditional helper method, we have to negate the 
                 * returned value. True becomes false and vice versa.
                 */
                $this->$helper($event_id, $event_name, $event, $log_data);
                $is_event_ignorable = ! $this->$helper( $event_id, $event_name, $event, $log_data );

                /**
                 * Filters the event conditional helper.
                 * @see EventConditionalParser::preIsEventValid()
                 */
                $_is_event_ignorable = apply_filters(
                    $filter_hook,
                    $is_event_ignorable, $event_id, $event_name, $event, $log_data
                );
            }
            else {
                /**
                 * Auto create a filter for the conditional helper that doesn't exists yet
                 */
                $_is_event_ignorable = apply_filters(
                    $filter_hook,
                    false, $event_id, $event_name, $event, $log_data
                );

            }

            // Bail the excluded or ignorable event once any condition is met
            if ( $_is_event_ignorable ) 
                return true;
        } // foreach()

        return $_is_event_ignorable;
    }

    /**
     * Check the specified user state
     * @see EventConditionalParser::preValidateEvent()
     */
    protected function is_event_user_state_valid( $event_id, $event_name, $event )
    {
        $user_state = $this->getEventConditionalData( __METHOD__, $event );
        if (empty($user_state) || !is_string($user_state)) 
            return false;

        $user_logged_in = is_user_logged_in();

        if ( 'logged_in' === $user_state ) {
            return $user_logged_in;
        }
        if ( 'both' != $user_state ) {
            return !$user_logged_in;
        }

        return true; // both
    }

    /**
     * Check whether the event slug/ID is excluded
     * @see EventConditionalParser::preValidateEvent()
     */
    protected function is_event_event_id__not_in_valid( $event_id, $event_name, $event )
    {
        return ! $this->isEventIdExcluded( $event_id, $event_name, $event );
    }

    /**
     * Check whether the event is disabled
     */
    protected function is_event_can_enable_valid( $event_id, $event_name, $event )
    {
        return true === $this->getEventConditionalData(__METHOD__, $event);
    }

    /**
     * Check whether the event can be loaded on the current screen
     * 
     * @see EventConditionalParser::preValidateEvent()
     * 
     * @since 1.0.0
     */
    protected function is_event_screen_valid( $event_id, $event_name, $event )
    {
        /**
         * Bail for ajax request
         */
        if (wp_doing_ajax() || wp_doing_cron()) 
            return true;

        $found     = [];
        $screens   = $this->getEventConditionalData( __METHOD__, $event );
        $site_type = '';

        // Bail out if screen is not defined
        if (empty($screens))  return true;

        foreach ( $screens as $screen )
        {
            switch ( $screen )
            {
                case 'admin':
                    $found[] = (int) $this->is_admin;
                    break;

                case 'user':
                    $found[] = (int) $this->is_user_admin;
                    break;
            
                case 'main_site':
                case 'mainsite':
                    $found[] = (int) $this->is_main_site;
                    break;
                    
                case 'network':
                    $found[] = (int) $this->is_network_admin;

                case 'multisite':
                    $found[]   = (int) $this->is_multisite;
                    $site_type = 'multisite';
                    break;

                case 'not-multisite':
                case 'not_multisite':
                    $found[]   = (int) !$this->is_multisite;
                    $site_type = 'not-multisite';
                    break;

                case 'public':
                    $found[] = (int) ( !$this->is_admin && !$this->is_network_admin );
                    break;
            }
        }

        if (in_array( 1, $found, true )) {
            if (!empty($site_type)) {
                if ('multisite' === $site_type && !$this->is_multisite)
                    return false;

                if ('not-multisite' === $site_type && $this->is_multisite)
                    return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Check whether the event can be loaded on the current page
     * 
     * @see EventConditionalParser::preValidateEvent()
     * 
     * @since 1.0.0
     */
    protected function is_event_pagenow_valid($event_id, $event_name, $event)
    {
        /**
         * Bail for ajax request
         */
        if (wp_doing_ajax() || wp_doing_cron())
            return true;

        $pages = (array) $this->getEventConditionalData(__METHOD__, $event);

        if (empty($pages)) return true;
        return in_array($this->pagenow, $pages, true);
    }

    /**
     * Check whether the logged in user has any of the specified capability 
     * before loading the event
     * 
     * @see EventConditionalParser::preValidateEvent()
     * 
     * @since 1.0.0
     */
    protected function is_event_logged_in_user_caps_valid( $event_id, $event_name, $event, $log_data )
    {
        $caps = (array) $this->getEventConditionalData( __METHOD__, $event );
        if (empty($caps)) return true;

        $user_id = $this->User->current_user_ID;
        
        // User is not logged in
        if ( 0 >= $user_id ) 
            return false;
        
        foreach ( $caps as $cap )
        {
            if ( is_string( $cap ) 
            && $this->User->canPerformAction( $cap, $user_id ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether the current user has a certain capability before loading 
     * or watching the event
     * 
     * @see EventConditionalParser::preValidateEvent()
     * @see EventConditionalParser::isEventIgnorable()
     * 
     * Note: We are deliberately refusing to bail out super admins on multisite 
     * administrator on single site. Reason is because we want to allow dynamic 
     * control over internal behavior such as creating custom capability using 
     * {@see map_meta_cap}
     */
    protected function is_event_user_caps__in_valid( $event_id, $event_name, $event, $log_data )
    {
        $caps = (array) $this->getEventConditionalData( __METHOD__, $event );
        if (empty($caps)) return true;

        $user_id = $this->getEventObjectUserId( $log_data );
        
        // User is not logged in or event object (user) is not present
        if ( 0 >= $user_id ) 
        return false; 
        
        foreach ( $caps as $cap )
        {
            if ( is_string( $cap ) 
            && $this->User->canPerformAction( $cap, $user_id ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does the opposite of {@see EventConditionalParser::is_event_user_caps__in_valid()}
     */
    protected function is_event_user_caps__not_in_valid( $event_id, $event_name, $event, $log_data )
    {
        $caps = (array) $this->getEventConditionalData( __METHOD__, $event );
        if (empty($caps)) return true;

        $user_id = $this->getEventObjectUserId( $log_data );
        
        // Ignore check if user is not logged in or event object (user) is not present
        if ( 0 >= $user_id ) 
            return true;

        foreach ( $caps as $cap )
        {
            if ( is_string( $cap ) 
            && $this->User->canPerformAction( $cap, $user_id ) ) {
                return false;
            }
        }
        return true;
    }
}