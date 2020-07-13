<?php
namespace ALM\Models;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Database Factory
 * @since 1.0.0
 * 
 * This class requires the following templates:
 *  - {@see \ALM\Models\Template\DatabaseMetaData} 
 *  - {@see \ALM\Models\Template\DatabaseQueryMetaData}
 */

class DB_Factory
{
    /**
     * The WPDB ($wpdb) resource handle
     * @var object
     * @since 1.0.0
     */
    protected $wpdb = null;

    /**
     * Specifies the query result return type.
     * 
     * This is only applied when getting rows {@see DB_Factory::getRow()} 
     * or results {@see DB_Factory::getResults()}
     * 
     * @var string
     * @since 1.0.0
     */
    protected $return_type = OBJECT;

    /**
     * Specifies the generated sql query string
     * @var string
     * @since 1.0.0
     */
    protected $sql = '';

    /**
     * Specifies the query string before the query conditional filter starts
     * @var string
     * @since 1.0.0
     */
    protected $query_str = '';

    /**
     * Specifies the query placeholder values
     * @var array
     * @since 1.0.0
     */
    protected $values = [];

    /**
     * Holds the last executed sql query string
     * @var string
     * @since 1.0.0
     */
    protected $last_query = '';

    /**
     * Get the selected most recent selected fields in the query
     * @var string
     * @since 1.0.0
     */
    protected $selected_fields = '';

    /**
     * Get the top level selected fields in the query.
     * This is useful to retrieve the first sets of fields that was selected 
     * even when using a sub-query
     * 
     * @var string
     * @since 1.0.0
     */
    protected $top_selected_fields = '';

    /**
     * Holds the time difference data which is used to construct the MySQL 
     * TIMESTAMPDIFF() filter
     * @var array
     * @since 1.0.0
     */
    protected $time_stamp_diff_props = [];

    /**
     * Setup the WPDB database object
     * 
     * @since 1.0.0
     * 
     * @param WPDB $wpdb Specifies the WordPress Database object handle
     */
    public function setup( $wpdb )
    {
        $this->wpdb = $wpdb;
    }

    /**
     * Get magic method
     */
    public function __get( $name )
    {
        if ( property_exists( $this, $name ) ) 
            return $this->$name;

        return null;
    }

    /**
     * Set the query variables
     * 
     * @since 1.0.0
     * 
     * @param array $vars Specifies an array containing the query variable ame 
     * as array key and query variable value as array value.
     */
    public function setVar( array $vars )
    {
        $var_list = '';
        foreach ( $vars as $key => $value )
        {
            $key       = sanitize_key( $key );
            $value     = esc_sql( $value );
            $var_list .= "SET @$key = '$value';";
        }

        $this->wpdb->query( $var_list );
        return $this;
    }

    /**
     * Specifies a string to append to the query string
     * 
     * @since 1.0.0
     * 
     * @param string $str Specifies the string to add
     */
    public function addQueryStr( $str )
    {
        $this->query_str .= $str;
        return $this;
    }

    /**
     * Specifies the query result return type as object
     * 
     * @since 1.0.0
     */
    public function isResultObject()
    {
        $this->return_type = OBJECT;
        return $this;
    }

    /**
     * Specifies the query result return type as array (associative)
     * 
     * @since 1.0.0
     */
    public function isResultArray()
    {
        $this->return_type = ARRAY_A;
        return $this;
    }

    /**
     * Specifies the query result return type as array (numeric)
     * 
     * @since 1.0.0
     */
    public function isResultArray_N()
    {
        $this->return_type = ARRAY_N;
        return $this;
    }

    /**
     * Set the result order type to ascending (ASC)
     * 
     * @since 1.0.0
     */
    public function isAsc()
    {
        $this->query_str .= 'ASC ';
        return $this;
    }

    /**
     * Set the result order type to descending (DESC)
     * 
     * @since 1.0.0
     */
    public function isDesc()
    {
        $this->query_str .= 'DESC ';
        return $this;
    }

    /**
     * Add distinct flag to the query
     * 
     * @since 1.0.0
     */
    public function isDistinct()
    {
        $this->query_str .= 'DISTINCT ';
        return $this;
    }

