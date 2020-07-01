<?php
namespace ALM\Controllers\Audit;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Class: Auditor
 * @since 1.0.0
 */

class Auditor extends \ALM\Controllers\Base\PluginFactory implements \SplSubject
{
    /**
     * Add the AuditableEventsList template
     */
    use \ALM\Controllers\Audit\Templates\EventList;

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
     * Holds all auditable event names and corresponding Audit (Log) IDs
     * @var array
     * @see \ALM\Controllers\Audit\Templates\AuditableEventsList
     */
    protected $audit_Id_list = [];

    /**
     * Audit Log Argument List
     * @var array
     * @see \ALM\Controllers\Audit\Templates\AuditableEventsList
     */
    protected $audit_args = [];

    /**
     * Audit ID (Error Code)
     * @var int
     */
    protected $audit_ID = 0;

    /**
     * Event data for setting up action and filter hook.
     */
    protected $event_hook     = '';
    protected $event_name     = '';
    protected $event_callback = null;
    protected $event_priority = '';
    protected $event_num_args = '';

    /**
     * Auditable event list
     * @var array
     */
    protected $auditable_event_list = [];

    /**
     * Audit log data
     * @var array
     */
    protected $log_data = [];

    /**
     * Setup the Auditor
     */
    public function __runSetup()
    {
        // Will create a \SplObjectStorage() object instance
        $this->_observers = new \SplObjectStorage();

        // Setup the Audit Observer
        $this->AuditObserver->init( $this );
    }

    /**
     * Run when the Auditor has been setup completely
     */
    public function __runAfterSetup()
    {
        // Setup the event list
        $this->__setupEventList();

        $this->auditable_event_list = $this->getEventsList();

        // Attach the Audit Observer
        $this->attach( $this->AuditObserver );

        /**
         * Run the Audit Object Storage.
         * Start Auditing - save activities and send notifications
         */
        $this->notify();
    }

    /**
     * Attach any available observer (SplObserver)
     */
    public function attach( \SplObserver $observer )
    {
        $this->_observers->attach( $observer );
    }

    /**
     * Detach any attached observer (SplObserver)
     */
    public function detach( \SplObserver $observer )
    {
        $this->_observers->attach( $observer );
    }

    /**
     * Register the auditable events and watch activities as they happen
     */
    public function notify()
    {
        /**
         * @var \SplObserver $observer
         */
        foreach ( $this->_observers as $observer ) {
            $observer->update( $this );
        }
    }

    /**
     * Build the Audit ID list
     */
    public function buildIDList()
    {
        $this->audit_Id_list[ $this->event_name ] = $this->audit_ID;
    }

    /**
     * Setup the audit log arguments
     */
    public function setupObserver( array $audit_args )
    {
        $this->audit_args = $audit_args;
        $this->prepareEvent();

        return $this;
    }

    /**
     * Load all auditable events
     * @return array The registered auditable events
     */
    public function loadEvents()
    {
        return $this->auditable_event_list;
    }

    /**
     * Get an auditable event data
     * 
     * @param   int|string  Specifies the Log ID or event name
     * 
     * @return  array       Returns an array containing the auditable event data.
     *                      Returns an empty array if the audit ID or event name is not found, 
     *                      or when the $audit_ID is neither a string or an integer value.
     */
    public function getAuditableEvent( $audit_ID ) 
    {
        if ( ! is_int( $audit_ID ) || ! is_string( $audit_ID ) )
        {
            throw new \Exception( sprintf( 
                alm__( 'Audit ID (%s) is invalid: must be an integer or string. Type given is %s.' ),
                esc_html( 'audit_ID' ),
                gettype( $audit_ID )
            ) );
        }

        $audit_data = isset( $this->auditable_event_list[ $audit_ID ] ) ? 
            $this->auditable_event_list[ $audit_ID ] : [];

        if ( empty( $audit_data ) )
        {
            // Try getting the audit data using the event name
            $_audit_ID = isset( $this->audit_Id_list[ $audit_ID ] ) ? 
                $this->audit_Id_list[ $audit_ID ] : '';

            $audit_data = ( ! empty( $_audit_ID ) ) ? 
                ( 
                    isset( $this->auditable_event_list[ $_audit_ID ] ) ? 
                        $this->auditable_event_list[ $_audit_ID ] : []
                ) 
                : 
                [];
        }

        return $audit_data;
    }

    /**
     * Get the audit ID by using the event name
     * 
     * @param  string  Specifies the audit event name
     * @return int     The audit ID if found. Otherwise 0 is returned.
     */
    public function eventNameToAuditID( $event_name )
    {
        $audit_ID = isset( $this->audit_Id_list[ $event_name ] ) ? 
            $this->audit_Id_list[ $event_name ] : 0;

        return (int) $audit_ID;
    }

    /**
     * Get the audit event name by using the audit ID
     * 
     * @param  int     Specifies the audit event name
     * @return string  The audit event name
     */
    public function auditIDToEventName( $audit_ID ) 
    {
        $event_names = array_flip( $this->audit_Id_list );
        
        return isset( $event_names[ $audit_ID ] ) ? $event_names[ $audit_ID ] : '';
    }

    /**
     * Get the event hooks
     */
    public function getEventHooks() 
    {
        return [ 'action', 'filter' ];
    }

