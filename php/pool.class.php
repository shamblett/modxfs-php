<?php
/*
* Pool class
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
 * This class is responsible for allocating a database connection for
 * use with the main modxfs-php classes. Although originally implemented
 * as a pool of connections this class implements only one instance for the
 * initial release.
 *
 * The config array should supply :-
 * server
 * username
 * password
 * database
 * port
 * prefix - default modx_
 *
 * If not MYSQL defaults will be used.
 *
 * The class is static so no object references are made in the extended FUSE
 * class.
 */
class Pool {

    /**
    * @var config database configuration settings
    * @access private
    */
    private static $_config = array();

    /**
     * @var tablePrefix the MODx table prefix
     * @access private
     */
    private static $_tablePrefix;

    /**
     * @var dbname database name
     * @access private
     */
    private static $_dbName;
    
    /**
     * @var dbConn database connection
     * @access private
     */
    private static $_dbConn;

    /**
     * Initalize the pool
     *
     * @access public
     * @param $config the configuration array
     *
     */
    public static function initialize($config) {
        
        Log::in("Pool initialize");

        Pool::$_config = $config;
        Pool::$_tablePrefix = $config['prefix'];
        Pool::$_dbName = $config['database'];
        
        /* Connect */
        $error = Pool::_connect($dbConn);
        if ( $error != "" ) {
            Log::output($error);
            Log::out("Pool initialize - no connect");
            die($error."\n");
            
        }

        Log::out("Pool initialize success");
        Pool::$_dbConn = $dbConn;
        
    }

    /**
     * Get the database connection from the pool
     *
     * @access public
     *
     * @return $dbConn
     */
    public static function get() {

        return Pool::$_dbConn;

    }

    /**
     * Get the table prefix
     *
     * @access public
     *
     * @return $tablePrefix
     */
    public static function getTablePrefix() {

        return Pool::$_tablePrefix;
    }

    /**
     * Get the database name
     *
     * @access public
     *
     * @return $databasename
     */
    public static function getDbName() {
        
        return Pool::$_dbName;
    }


    /**
     * Get a database connection instance
     *
     * @access private
     *
     * @param $dbConn a database connection if no error
     * @return $error error string
     */
    private static function _connect(&$dbConn) {

        $error = "";

        Log::in("Pool connect");

        $server = Pool::$_config['server'];

        if (Pool::$_config['port'] != "") {

            $server .= ':' . Pool::$_config['port'];
        }

        /* Connect */
        if ( !$dbConn = mysql_connect($server,
        Pool::$_config['username'],
        Pool::$_config['password']) ) {

            $error = mysql_error($dbConn);
            Log::out("Pool connect error");
            return $error;

        }
        
        /* Open the database */
        Pool::$_dbName = Pool::$_config['database'];
        if ( !$selected = mysql_select_db(Pool::$_dbName, $dbConn)) {

            $error = mysql_error($dbConn);
            Log::out("Pool connect db select error");
            return $error;
        }

        /* Success */
        Log::out("Pool connect sucess");
        return $error;
        
    }

    /**
     * Release a database connection
     *
     * @access public
     *
     * @param $dbConn connection to release
     */
    public static function release(&$dbConn) {

      /* Do nothing, compatibility function only */
      
    }

}

