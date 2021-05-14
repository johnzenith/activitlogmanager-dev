<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Array Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait ArrayFactory
{    
    /**
     * Get vowel letters
     * 
     * @return array
     */
    public function getVowelLetters()
    {
        return ['a', 'e', 'i', 'o', 'u'];
    }

    /**
     * Recursively get the differences between two arrays
     * @param  array $array1 The new array to get difference for
     * @param  array $array2 Existing array to check on
     * @return array The differences between the two arrays which is taken from the first array.
     */
    public function arrayDiffAssocRecursive( array $array1, array $array2 )
    {
        $difference = [];
        foreach ( $array1 as $key => $value )
        {
            if ( is_array( $value ) )
            {
                if ( !isset( $array2[ $key ] ) || !is_array( $array2[ $key ] ) ) {
                    $difference[ $key ] = $value;
                }
                else {
                    $new_diff = $this->arrayDiffAssocRecursive( $value, $array2[ $key ] );
                    if ( !empty( $new_diff ) )
                        $difference[ $key ] = $new_diff;
                }
            }
            elseif ( !array_key_exists( $key, $array2 ) || $array2[ $key ] !== $value ) {
                $difference[ $key ] = $value;
            }
        }
        return $difference;
    }

    /**
     * Get array values recursively
     * 
     * @since 1.0.0
     * 
     * @see array_walk_recursive()
     */
    public function arrayValuesToStringRecursive( array $data )
    {
        $values = '';
        array_walk_recursive(
            $data,
            function ( $v, $k ) use ( &$values )
            {
                $values .= "$v, ";
            }
        );

        return trim( $values, ', ' );
    }

    /**
     * Check whether an array is a large array.
     * 
     * @since 1.0.0
     * 
     * Large array criteria: 
     *  - array keys is not numeric
     *  - array keys is numeric and values count exceeds 3
     *  - is a multi-dimensional array 
     *  - is an object
     * 
     *  - Optionally, if the $deep_level is greater than 0 and array length
     *    is greater than or equal to the $deep_level, then true is returned;
     * 
     * @param  array $data       Specifies the array data
     * @param  int   $deep_level Specifies how deep to walk through the array/object data
     * @return bool              True if array meets the large criteria. Otherwise false.
     */
    public function isLargeArray( $data, $deep_level = 0 )
    {
        $is_obj = is_object( $data );
        if ( ! is_array ( $data ) && ! $is_obj ) 
            return false;
        
        if ( $is_obj ) return true;

        if ( empty( $data ) ) return true;

        $deep_level = absint($deep_level);
        // if ( $deep_level > 0 && count($data) <= $deep_level )
        if ( $deep_level > 0 && count($data) >= $deep_level )
            return true;
            
        foreach ( $data as $k => $d )
        {
            // Check if keys is numeric
            if ( ! is_int( $k ) ) 
                return true;

            // Check for multi-dimensional array
            if ( is_array( $d ) ) 
                return true;
        }

        // From this point, it means array keys is numeric, check if values exceeds 3
        if ( count( $data ) > 3 ) 
            return true;

        return false;
    }
}