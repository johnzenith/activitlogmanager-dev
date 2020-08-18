<?php
namespace ALM\Controllers\Audit;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * Class: Auditor
 * @since 1.0.0
 */

use \ALM\Controllers\Audit\Templates as ALM_EventTemplates;

class Auditor extends \ALM\Controllers\Base\PluginFactory implements \SplSubject
{
    /**
     * Add the Event List template
     */
    use ALM_EventTemplates\EventList,
        ALM_EventTemplates\EventHandlers;

    /**
     * @var \SplObjectStorage
     * @since 1.0.0
     */
    private $_observers;

    /**
     * Audit Observer Object
     * @var object
     * @since 1.0.0
     */
    protected $AuditObserver;

    /**
     * Event ID (Event Code)
     * @var int
     * @since 1.0.0
     */
    protected $event_ID = 0;

    /**
     * Event data for setting up action and filter hook.
     * @var string
     * @since 1.0.0
     */
    protected $event_hook     = '';
    protected $event_slug     = '';
    protected $event_callback = null;
    protected $event_priority = '';
    protected $event_num_args = '';

    /**
     * Auditable event list
     * @var array
     * @since 1.0.0
     */
    protected $auditable_event_list = [];

    /**
     * Audit log data
     * @var array
     * @since 1.0.0
     */
    protected $log_data     = [];
    protected $log_data_raw = [];

    /**
     * Audit log data type format
     * @var array
     * @since 1.0.0
     */
    protected $log_data_format = [];

    /**
     * Specifies whether we need to update the active event log data
     * or just create a new one
     * @var bool
     * @since 1.0.0
     */
    protected $is_active_log_updatable = false;

    /**
     * Specifies the active event error log data for the event successor
     * @var object|array
     * @since 1.0.0
     */
    protected $active_event_error_log_data = false;

    /**
     * Setup the Auditor
     */
    public function __runSetup()
    {
        // Create the \SplObjectStorage() object instance
        $this->_observers = new \SplObjectStorage();

        // Setup the plugin factory data
        $this->init();

        // Setup the database factory
        $this->DB->setup($this->wpdb);

        // Setup the event list
        $this->__setupEventList();
        $this->auditable_event_list = $this->getEventsList();

        // Setup the Audit Observer
        $this->AuditObserver->init($this);

        // Log all failed events
        $this->_logFailedEvents();

        // var_dump($this->wpdb->prefix);exit;

        add_action('init', function()
        {
        // echo '<pre>';
        // global $wp_roles;
        // print_r($wp_roles->role_names);
        // $caps = get_user_meta(8, $this->wpdb->prefix . 'capabilities', true);
        // $user_data = get_userdata(4);
        // $user_data->add_cap('edit_posts');
        // $user_data->add_role('blogger');
        // $user_data->remove_role('author');
        // $user_data->set_role('editor'); 
        // // print_r( $caps);
        // print_r( $user_data->get_role_caps());
        // echo '<hr>';
        // print_r( $user_data->roles );
        // echo '<hr>';
        // print_r( $user_data->caps );
        // echo '<hr>';
        // var_dump( $user_data->role );

        // $this->appendUpdatedUserProfileData('test_field1', [
        //     'new' => 'first term',
        //     'previous' => 'last term',
            
        // ]);
        // var_dump( $this->__aggregateUserMetaFields() );
        
        //     update_user_meta($this->User->current_user_ID, 'alm_ms_dashboard_quick_press_last_post_id', 4);

        // var_dump($this->maybe_trigger_failed_events);

        // var_dump($this->network_data);
        // wp_die();
        });
    }

    /**
     * Run when the Auditor has been setup completely
     */
    public function __runAfterSetup()
    {
        // Attach the Audit Observer
        $this->attach( $this->AuditObserver );

        /**
         * Run the Audit Object Storage.
         * Start Auditing - log activities and send notifications
         */
        $this->notify();
    }

    /**
     * Attach any available observer (SplObserver)
     */
    public function attach(\SplObserver $observer)
    {
        $this->_observers->attach($observer);
    }

    /**
     * Detach any attached observer (SplObserver)
     */
    public function detach(\SplObserver $observer)
    {
        $this->_observers->attach($observer);
    }