    /**
     * Add the blog filter on multisite
     */
    public function isBlog( $blog_id = 0 )
    {
        if ( is_multisite() ) {
            $_blog_id         = absint( $blog_id );
            $this->values[]   = ( $_blog_id > 0 ) ? $_blog_id : get_current_blog_id();
            $this->query_str .= "AND blog_id = %d ";
        }

        return $this;
    }

    /**
     * Add the blog filter on multisite
     */
    public function isMainSite( $site_id = 0 )
    {
        if ( is_multisite() ) {
            $_site_id         = absint( $site_id );
            $this->values[]   = ( $_site_id > 0 ) ? $_site_id : get_main_site_id();
            $this->query_str .= "AND blog_id = %d ";
        }

        return $this;
    }

    /**
     * Specifies the fields to select in the query
     * 
     * @since 1.0.0
     * 
     * @param string|array Specifies the fields to select in the query
     */
    public function select( $fields )
    {
        $fields  = (array) $fields;
        $_fields = implode( ',', $fields );

        $this->query_str       .= "SELECT $_fields ";
        $this->selected_fields  = $_fields;

        if ( empty( $this->top_selected_fields ) ) 
            $this->top_selected_fields = $this->selected_fields;

        return $this;
    }
    
    /**
     * Specify the table to select from 
     * 
     * @since 1.0.0
     * 
     * @param string $table Specifies the table name to select from
     */
    public function from( $table, $alias = null )
    {
        $this->query_str .= "FROM $table" . ( empty( $alias ) ? '' : " AS $alias " );
        return $this;
    }

    /**
     * Select fields with alias using array format.
     * 
     * @since 1.0.0
     * 
     * @param array Specifies the fields list to select in the query.
     *              The table field is the array key and value the field alias
     */
    public function selectWithAlias( array $fields )
    {
        $select = '';
        foreach ( $fields as $field => $alias ) {
            $select .= " $field AS $alias,";
        }

        $this->query_str       .= 'SELECT ' . rtrim( ',', $select ) . ' ';
        $this->selected_fields  = $select . ' ';

        if ( empty( $this->top_selected_fields ) ) 
            $this->top_selected_fields = $this->selected_fields;

        return $this;
    }

    /**
     * Count occurrence in a table.
     * 
     * @since 1.0.0
     * 
     * @param string $table If specified, then the table name will be initialized along with 
     *                      the {@see SELECT COUNT(*)} SQL statement
     */
    public function count( $table = '' )
    {
        $field                  = 'COUNT(*) ';
        $this->query_str       .="SELECT $field";
        $this->selected_fields  = $field;

        if ( empty( $this->top_selected_fields ) ) 
            $this->top_selected_fields = $this->selected_fields;

        if ( ! empty( $table ) )
            $this->from( $table );
            
        return $this;
    }

    /**
     * Get the selected fields in the query {@see $this->selected_fields}
     * @return string
     */
    public function getSelectFields()
    {
        return $this->selected_fields;
    }

    /**
     * Get the top level selected fields in the query {@see $this->top_selected_fields}
     * @return string
     */
    public function getTopSelectFields()
    {
        return $this->top_selected_fields;
    }

    /**
     * Query where clause filter.
     * 
     * @since 1.0.0
     * 
     * @param string $field          Specifies the table field to apply the condition on.
     *                               If empty, it uses a global check to create the where 
     *                               clause. That is, it sets the field and value to 1 
     *                               which transforms to (1=1)
     * 
     * @param mixed  $value          Specifies the value to filter the field with
     * 
     * @param string $operator       Specifies the operator to use in filtering the field
     * 
     * @param bool   $is_table_field Specifies whether the condition is between a real 
     *                               table field (column) to prevent wrapping the value 
     *                               ($value) in single quote
     */
    public function where( $field = '' , $value = null, $operator = '=', $is_table_field = false )
    {
        if ( empty( $field ) ) {
            $field    = 1;
            $value    = 1;
            $operator = '=';
        }

        $this->query_str .= is_null( $value ) ? 
            ' WHERE ' 
            : 
            ( 
                $is_table_field ? 
                    " WHERE $field $operator $value " : " WHERE $field $operator %s "
            );

        if ( ! $is_table_field && ! is_null( $value ) ) {
            $this->values[] = $value;
        }
        
        return $this;
    }

