<?php
/*
* Query class
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
 * This class implements the range of queries needed by the modxfs-php package.
 * Taken from the original C implementation by Chad Robinson.
 * Copyright (C) 2007 ITema, Inc.<php@itema.com>
 *
 * The class is static so no object references are made in the extended FUSE
 * class.
*/
class Query {

    /**
     * @const fileIdMarker field to delimit the object id in the filename
     */
    const fileIdMarker = '-';

    /**
     * @const fileIdMarker field to delimit the object id in the filename
     */
    const noFileId = 0;

    /**
     * Database type constants
     */
    const dbIsEvo = 1;
    const dbIsRevo = 2;

    /**
     * @var tableType table type array
     * @access private
     */

    private static $_tableType = array(
            array("Chunks", "site_htmlsnippets", "name", "snippet",
                            PT_CHUNK, 1, ".html"),
            array("Resources", "site_content", "pagetitle", "content",
                            PT_PAGE, 2, ".html"),
            array("Snippets", "site_snippets", "name", "snippet",
                            PT_SNIPPET, 4, ".php"),
            array("Templates", "site_templates", "templatename", "content",
                            PT_TEMPLATE, 8, ".html"),
            array("Modules", "site_modules", "name", "modulecode",
                            PT_MODULE, 16, ".php"),
            array("Plugins", "site_plugins", "name", "plugincode",
                            PT_PLUGIN, 32, ".php"),
            array("TVs", "site_tmplvars", "name", "default_text",
                            PT_TV, 64, ".php")
    );

    /**
     * @var invalidChars invalid filename character array
     * @access private
     */
    private static $_invalidChars = array('~', '#');

    /**
     * @var dbType, 1 is Evo, 2 is Revo
     * @access private
     */
    private static $_dbType;

    /**
     * Set the databse type
     *
     * @access public
     *
     * @param $dbType
     */
    public static function setDatabaseType($dbType) {

        Query::$_dbType = Query::dbIsEvo;
        if ( ($dbType == Query::dbIsEvo ) || ($dbType == Query::dbIsRevo) ) {

            Query::$_dbType = $dbType;
        }
        
        Log::out("query - database type set");

    }

    /**
     * Fill in the category identity
     *
     * @access public
     *
     * @param $pi path information
     */
    public static function fillCategoryId(&$pi) {

        Log::in("query - Fill Category");

        $category = $pi[PI_CATEGORY];

        /* If Uncategorized the category is 0 */
        if ( $category == "Uncategorized") {

            $pi[PI_CATEGORYID] = 0;
            Log::out("query - Fill Category uncategorised");
            return 0;
        }

        /* Otherwise find it */
        Log::output("query - Fill Category - getting category from db");
        $dbConn = Pool::get();
        $category = mysql_real_escape_string($category, $dbConn);

        $sql = "SELECT id FROM ";
        $prefix = Pool::getTableprefix();
        $sql .= "`$prefix" . "categories`";
        $sql .= " WHERE category = '$category'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - Fill Category - checking for failure");
        $result = mysql_query($sql, $dbConn);
        if ( $result === false ) {
            Log::output(mysql_error($dbConn));
            Pool::release($dbConn);
            Log::out("query - Fill Category EIO");
            return -FUSE_EIO;
        }

        /* Get one row */
        Log::output("query - Fill Category - checking row");
        if ( mysql_num_rows($result) != 1 ) {
            mysql_free_result($result);
            Log::output("query - fillcategory - Zero or more than one row");
            Pool::release($dbConn);
            Log::out("query - Fill Category ENOENT");
            return -FUSE_ENOENT;
        }

        Log::output("query - Fill Category - fetching row");
        $row = mysql_fetch_row($result);
        $pi[PI_CATEGORYID] = $row[0];

        Pool::release($dbConn);

