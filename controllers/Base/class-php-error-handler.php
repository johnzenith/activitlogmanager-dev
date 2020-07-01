<?php
namespace ALM\Controllers\Base;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package    PHP Error Handler
 * @since      1.0
 */
class PHPErrorHandler
{
    /**
     * Prevents error handler from entering a recursive mode.
     * @var bool
     */
    protected $is_error_mode_recursive = false;

    /**
     * Error List
     * @var array
     */
    protected $error_list = [];

    /**
     * Last error hash
     * @var string
     */
    protected $last_error_hash = '';

    /**
     * Error Handler Constructor
     */
    public function __construct()
    {
        $this->watch();
    }

    /**
     * Register the error handlers for watching corresponding error events
     */
    public function watch()
    {
        set_error_handler([ $this, 'catchError' ], E_ALL);
        set_exception_handler([ $this, 'catchException' ]);
        
        add_action( 'shutdown', [ $this, 'runShutdownCleaner' ]);
    }

    /**
     * @todo
     * Get the php error code as specified in the audit events list
     */
    protected function getLogID( $error_code )
    {
        
    }

    /**
     * Get the error recursive mode
     */
    protected function isErrorModeRecursive()
    {
        return true === $this->is_error_mode_recursive;
    }

    /**
     * Check whether or not the php last error is already handled
     * @see error_get_last()
     */
    protected function isLastErrorAlreadyHandled( $last_error )
    {
        if ( empty( $this->last_error_hash ) || empty( $last_error ) ) return;

        return hash_equals( $this->last_error_hash, $this->generateErrorHash( $last_error ) );
    }

    /**
     * Generate error hash by using the error [type, message, file, line]
     * @see error_get_last()
     * @return string  The error hash
     */
    protected function generateErrorHash( $error_args = null )
    {
        if ( empty( $error_args ) ) return;

        // Make sure the $error_args is compatible with the error_get_last() array
        $usable_error_args = [
            'type'    => '',
            'file'    => '',
            'line'    => '',
            'message' => '',
        ];

        foreach ( $error_args as $index => $error_arg )
        {
            if ( isset( $usable_error_args[ $index ] ) ) {
                $usable_error_args[ $index ] = $error_arg;
            }
        }

        return hash( 'md5', implode( '|', $usable_error_args ) );
    }

    /**
     * Update error recursive mode
     */
    protected function putErrorInRecursiveMode()
    {
        $this->is_error_mode_recursive = true;
    }

    /**
     * Clear the error recursive mode status
     */
    protected function clearErrorRecursiveMode()
    {
        $this->is_error_mode_recursive = false;
    }

    /**
     * Get the error backtrace if any
     */
    protected function getErrorBackTrace()
    {
        /**
         * @todo check if debug back trace is enabled
         */
        ob_start();
        debug_print_backtrace();
        $backtrace = ob_get_clean();

        if ( empty( $backtrace ) ) {
            $backtrace = 'Backtrace is empty';
        }
        return $backtrace;
    }

    /**
     * Prettify error backtrace
     */
    protected function prettifyErrorBackTrace( \Exception $e )
    {
        /**
         * @todo check if debug back trace is enabled
         */

        $trace = explode( "\n" , $e->getTraceAsString() );

        // Sort the line chronologically
        $trace = array_reverse( $trace );

        array_shift( $trace ); // Remove {main}
        array_pop( $trace );   // Remove call to this method

        $length     = count($trace);
        $error_data = [];
       
        for ( $i = 0; $i < $length; $i++ )
        {
            $trace_step = $trace[ $i ];

            $error_data[] = sprintf(
                '#%d) %s',
                ($i + 1),
                substr( $trace_step, $trace_step, ' ' )
            );
        }
       
        return "\t" . implode( "\n\t", $error_data );
    }

    /**
     * Error handler
     * @param int       $error_no   contains the level of the error raised, as an integer.
     * @param string    $error_str  contains the error message, as a string.
     * @param string    $error_file contains the filename that the error was raised in
     * @param int       $error_line contains the line number the error was raised at
     */
    public function catchError( $error_no, $error_str, $error_file = 'unknown', $error_line = 0 )
    {
        // Error recursive state check
        if ( $this->isErrorModeRecursive() ) return;

        $this->error_list = [
            'type'    => $error_no,
            'file'    => $error_file,
            'line'    => $error_line,
            'message' => $error_str,
            'trace'   => $this->getErrorBackTrace(),
        ];

        $this->last_error_hash = $this->generateErrorHash( $this->error_list );
        $this->sendErrorAlert();
    }

    /**
     * Exception handler
     * @param object Exception
     */
    public function catchException( \Exception $e )
    {
        // Error recursive state check
        if ( $this->isErrorModeRecursive() ) return;

        $this->error_list = [
            'type'    => $e->getCode(),
            'file'    => $e->getFile(),
            'class'   => get_class( $e ),
            'line'    => $e->getLine(),
            'message' => $e->getMessage(),
            'trace'   => $this->prettifyErrorBackTrace( $e ),
        ];

        $this->sendErrorAlert();
    }

    /**
     * PHP shutdown handler
     */
    public function runShutdownCleaner()
    {
        // Error recursive state check
        if ( $this->isErrorModeRecursive() ) return;

        $last_error = error_get_last();

        // Ignore if error is already handled
        if ( $this->isLastErrorAlreadyHandled( $last_error ) ) return;

        $this->sendErrorAlert();
    }

    /**
     * Send error notification
     */
    protected function sendErrorAlert()
    {
        $this->putErrorInRecursiveMode();
        /**
         * @todo
         * Send error notification here
         */
        echo '<pre>';
        print_r( $this->error_list );
        echo '</pre>';
        $this->clearErrorRecursiveMode();
    }
}