    /**
     * Table Field condition filter.
     * 
     * This is used for relating two table fields (columns) together
     * 
     * @since 1.0.0
     * 
     * @param string $field1    Specifies the first table field
     * 
     * @param string $field2    Specifies the second table field
     * 
     * @param string $operator  Specifies the operator to use to relate  
     *                          $field1 and $field2 together
     */
    public function relateField( $field1, $field2, $operator = '' )
    {
        $this->query_str .= "$field1 $operator $field2 ";
        return $this;
    }

    /**
     * Query filter helper.
     * 
     * @since 1.0.0
     * 
     * @param string $field           Specifies the field to apply the condition on
     * @param mixed  $value           Specifies the value to filter the field with
     * @param string $operator        Specifies the operator to use in filtering the field
     * @param bool   $use_placeholder Specifies whether to prevent using a placeholder to set the value
     * @param string $type            Specifies the query filter type: (AND, OR, etc.)
     */
    protected function _filterHelper( $field, $value, $operator = '=', $use_placeholder = true, $type = 'AND' )
    {
        $value = sanitize_text_field( $value );
        
        if ( ! $use_placeholder ) {
            $helper = "$type $field $operator $value ";
        } else {
            $helper         = "$type $field $operator %s ";
            $this->values[] = $value;
        }

        return $helper;
    }

    /**
     * Add the query filter AND clause
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_filterHelper()
     */
    public function and( $field = '', $value = '', $operator = '=', $use_placeholder = true )
    {
        if ( empty( $field ) ) {
            $this->query_str .= 'AND ';
        } else {
            $this->query_str .= $this->_filterHelper( $field, $value, $operator, $use_placeholder, 'AND' );
        }
        return $this;
    }

    /**
     * Add the query filter OR clause
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_filterHelper()
     */
    public function or( $field = '', $value = '', $operator = '=', $use_placeholder = true )
    {
        if ( empty( $field ) ) {
            $this->query_str .= 'OR ';
        } else {
            $this->query_str .= $this->_filterHelper( $field, $value, $operator, $use_placeholder, 'OR' );
        }
        return $this;
    }

    /**
     * GROUP BY clause
     * 
     * @since 1.0.0
     * 
     * @param string|array $field specifies the field to group the query result with
     */
    public function groupBy( $field )
    {
        $field  = (array) $field;
        $_field = implode( ',', $field );

        $this->query_str .= "GROUP BY $_field ";
        return $this;
    }

    /**
     * ORDER BY clause
     * 
     * @since 1.0.0
     * 
     * @param string|array $field specifies the field to order the query result with
     */
    public function orderBy( $field )
    {
        $field  = (array) $field;
        $_field = implode( ',', $field );

        $this->query_str .= "ORDER BY $_field ";
        return $this;
    }

