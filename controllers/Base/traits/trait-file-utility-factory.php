<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * File Utility Base Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait FileUtility
{
    /**
     * Load specified file(s) by specifying the full file path(s)
     *
     * @param  string|array  $file        The absolute file path to load
     * 
     * @param  string        $load_type   The method to use in loading the file. 
     *                                    Default to 'require_once'.
     * 
     * @return bool                       True is returned if file exists and was loaded.
     *                                    False if file was not found and WP_DEBUG is false.
     * 
     *                                    Note: An Exception will be thrown if WP_DEBUG is true 
     *                                    and file was not found.
     */
    public function include( $file, $load_type = 'require_once' )
    {
        if ( is_array( $file ) )
        {
            foreach ( $file as $file_path ) {
                $this->include( $file_path, $load_type );
            }
        }
        else {
            $file = $this->sanitizeOption( $file, 'file_path' );

            if ( ! is_file( $file ) || ! file_exists( $file ) )
            {
                if ( WP_DEBUG ) {
                    $msg = sprintf( alm__('File not found: %s' ), $file ); 
                    throw new \Exception( $msg );
                }
                return false;
            }

            switch ( mb_strtolower( $load_type ) )
            {
                case 'include':
                    include $file;
                    break;

                case 'include_once':
                    include_once $file;
                    break;

                case 'require':
                    require $file;
                    break;
                
                default:
                    require_once $file;
                    break;
            }
        }
        return true;
    }

    /**
     * Load file(s) by specifying its directory name and file name(s)
     * 
     * @param string        $dir        Specify the directory to lookup the file name
     * 
     * @param string|array  $file       A file name or list of file names to load in the $dir
     * 
     * @param string        $load_type  Specify the method to using in loading the file.
     *                                  Default to 'require_once'.
     */
    public function autoload( $dir, $file, $load_type = 'require_once' )
    {
        $remove_index = '';

        // Properly set the file autoload directory
        if ( $this->strStartsWith( $dir, wp_normalize_path( ABSPATH ) ) ) {
            $path = '';
        } else {
            $path = ALM_PLUGIN_DIR;
        }

        // Check whether we need to append a forward slash to the $dir
        $dir = $this->strEndsWith( $dir, '/' ) ? $dir : "{$dir}/";

        // Load the entire files in directory if wildcard (*) is used
        if ( ! is_array( $file ) 
        && false !== strpos( $file, '*' ) )
        {
            $files = glob( $dir . $file );

            // remove index.php
            $remove_index = 'index.php';

            // Reset the $dir and $path variables
            $path = $dir = '';
        }
        else {
            $files = is_array( $file ) ? $file : [ $file ];
        }

        foreach ( $files as $f )
        {
            // We don't want to load '.' and '..' when using glob()
            if ( in_array( $f, [ '.', '..', $remove_index ], true ) ) continue;

            // Check whether we need to append a '.php' extension to the file name
            $f = $this->strEndsWith( $f, '.php' ) ? $f : "{$f}.php";

            $file_path = $path . $dir . $f;
            $this->include( $file_path, $load_type );
        }
    }

    /**
     * Check whether the specified file exists, is a real file and is readable
     * 
     * @param  string  $file    Specifies the file to check
     * 
     * @return bool             Returns true if the file exists, is a real file and is readable.
     *                          Otherwise false. On debug mode, an exception is thrown.
     */
    public function isFileOK( $file )
    {
        clearstatcache( true, $file );

        if ( ! file_exists( $file ) 
        || ! is_file( $file ) 
        || ! is_readable( $file ) )
        {
            if ( WP_DEBUG ) {
                throw new \Exception( sprintf( alm__( '%s file does not exists.'), esc_html( $file ) ) );
            }
            return false;
        }
        return true;
    }

    /**
     * Check whether the specified directory exists, is a real directory and is readable.
     * 
     * @param  string  $dir  Specifies the directory to check
     * 
     * @return bool         Returns true if the directory exists, is a real directory and is 
     *                      readable. Otherwise false. On debug mode, an exception is thrown.
     */
    public function isDirOK( $dir )
    {
        if ( ! file_exists ($dir ) 
        || ! is_dir( $dir ) 
        || ! is_readable( $dir ) )
        {
            if ( WP_DEBUG ) {
                throw new \Exception( sprintf( alm__( '%s path does not exists.'), esc_html( $dir ) ) );
            }
            return false;
        }
        return true;
    }

    /**
     * Get a file permission
     * 
     * @param  string          $file    Specifies the file to get its permission.
     * 
     * @param  bool            $is_dir  Specifies whether to enforce file permission check for 
     *                                  directory only.
     * 
     * @return string|false             Returns the file permission as an octal value if the 
     *                                  file does exists and is readable. Otherwise false.
     */
    public function getFilePerm( $file, $is_dir = false ) 
    {
        if ( $is_dir ) {
            if ( ! $this->isDirOK( $file ) ) return false;
        }
        else {
            if ( ! $this->isFileOK( $file ) ) return false;
        }

        return substr( sprintf( '%o', fileperms( $file ) ), -4 );
    }

    /**
     * Check whether the chmod() context is for file or directory
     * @see FileUtility::changeFileMode()
     */
    public function isDirMode( $mode )
    {
        return $this->strEndsWith( (explode('_', $mode)[0] ?? ''), 'dir' );
    }

    /**
     * Change file permission for reading/writing
     *  
     * @param string      $file              Specifies the file to change mode for.
     * 
     * @param string      $mode              Specifies the mode of the file.
     * 
     * @param bool        $is_creating_file  Specifies whether the file/directory may not exists 
     *                                       and it will be created.
     * 
     * @return bool|null                     True on success or when the file is already in that 
     *                                       mode. False is returned when the given file could 
     *                                       not be changed to the mode. Null is returned when the 
     *                                       specified ($mode) mode is not registered.
     */
    public function changeFileMode( $file, $mode = 'lock' )
    {
        // Lookup the 'dir' slug in the specified $mode
        $has_dir_slug = $this->isDirMode( $mode );

        // Check whether directory is valid
        if ( $has_dir_slug ) {
            if ( ! $this->isDirOK( $file ) ) return false;
        }
        // Check whether file is valid
        else {
            if ( ! $this->isFileOK( $file ) ) return false;
        }

        $mod   = null;
        $perms = $this->getFilePerm( $file, $has_dir_slug );

        /**
         * Directories
         */

        // Owner  : read, write, execute
        // Group  : read, execute
        // Others : read, execute
        if ( 'opendir' === $mode ) {
            if ( '0755' !== $perms ) $mod = $this->chmod( $file, 0755, true );
        }

        // Owner  : read, write, execute
        // Group  : read, execute
        // Others : none
        if ( 'opendir_strict' === $mode ) {
            if ( '0750' !== $perms )  $mod = $this->chmod( $file, 0750, true );
        }

        /**
         * Files
         */

        // Owner  : read, write
        // Group  : read
        // Others : none
        if ( 'open' === $mode ) {
            if ( '0640' !== $perms ) $mod = $this->chmod( $file, 0640 );
        }

        // Owner  : read, write
        // Group  : read
        // Others : read
        if ( 'open_read' === $mode ) {
            if ( '0644' !== $perms ) $mod = $this->chmod( $file, 0644 );
        }

        // Owner  : read
        // Group  : read
        // Others : none
        if ( in_array( $mode, [ 'lock', 'read_only', ], true ) ) {
            if ( '0440' !== $perms ) $mod = $this->chmod( $file, 0440 );
        }

        // Owner  : read
        // Group  : none
        // Others : none
        if ( in_array( $mode, [ 'lock_strict', 'read_strict', ], true ) ) {
            if ( '0400' !== $perms )  $mod = $this->chmod( $file, 0400 );
        }

        return $mod;
    }

    /**
     * Change a file mode by specifying the mode in octal value.
     * 
     * @param bool  $file    Specify the file name to change permission for.
     * 
     * @param int   $mode    An octal value representing the file permission.
     *                       Always prefix with zero to avoid unexpected operation.
     * 
     * @param bool  $is_dir  Specify whether the file is a directory or not.
     * 
     * @return bool          True if the file mode was changed successfully or is already 
     *                       in that mode. Otherwise false.
     */
    public function chmod( $file, $mode, $is_dir = false )
    {
        /**
         * Filters the given file mode
         * 
         * @param int   $mode    An octal value representing the file permission.
         *                       Always prefix with zero to avoid unexpected operation.
         * 
         * @param bool  $file    Specify the file name to change permission for
         * 
         * @param bool  $is_dir  Specify whether the file is a directory or not.
         * 
         * @return int          An octal value representing the file permission.
         */
        $_perms = apply_filters( 'alm/file/mode', $mode, $file, $is_dir );

        // Lookup the 'dir' slug in the specified $mode
        $has_dir_slug = $this->isDirMode( $mode );

        /**
         * Activate safe-mode measures for file and directory permission changes
         * Files     : 0644 (maximum)
         * Directory : 0755 (maximum)
         */
        $max_perms = $has_dir_slug ? 0755 : 044;

        if ( max([ $max_perms, $_perms ]) == $_perms ) {
            $safe_perms = $max_perms;
        } else {
            $safe_perms = $_perms;
        }

        return chmod( $file, $safe_perms );
    }

    // Get the new index file content
    public function getNewIndexFileContent()
    {
        return '<?php' . PHP_EOL . '// Silence is golden';
    }

    /**
     * Create a new index file inside a directory
     * @param string  $dir Specifies the directory where the index.php should be created.
     */
    public function createNewIndexFile( $dir )
    {
        if ( ! $this->isDirOK( $dir ) ) return false;

        $dir  = $this->strEndsWith( '/', '/' ) ? '' : '/';
        $file = $dir . 'index.php';

        if ( ! file_exists( $file ) )
        {
            $index_file = fopen( $file, 'w+' );
            fwrite( $index_file, $this->getNewIndexFileContent() );
            fclose( $index_file );
        }
        $this->changeFileMode( $file, 'open_read' );
    }
}