    /**
     * Register the auditable events and watch activities as they happen
     */
    public function notify()
    {
        /**
         * @var \SplObserver $observer
         */
        foreach ($this->_observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * Setup the event arguments
     * @param array $event_args Specifies list of event arguments 
     */
    public function setupObserver(array $event_args)
    {
        $this->prepareEvent($event_args);
        return $this;
    }

    /**
     * Load all auditable events
     * @return array The registered auditable events
     */
    public function loadEvents()
    {
        // echo '<pre>';
        // $data = $this->DB
        //     ->select([ 'id', 'blog_id', 'option_name', 'option_value', 'created_at', 'updated_at' ])
        //     ->from( $this->tables->settings, 'settings' )
        //     ->where( 'id', 'id', '=', true )
        //     ->and()
        //     ->dateRange( 'updated_at', '-7 day', 'now' )
        //     ->orderBy( 'id' )
        //     ->limit( 011 )
        //     ->getResults();

        //     echo $this->DB->last_query;
        //     echo '<hr>';

        // print_r( $data );
        // exit;

        return $this->auditable_event_list;
    }

    /**
     * Get the event hooks
     */
    public function getEventHooks()
    {
        return ['action', 'filter', 'callback',];
    }

    /**
     * Prepare the audit log event data
     * @see Auditor::loadEvents()
     * @see Auditor::setupObserver()
     */
    protected function prepareEvent(array $event_args = [])
    {
        $this->event_ID       = $event_args['event_id'];
        $this->event_slug     = $event_args['event'];
        $this->event_hook     = $event_args['event_handler']['hook'];
        $this->event_callback = $event_args['event_handler']['callback'];
        $this->event_priority = $event_args['event_handler']['priority'];
        $this->event_num_args = $event_args['event_handler']['num_args'];

        // Build the audit event callback
        $this->maybeBuildAuditableEvents();

        return $this;
    }

    /**
     * Build the audit event callback
     * @see \ALM\Controllers\Audit\Templates\EventHandlers
     */
    protected function maybeBuildAuditableEvents()
    {
        // Only build the event callback if callback is set to null 
        if (!is_null($this->event_callback)) return;

        // Before building, check whether the current event has a registered callback handler
        $event_namespace = $this->event_slug . '_event';
        if (method_exists($this, $event_namespace)) {
            $this->event_callback = [$this, $event_namespace];
        }
    }

    /**
     * Add the Event. This could be a WordPress Action or Filter Hook.
     */
    public function addEvent()
    {
        // WordPress Action or Filter Hook, or any customized callback
        if ( is_callable($this->event_callback, true) )
        {
            /**
             * If the event callback is a normal PHP function or class method,
             * let's just ignore it silently. It will be triggered automatically. 
             */
            if ('callback' != $this->event_hook) {
                $event_hook = 'add' . ucfirst($this->event_hook);
                $this->$event_hook();
            }
        }
    }

    /**
     * Add Action Hook Event
     * @see add_action()
     */
    protected function addAction()
    {
        add_action($this->event_slug, $this->event_callback, $this->event_priority, $this->event_num_args);
    }

    /**
     * Add Filter Hook Event
     * @see add_filter()
     */
    protected function addFilter()
    {
        add_filter($this->event_slug, $this->event_callback, $this->event_priority, $this->event_num_args);
    }

    /**
     * @todo
     * Check whether or not the active loggable event can trigger notification
     * 
     * @see \ALM\Controllers\Audit\Templates\EventList::LogActiveEvent()
     * 
     * @return bool True if the logged active event can send notification. Otherwise false.
     */
    protected function isActiveEventNotifiable()
    {
        return false;
    }

    /**
     * Get the active event prepared data
     * 
     * Notice the usage of isset(), we have to avoid undefined index at all cost!
     * 
     * @param string $name Specifies the active event data to get
     * @param string $arg  Specifies the array key to retrieve value for if data is an array
     * 
     * @param mixed The event data on success.
     */
    protected function _getActiveEventData($name, $arg = '')
    {
        $use_active_event_alt = isset($this->active_event_alt[$name]);
        if (!isset($this->active_event[$name]) && !$use_active_event_alt)
            return '';

        $data = $use_active_event_alt ?
            $this->active_event_alt[$name] : $this->active_event[$name];

        if (!empty($arg) && is_scalar($arg)) {
            return (isset($data[$arg])) ? $data[$arg] : '';
        }

        return $data;
    }

    /**
     * Get the user event data
     * 
     * @since 1.0.0
     * 
     * @param  int   $user_id           Specifies the user ID whose event data should be returned
     * @param  bool  $user_description  Specifies whether to add user description data or not
     * 
     * @return array                    Returns the user data for the active event.
     */
    protected function _getActiveEventUserData($user_id = 0, $user_description = true)
    {
        $_user_id = (int) $user_id;
        if ( 0 === $_user_id )
            $_user_id = $this->User->current_user_ID;

        $role_labels = $this->User->getUserRoleLabels($this->User->getUserRoles($_user_id));
        $user_roles  = $this->parseValueForDb($role_labels, 1);
            
        $user_data = [
            'user_url'      => $this->User->getUserInfo(0, 'user_url', 'url'),
            'last_name'     => $this->User->getUserInfo(0, 'last_name', false),
            'avatar_url'    => get_avatar_url( $_user_id ),
            'user_roles'    => $user_roles,
            'first_name'    => $this->User->getUserInfo(0, 'first_name', false),
            'user_status'   => $this->User->getUserInfo(0, 'user_status', 'int'),
            'user_login'    => $this->User->getUserInfo(0, 'user_login', 'username'),
            'display_name'  => $this->User->getUserInfo(0, 'display_name'),
            'email_address' => $this->User->getUserInfo(0, 'user_email', 'email'),
            'user_nicename' => $this->User->getUserInfo(0, 'user_nicename', false),
        ];

        if ($user_description)
            $user_data['description'] = $this->User->getUserInfo(0, 'description');

        return $user_data;
    }

    /**
     * Prepare the audit event log data
     * 
     * Important: When in update mode, that is performing event log update such as:
     * failed login attempts, too many incorrect password reset requests, etc.,
     * This method will only focus on those fields that needs to be updated.
     * 
     * @see Auditor::_saveLog()
     * 
     * @see 'alm/event/log/update/selected_fields' filter documented in 
     * \ALM\Controllers\Audit\Templates::EventList
     * 
     * @since 1.0.0
     */
    protected function prepareLogData()
    {
        if (empty($this->active_event))
            return;

        // var_dump( $this->eventMsgArgExists( 'user', '' ) );
        // var_dump( explode( '|||', $this->generateEventMessageForDb( 'user', $this->_getActiveEventData('message') ) ) );
        // wp_die();

        $event_group = $this->_getActiveEventData('group');

        // Use the current user ID from the event message data if set
        $event_msg_current_user_id = (int) $this->getEventMsgArg($event_group, '_current_user_id', 0);

        if ($event_msg_current_user_id > 0) {
            $current_user_id = $event_msg_current_user_id;
        } else {
            $current_user_id = $this->User->getCurrentUserId();
        }

        // Object ID: this can either be a user ID, post ID, comment ID, meta_id, etc.
        $object_id = $this->getEventMsgArg($event_group, 'object_id', 0);

        /**
         * Refresh the current user data
         */
        $this->User->refreshCurrentUserData($current_user_id);

        $_user_id = $this->User->getUserInfo(0, 'ID', 'int');

        /**
         * This specifies whether or not the active event data can be updated 
         * rather than creating a new one (insert a new row)
         */
        $this->is_active_log_updatable = $this->isActiveEventLogIncrementValid(
            $_user_id,
            $object_id
        );

        $referer_url          = $this->canLogReferer() ? $this->getReferer() : '';
        $device_data          = $this->getClientDeviceData();
        $event_object         = $this->_getActiveEventData('object');
        $event_data_separator = $this->getEventMetadataSeparatorChar();

        /**
         * There's no need to re-run all the log data setup process
         */
        if (!$this->is_active_log_updatable) {
            $_client_ip = $this->getTopLevelIp();

            // A WP_Error object is never expected to be returned,
            // but we still have to check to catch malformed IP addresses
            if (is_wp_error($_client_ip)) {
                $client_ip = $_client_ip->get_error_message();
            } else {
                $client_ip = $_client_ip;
            }

            $this->log_data['user_id']       = $_user_id;
            $this->log_data['event_id']      = $this->active_event_ID;
            $this->log_data['event_slug']    = $this->active_event_slug;
            $this->log_data['severity']      = $this->_getActiveEventData('severity');
            $this->log_data['source_ip']     = $client_ip;
            $this->log_data['object_id']     = $object_id;
            $this->log_data['user_login']    = $this->User->getUserInfo(0, 'user_login', 'username');
            $this->log_data['event_group']   = $event_group;
            $this->log_data['event_title']   = $this->_getActiveEventData('title');
            $this->log_data['referer_url']   = $referer_url;
            $this->log_data['event_object']  = $event_object;
            $this->log_data['event_action']  = $this->_getActiveEventData('action');

            $this->log_data['last_name']     = $this->User->getUserInfo(0, 'last_name', false);
            $this->log_data['first_name']    = $this->User->getUserInfo(0, 'first_name', false);

            $this->log_data['browser']       = $device_data['browser'];
            $this->log_data['platform']      = $device_data['platform'];
            $this->log_data['is_robot']      = $device_data['is_robot'];
            $this->log_data['is_mobile']     = $device_data['is_mobile'];

            if ($this->is_multisite) {
                $this->log_data['blog_id']   = get_current_blog_id();
                $this->log_data['blog_url']  = $this->getBlogUrl(true);
                $this->log_data['blog_name'] = $this->getBlogName(true, true);
            }

            // Event revision
            $this->log_data['new_content']      = $this->getEventMsgArg($event_group, 'new_content');
            $this->log_data['previous_content'] = $this->getEventMsgArg($event_group, 'previous_content');

            if (empty($this->log_data['new_content']))
                $this->log_data['new_content'] = $this->_getActiveEventData('_new_content');

            if (empty($this->log_data['previous_content']))
                $this->log_data['previous_content'] = $this->_getActiveEventData('_previous_content');
        }

        $this->log_data['message'] = $this->generateEventMessageForDb(
            $event_group,
            ((array) $this->_getActiveEventData('message')),
            $this->active_event
        );

        /**
         * User data
         * 
         * For event data integrity, we don't want scenario where a change to the user data will
         * affect the logged event data. If this happens, then the logged data will definitely 
         * mean something entirely different from the time it was logged.
         * 
         * For example:
         * 
         * The plugin editors can determine what data to display in the event message column,
         * if an event is logged about a user changing their first name, display name, or 
         * any user related data, we must logged those changes. Though the changed data is 
         * always logged, but if a message field is disabled from being displayed and it is not 
         * saved (logged) at the time of the event, then whenever that specific event data is 
         * requested, regardless of whether it has been logged or not, we need to make sure that 
         * we are referencing the original event data at the time the event was logged. 
         * So even if the user data is modified after the logged event, the changes doesn't 
         * affect the logged event data.
         */
        $this->log_data['user_data'] = $this->_getActiveEventUserData(0, false);

        /**
         * Object metadata
         * 
         * Refresh the event user target data
         */
        $object_data = [];

        if ('user' == $event_object)
        {
            if ($object_id != $current_user_id)
                $this->User->refreshCurrentUserData($object_id);

            if ($object_id > 0) {
                $object_data = $this->_getActiveEventUserData();
            }
            // Use the user object if available
            else {
                $user_obj = $this->getEventMsgArg($event_group, 'user_obj', null);
                if (is_object($user_obj)) {
                    unset($user_obj->comment_shortcuts);
                    $object_data = (array) $user_obj;
                }
            }
        }
        elseif ('taxonomy' == $event_object) {
            if (taxonomy_exists($event_object)) {
            }
        }
        elseif ('term' == $event_object) {
        }
        elseif ('widget' == $event_object) {
        }
        elseif ('theme' == $event_object) {
        }
        elseif ('plugin' == $event_object) {
            $object_data = $this->current_plugin_data;
        }
        elseif ('custom_field' == $event_object) {
        }
        // post, page, revision, wp_block, user_request, custom_css, etc.
        elseif (
            'post_type' == $event_object
            || post_type_exists($event_object)
        ) {
            // Get the post type name
            $post_type_name = get_post_type($object_id);

            if (!empty($post_type_name)) {
                // Update the event object
                $this->log_data['event_object'] = $post_type_name;
            }
        }
        else {
        }

        $this->log_data['object_data'] = $object_data;

        /**
         * Event metadata
         */
        $event_metadata = [
            'user_agent'                     => $device_data['user_agent'],
            'browser_version'                => $device_data['browser_version'],
            'platform_version'               => $device_data['platform_version'],
            'platform_is_64_bit'             => $device_data['platform_is_64_bit'],
            'platform_version_name'          => $device_data['platform_version_name'],

            'client_ips'                     => $this->joinValues(
                $this->getClientIpList(),
                $event_data_separator
            ),

            'post_data'                      => $_POST,
            'remote_port'                    => $this->getServerVar($_SERVER['REMOTE_PORT']),
            'server_port'                    => $this->getServerVar($_SERVER['SERVER_PORT']),
            'request_uri'                    => $this->getServerVar($_SERVER['REQUEST_URI'], true),
            'query_string'                   => $this->getServerVar($_SERVER['QUERY_STRING'], true),
            'server_address'                 => $this->getServerVar($_SERVER['SERVER_ADDR']),
            'request_scheme'                 => sanitize_key($this->getServerVar('REQUEST_SCHEME')),
            'request_method'                 => $this->getRequestMethod(),
            'script_filename'                => $this->getServerVar($_SERVER['SCRIPT_FILENAME']),
            'server_protocol'                => $this->getServerVar($_SERVER['SERVER_PROTOCOL']),
            'request_time_float'             => (float) ($this->getServerVar('REQUEST_TIME_FLOAT')),
            'load_process_end_time'          => $this->getLoadProcessEndTime(),
            'load_process_start_time'        => $this->getLoadProcessStartTime(),
            'load_process_total_time'        => $this->_load_process_total_time,
            'http_upgrade_insecure_requests' => $this->getServerVar('HTTP_UPGRADE_INSECURE_REQUESTS'),
        ];

        /**
         * The referer url may not be the same as the first one,
         * let's keep a reference
         */
        if ($this->is_active_log_updatable && $this->canLogReferer())
            $event_metadata['referer_url'] = $referer_url;

        $this->log_data['metadata'] = $event_metadata;

        /**
         * Log date
         */
        if (!$this->is_active_log_updatable)
            $this->log_data['created_at'] = $this->getDate();

        /**
         * If we are not going to insert a new log record in the log table,
         * then we have to update some existing fields
         */
        if ($this->is_active_log_updatable) {
            // Increment the log counter
            $log_counter                   = $this->getActiveEventLogCounterIncrement();
            $this->log_data['log_counter'] = $log_counter;

            // Last updated date
            $this->log_data['updated_at'] = $this->getDate();

            // Update the event message 'failed_attempts=1' field
            $attempts                  = "failed_attempts=$log_counter";
            $event_msg                 = $this->log_data['message'];
            $this->log_data['message'] = preg_replace('/failed_attempts\=1/', $attempts, $event_msg);
        }

        /**
         * Here is our event conditional flag check point.
         * We have to ignore all excluded event entities
         */
        $is_event_ignorable = $this->isEventIgnorable(
            $this->active_event_ID,
            $this->active_event_slug,
            $this->active_event,
            $this->log_data
        );

        /**
         * As a final paradigm, let's allow the event ignorable state to be filtered
         * 
         * Filter: alm/event/log/ignorable
         * 
         * @since 1.0.0
         * 
         * @param  bool   $ignorable  Specifies whether the event log should be ignored
         * 
         * @param  string $event_id   Specifies the event ID
         * 
         * @param  string $event_name Specifies the event name (event action/filter hook)
         * 
         * @param  array  $event_data Specifies the main event arguments list
         * 
         * @param  array  $log_data   Specifies the event log data to be saved in the log table.
         *                            Note: all the event log data may not be available during 
         *                            event log update.
         */
        $_is_event_ignorable = apply_filters(
            'alm/event/log/ignorable',
            $is_event_ignorable,
            $this->active_event_ID,
            $this->active_event_slug,
            $this->active_event,
            $this->log_data
        );

        // Clear the log data
        if ($_is_event_ignorable) {
            $this->log_data = [];
            return;
        }

        // Keep the untransformed log data
        $this->log_data_raw = $this->log_data;

        /**
         * Filters the log data before array values are transformed to string 
         * 
         * @since 1.0.0
         * 
         * Passed two arguments:
         * 
         * @param array   $log_data Specifies the log data for the active event
         * @param Auditor $auditor  The Auditor object. Passed by reference.
         */
        $this->log_data = apply_filters_ref_array(
            'alm/event/log/data',
            [$this->log_data, &$this]
        );

        /**
         * Transform all non-scalar log data to string
         */
        if (!$this->is_active_log_updatable) {
            $this->log_data['user_data'] = $this->joinValues(
                $this->log_data['user_data'],
                $event_data_separator
            );
            $this->log_data['object_data'] = $this->joinValues(
                $this->log_data['object_data'],
                $event_data_separator
            );
            $this->log_data['metadata'] = $this->joinValues(
                $this->log_data['metadata'],
                $event_data_separator
            );
        }
        /**
         * We have to aggregate existing log data
         */
        else {
            $existing_message = $this->sanitizeOption(
                $this->getVar($this->active_event_error_log_data, 'message', '')
            );

            $existing_metadata = $this->sanitizeOption(
                $this->getVar($this->active_event_error_log_data, 'metadata', '')
            );

            $existing_user_data = $this->sanitizeOption(
                $this->getVar($this->active_event_error_log_data, 'user_data', '')
            );

            $existing_object_data = $this->sanitizeOption(
                $this->getVar($this->active_event_error_log_data, 'object_data', '')
            );

            $existing_message .= empty($existing_message) ?
                '' : $this->getEventLogUpdateIdentifier();

            $existing_metadata .= empty($existing_metadata) ?
                '' : $this->getEventLogUpdateIdentifier();

            $existing_user_data .= empty($existing_user_data) ?
                '' : $this->getEventLogUpdateIdentifier();

            $existing_object_data .= empty($existing_object_data) ?
                '' : $this->getEventLogUpdateIdentifier();

            /**
             * Merge new and existing data together
             */
            $this->log_data['message'] = $existing_message . $this->log_data['message'];

            $this->log_data['user_data'] = $existing_user_data . $this->joinValues(
                $this->log_data['user_data'],
                $event_data_separator
            );

            $this->log_data['object_data'] = $existing_object_data . $this->joinValues(
                $this->log_data['object_data'],
                $event_data_separator
            );

            $this->log_data['metadata'] = $existing_metadata . $this->joinValues(
                $this->log_data['metadata'],
                $event_data_separator
            );
        }

        /**
         * Setup up the log data format
         */
        $integer_fields = ['user_id', 'blog_id', 'event_id', 'object_id', 'is_robot', 'is_mobile', 'log_counter',];

        foreach ($this->log_data as $field => $value) {
            if (in_array($field, $integer_fields, true)) {
                $this->log_data_format[] = '%d';
            } else {
                $this->log_data_format[] = '%s';
            }
        }

        /**
         * Reset the user data
         */
        $this->User->refreshCurrentUserData();
    }

    /**
     * Log the active event data
     * @return bool True if the active event data was logged successfully. Otherwise false.
     */
    protected function _saveLog()
    {
        /**
         * Prepare the log data
         */
        $this->prepareLogData();

        if (empty($this->log_data)) {
            // Clear the active event loggable state to prevent abnormalities
            $this->is_active_event_loggable = false;
            return false;
        }

        if ($this->is_active_log_updatable) {
            $log_id   = (int) $this->getVar($this->active_event_error_log_data, 'log_id');

            $save_log = (bool) $this->wpdb->update(
                $this->tables->activity_logs,
                $this->log_data,
                ['log_id' => $log_id],
                $this->log_data_format
            );
        } else {
            $save_log = (bool) $this->wpdb->insert(
                $this->tables->activity_logs,
                $this->log_data,
                $this->log_data_format
            );
        }

        if (!$save_log) {
            /**
             * Fires after an attempt to save the log data for the active event failed
             * 
             * @since 1.0.0
             * 
             * Passed three arguments:
             * 
             * @param array   $log_data   Specifies the log data for the active event
             * @param array   $log_format Specifies the log data format
             * @param Auditor $auditor    The Auditor object
             */
            do_action_ref_array(
                'alm/event/log/failed',
                [$this->log_data, $this->log_format, &$this]
            );
            return false;
        }

        /**
         * Fires after the active event has been logged (saved) successfully
         * 
         * @since 1.0.0
         * 
         * Passed 3 arguments:
         * 
         * @param array   $log_data     Specifies the transformed log data for the active event
         * @param array   $log_data_raw Specifies the untransformed log data for the active event
         * @param Auditor $auditor      The Auditor object
         */
        do_action_ref_array(
            'alm/event/log/saved',
            [$this->log_data, $this->log_data_raw, &$this]
        );

        /**
         * Trigger notification for the event
         */
        if ($this->isActiveEventNotifiable())
            $this->Notification->trigger();

        /**
         * @todo
         * Run the audit log statistics update
         */

        // We have to clear the active event loggable state after successful logging
        $this->is_active_event_loggable = false;

        return $save_log;
    }

    /**
     * Wrapper for auto saving the audit log
     * @see \Stack_Auth\Controllers\Audit\Auditor::_saveLog()
     */
    final public function Log()
    {
        return $this->_saveLog();
    }
}