    /**
     * HAVING query filter
     * 
     * @since 1.0.0
     * 
     * @param string $aggregate_function Specifies the aggregate function to use in 
     *                                   creating the conditional filter
     * 
     * @param mixed  $value              Specifies the value to check for
     * 
     * @param string $operator           Specifies the operator to use in relating the 
     *                                   aggregate function and given value
     */
    public function having( $aggregate_function, $value, $operator = '>' )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= "$aggregate_function $operator %s ";
        return $this;
    }

    /**
     * EXISTS clause.
     * 
     * This is used to start the exists query filter clause which uses an 
     * open parenthesis as the starting point.
     * 
     * Note: The {@see DB_Factory::endQueryFilterGroup()} should be called 
     * after the sub-query to close the open parenthesis
     * 
     * @since 1.0.0
     */
    public function exists()
    {
        $this->query_str .= "EXISTS ( ";
        return $this;
    }

    /**
     * UNION clause
     * 
     * @since 1.0.0
     */
    public function union()
    {
        $this->query_str .= "UNION ";
        return $this;
    }

    /**
     * UNION ALL clause
     * 
     * @since 1.0.0
     */
    public function unionAll()
    {
        $this->query_str .= "UNION ALL";
        return $this;
    }

    /**
     * Set the query limit
     * 
     * @since 1.0.0
     * 
     * @param int $min Specifies the offset from where to start fetching the result
     * @param int $max Specifies the maximum result to return from the given offset
     */
    public function limit( $min, $max = null )
    {
        $min   = (int) $min;
        $max   = is_null( $max ) ? $max : (int) $max;

        $limit = "LIMIT $min";

        if ( ! is_null( $max ) ) 
            $limit .= ", $max";

        $this->query_str .= $limit;

        return $this;
    }

    /**
     * Filter the result using the LIKE conditional keyword
     * 
     * Important: The values must not be escaped.
     * 
     * @since 1.0.0
     * 
     * @param string $field           Specifies the table field to apply the condition on.
     * @param string $value           Specifies the value to use in filtering the field data
     * @param bool   $use_placeholder Specifies whether to prevent using a placeholder to set the value
     * @param bool   $not_like        Specifies whether to negate the like query
     */
    protected function _likeHelper( $field, $value, $use_placeholder = true, $not_like = false )
    {
        $field = preg_replace( '/[^\w]/', '', $field );

        // $value = str_replace(
        //     [ '%', '_', '*', '[', ']', '-', '#' ],
        //     [ '\%', '\_', '\*', '\[', '\]', '\-', '\#' ],
        //     sanitize_text_field( $value )
        // );

        $value = sanitize_text_field( $value );
        
        if ( ! $use_placeholder ) {
            $like = $not_like ? 
            "$field NOT LIKE $value " : "$field LIKE $value ";
        }
        else {
            $like = $not_like ? 
            "$field NOT LIKE %s " : "$field LIKE %s ";
            
            $this->values[] = $this->wpdb->esc_like( $value );
        }

        return $like;
    }

    /**
     * Wrapper for the like query filter
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_likeHelper()
     */
    public function like(  $field, $value, $use_placeholder = true )
    {
        $this->query_str .= $this->_likeHelper( $field, $value, $use_placeholder );
        return $this;
    }

    /**
     * Wrapper for the like query filter with the NOT operator
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_likeHelper()
     */
    public function notLike(  $field, $value, $use_placeholder )
    {
        $this->query_str .= $this->_likeHelper( $field, $value, $use_placeholder, true );

        return $this;
    }

    /**
     * The quey IN() clause
     * 
     * @since 1.0.0
     * 
     * @param string $field Specifies the table field to check the values on
     * @param array $values Specifies list of values to check on the table field
     */
    public function in( $field, array $values )
    {
        if ( empty( $values ) ) 
            return;

        $in_clause = " $field IN( ";

        foreach ( $values as $v )
        {
            $in_clause      .= '%s,';
            $this->values[] = sanitize_text_field( $v );
        }

        $in_clause  = rtrim( $in_clause, ',' );
        $in_clause .= ') ';

        $this->query_str .= $in_clause;

        return $this;
    }

    /**
     * Starts the query group filter.
     * 
     * Basically, this just adds an open parenthesis before the next query filter 
     * so that the condition can be grouped as one
     * 
     * @since 1.0
     */
    public function startQueryFilterGroup()
    {
        $this->query_str .= '(';
        return $this;
    }

    /**
     * End the query group filter.
     * 
     * This wil add a closing parenthesis after the last query filter 
     * so that the condition can be grouped as one from the point where the 
     * last {@see DB_Factory::startQueryFilterGroup()} was called.
     * 
     * @since 1.0
     */
    public function endQueryFilterGroup()
    {
        $this->query_str .= ') ';
        return $this;
    }

    /**
     * Add the query filter BETWEEN clause
     * 
     * @since 1.0.0
     * 
     * @param string $field    Specifies the field to apply the condition on
     * 
     * @param array  $ranges   Specifies an array containing the min and max range
     * 
     * @param bool   $raw      Specifies whether the ranges contains an aggregate function so 
     *                         that the values won't be passed as placeholders
     * 
     * @param bool   $not      Specifies whether to negate the filter with the NOT Operator
     */
    public function _betweenHelper( $field, array $ranges, $raw = false, $not = false )
    {
        $min = 0;
        $max = 0;

        if ( isset( $ranges[0] ) ) 
            $min = $ranges[0];

        if ( isset( $ranges['min'] ) ) 
            $min = $ranges['min'];

        if ( isset( $ranges[1] ) ) 
            $max = $ranges[1];

        if ( isset( $ranges['max'] ) ) 
            $max = $ranges['max'];

        $min      = sanitize_text_field( $min );
        $max      = sanitize_text_field( $max );
            
        $between  = $field;
        $between .= $not ? ' NOT' : '';
        $between .= $raw ? " BETWEEN $min AND $max " : " BETWEEN %s AND %s ";

        if ( ! $raw ) {
            $this->values[] = $min;
            $this->values[] = $max;
        }

        return $between;
    }

    /**
     * BETWEEN query filter
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_betweenHelper()
     */
    public function between( $field, array $ranges, $raw = false )
    {
        $this->query_str .= $this->_betweenHelper( $field, $ranges, $raw );
        return $this;
    }

    /**
     * NOT BETWEEN query filter
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_betweenHelper()
     */
    public function notBetween( $field, array $ranges, $raw = false )
    {
        $this->query_str .= $this->_betweenHelper( $field, $ranges, $raw, true );
        return $this;
    }

    /**
     * NULL query filter helper
     * 
     * @since 1.0.0
     * 
     * @param string $field Specifies the field to apply the condition on
     * @param bool   $not   Specifies whether to negate the filter with the NOT Operator
     */
    protected function _isNullHelper( $field, $not = false )
    {
        $null_helper  = "$field IS ";
        $null_helper .= $not ? 'NOT ' : '';
        $null_helper .= 'NULL ';

        return $null_helper;
    }

    /**
     * IS NULL query filter 
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_isNullHelper()
     */
    public function isNull( $field )
    {
        $this->query_str .= $this->_isNullHelper( $field );
        return $this;
    }

    /**
     * IS NOT NULL query filter 
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_isNullHelper()
     */
    public function isNotNull( $field )
    {
        $this->query_str .= $this->_isNullHelper( $field, true );
        return $this;
    }

    /**
     * ANY clause
     * 
     * As expected, the {@see DB_Factory::endQueryFilterGroup()} should be called 
     * after the sub-query has ended
     * 
     * @since 1.0.0
     */
    public function any()
    {
        $this->query_str .= 'ANY ( ';
        return $this;
    }

    /**
     * ANY clause with WHERE()
     * 
     * As expected, the {@see DB_Factory::endQueryFilterGroup()} should be called 
     * after the sub-query has ended
     * 
     * @since 1.0.0
     * 
     * @param string $field    Specifies the field to apply the condition on
     * @param string $operator Specifies the operator to use in filtering the field
     */
    public function whereAny( $field, $operator )
    {
        $this->query_str .= "WHERE $field $operator ANY ( ";
        return $this;
    }

    /**
     * ALL clause
     * 
     * As expected, the {@see DB_Factory::endQueryFilterGroup()} should be called 
     * after the sub-query has ended
     * 
     * @since 1.0.0
     */
    public function all()
    {
        $this->query_str .= 'ALL ( ';
        return $this;
    }

    /**
     * ALL clause with WHERE()
     * 
     * As expected, the {@see DB_Factory::endQueryFilterGroup()} should be called 
     * after the sub-query has ended
     * 
     * @since 1.0.0
     * 
     * @param string $field    Specifies the field to apply the condition on
     * @param string $operator Specifies the operator to use in filtering the field
     */
    public function whereAll( $field, $operator )
    {
        $this->query_str .= "WHERE $field $operator ALL ( ";
        return $this;
    }

    /**
     * Starts the CASE clause
     * 
     * As expected, the {@see DB_Factory::endCase()} should be called 
     * to end the case condition
     * 
     * @since 1.0.0
     */
    public function case()
    {
        $this->query_str .= "CASE ";
        return $this;
    }

    /**
     * Case WHEN filter.
     * 
     * Used in conjunction with the {@see DB_Factory::case()}
     * 
     * @since 1.0.0
     * 
     * @param string $field    Specifies the field to apply the condition on
     * @param mixed  $value    Specifies the value to filter the field with
     * @param string $operator Specifies the operator to use in filtering the field
     */
    public function when( $field, $value, $operator )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= "WHEN $field $operator %s ";

        return $this;
    }

    /**
     * THEN clause filter
     * 
     * Used in conjunction with the {@see DB_Factory::case()}
     * 
     * @since 1.0.0
     * 
     * @param mixed  $value Specifies the value use assign to the condition 
     *                      if it evaluates to true
     */
    public function then( $value )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= 'THEN %s ';

        return $this;
    }

    /**
     * ELSE clause filter
     * 
     * @since 1.0.0
     */
    public function else( $value )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= 'THEN %s ';

        return $this;
    }

    /**
     * End the CASE() clause
     * 
     * @since 1.0.0
     * 
     * @param string $alias Specifies the alias to use at the end of the CASE clause
     */
    public function endCase( $alias = '' )
    {
        $this->query_str .= 'END ' . ( empty( $alias ) ? ') ' : "AS $alias ) " );
        return $this;
    }

    /**
     * Starts the IF() clause query filter
     * 
     * As expected, the {@see DB_Factory::endIf()} should be called 
     * to end the IF clause
     * 
     * @since 1.0.0
     */
    public function if()
    {
        $this->query_str .= "IF ( ";
        return $this;
    }

    /**
     * Specifies the truthy value for the {@see DB_Factory::if()}
     * 
     * @since 1.0.0
     */
    public function valueIfTrue( $value )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= '%s, ';
        return $this;
    }

    /**
     * Specifies the falsy value for the {@see DB_Factory::if()}
     * 
     * @since 1.0.0
     */
    public function valueIfFalse( $value )
    {
        $this->values[]   = sanitize_text_field( $value );
        $this->query_str .= '%s ';
        return $this;
    }
    
    /**
     * End the IF() clause
     * 
     * @since 1.0.0
     */
    public function endIf()
    {
        $this->endQueryFilterGroup();
        return $this;
    }

    /**
     * JOINS 
     * 
     * @since 1.0.0
     * 
     * @param string $table     Specifies the table to join into
     * 
     * @param array  $on_fields Specifies a list containing the two fields to use in 
     *                          relating the JOIN with th ON keyword
     * 
     * @param string $type      Specifies the type of JOIN to create
     */
    public function _joinHelper( $table, array $on_fields, $type = 'INNER' )
    {
        if ( count( $on_fields ) < 2 )
        {
            throw new \Exception( sprintf( 'The second argument (on_fields) must be an array containing the two fields to use in relating the %s', esc_html( $type ) ) );
            return;
        }

        $type   = strtoupper( $type );
        $fields = array_values( $on_fields );
        $field1 = $fields[0];
        $field2 = $fields[1];

        return "$type JOIN $table ON $field1 = $field2 ";
    }

    /**
     * INNER JOIN
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_joinHelper()
     */
    public function innerJoin( $table, array $on_fields )
    {
        $this->query_str .= $this->_joinHelper( $table, $on_fields );
        return $this;
    }

    /**
     * LEFT JOIN
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_joinHelper()
     */
    public function leftJoin( $table, array $on_fields )
    {
        $this->query_str .= $this->_joinHelper( $table, $on_fields, 'LEFT' );
        return $this;
    }
    
    /**
     * RIGHT JOIN
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_joinHelper()
     */
    public function rightJoin( $table, array $on_fields )
    {
        $this->query_str .= $this->_joinHelper( $table, $on_fields, 'RIGHT' );
        return $this;
    }

    /**
     * JOIN tables
     * 
     * @since 1.0.0
     * 
     * @see DB_Factory::_joinHelper()
     */
    public function join( $table, array $on_fields )
    {
        $this->query_str .= $this->_joinHelper( $table, $on_fields );
        return $this;
    }

    /**
     * DateRange
     * 
     * Fetch data from the plugin table by specifying the minimum and maximum date
     * 
     * @since 1.0.0
     * 
     * Note: For best result, the minimum and maximum date value should be specified.
     * 
     * @param string $field    Specifies the table field to relate the date query with.
     *                         A real date time value can also be passed.
     * 
     * @param string $min_date Specifies the date to start fetching the data from
     * 
     * @param string $max_date Specifies the end date to stop fetching
     * 
     * @param bool   $raw      Specifies whether the ranges contains an aggregate function so 
     *                         that the values won't be passed as placeholders
     */
    public function dateRange( $field, $min_date, $max_date = '', $raw = false )
    {
        $min_date   = sanitize_text_field( $min_date );
        $max_date   = empty( $max_date ) ? '' : sanitize_text_field( $max_date );
        $date_query = '';

        if ( ! $raw )
        {
            /**
             * Maybe we need to parse the date to what MySQL understands
             */
            if ( @strtotime( $min_date ) ) {
                $d1       = new \DateTime( $min_date );
                $min_date = $d1->format('Y-m-d H:i:s.u');
            }

            if ( ! empty( $max_date ) && @strtotime( $max_date ) ) {
                $d2       = new \DateTime( $max_date );
                $max_date = $d2->format('Y-m-d H:i:s.u');
            }
        }

        /**
         * We don't want to use the MySQL DATE() function, it's inefficient because 
         * it doesn't make good use of an index
         */
        if ( empty( $max_date ) )
        {
            if ( ! $raw ) {
                // We need the $min_date placeholders twice
                $this->values[] = $min_date;
                $this->values[] = $min_date;
                $min_date       = '%s';
            }

            $date_query = "$field >= $min_date AND $field < $min_date + INTERVAL 1 DAY ";
        }
        else {
            // $date_query = "$field >= $min_date AND $field <= $max_date ";
            $this->between( $field, [ $min_date, $max_date ], $raw );
        }

        $this->query_str .= $date_query;
        return $this;
    }

    /**
     * TIMESTAMPDIFF() date function
     * 
     * @since 1.0.0
     * 
     * Important: The {@see DB_Factory::addTimeStampFilter()} method must be called 
     * after this method in other to apply the filter
     * 
     * @param string $field     Specifies the table field to relate the date query with.
     *                          A real date time value can also be passed.
     * 
     * @param string $unit      Specifies the unit to get the difference in.
     *                          FRAC_SECOND (microseconds), SECOND, MINUTE, HOUR, 
     *                          DAY, WEEK, MONTH, YEAR. The QUARTER unit is never used.
     * 
     * @param string $from_date Specifies the start date to get the difference from
     * 
     * @param string $to_date   Specifies the end date to get the difference to
     */
    public function timeStampDiff( $field, $unit, $from_date, $to_date = '' )
    {
        $alias = $field . '_alias';
        $_unit = strtoupper( $unit );

        if ( 'QUARTER' == $_unit )
            return $this;

        $to_date   = empty( $to_date ) ? 'now' : $to_date;
        $time_diff = "TIMESTAMPDIFF( $_unit, $field, NOW() ) as $alias ";

        // This will be used to create the BETWEEN clause
        $this->time_stamp_diff_props = [
            'unit'      => $_unit,
            'alias'     => $alias,
            'to_date'   => $to_date,
            'from_date' => $from_date,
        ];

        $this->_query_str .= $time_diff;
        return $this;
    }

    /**
     * Add the time stamp difference filter.
     * 
     * @since 1.0.0
     * 
     * Important: This method() must be called if the {@see DB_Factory::timeStampDiff()} 
     * method is used.
     */
    public function applyTimeStampFilter()
    {
        if ( ! is_array( $this->time_stamp_diff_props ) 
        || count( $this->time_stamp_diff_props ) < 4 ) 
            return $this;

        $now            = time();
        $unit           = $this->time_stamp_diff_props['unit'];
        $to_date        = $this->time_stamp_diff_props['to_date'];
        $from_date      = $this->time_stamp_diff_props['from_date'];
        $field_alias    = $this->time_stamp_diff_props['alias'];
        
        $to_date_obj    = new \DateTime( $to_date );
        $from_date_obj  = new \DateTime( $from_date );
        

        $format         = ( 'FRAC_SECOND' == $unit ) ? 'U.u' : 'U';
        $to_date_secs   = $to_date_obj->format( $format )   - $now;
        $from_date_secs = $from_date_obj->format( $format ) - $now;

        switch ( $unit )
        {
            case 'SECOND':
            case 'FRAC_SECOND':
                $_to_date   = $to_date_secs;
                $_from_date = $from_date_secs;
                break;

            case 'MINUTE':
                $_to_date   = $to_date_secs   / MINUTE_IN_SECONDS;
                $_from_date = $from_date_secs / MINUTE_IN_SECONDS;
                break;

            case 'HOUR':
                $_to_date   = $to_date_secs   / HOUR_IN_SECONDS;
                $_from_date = $from_date_secs / HOUR_IN_SECONDS;
                break;

            case 'DAY':
                $_to_date   = $to_date_secs   / DAY_IN_SECONDS;
                $_from_date = $from_date_secs / DAY_IN_SECONDS;
                break;

            case 'WEEK':
                $_to_date   = $to_date_secs   / WEEK_IN_SECONDS;
                $_from_date = $from_date_secs / WEEK_IN_SECONDS;
                break;

            case 'MONTH':
                $_to_date   = $to_date_secs   / ALM_MONTH_IN_SECONDS;
                $_from_date = $from_date_secs / ALM_MONTH_IN_SECONDS;
                break;

            case 'YEAR':
                $_to_date   = $to_date_secs   / ALM_YEAR_IN_SECONDS;
                $_from_date = $from_date_secs / ALM_YEAR_IN_SECONDS;
                break;
            
            default:
                // This part of the code may never be reached, but just in case
                return $this;
        }

        $this->values[]   = $_to_date;
        $this->values[]   = $_from_date;

        $this->query_str .= "$field_alias BETWEEN %s AND %s ";
        return $this;
    }

    /**
     * MATCH() clause filter
     * 
     * @since 1.0.0
     * 
     * @param string|array $field  Specifies the table field to relate the fulltext search with.
     * @param mixed        $keyword Specifies the search keyword
     */
    public function match( $fields, $keyword = '', $mode = '' )
    {
        $_keyword = $this->_applySearchKeywordHelper( $keyword );

        // Ignore if the search keyword is less than 3 characters in length
        if ( strlen( $_keyword ) < 3 ) 
            return $this;

        $_mode = str_replace( ' ', '-', strtolower( $mode ) );
        
        switch ( $_mode )
        {
            case 'boolean':
            case 'boolean-mode':
                $search_mode = "IN BOOLEAN MODE";
                break;
            
            default:
                $search_mode = "IN NATURAL LANGUAGE MODE";
                break;
        }

        $_fields          = implode( ',', (array) $fields );
        $search_term      = "%s $search_mode";

        $this->values[]   = $_keyword;
        $this->query_str .= "MATCH ( $_fields ) AGAINST ( $search_term ) ";

        return $this;
    }

    /**
     * Sanitize the search keyword
     * 
     * @since 1.0.0
     * 
     * @param $keyword Specifies the search keyword to sanitize for use in MATCH() and 
     * AGAINST() clause
     * 
     * @return string
     */
    protected function _applySearchKeywordHelper( $keyword )
    {
        /**
         * @todo
         * We need to escape the keyword in cases where more than one special 
         * characters exists
         */
        $special_chars = [ '*', '-', '>', '<', '~', '(', ')', '"' ];

        $_keyword = wp_strip_all_tags( sanitize_text_field( $keyword ), true );
        $_keyword = preg_replace( '/\;\%\`\^/', '', $_keyword );

        $escaper = function() use ( &$special_chars, &$keyword )
        {
            $escaped_keywords = '';
            foreach ( (array) str_split( $keyword, 1 ) as $k )
            {
                if ( in_array( $k, $special_chars, true ) ) {
                    $escaped_keywords .= "\\$k";
                } else {
                    $escaped_keywords .= $k;
                }
            }
            return $escaped_keywords;
        };

        return $escaper();
    }

    /**
     * Prepare the sql query
     * 
     * @see WPDB->prepare()
     * 
     * @since 1.0.0
     */
    protected function prepare()
    {
        if ( empty( $this->values ) ) 
            return $this->query_str;

        return $this->wpdb->prepare( $this->query_str, $this->values );
    }

    /**
     * Reset the query string
     * 
     * @since 1.0.0
     */
    public function reset()
    {
        $this->values    = [];
        $this->query_str = '';
        return $this;
    }

    /**
     * Execute the generated sql query string
     * 
     * @since 1.0.0
     */
    protected function __execute()
    {
        /**
         * Check if WPDB object is setup
         */
        if ( empty( $this->wpdb ) ) 
            throw new \Exception('The WPDB object must be setup before executing any query');

        // Keep reference to the last executed query
        $this->last_query = $this->query_str;
    }

    /**
     * @see WPDB->get_var()
     * 
     * @since 1.0.0
     */
    public function getVar()
    {
        $this->__execute();
        return esc_attr( $this->wpdb->get_var( $this->prepare() ) );
    }

    /**
     * @see WPDB->get_row()
     * 
     * @since 1.0.0
     */
    public function getRow()
    {
        $this->__execute();
        return $this->wpdb->get_row( $this->prepare(), $this->return_type );
    }

    /**
     * @see WPDB->get_col()
     * 
     * @since 1.0.0
     */
    public function getCol()
    {
        $this->__execute();
        return $this->wpdb->get_col( $this->prepare() );
    }

    /**
     * @see WPDB->get_results()
     * 
     * @since 1.0.0
     */
    public function getResults()
    {
        $this->__execute();
        return $this->wpdb->get_results( $this->prepare(), $this->return_type );
    }
}