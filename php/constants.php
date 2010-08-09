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
 * the fuse filesystem. Project wide constants defined here.
 *
 *
 */

/* Path info structure */

/* Table Info fields and types */
define('PT_CHUNK', 1);
define('PT_SNIPPET', 2);
define('PT_MODULE', 3);
define('PT_PLUGIN', 4);
define('PT_TEMPLATE', 5);
define('PT_TV', 6);
define('PT_PAGE', 7);
define ('PT_LAST', 7);

define('TI_NAME', 0);
define('TI_TABLE', 1);
define('TI_NAMEFIELD', 2);
define('TI_CONTENTFIELD', 3);
define('TI_TYPE', 4);
define('TI_INODEMASK', 5);
define('TI_FILEEXT', 6);

define('PI_LEVEL', 0);
define('PI_CATEGORYID', 1);
define('PI_CONTENTID', 2);
define('PI_DB', 3);
define('PI_TI', 4);
define('PI_CATEGORY', 5);
define('PI_FILENAME', 6);
define('PI_TYPE', 7);
define('PI_INSANE', 8);


define('PL_ROOT', 0);
define('PL_DB', 1);
define('PL_TYPE', 2);
define('PL_CATEGORY', 3);
define('PL_FILENAME', 4);

/* Read/Write maximum lengths */
define('SQL_MAX', 1024*1024);