    /**
     * Prepare the audit log event data
     * @see Auditor::loadEvents()
     */
    protected function prepareEvent()
    {
        $this->audit_ID       = $this->audit_args['audit_ID']   ?? 0;
        $this->event_hook     = $this->audit_args['hook']       ?? '';
        $this->event_name     = $this->audit_args['event']      ?? '';
        $this->event_callback = $this->audit_args['callback']   ?? null;
        $this->event_priority = $this->audit_args['priority']   ?? '';
        $this->event_num_args = $this->audit_args['num_args']   ?? '';

        // Build the Audit ID List
        $this->buildIDList();

        // Build the audit event callback
        $this->buildAuditableEvent();

        return $this;
    }

    /**
     * Build the audit event callback
     * @see \ALM\Controllers\Audit\Templates\EventCallbacks
     */
    protected function buildAuditableEvent()
    {
        // Only build the event callback if callback is set to null 
        if ( ! is_null( $this->event_callback ) ) return false;

        // Set the event hook namespace handle
        $event_type = ucfirst( $this->event_hook ) . 's';

        // Check whether the current event has a registered callback
        if ( method_exists( "\ALM\Controllers\Audit\Templates\EventCallbacks", $this->event_name ) )
        {
            $this->event_callback = [
                "\ALM\Controllers\Audit\Templates\EventCallbacks",
                $this->event_name
            ];
        }
    }

    /**
     * Add Audit Event. This could is a WordPress Action or Filter Hook.
     */
    public function addEvent()
    {
        if ( ! $this->isEventValid() ) return false;

        // WordPress Action or Filter Hook
        if ( in_array( $this->event_hook, $this->getEventHooks(), true ) )
        {
            $event_hook = 'add' . ucfirst( $this->event_hook );

            $this->$event_hook(
                $this->event_name,
                $this->event_callback,
                $this->event_priority,
                $this->event_num_args
            );
        }
    }

    /**
     * Check whether a given event is valid.
     * @return bool True if the event is valid. Otherwise false.
     */
    protected function isEventValid() 
    {
        if ( empty( $this->event_name ) 
        || ( ! is_null( $this->event_callback ) && empty( $this->event_callback ) ) 
        || empty( $this->event_priority ) 
        || empty( $this->event_num_args ) )
        {
            return false;
        }
        return true;
    }

    /**
     * Add Action Hook Event
     * @see add_action()
     */
    protected function addAction( $action, $callback, $priority, $num_args )
    {
        add_action( $action, $callback, $priority, $num_args );
    }
    
    /**
     * Add Filter Hook Event
     * @see add_filter()
     */
    protected function addFilter( $filter, $callback, $priority, $num_args )
    {
        add_filter( $filter, $callback, $priority, $num_args );
    }

    /**
     * Prepare the audit event log data
     * @see Auditor::_saveLog()
     */
    protected function prepareLogData( $event, array $data = [] )
    {
        $audit_sid = $this->eventNameToAuditSID( $event );
        $client_ip = $this->getTopLevelIp();

        // Init the log data with the audit log initial setup data
        $log_data    = $this->getAuditableEvent( $audit_sid );
        $device_data = $this->getClientDeviceData();

        $this->log_data['blog_id']          = $this->blog_ID;
        $this->log_data['user_id']           = $this->user_ID;
        $this->log_data['message']           = $data['message'];
        $this->log_data['browser']           = $device_data['browser'];
        $this->log_data['severity']          = $log_data['severity'];
        $this->log_data['platform']          = $device_data['platform'];
        $this->log_data['is_robot']          = $device_data['is_robot'];
        $this->log_data['is_mobile']         = $device_data['is_mobile'];
        $this->log_data['source_ip']         = $client_ip;
        $this->log_data['audit_slug']        = $event;
        $this->log_data['referer_url']       = $this->getReferrer();
        $this->log_data['request_method']    = $this->getRequestMethod();
        $this->log_data['updated_content']   = '';
        $this->log_data['previous_content']  = '';

        // $this->setupLocationData();

        // Audit log Metadata
        $this->log_data['metadata'] = serialize([
            'user_agent'            => $device_data['user_agent'],
            'browser_version'       => $device_data['browser_version'],
            'platform_version'      => $device_data['platform_version'],
            'platform_is_64_bit'    => $device_data['platform_is_64_bit'],
            'platform_version_name' => $device_data['platform_version_name'],

            'request_scheme'        => sanitize_key( $_SERVER['REQUEST_SCHEME'] ?? '' ),
            'request_time_float'    => (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? 0 ),
            
            'query_string'          => sanitize_text_field( rawurlencode( wp_unslash( $_SERVER['QUERY_STRING'] ?? '' ) ) ),
        ]);

        // Merge the updated data
        $this->log_data = array_merge( $this->log_data, $data );
    }

    /**
     * Save the audit log activities
     * @param  string  $event   The audit log event to save
     * @param  array   $data    Specifies data to use for creating the audit log.
     * @return bool             True if the audit log is saved successfully. Otherwise false.
     */
    protected function _saveLog( $event, array $data = [] ) 
    {
        $this->prepareLogData( $event, $data );

        $save_log = (bool) $this->wpdb->insert(
            $this->tables->activity_logs,
            $this->log_data,
            $this->log_data_format
        );

        if ( $save_log ) {
            /**
             * Run the audit log statistics update
             */
        }

        return $save_log;
    }

    /**
     * Wrapper for auto saving the audit log
     * @see \Stack_Auth\Controllers\Audit\AuditManager::saveLog()
     */
    final public function Log( $event = '', array $data = [] ) 
    {
        if ( empty( $event ) ) return false;

        $default_log_data = [
            'message'          => '',
            'updated_content'  => '',
            'previous_content' => '',
        ];

        $data = array_merge( $default_log_data, $data );

        return $this->_saveLog( $event, $data );
    }
}
