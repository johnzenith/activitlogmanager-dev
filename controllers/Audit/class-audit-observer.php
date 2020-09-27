<?php
namespace ALM\Controllers\Audit;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Audit Observer
 * @since   1.0.0
 */

class AuditObserver implements \SplObserver
{
    /**
     * @var Audit
     * This are the states for auditing
     */
    private $_audit = null;

    /**
     * @var \SplFixedArray
     */
    private $_events = null;

    /**
     * @var array
     */
    private $_event = null;

    /**
     * Auditor
     * @var object
     */
    protected $Auditor = null;

    /**
     * An empty constructor.
     */
    public function __construct() {}

    /**
     * Setup the audit observer.
     * This should be called by the ALM\Controllers\Audit\Auditor controller
     */
    public function init( \ALM\Controllers\Audit\Auditor $Auditor )
    {
        $this->Auditor = $Auditor;

        $events = $this->Auditor->loadEvents();
        if (empty( $events )) return;

        $this->_events = \SplFixedArray::fromArray($events, false);
    }

    /**
     * This is called by the subject, usually \SplSubject::notify()
     * @param \SplSubject $subject
     */
    public function update( \SplSubject $subject )
    {
        /**
         * @todo
         * Inspect the {@see $this->_audit} subject handle whether we need to ignore it
         */
        $this->_audit = clone $subject;
        $this->registerEvents();
    }

    /**
     * Register event listeners for collecting logs
     */
    public function registerEvents()
    {
        if (!$this->_events instanceof \SplFixedArray) return;
        
        do 
        {
            $this->prepareEventData($this->_events->current())->watch();
            $this->_events->next();
        } 
        while ($this->_events->valid());
    }

    /**
     * Prepare Event Data
     * @param array $event
     * @see \ALM\Controllers\Audit\Auditor::loadEvents()
     */
    protected function prepareEventData( array $event )
    {
        $this->_event = $event;
        return $this;
    }

    /**
     * Check whether the event is watchable (not disabled)
     * @return bool True if event is watchable. Otherwise false.
     */
    protected function isEventWatchable()
    {
        $event_disabled = isset($this->_event['disable']) ? 
            ((bool) $this->_event['disable']) : false;

        return !$event_disabled;
    }

    /**
     * Watch the registered event
     */
    protected function watch()
    {
        if (!$this->isEventWatchable()) return;

        /**
         * Start watching the event
         */
        $this->Auditor->setupObserver($this->_event)->addEvent();
    }
}