        /* Success */
        Log::out("query - Fill Category");
        return 0;

    }

    /**
     * Fill in the content identity
     *
     * @access public
     *
     * @param $pi path information
     * @param $fh file handle
     */
    public static function fillContentId(&$pi, $fh) {

        Log::in("query - Fill Content");

        /* If we have a file handle use it */
        if ( $fh > 0 ) {
            $pi[PI_CONTENTID] = $fh;
            Log::out("query - Fill Content - got FH");
            return 0;
        }

        $filename = $pi[PI_FILENAME];

        /* If we have an id in the filename use it */
        $id = Query::_getIdFromFilename($filename);
        if ( $id != Query::noFileId ) {
            $pi[PI_CONTENTID] = $id;
            Log::out("query - Fill Content - got id from filename");
            return 0;
        }

        /* Otherwise look it up */
        Log::output("query - Fill Content - getting id from db");
        $dbConn = Pool::get();
        $filename = mysql_real_escape_string($filename, $dbConn);

        $table = $pi[PI_TI][TI_TABLE];
        $namefield = $pi[PI_TI][TI_NAMEFIELD];
        $prefix = Pool::getTableprefix();

        $sql = "SELECT id FROM ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " WHERE $namefield = '$filename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - Fill Content - checking for failure");
        $result = mysql_query($sql, $dbConn);
        if ( $result === false ) {
            Log::output(mysql_error($dbConn));
            Pool::release($dbConn);
            Log::out("query - Fill Content");
            return -FUSE_EIO;
        }

        /* Get one row */
        Log::output("query - Fill Content - checking row");
        $rows = mysql_num_rows($result);
        if (  $rows != 1 ) {
            mysql_free_result($result);
            Log::output("query - fillcontent - Zero or more than one row = rows $rows");
            Pool::release($dbConn);
            Log::out("query - Fill Content");
            return -FUSE_ENOENT;
        }

        Log::output("query - Fill Content - fetching row");
        $row = mysql_fetch_row($result);
        $pi[PI_CONTENTID] = $row[0];

        Pool::release($dbConn);

        /* Success */
        Log::out("query - Fill Content");
        return 0;

    }

    /**
     * Get the file stats
     *
     * @access public
     *
     * @param $pi path information
     * @param $st file statistics
     */
    public static function fileStats($pi, &$st) {

        Log::in("query - File stats");
        
        /* Fill in common stats */
        $st['uid'] = getmyuid();
        $st['gid'] = getmygid();

        /* All objects except file nodes and categories are preset directories */
        if ( $pi[PI_LEVEL] != PL_FILENAME ) {

            Log::output("query - File stats - directory level");

            /* If a category, return this correctly, ie check existence etc. */
            if ( $pi[PI_LEVEL] == PL_CATEGORY ) {

                Log::output("query - Filestats - category level");

                /* Check the filename is sane */
                Log::output("query - File stats - insanity check");
                $insane = $pi[PI_INSANE];
                if ( $insane == 1) {

                    Log::output("query - filestats - insane file name");
                    Log::out("query - File stats");
                    return -FUSE_ENOENT;
                }

                $category = $pi[PI_CATEGORY];

                /* Check for Uncategorised */
                if ( $category == 'Uncategorized' ) {

                    $mask = ( 0775 | FUSE_S_IFDIR );
                    $st['mode'] = $mask;
                    $st['ino'] = 0;
                    $st['size'] = 4096;
                    Log::out("query - File stats - category - Uncat");
                    return 0;
                }

                Log::output("query - Filestats - checking category in db");
                $dbConn = Pool::get();

                $sql = "SELECT id FROM ";
                $prefix = Pool::getTableprefix();
                $sql .= "`$prefix" . "categories`";
                $sql .= " WHERE category = '$category'";

                /* Check for failure */
                $result = mysql_query($sql, $dbConn);

                /* No rows, possible file create */
                Log::output("query - File stats - category - checking for no rows");
                if ( mysql_num_rows($result) == 0) {
                    mysql_free_result($result);
                    Log::output("query - filestats - category - no rows, maybe create");
                    Pool::release($dbConn);
                    Log::out("query - File stats - category");
                    return -FUSE_ENOENT;

                } else {

                    /* Category exists */
                    $row = mysql_fetch_row($result);
                    $id = $row[0];
                    $mask = ( 0775 | FUSE_S_IFDIR );
                    $st['mode'] = $mask;
                    $st['ino'] = $row;
                    $st['size'] = 4096;
                    mysql_free_result($result);
                    Pool::release($dbConn);
                    Log::out("query - File stats - category - existing directory");
                    return 0;

                } // Check fail

            } else {

                /* Preset directory */
                Log::output("query - File stats - preset directory level");
                $mask = ( 0775 | FUSE_S_IFDIR );
                $st['mode'] = $mask;
                $st['ino'] = 1;
                $st['size'] = 4096;
                Log::out("query - File stats - directory");
                return 0;

            }

        } // Not file level

        Log::output("query - File stats - file level");

        $filename = $pi[PI_FILENAME];
        Log::output("query - File stats - filename is - $filename");

        /* Check the filename is sane */
        Log::output("query - File stats - insanity check");
        $insane = $pi[PI_INSANE];
        if ( $insane == 1) {

            Log::output("query - filestats - insane file name");
            Log::out("query - File stats");
            return -FUSE_ENOENT;
        }

        /*  Get the file id */
        $dbConn = Pool::get();
        $contentfield = $pi[PI_TI][TI_CONTENTFIELD];
        $db = $pi[PI_DB];
        $prefix = Pool::getTableprefix();
        $table = $pi[PI_TI][TI_TABLE];
        $namefield = $pi[PI_TI][TI_NAMEFIELD];

        /* Check for the id in the file name */
        $id = Query::_getIdFromFilename($filename);
        if ( $id != Query::noFileId ) {

            Log::output("query - File stats - getting stats from file id");
            $sql = "SELECT length($contentfield) FROM ";
            $sql .= "`$prefix" . "$table` ";
            $sql .= "WHERE `id` = '$id'";
            Log::output($sql);

        } else {

            Log::output("query - File stats - getting stats from filename");
            $filename = mysql_real_escape_string($filename, $dbConn);
            $sql = "SELECT id, length($contentfield) FROM ";
            $sql .= "`$prefix" . "$table` ";
            $sql .= "WHERE `$namefield` = '$filename'";
            Log::output($sql);
        }

        /* Check for failure */
        Log::output("query - File stats - checking for failure");
        $result = mysql_query($sql, $dbConn);
        if ( $result === false ) {
            Log::output(mysql_error($dbConn));
            Pool::release($dbConn);
            Log::out("query - File stats - mysql fail");
            return -FUSE_EIO;
        }

        /* More than one row, fail */
        Log::output("query - File stats - checking number of rows");
        if ( mysql_num_rows($result) > 1) {
            mysql_free_result($result);
            Log::output("query - filestats - more than one row");
            Pool::release($dbConn);
            Log::out("query - File stats");
            return -FUSE_EIO;
        }

        /* No rows, possible file create */
        Log::output("query - File stats - checking for no rows");
        if ( mysql_num_rows($result) == 0) {
            mysql_free_result($result);
            Log::output("query - filestats - no rows, maybe create");
            Pool::release($dbConn);
            Log::out("query - File stats");
            return -FUSE_ENOENT;
        }

        /* Only one row, get the result */
        Log::output("query - File stats - one row, get it");
        $row = mysql_fetch_row($result);
        if ( $row === false ) {
            mysql_free_result($result);
            Log::output("query - filestats - Cant fetch row");
            Pool::release($dbConn);
            Log::out("query - File stats");
            return -FUSE_EIO;
        }

        /* Fill in the stats buffer, check for id in filename */
        Log::output("query - File stats - fill in stats details");
        if ( $id == Query::noFileId ) {
            $st['ino'] = $row[0];
            $st['size'] = $row[1];
        } else {
            $st['ino'] = $id;
            $st['size'] = $row[0];
        }

        $mask = ( 0664 | FUSE_S_IFREG );
        $st['mode'] = $mask;

        mysql_free_result($result);
        Pool::release($dbConn);

        /* Success */
        Log::out("query - File stats");
        return 0;
    }

    /**
     * Read a directory
     *
     * @access public
     *
     * @param $pi path information
     * @param $retval file names in the directory
     */
    public static function getdir($pi, &$retval) {

        $filearray = array();

        Log::in("query - Getdir");

        /* Clear the return value */
        array_splice($retval, 0);

        switch ($pi[PI_LEVEL]) {

            case PL_ROOT:
                Log::output("query - Getdir - Getting psuedo entry list - root");
                $dirname = Pool::getDbName();
                $retval["."] = array('type' => FUSE_DT_DIR);
                $retval[".."] = array('type' => FUSE_DT_DIR);
                $retval["$dirname"] = array('type' => FUSE_DT_DIR);
                Log::out("query - Getdir");
                return 0;

            case PL_DB:
                Log::output("query - Getdir - Getting psuedo entry list - database");
                /* Get the db name */
                $dbname = Pool::getDbName();
                $retval["."] = array('type' => FUSE_DT_DIR);
                $retval[".."] = array('type' => FUSE_DT_DIR);
                /* Check for Evo or Revo database */
                foreach (Query::$_tableType as $tientry ) {
                    $dirname = $tientry[TI_NAME];
                    if ( Query::$_dbType == Query::dbIsRevo ) {
                        if ( $dirname == 'Modules' ) continue;
                    }
                    $retval["$dirname"] = array('type' => FUSE_DT_DIR);
                }
                Log::out("query - Getdir");
                return 0;

            case PL_TYPE:
                Log::output("query - Getdir - Getting category list");
                $retval["."] = array('type' => FUSE_DT_DIR);
                $retval[".."] = array('type' => FUSE_DT_DIR);
                $retval["Uncategorized"] = array('type' => FUSE_DT_DIR);
                /* If a resource group all of them under Uncategorized */
                if ( $pi[PI_TYPE] == "Resources") {
                    Log::out("query - Getdir - Resources");
                    return 0;
                }

                /* Otherwise get the categories */
                Log::output("query - Getdir - Getting categories");
                $dbConn = Pool::get();
                $prefix = Pool::getTableprefix();
                $sql = "SELECT category FROM ";
                $sql .= "`$prefix" . "categories`";
                Log::output($sql);

                /* Check for failure */
                Log::output("query - Getdir - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - Getdir - categories - EIO");
                    return -FUSE_EIO;
                }

                Log::output("query - Getdir - fetching rows");
                while ( $row = mysql_fetch_row($result)) {
                    $dirname = $row[0];
                    $retval["$dirname"] = array('type' => FUSE_DT_DIR);
                }

                mysql_free_result($result);
                Pool::release($dbConn);
                Log::out("query - Getdir - categories");
                return 0;

            case PL_CATEGORY:
                Log::output("query - Getdir - Getting file list");
                $ret = Query::fillCategoryId($pi);
                if ( $ret != 0 ) {
                    Log::out("query - Getdir fillcat failed");
                    return $ret;
                }

                $dbConn = Pool::get();
                $catid = $pi[PI_CATEGORYID];
                $name = $pi[PI_TI][TI_NAMEFIELD];
                $ext = $pi[PI_TI][TI_FILEEXT];
                $table = $pi[PI_TI][TI_TABLE];
                $prefix = Pool::getTableprefix();

                /* If a resource group all of them under Uncategorized */
                Log::output("query - Getdir - create sql");
                if ( $pi[PI_TYPE] == "Resources") {

                    $sql = "SELECT id, $name  FROM ";
                    $sql .= "`$prefix" . "$table`";

                } else {

                    $sql = "SELECT id, $name  FROM ";
                    $sql .= "`$prefix" . "$table`";
                    $sql .= " WHERE category = '$catid'";
                    Log::output($sql);
                }

                /* Check for failure */
                Log::output("query - Getdir - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - Getdir");
                    return -FUSE_EIO;
                }

                Log::output("query - Getdir - fetching rows");
                $retval["."] = array('type' => FUSE_DT_DIR);
                $retval[".."] = array('type' => FUSE_DT_DIR);
                while ( $row = mysql_fetch_row($result)) {
                    $fileentry = array();
                    /* Get an entry from the DB and stack it */
                    $fileentry['id'] = $row[0];
                    $fileentry['name'] = $row[1];
                    $filestack[] = $fileentry;
                }

                /* Construct a list of id appended filenames */
                $filenames = Query::_constructFilename($filestack);
                /* Return them */
                foreach ( $filenames as $filename) {
                    $fullfilename = $filename . $ext;
                    $retval["$fullfilename"] = array('type' => FUSE_DT_REG);
                }

                mysql_free_result($result);
                Pool::release($dbConn);
                Log::out("query - Getdir");
                return 0;

            default :
                Log::output("query - Getdir - Invalid PI_LEVEL");
                break;
        }

        /* Failed if we get here */
        Log::out("query - Getdir - failed");
        return -FUSE_EIO;

    }
    /**
     * Get the current path information
     *
     * @access public
     *
     * @param $path path information
     * @return $pi path information array
     */
    public static function pathInfo($path) {

        Log::in("query - Path Info");

        $explodedPath = array();
		
        /* Path structure is :-
         * /db name/content type/category/filename
        */

        /* Check for root */
        if ( $path == '/') {
            $pi[PI_LEVEL] = PL_ROOT;
            Log::out("query - Path Info - root level");
            return $pi;
        }

        /* Explode the path and pass back */
        $explodedPath = explode('/', $path);

        /* Database - must have this */
        if ( $explodedPath[1] != "" ) {

            Log::output("query - Path Info - db level");
            $pi[PI_LEVEL] = PL_DB;
            $pi[PI_DB] = $explodedPath[1];
        }

        /* Content type */
        if ( isset($explodedPath[2])) {
            if ( $explodedPath[2] != "" ) {

                Log::output("query - Path Info - content type");
                $pi[PI_LEVEL] = PL_TYPE;
                $pi[PI_TYPE] = $explodedPath[2];
                for ( $i = 0; $i < PT_LAST; $i++) {
                    if ( Query::$_tableType[$i][0] == $pi[PI_TYPE]) {
                        $pi[PI_TI] = Query::$_tableType[$i];
                    }
                }
            }
        }

        /* Category */
        if ( isset($explodedPath[3])) {
            if ( $explodedPath[3] != "" ) {

                Log::output("query - Path Info - category");
                $pi[PI_LEVEL] = PL_CATEGORY;
                $pi[PI_CATEGORY] = $explodedPath[3];
            }
        }

        /* File name */
        if ( isset($explodedPath[4])) {
            if ( $explodedPath[4] != "" ) {
                Log::output("query - Path Info - filename is $explodedPath[4]");
                $pi[PI_LEVEL] = PL_FILENAME;
                $filenoext = explode('.', $explodedPath[4] );
                /* Check for file names with a '.' in them */
                if ((count($filenoext) == 2) || count($filenoext) == 1) {
					$pi[PI_FILENAME] = $filenoext[0];
				} else {
					array_pop($filenoext);
					$fullfilename = implode('.', $filenoext);
					$pi[PI_FILENAME] = $fullfilename;
				}
                /* Check for sanity */
                $sane = Query::_sanitizeFilename($explodedPath[4]);
                if ( !$sane ) {
                    $pi[PI_INSANE] = 1;
                }
            }
        }

        Log::out("query - Path Info");
        return $pi;
    }

    /**
     * Make a file node
     *
     * @access public
     *
     * @param $pi path information
     */
    public static function mknod($pi) {

        Log::in("query - mknod");

        /* Check insanity */
        $insane = $pi[PI_INSANE];
        if ( $insane == 1) {

            Log::output("query - mknod - insane file name");
            Log::out("query - mknod - failed");
            return -FUSE_ENOENT;
        }
        /* Need to check the level, if at a category level, create
         * the category.
        */
        if ( $pi[PI_LEVEL] == PL_CATEGORY ) {

            Log::output("query - mknod - create category");
            $dbConn = Pool::get();
            $filename = $pi[PI_CATEGORY];
            $filename = mysql_real_escape_string($filename, $dbConn);
            $prefix = Pool::getTableprefix();

            $sql = "INSERT INTO ";
            $sql .= "`$prefix" . "categories`";
            $sql .= "(category)";
            $sql .= " VALUES ('$filename')";

            /* Check for failure */
            Log::output("query - mknod - create category - check for failure");
            $result = mysql_query($sql, $dbConn);
            if ( $result === false ) {
                Log::output(mysql_error($dbConn));
                Pool::release($dbConn);
                Log::out("query - mknod - create category - failed");
                return -FUSE_ENOENT;
            }

            /* Success */
            Pool::release($dbConn);
            Log::out("query - mknod - create category");
            return 0;

        } else {

            Log::output("query - mknod - create file");

            /* Need to get the category for the file create */
            $ret = Query::fillCategoryId($pi);
            if ( $ret != 0 ) {

                Log::out("query - mknod - no category - failed");
                return -FUSE_ENOENT;
            }

            $dbConn = Pool::get();
            $filename = $pi[PI_FILENAME];
            $filename = mysql_real_escape_string($filename, $dbConn);
            $prefix = Pool::getTableprefix();
            $table = $pi[PI_TI][TI_TABLE];
            $name = $pi[PI_TI][TI_NAMEFIELD];
            $content = $pi[PI_TI][TI_CONTENTFIELD];
            $category = $pi[PI_CATEGORYID];

            Log::output("query - mknod create sql");
            if ( $pi[PI_TYPE] == "Resources") {
                $sql = "INSERT INTO ";
                $sql .= "`$prefix" . "$table`";
                $sql .= "($name, $content)";
                $sql .= " VALUES ('$filename', '')";
            } else {
                $sql = "INSERT INTO ";
                $sql .= "`$prefix" . "$table`";
                $sql .= "($name, $content, category)";
                $sql .= " VALUES ('$filename', '', $category)";
            }
            Log::output($sql);

            /* Check for failure */
            Log::output("query - mknod - -create file - check for failure");
            $result = mysql_query($sql, $dbConn);
            if ( $result === false ) {
                Log::output(mysql_error($dbConn));
                Pool::release($dbConn);
                Log::out("query - mknod - create file - failed");
                return -FUSE_ENOENT;
            }

            /* Success */
            Pool::release($dbConn);
            Log::out("query - mknod");
            return 0;

        }
    }

    /**
     * Unlink a file
     *
     * @access public
     *
     * @param $pi path information
     */
    public static function unlink($pi) {

        Log::in("query - unlink");

        $dbConn = Pool::get();

        $filename = $pi[PI_FILENAME];
        $filename = mysql_real_escape_string($filename, $dbConn);

        $prefix = Pool::getTableprefix();
        $table = $pi[PI_TI][TI_TABLE];
        $name = $pi[PI_TI][TI_NAMEFIELD];

        Log::output("query - unlink - create sql");
        $sql = "DELETE FROM ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " WHERE $name = '$filename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - unlink - check for failure");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);

        if ( ($result === false)) {
            /* Dont fail for no rows, just exit */
            $error = mysql_error($dbConn);
            if ( $error == "" ) {
                Log::output("query - unlink - object does not exist");
            } else {
                Log::output($error);
                Pool::release($dbConn);
                Log::out("query - unlink - failed");
                return -FUSE_ENOENT;
            }
        }

        /* Success */
        Pool::release($dbConn);
        Log::out("query - unlink");
        return 0;
    }

    /**
     * Truncate a file
     *
     * @access public
     *
     * @param $pi path information
     */
    public static function truncate($pi) {

        Log::in("query - truncate");

        $dbConn = Pool::get();

        $filename = $pi[PI_FILENAME];
        $filename = mysql_real_escape_string($filename, $dbConn);

        $prefix = Pool::getTableprefix();
        $table = $pi[PI_TI][TI_TABLE];
        $name = $pi[PI_TI][TI_NAMEFIELD];
        $content = $pi[PI_TI][TI_CONTENTFIELD];

        Log::output("query - truncate - create sql");
        $sql = "UPDATE ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " SET $content = '' WHERE ";
        $sql .= "$name = '$filename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - truncate - check for fail");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);
        if ( $result === false ) {
            $error = mysql_error($dbConn);
            if ( $error == "" ) $error = "Update query failed";
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - truncate - failed");
            return -FUSE_ENOENT;
        }

        /* Success */
        Pool::release($dbConn);
        Log::out("query - truncate");
        return 0;
    }

    /**
     * Read a file
     *
     * @access public
     *
     * @param $pi path information
     * @param $buf read bytes
     * @param $size size to read
     * @param $offset offset to read from
     */
    public static function read($pi, &$buf, $size, $offset) {

        Log::in("query - read");

        if ( !$pi[PI_TI]) {

            Log::out("query - read - no PITI");
            return -FUSE_EIO;
        }

        $dbConn = Pool::get();

        Log::output("Query - read - create sql");
        $tableType = $pi[PI_TI];
        $contentid = $pi[PI_CONTENTID];
        $table = $tableType[TI_TABLE];
        $content = $pi[PI_TI][TI_CONTENTFIELD];
        $prefix = Pool::getTableprefix();

        switch ( $tableType[TI_TYPE] ) {

            case PT_CHUNK :
            case PT_MODULE:
            case PT_PLUGIN:
            case PT_TEMPLATE:
            case PT_TV:
            case PT_PAGE :
            case PT_SNIPPET :

                $sql = "SELECT $content";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";
                break;

            default :
                Log::output("query - read - invalid type");
                Log::out("query - read");
                return -FUSE_ENOENT;

        }

        Log::output($sql);

        /* Check for failure */
        Log::output("Query - read - check for failure");
        $result = mysql_query($sql, $dbConn);
        if ( $result === false ) {
            Log::output(mysql_error($dbConn));
            Pool::release($dbConn);
            Log::out("query - read - fail");
            return -FUSE_ENOENT;
        }

        Log::output("Query - checking content row");
        if ( mysql_num_rows($result) != 1) {
            mysql_free_result($result);
            Log::output("query - read - Zero or more than one row");
            Pool::release($dbConn);
            Log::out("query - read - fail");
            return -FUSE_ENOENT;
        }

        Log::output("Query - getting content row");
        $row = mysql_fetch_row($result);
        if ( $row === false ) {
            mysql_free_result($result);
            Log::output("query - read - cannot fetch row");
            Pool::release($dbConn);
            Log::out("query - read - fail");
            return -FUSE_ENOENT;
        }

        $bytes = strlen($row[0]);
        Log::output("query - read - we have read ".$bytes. " bytes");
        $actoff = ($offset > $bytes) ? $bytes : $offset;
        $actsize = ($actoff + $size > $bytes) ? $bytes - $actoff : $size;
        Log::output("query - read - actsize is ".$actsize. " bytes");

        Log::output("query - read -assigning buffer");
        $buf = $row[0];

        Log::output("query - read -freeing result");
        mysql_free_result($result);
        Pool::release($dbConn);

        Log::out("query - read");
        return $actsize;

    }

    /**
     * Write a file
     *
     * @access public
     *
     * @param $pi path information
     * @param $buf bytes to write
     * @param $size length of the write
     * @param $offset offset to write from
     */
    public static function write($pi, $buf, $size, $offset) {

        $content = "";

        Log::in("query - write");

        $dbConn = Pool::get();

        if ( !$pi[PI_TI]) {

            Log::out("query - write - no PITI");
            return -FUSE_EIO;
        }

        $tableType = $pi[PI_TI];
        $contentid = $pi[PI_CONTENTID];
        $table = $tableType[TI_TABLE];
        $content = $tableType[TI_CONTENTFIELD];
        $prefix = Pool::getTableprefix();

        /* Read the contents as of now if we have an offset */
        Log::out("query - write - checking offset");
        if ( $offset > 0 ) {

            Log::out("query - write - we have an offset");
            $sql = "SELECT $content  as";
            $sql .= " ct,length($content)";
            $sql .= " FROM `$prefix" . "$table`";
            $sql .= " WHERE id = $contentid";

            $result = mysql_query($sql, $dbConn);
            if ( $result === false ) {
                Log::output(mysql_error($dbConn));
                Pool::release($dbConn);
                Log::out("query - write - failed reading");
                return -FUSE_ENOENT;
            }

            if ( mysql_num_rows($result) != 1) {
                mysql_free_result($result);
                Log::output("query - write - Zero or more than one row");
                Pool::release($dbConn);
                Log::out("query - write - failed reading");
                return -FUSE_ENOENT;
            }

            $row = mysql_fetch_row($result);

            /* Adjust buf */
            $buf = $row[0] . $buf;
        }

        /* Do the update */
        Log::output("query - write - updating the db");
        $escapedbuf = mysql_real_escape_string($buf, $dbConn);

        $sql = "UPDATE ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " SET $content = '$escapedbuf'";
        $sql .= " WHERE id = $contentid";

        /* Check for failure */
        Log::out("query - write - checking for failure");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);
        if ( ($result === false) || ($rows == 0) ) {
            $error = mysql_error($dbConn);
            if ( $error == "" ) $error = "Now rows updated - object does not exist";
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - write failed");
            return -FUSE_EIO;
        }

        /* Success */
        Pool::release($dbConn);
        Log::out("query - write");
        return $size;

    }

    /**
     * Rename a normal file to a db file
     *
     * @access public
     *
     * @param $path file from path
     * @param $pi the pi of the db entry
     */
    public static function renameToDb($path, $pi) {

        Log::in("query - renameToDb - path is - $path");

        $dbConn = Pool::get();

        /* Get the query properties */
        $tableType = $pi[PI_TI];
        $table = $tableType[TI_TABLE];
        $name = $tableType[TI_NAMEFIELD];
        $prefix = Pool::getTableprefix();
        $toFilename = $pi[PI_FILENAME];

        /* Get the from file name */
        $fromFilename = basename($path);
        $fromFilenameArray = explode('.', $fromFilename);
        $fromFilename = $fromFilenameArray[0];

        Log::output("query - renameToDb - create sql");
        $sql = "UPDATE ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " SET $name = '$toFilename'";
        $sql .= " WHERE $name = '$fromFilename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - renameToDb - check for failure");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);
        if ( ($result === false) || ($rows == 0) ) {
            /* If no rows, create the entity */
            if ( $rows == 0 ) {
                Log::output("query - renameToDb - creating entity");
                $buf = file_get_contents($path);
                $size = strlen($buf);
                $ret = Query::mknod($pi);
                if ( $ret != 0 ) {
                    Pool::release($dbConn);
                    Log::out("query - renameToDb failed - cannot create entity");
                    return -FUSE_EIO;
                }
                Query::fillContentId($pi, 0);
                Query::Write($pi, $buf, $size, 0);
                Pool::release($dbConn);
                Log::out("query - renameToDb - create - OK");
                return 0;
            }
            /* Otherwise process the error */
            $error = mysql_error($dbConn);
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - renameToDb failed");
            return -FUSE_EIO;
        }

        Pool::release($dbConn);
        Log::out("query - renameToDb");
        return 0;
    }

    /**
     * Rename a db file to a passthru file
     *
     * @access public
     *
     * @param $path file to path
     * @param $pi the pi of the db entry
     */
    public static function renameToPassthru($path, $pi) {

        Log::in("query - renameToPassthru");

        $dbConn = Pool::get();

        /* Get the query properties */
        $tableType = $pi[PI_TI];
        $table = $tableType[TI_TABLE];
        $name = $tableType[TI_NAMEFIELD];
        $prefix = Pool::getTableprefix();
        $fromFilename = $pi[PI_FILENAME];

        /* Get the from file name */
        $toFilename = basename($path);
        $toFilenameArray = explode('.', $toFilename);
        $toFilename = $toFilenameArray[0];

        Log::output("query - renameToPassthru - create sql");
        $sql = "UPDATE ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " SET $name = '$toFilename'";
        $sql .= " WHERE $name = '$fromFilename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - renameToPassthru - check for failure");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);
        if ( ($result === false) || ($rows == 0) ) {
            $error = mysql_error($dbConn);
            if ( $error == "" ) $error = "Now rows updated - object does not exist";
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - renameToPassthru failed");
            return -FUSE_EIO;
        }

        /* Success */
        Pool::release($dbConn);
        Log::out("query - renameToPassthru");
        return 0;
    }
    
    /**
     * Rename a db file to a db file
     *
     * @access public
     *
     * @param $fromPi the from pi
     * @param $pathTo the filename to rename
     */
    public static function renameInDb($fromPi, $pathTo) {

        Log::in("query - renameInDb - path is - $path");

        $dbConn = Pool::get();

        /* Get the query properties */
        $tableType = $fromPi[PI_TI];
        $table = $tableType[TI_TABLE];
        $name = $tableType[TI_NAMEFIELD];
        $prefix = Pool::getTableprefix();
        $fromFilename = $fromPi[PI_FILENAME];

        /* Get the to file name */
        $toFilename = basename($pathTo);
        $toFilenameArray = explode('.', $toFilename);
        $toFilename = $toFilenameArray[0];

        Log::output("query - renameInDb - create sql");
        $sql = "UPDATE ";
        $sql .= "`$prefix" . "$table`";
        $sql .= " SET $name = '$toFilename'";
        $sql .= " WHERE $name = '$fromFilename'";
        Log::output($sql);

        /* Check for failure */
        Log::output("query - renameInDb - check for failure");
        $result = mysql_query($sql, $dbConn);
        if ( $result === false ) {
            /* Process the error */
            $error = mysql_error($dbConn);
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - renameInDb failed");
            return -FUSE_EIO;
        }

        Pool::release($dbConn);
        Log::out("query - renameInDb");
        return 0;
    }

    /**
     * Remove a directory
     *
     * @access public
     *
     * @param $pi path information
     */
    public static function rmdir($pi) {

        Log::in("query - rmdir");

        Query::fillCategoryId($pi);
        $category = $pi[PI_CATEGORYID];

        /* Can't delete uncategorized */
        if ( $category == 0 ) {
            Log::out("query - rmdir - cannot delete uncategorized");
            return -FUSE_ENOENT;
        }

        /* Ok, delete the category */
        $dbConn = Pool::get();
        $prefix = Pool::getTableprefix();
        Log::output("query - rmdir - create sql - delete");
        $sql = "DELETE FROM ";
        $sql .= "`$prefix" . "categories`";
        $sql .= " WHERE id = '$category'";

        /* Check for failure */
        Log::output("query - rmdir - check for failure");
        $result = mysql_query($sql, $dbConn);
        $rows = mysql_affected_rows($dbConn);
        if ( ($result === false) || ($rows == 0) ) {
            $error = mysql_error($dbConn);
            Log::output($error);
            Pool::release($dbConn);
            Log::out("query - rmdir failed");
            return -FUSE_EIO;
        }

        /* OK, update all other content tables to set the old category
         * to uncategorized. Don't check this, just do it.
        */
        $sql = "UPDATE ";
        $sql .= "`$prefix" . "site_htmlsnippets`";
        $sql .= " SET category = '0'";
        $sql .= " WHERE category = '$category'";
        mysql_query($sql, $dbConn);

        $sql = "UPDATE ";
        $sql .= "`$prefix" . "site_modules`";
        $sql .= " SET category = '0'";
        $sql .= " WHERE category = '$category'";
        mysql_query($sql, $dbConn);

        $sql = "UPDATE ";
        $sql .= "`$prefix" . "site_plugins`";
        $sql .= " SET category = '0'";
        $sql .= " WHERE category = '$category'";
        mysql_query($sql, $dbConn);

        $sql = "UPDATE ";
        $sql .= "`$prefix" . "site_snippets`";
        $sql .= " SET category = '0'";
        $sql .= " WHERE category = '$category'";
        mysql_query($sql, $dbConn);

        $sql = "UPDATE ";
        $sql .= "`$prefix" . "site_templates`";
        $sql .= " SET category = '0'";
        $sql .= " WHERE category = '$category'";
        mysql_query($sql, $dbConn);

        /* Success */
        Pool::release($dbConn);
        Log::out("query - rmdir");
        return 0;

    }

    /**
     * Get a files extended attributes
     *
     * @access public
     *
     * @param $pi path information
     * @param $retval file extended attributes
     */
    public static function listxattr($pi, &$retval) {

        Log::in("query - listxattr");

        /* Check for filename level */
        if ( $pi[PI_LEVEL] != PL_FILENAME ) {
            $retval['error'] = "Attributes are not yet available for this level";
            Log::out("query - listxattr - invalid level");
            return 0;
        }

        $dbConn = Pool::get();
        $prefix = Pool::getTableprefix();
        $contentid = $pi[PI_CONTENTID];
        $tableType = $pi[PI_TI];
        $table = $tableType[TI_TABLE];

        switch ( $tableType[TI_TYPE] ) {

            case PT_CHUNK :
            /* Set up the sql and get the row data */
                $sql = "SELECT id, description, editor_type, cache_type, locked";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Chunk - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Chunk - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Chunk - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Chunk - success");
                return 0;

            case PT_MODULE:
            /* Set up the sql and get the row data */
                $sql = "SELECT id, description, editor_type, disabled, wrap, locked,";
                $sql .= " icon, enable_resource, resourcefile, createdon,";
                $sql .= " editedon, guid, enable_sharedparams, properties";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Module - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Module - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Module - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Module - success");
                return 0;

            case PT_PLUGIN:
            /* Set up the sql and get the row data */
                $sql = "SELECT id, description, editor_type, cache_type, locked,";
                $sql .= " properties, disabled, moduleguid";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Plugin - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Plugin - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Plugin - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Plugin - success");
                return 0;

            case PT_TEMPLATE:
            /* Set up the sql and get the row data */
                $sql = "SELECT id, description, editor_type, icon, template_type, locked";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Template - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Template - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Template - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Template - success");
                return 0;

            case PT_TV:
            /* Set up the sql and get the row data */
                $sql = "SELECT id, type, name, caption, description, editor_type,";
                $sql = " category, locked, elements, ranked, display, display_params, default_text";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - TV - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - TV - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - TV - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - TV - success");
                return 0;


            case PT_PAGE :
            /* Set up the sql and get the row data */
                $sql = "SELECT id, type, contentType, longtitle, description, alias,";
                $sql .= " link_attributes, published, pub_date,";
                $sql .= " unpub_date, parent, isfolder, introtext, richtext,";
                $sql .= " template, menuindex, searchable, cacheable, createdon,";
                $sql .= " createdby, editedby, editedon, deleted, deletedon,";
                $sql .= " deletedby, publishedon, publishedby, menutitle,";
                $sql .= " donthit, haskeywords, hasmetatags, privateweb, privatemgr,";
                $sql .= " content_dispo, hidemenu";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Page - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Page - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Page - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Page - success");
                return 0;

            case PT_SNIPPET :
            /* Set up the sql and get the row data */
                $sql = "SELECT id, description, editor_type, cache_type, locked";
                $sql .= " properties, moduleguid";
                $sql .= " FROM `$prefix" . "$table`";
                $sql .= " WHERE id = $contentid";

                Log::output("query - listxattr - Snippet - checking for failure");
                $result = mysql_query($sql, $dbConn);
                if ( $result === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Snippet - query error");
                    return -FUSE_EIO;
                }

                $row = mysql_fetch_assoc($result);
                if ( $row === false ) {
                    Log::output(mysql_error($dbConn));
                    Pool::release($dbConn);
                    Log::out("query - listxattr - Snippet - no row");
                    return -FUSE_EIO;
                }

                /* Ok return the data */
                $retval = $row;
                Pool::release($dbConn);
                Log::out("query - listxattr - Snippet - success");
                return 0;

            default :
                Log::output("query - listxattr - invalid type");
                Log::out("query - listxattr");
                return -FUSE_ENOENT;

        }


    }

    /**
     * Resolve a files extended attributes
     *
     * @access public
     *
     * @param $retval file extended attributes
     */
    public static function resolveAttributes(&$retval) {

        Log::in("query - resolveAtributes");

        /* For printing via modxfs-getfattr for instance we need to
         * resolve things like user number to a name, template id to
         * a name, dates into strings etc.
         * We are only using select statements here, if a query fails
         * just leave the attribute alone and move on.
        */

        $dbConn = Pool::get();
        $prefix = Pool::getTableprefix();

        foreach( $retval as $key=>&$value ) {

            switch( $key ) {

                case 'editedon'    :
                case 'createdon'   :
                case 'pub_date'    :
                case 'unpub_date'  :
                case 'deletedon'   :
                case 'publishedon' :

                    $value = strftime("%G-%m-%j %H:%M:%S", $value);
                    break;

                case 'parent':

                    $sql = "SELECT pagetitle";
                    $sql .= " FROM `$prefix" . "site_content`";
                    $sql .= " WHERE id = $value";
                    $result = mysql_query($sql, $dbConn);
                    if ( $result !== false ) {
                        $row = mysql_fetch_assoc($result);
                        if ( $row['pagetitle'] != "" ) {
                            $value = $row['pagetitle'];
                        }
                    }
                    break;

                case 'template' :

                    $sql = "SELECT templatename";
                    $sql .= " FROM `$prefix" . "site_templates`";
                    $sql .= " WHERE id = $value";
                    $result = mysql_query($sql, $dbConn);
                    if ( $result !== false ) {
                        $row = mysql_fetch_assoc($result);
                        if ( $row['templatename'] != "" ) {
                            $value = $row['templatename'];
                        }
                    }
                    break;

                case 'editedby'    :
                case 'deletedby'   :
                case 'publishedby' :
                case 'createdby'   :

                /* Only manager users here */
                    $sql = "SELECT username";
                    $sql .= " FROM `$prefix" . "manager_users`";
                    $sql .= " WHERE id = $value";
                    $result = mysql_query($sql, $dbConn);
                    if ( $result !== false ) {
                        $row = mysql_fetch_assoc($result);
                        if ( $row['username'] != "" ) {
                            $value = $row['username'];
                        }
                    }
                    break;

                default:

                    break;

            }

        }

        Pool::release($dbConn);
        Log::out("query - resolveAtributes");

    }

    /**
     * Sanitize a file name
     *
     * @access private
     *
     * @param $filename the file name
     */
    private static function _sanitizeFilename($filename) {

        $sane = true;
        foreach( Query::$_invalidChars as $invalidChar) {

            $pos = strpos($filename, $invalidChar);
            if ( $pos !== false ) {
                $sane = false;
                break;
            }
        }

        return $sane;

    }

    /**
     * Construct Id appended filenames
     *
     * @access private
     *
     * @param $filestack the stack of file entries
     */
    private static function _constructFilename($filestack) {

        $filenames = array();
        $filenamestack = array();

        /* Here we need to decide what filenames are duplicates and
    	 * append the object id as necessary.
        */

        /* Go through the file stack and get the filenames */
        foreach ($filestack as $fileentry ) {
            $filenamestack[] = $fileentry['name'];

        }

        /* Get an array containing a count of equal values */
        $valarray = array_count_values($filenamestack);

        /* Go back through the file stack and construct the file names
    	 * using the duplicate count values just found.
        */
        foreach ($filestack as $fileentry ) {

            $name = $fileentry['name'];

            if ( $valarray[$name] > 1 ) {
                /* Need to add object id */
                $filenames[] = $fileentry['name'] . Query::fileIdMarker . $fileentry['id'];
            } else {
                $filenames[] = $fileentry['name'];
            }

        }

        return $filenames;

    }

    /**
     * Get the object id from the filename
     *
     * @access private
     *
     * @param $filename the name of the file
     */
    private static function _getIdFromFilename($filename) {

        /* Is the id marker string present */
        if ( stristr($filename, Query::fileIdMarker) === false ) {

            /* No, return no id */
            return Query::noFileId;

        }

        /* Ok, we have an id, return it */
        $namearray = explode(Query::fileIdMarker, $filename);
        return end($namearray);

    }


}
