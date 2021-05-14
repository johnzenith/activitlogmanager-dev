<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Category Event Handlers
 * @since   1.0.0
 */

trait CategoryEvents
{
    /**
     * Fires after a term has been saved, and the term cache has been cleared.
     * @see wp_insert_term()
     */
    public function saved_term_event($term_id, $tt_id, $taxonomy, $update)
    {
        
    }

    /**
     * Saved term helper
     */
    private function _savedTermEventHelper($term_id, $tt_id, $taxonomy, $update)
    {
        switch($taxonomy)
        {
            
        }
    }
}