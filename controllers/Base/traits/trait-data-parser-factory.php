<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Data Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait DataParserFactory
{
    /**
     * Parse data for DB.
     * Properly transform an array/object data before it is saved in database.
     * 
     * Note: In 'display' context, a 'view more' button will be added if the 
     * given array/object is large.
     * 
     * @since 1.0.0
     * 
     * @see PluginFactory::isLargeArray()
     * 
     * @param  array $data       Specifies the array data
     * 
     * @param  int   $deep_level Specifies how deep to walk through the array/object data
     * 
     * @param  bool  $flip_array Specifies whether to apply the {@see array_flip()} function
     *                           on the given value, for switching the array keys and values.
     * 
     * @return mixed
     */
    public function parseValueForDb($value, $flip_array = false)
    {
        if (!is_array($value) && !is_object($value))
            return $value;

        if ($flip_array) {
            $new_val = [];
            foreach ($value as $k => $v) {
                $new_val[] = $this->sanitizeOption($k);
            }
        } else {
            $new_val = &$value;
        }

        return $this->serialize($value);
    }

    /**
     * Parse data for DB.
     * Properly transform an array/object data before it is being displayed on screen.
     * 
     * @since 1.0.0
     * 
     * @see PluginFactory::isLargeArray()
     * 
     * @param  array $data       Specifies the array/object data
     * 
     * @param  int   $deep_level Specifies how deep to walk through the array/object data
     * 
     * @param  bool  $flip_array Specifies whether to apply the {@see array_flip()} function
     *                           on the given value, for switching the array keys and values.
     * 
     * @return mixed
     */
    public function parseValueForDisplay( $value, $deep_level = 5, $flip_array = false )
    {
        if ( !is_array($value) && !is_object($value))
            return $value;

        $deep_level = absint($deep_level);

        if ( $deep_level <= 0 ) {
            if ( $this->isLargeArray($value, $deep_level) ) {
                /**
                 * Create a view more button for walking through the serialized string
                 */
                $data = $this->getEventMsgViewMoreBtnIdentifier(true) . ' ' . $this->serialize($value);
            } else {
                $data = implode(', ', $value);
            }
        }
        else {
            if ( $flip_array ) {
                $_value  = array_slice($value, 0, $deep_level);
                $new_val = [];
                foreach ( $_value as $k => $v ) {
                   $new_val[] = $this->sanitizeOption($k);
                }
            } else {
                $new_val = array_slice($value, 0, $deep_level);
            }

            // Add a view more button if we have more elements in the array
            $view_more = '';
            if ( count($value) > $deep_level ) {
                $view_more = $this->getEventMsgViewMoreBtnIdentifier();

                // Append the rest elements in the array
                $view_more .= ' ' . implode( ', ', array_slice($value, $deep_level) );
            }
            
            $data = implode( ', ', $new_val ) . ' ' . $view_more;
        }

        return $data;
    }
}