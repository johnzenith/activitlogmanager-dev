<?php
namespace ALM\Models\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Models
 * @subpackage  Database Query Data
 * @since       1.0.0
 * 
 * This template requires the {@see \ALM\Models\Template\DatabaseMetaData} 
 * template as well
 */

trait DatabaseQueryMetaData
{
    /**
     * Check whether or not the last executed query is okay.
     * 
     * @since 1.0.0
     * 
     * @return bool True if last query was executed without any error. Otherwise false.
     */
    protected function isLastQueryOK()
    {
        return empty($this->wpdb->last_error);
    }

    /**
     * Get the last insert ID
     * 
     * @since 1.0.0
     * 
     * @return int
     */
    public function getLastInsertId()
    {
        if (!empty( $this->wpdb->insert_id )) 
            return (int) $this->wpdb->insert_id;

        return 0;
    }

    /**
     * Check whether or not the last executed query result is empty.
     * 
     * @since 1.0.0
     * 
     * @param  string  $query_type  Specifies the query type to perform the check for:
     *                              |SELECT|INSERT|UPDATE|DELETE|UPDATE|ALTER|CREATE|DROP|
     *                               REPLACE|TRUNCATE|
     * 
     * @return bool                 False if last query result is empty. Otherwise true.
     */
    protected function isLastQueryResultEmpty( $query_type = 'select' )
    {
        $query_type = strtolower( $query_type );

        $rows_affected_list = [ 'create', 'alter', 'truncate', 'drop', 'insert', 'delete', 'update', 'replace', ];

        if ( in_array( $query_type, $rows_affected_list, true ) )
        {
            $rows_affected = (int) $this->rows_affected;
            return ( 0 >= $rows_affected );
        }
        else {
            $num_rows = (int) $this->wpdb->num_rows;
            return ( 0 >= $num_rows );
        }
    }
}