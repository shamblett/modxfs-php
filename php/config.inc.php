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
 
/* 
 * Database type
 * 1 = Evolution, 2 = Revolution, if not one of these assumes the value 1
 */
$dbType = 2;

/* Database parameters */
$host = 'localhost';
$user = '';
$password = '';
$database = '';
$port = '';
$prefix = '';

/* Working directory */
$working = '';

/* Mount point */
$mount_point = '/';

/* Passthru */
$passthru_point = '/';

/* Log file */
$logfile = '';

/* 
 * Array of pass through directories/files, case sensitive.
 * Defaults to the minimum required to work with SVN 
 */
$passthrudirs = array(0=>'.svn',
                      1=>'checkoutmode');
