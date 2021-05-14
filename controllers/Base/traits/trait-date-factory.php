<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Date Factory Template for the Plugin Factory Controller
 * @see   \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait DateFactory
{
    /**
     * Specifies the load process total time in seconds
     * 
     * This property is useful only when the PluginFactory::getLoadProcessEndTime() 
     * has been called
     * 
     * @var float
     * @since 1.0.0
     */
    protected $_load_process_total_time = 0;

    /**
     * @todo - get the plugin date from settings table
     * Get the plugin time format
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getPluginTimeFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the WordPress load process start time
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getLoadProcessStartTime()
    {
        global $timestart;
        if ( empty( $timestart ) )
            return '';

        return @gmdate( $this->getPluginTimeFormat(), $timestart );
    }

    /**
     * Get the WordPress load process end time
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getLoadProcessEndTime()
    {
        global $timeend;

        // Update the load process end time variable 
        $this->_load_process_total_time = timer_stop();

        if ( empty( $timeend ) )
            return '';

        return @gmdate( $this->getPluginTimeFormat(), $timeend );
    }

    /**
     * @todo - finish this date formatter
     * Get the days equivalent from any given date, date format or timestamp
     * 
     * @since 1.0.0
     * 
     * @param string $format Specifies the date, datetime format or timestamp
     * 
     * @param bool  $future  Specifies whether to subtract from or add to the current date.
     *                       True will will add to the current date.
     *                       False will subtract from the current date.
     * 
     * @return int           The day(s) equivalent in the given date
     */
    public function getDateFormatInDays( $date, $future = false )
    {
        $days  = 0;
        $sign  = $future ? '+' : '-';
        $_date = strtolower( $date );

        switch ( $_date )
        {
            case 'last week':
                $days = 7;
                break;
            
            case 'next week':
                $days = 7;
                break;
            
            default:
                # code...
                break;
        }

        return $days;
    }

    /**
     * Get a date time by a given format.
     * Note: The format must be parsable by the PHP date() function
     * 
     * @since 1.0.0
     * 
     * @see date()
     * 
     * @param string  $date   Specifies the date to retrieve in the given format
     * 
     * @param string  $format Specifies the format to use in creating the date
     * 
     * @return string         The date in the given format. Empty string is returned 
     *                        when the format is not parsable.
     */
    public function getDateByFormat( $date = 'now', $format = '' )
    {
        if ( empty( $date ) ) 
            return '';

        $_format = empty( $format ) ? 'Y-m-d H:i:s' : $format;

        $d = new \DateTime( $date );
        return $d->format( $_format );
    }

    /**
     * Get the date time with microseconds
     * 
     * @since 1.0.0
     * 
     * @param  bool   $microseconds  Specifies whether to return the datetime with microseconds
     * @return string                The formatted datetime.
     */
    public function getDate( $microseconds = true )
    {
        $format = $microseconds ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        return $this->getDateByFormat( 'now', $format );
    }

    /**
     * @todo - Specifies the plugin time format
     * 
     * @since 1.0.0
     * 
     * @see PluginFactory::getDateByFormat()
     * 
     * Get the date time in the plugin format
     * 
     * @param  string Specifies the date to retrieve in the selected plugin datetime format
     * @return string The formatted datetime
     */
    public function getDataInPluginFormat( $datetime = 'now' )
    { 
        return $this->getDateByFormat( $datetime, $this->getPluginTimeFormat() );
    }
}