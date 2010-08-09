<?php
/*
* modxfs-php
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
 * This is the implementation file of the modxfs-php package.
 * It implements the original modxfs.c code in php and initialises
 * the fuse filesystem.
 *
 * Taken from the original C implementation by Chad Robinson.
 * Copyright (C) 2007 ITema, Inc.<php@itema.com>
 *
 */


/* Configuration */
include 'config.inc.php';

/*
 * Class instantiations
 */
include 'constants.php';

$mount = $working . $mount_point;
$passthru = $working . $passthru_point; 

include 'log.class.php';
Log::initialize($logfile);

include 'pool.class.php';
$dbConfig = array ('server' => $host,
    'username' => $user,
    'password' => $password,
    'database' => $database,
    'port' => $port,
    'prefix' => $prefix);

$error = Pool::initialize($dbConfig);
if ( $error != "" )
{
    die ($error);
}

include 'passthru.class.php';
/* Get the real path of the passthru directory */
$realpath = realpath($passthru);
PassThru::initialize($passthrudirs, $realpath);

include 'query.class.php';
/* Set the databse type */
Query::setDatabaseType($dbType); 

/*
 * Main FUSE functionality starts here
 */
$realmount = realpath($mount);
echo "Mount point is : $realmount\n";
echo "Passthru is : $realpath\n";
if ( $logfile != '' ) {
	$reallogfilepath = realpath($logfile);
    echo "Logfile is at : $reallogfilepath\n";
}
echo "Starting MODXFS-PHP FUSE process\n";

include 'modxfs.class.php';

$modxFuse = new Modxfs();
$modxFuse->mount($realmount, "default_permissions");

