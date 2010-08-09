<?php
/*
* Log class
*
* @category  modxfs-php
* @author    S. Hamblett <steve.hamblett@linux.com>
* @copyright 2009 S. Hamblett
* @license   GPLv3 http://www.gnu.org/licenses/gpl.html
* @link      none
*
* @package modxfs-php
*
*/

/*
 * This class implements the a logging facilty for the modxfs-php package.
 * The class is static so no object references are made in the extended FUSE
 * class.
 */
class Log {

    /**
    * @var filename the log file name and path
    * @access private
    */
    private static $_filename;

    /**
     * @var handle the log file handle
     * @access private
     */
    private static $_handle;

    /**
     * @var print boolean to turn logging on or off
     * @access private
     */
    private static $_print;

    /**
     * Initalize the logging function
     *
     * @access public
     * @param $filename the log file name
     */
    public static function initialize($filename) {

        Log::$_print = false;

        /* Only log if we have a filename */
        if ( $filename != "" ) {

            Log::$_filename = $filename;
            Log::$_print = true;

        }

        if ( Log::$_print ) {
            Log::$_handle = fopen(Log::$_filename, "a");
            if (   Log::$_handle === false ) {
                die("Failed to open the log file");
            }
        }
    }

    /**
     * Print a log line
     *
     * @access public
     *
     * @param $line a line of log text
     */
    public static function output($line) {

         if ( Log::$_print ) {

            $time = strftime("%H:%M:%S");
            $outline = $time . "--" . $line . "\n";
            fwrite(Log::$_handle, $outline);
        }
    }
    
    /**
     * Print an entry trace line
     *
     * @access public
     *
     * @param $line a line of log text
     */
    public static function in($line) {

         if ( Log::$_print ) {

            $time = strftime("%H:%M:%S");
            $outline = $time . "--" . $line . " - ENTRY: \n";
            fwrite(Log::$_handle, $outline);
        }
    }
    
    /**
     * Print an exit trace line
     *
     * @access public
     *
     * @param $line a line of log text
     */
    public static function out($line) {

         if ( Log::$_print ) {

            $time = strftime("%H:%M:%S");
            $outline = $time . "--" . $line . " - EXIT: \n";
            fwrite(Log::$_handle, $outline);
        }
    }
}
