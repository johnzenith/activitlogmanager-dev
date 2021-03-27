<?php
namespace ALM\Models\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Models
 * @subpackage  Activity Log Table SQL
 * @since       1.0.0
 * 
 * This template requires the {@see \ALM\Models\Template\DatabaseMetaData} 
 * template as well
 */

trait ActivityLogTableSql
{
    /**
     * Get the last activity log data
     * 
     * @param array $fields Specifies list of table columns to returned in the result set

     * @return mixed
     */
    protected function getLastActivityLogData( $fields = ['event_slug', 'event_id'] )
    {
        $this->DB
            ->reset()
            ->select($fields)
            ->from($this->tables->activity_logs)
            ->where()
            ->orderBy('log_id')
            ->isDesc()
            ->limit(1);

        return count($fields) > 1 ? $this->DB->getRow() : $this->DB->getVar();
    }
}