<?php
/*
* MODxfs Fuse class
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
 * This class extends the fuse class overriding the fuse methods with the
 * methods needed to implement the modxfs-php package.
 * Taken from the original C implementation by Chad Robinson.
 * Copyright (C) 2007 ITema, Inc.<php@itema.com>
 */


class Modxfs extends Fuse {

    /**
     * Get file attributes
     *
     * @access public
     *
     * @param $path file path
     * @param $st file attributes array
     */
    function getattr($path, &$st) {

        Log::in("GETATTR $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {
            
            $ret = PassThru::filestats($path, $st);
            
        } else {

            $pi = Query::pathInfo($path);
            $ret = Query::fileStats($pi, $st);
        }

        Log::out("GETATTR");
        
        return $ret;

    }

    /**
     * Read a directory
     *
     * @access public
     *
     * @param $path file path
     * @param $retval file name array of files in the directory
     */
    function getdir($path, &$retval) {

        $passthruRetval = array();
        $dbmountRetval = array();

        Log::in("GETDIR $path");

        /* Check for passthru, if we are in passthru just return
         * the passthru files, not any db mounted files. 
         */
        if ( PassThru::check($path) ) {
            
            $ret = PassThru::getdir($path, $retval);
            
        } else {

        	/* Need to get both the passthru directory files and
         	* the db mounted directory files and merge them, so the user
         	* sees a combined view of the two seperate file systems in
         	* the db mount.
         	*/
         	$passthruPath = PassThru::getpassthrupath($path);
         	$ret = PassThru::getdir($passthruPath, $passthruRetval);
         	if ( $ret != 0 ) {
            	Log::out("GETDIR - failed to get passthru files");
            	return -FUSE_EIO;
         	}

         	$pi = Query::pathInfo($path);
         	$ret = Query::getdir($pi, $dbmountRetval);
         	if ( $ret != 0 ) {
            	Log::out("GETDIR - failed to get db mount files");
            	return -FUSE_EIO;
         	}
        
         	/* OK, merge the arrays */
        	$retval = array_merge_recursive($dbmountRetval, $passthruRetval);
        }

        Log::out("GETDIR");        
        return $ret;

    }

    /**
     * Make a filesystem node
     *
     * @access public
     *
     * @param $path file path
     * @param $mode perma and type of the node
     * @param $dev device number
     */
    function mknod($path, $mode, $dev) {

        $isdir = false;
        $isfile = false;
        $islnk = false;

        Log::in("MKNOD");
        Log::output("Path $path");
        Log::output("Mode $mode");
        Log::output("Device $dev");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::mknod($path, $mode);
            Log::out("MKNOD");
            return $ret;
        }

        if ( ($mode & FUSE_S_IFMT) == FUSE_S_IFLNK ) $islnk = true;

        if ( $islnk ) {
            Log::out("MKNOD - Tried to create a sym link- failed");
            return -FUSE_ENOENT;
        }

        /* Otherwise must be a file or category, check first */
        Log::output("MKNOD - a file or a category");
        $pi = Query::pathInfo($path);
        if ( ($pi[PI_LEVEL] == PL_FILENAME) || (!$pi[PI_LEVEL] == PL_CATEGORY) ) {

        	Log::output("MKNOD - calling query");
            $ret = Query::mknod($pi);
            Log::out("MKNOD");       
            return $ret;
                
        } else {
            	
        	Log::out("MKNOD - not a file or category");
            return -FUSE_ENOENT;
            
		}
                    
    }

    /**
     * Unlink(delete) a file
     *
     * @access public
     *
     * @param $path file path
     */
    function unlink($path) {

    // TODO: Allow unlinking a category directory; be sure to change entries
    // that use it to 0
    // TODO: Normal filesystems would give an ENOENT if the entry to remove
    // didn't actually exist - should we? It's an extra query.

        Log::in("UNLINK $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::unlink($path);

        } else {

            /* Check for a file */
            $pi = Query::pathInfo($path);
            if ( ($pi[PI_LEVEL] != PL_FILENAME)) {

                Log::out("UNLINK  - Invalid unlink - failed not a file");
                return -FUSE_ENOENT;
            }

            Log::output("UNLINK  - calling query");
            $ret = Query::unlink($pi);

        }

        Log::out("UNLINK");
        return $ret;

    }

    /**
     * Make a directory
     *
     * @access public
     *
     * @param $path file path
     * @param $mode directory mode
     */
    function mkdir($path, $mode) {

        Log::in("MKDIR");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::mkdir($path, $mode);

        } else {

            /* Ok, we can only make categories, let mknod do this */
            $pi = Query::pathInfo($path);
            $ret = Query::mknod($pi);
            
        }

         Log::out("MKDIR");
         return $ret;

    }



    /**
     * Change file modes and perms
     *
     * @access public
     *
     * @param $path file path
     * @param $mode mode to change to
     */
    function chmod($path, $mode) {

        Log::in("CHMOD");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::chmod($path, $mode);

        } else {

            /* We will always create with the process owners uid/gid, so don't fail */
             Log::output("CHMOD - function not supported for query");
            $ret = 0;
        }

        Log::out("CHMOD");
        return $ret;
    }

    /**
     * Change file ownership
     *
     * @access public
     *
     * @param $path file path
     * @param $uid user id
     * @param $gid group id
     */
    function chown($path, $uid, $gid) {

        Log::in("CHOWN - function not supported");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::chown($path, $uid, $gid);

        } else {

            /* Should really return ENOTSUP/ENOSYS but we don't have one */
            Log::output("CHOWN - function not supported for query");
            $ret =  -FUSE_ENOENT;

        }

        Log::out("CHOWN");
        return $ret;
    }

    /**
     * Update file times
     *
     * @access public
     *
     * @param $path file path
     * @param $atime access time
     * @param $mtime modification time
     */
    function utime($path, $atime, $mtime) {

        Log::in("UTIME");

       /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::utime($path, $atime, $mtime);

        } else {

            Log::output("UTIME - always works for query");
            $ret =  0;
        }

        Log::out("UTIME");
        return $ret;
    }

    /**
     * Truncate a file
     *
     * @access public
     *
     * @param $path file path
     * @param $offset offset into the file
     */
    function truncate($path, $offset) {

        Log::in("TRUNCATE $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::truncate($path, $offset);

        } else {

            $pi = Query::pathInfo($path);
            if ( ($pi[PI_LEVEL] != PL_FILENAME) ||
                (!$pi[PI_TI]) ||
                (!$pi[PI_FILENAME])) {

                Log::out("TRUNCATE - Invalid truncate - fail");
                return -FUSE_ENOENT;
            }

            Log::output("TRUNCATE - calling query");
            $ret = Query::truncate($pi);
        }

        Log::out("TRUNCATE");
        return $ret;
    }

    /**
     * Open a file
     *
     * @access public
     *
     * @param $path file path
     * @param $mode file mode
     */
    function open($path, $mode) {

        Log::in("OPEN $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $retval = PassThru::open($path, $mode);

        } else {

            $pi = Query::pathInfo($path);
        
            Log::output("OPEN - checking filename");
            if ( ($pi[PI_LEVEL] != PL_FILENAME) ||
                (!$pi[PI_TI]) ||
                (!$pi[PI_FILENAME])) {

                Log::out("OPEN - Invalid open - failed");
                return -FUSE_ENOENT;
            }
        
            Log::output("OPEN - filling content");
            $ret = Query::fillContentId($pi, 0);
            if ( $ret != 0 ) {
                Log::out("OPEN - fill content - failed");
                return $ret;
            }
        
            $retval = $pi[PI_CONTENTID];
        }
        
       Log::out("OPEN with a retval of - ".$retval);        
       return $retval;
        
    }

    /**
     * Read a file
     *
     * @access public
     *
     * @param $path file path
     * @param $fh file descriptor
     * @param $offset offset to read from
     * @param $bufLen max length to read
     * @param $buf the read contents
     */
    function read($path, $fh, $offset, $bufLen, &$buf ) {
        
        $buffer = "";

        Log::in("READ");
        Log::output("Path $path");
        Log::output("FH $fh");
        Log::output("Offset $offset");
        Log::output("Length $bufLen");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::read($path, $offset, $bufLen, $buf);

        } else {

            $pi = Query::pathInfo($path);
        
            /* Get the content id from the path, or fh */
            $ret = Query::fillContentId($pi, $fh);
            if ( $ret != 0 ) {
                Log::out("READ fill content failed");
                return $ret;
            }
        
            /* Assign buf directly */
            $ret = Query::read($pi, $buffer, $bufLen, $offset);
            Log::output("READ assigning buffer");
            $buf = $buffer;

        }

        Log::out("READ");
        return $ret;

    }

    /**
     * Write a file
     *
     * @access public
     *
     * @param $path file path
     * @param $fh file descriptor
     * @param $offset offset to write from
     * @param $buf buffer to write */
     
    function write($path, $fh, $offset, $buf ) {

        $bufLen = strlen($buf);
        
        Log::in("WRITE");
        Log::output("Path $path");
        Log::output("FH $fh");
        Log::output("Offset $offset");
        Log::output("Buffer size $buflen");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::write($path, $offset, $bufLen, $buf);

        } else {

            $pi = Query::pathInfo($path);
        
            /* Get the content id from the path, or fh */
            Log::output("WRITE - filling content id");
            $ret = Query::fillContentId($pi, $fh);
            if ( $ret != 0 ) {
                Log::out("WRITE - fill content - failed");
                return $ret;
            }
        
            Log::output("WRITE - calling query");
            $ret = Query::write($pi, $buf, $bufLen, $offset);

        }

        Log::out("WRITE");
        return $ret;

    } 

    /**
     * Release a file
     *
     * @access public
     *
     * @param $path file path
     * @param $fh file descriptor
     */
    function release($path, $fh) {

        Log::in("RELEASE - always works");
        return 0;
    }

     /**
     * Link a file
     *
     * @access public
     *
     * @param $pathFrom from file
     * @param $pathTo to file
     */
    function link($pathFrom, $pathTo) {

        Log::in("LINK");

         /* Check for passthru */
        if ( (PassThru::check($pathFrom)) && (PassThru::check($pathTo)) ) {

            $ret = PassThru::link($pathFrom, $pathTo);

        } else {

            /* Should really return ENOTSUP/ENOSYS but we don't have one */
            Log::out("LINK - query - function not supported");
            return -FUSE_ENOENT;

        }

        Log::out("LINK");
        return $ret;
    }

    /**
     * Symlink a file
     *
     * @access public
     *
     * @param $pathFrom from file
     * @param $pathTo to file
     */
    function symlink($pathFrom, $pathTo) {

        Log::in("SYMLINK");

         /* Check for passthru */
        if ( (PassThru::check($pathFrom)) && (PassThru::check($pathTo)) ) {

            $ret = PassThru::symlink($pathFrom, $pathTo);

        } else {

            /* Should really return ENOTSUP/ENOSYS but we don't have one */
            Log::out("SYMLINK - query - function not supported");
            return -FUSE_ENOENT;

        }

        Log::out("SYMLINK");
        return $ret;
    }

    /**
     * Read a file link
     *
     * @access public
     *
     * @param $path file path
     * @param $retval path name to the destination
     */
    function readlink($path, &$retval) {

        Log::in("READLINK");

         /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::readlink($path, $retval);

        } else {

            /* Should really return ENOTSUP/ENOSYS but we don't have one */
            Log::out("READLINK - query - function not supported");
            return -FUSE_ENOENT;

        }

        Log::out("SYMLINK");
        return $ret;
    }

    /**
     * Rename a file
     *
     * @access public
     *
     * @param $pathFrom from file
     * @param $pathTo to file
     */
    function rename($pathFrom, $pathTo) {

        Log::in("RENAME");
        Log::output("From $pathFrom");
        Log::output("To $pathTo");

        $localPathFrom = $pathFrom;
        $localPathTo = $pathTo;
        
  		/* Check for a complete passthru rename */
        if ( (PassThru::check($localPathTo)) &&  (PassThru::check($localPathFrom))) {

            $ret = PassThru::rename($localPathFrom, $localPathTo);
			Log::out("RENAME");
			return $ret;
        }
        
		/* OK, we either have a passthru to db case, or a db to passthru case */
		$localPathFrom = $pathFrom;
        $localPathTo = $pathTo;
        
		/* Passthru to db */
		if (  PassThru::check($localPathFrom) ) {
            
            /* Get the to path as a pi */
            $toPi = Query::pathInfo($pathTo);

            /* Check the paths have filenames */
            Log::output("RENAME - checking filenames");
            if ( !is_file($localPathFrom) ||
                ($toPi[PI_LEVEL] != PL_FILENAME)
            ) {
                    Log::out("RENAME - filename(s) invalid - failed");
                    return -FUSE_ENOENT;
            }

            /* Unlink the to file */
            Log::output("RENAME - unlink the to file");
            Query::unlink($toPi);

            /* Do the rename */
            Log::output("RENAME - calling query - Passthrutodb");
            $ret = Query::renameToDb($localPathFrom, $toPi);
            Log::out("RENAME");
        	return $ret;

        }
        
        /* Db to passthru */
		if (  PassThru::check($localPathTo) ) {
            
            /* Get the from path as a pi */
            $fromPi = Query::pathInfo($pathFrom);

            /* Check the paths have filenames */
            Log::output("RENAME - checking filenames");
            if ( !is_file($localPathTo) ||
                ($fromPi[PI_LEVEL] != PL_FILENAME)
            ) {
                    Log::out("RENAME - filename(s) invalid - failed");
                    return -FUSE_ENOENT;
            }

            /* Unlink the from file */
            Log::output("RENAME - unlink the from file");
            Query::unlink($fromPi);

            /* Do the rename */
            Log::output("RENAME - calling query - Dbtopassthru");
            $ret = Query::renameToPassthru($localPathTo, $fromPi);
            Log::out("RENAME");
        	return $ret;

        }     
        
        /* Database only rename */
        Log::output("RENAME - calling query - dbtodb");
        $fromPi = Query::pathInfo($pathFrom);
        $ret = Query::renameInDb($fromPi, $pathTo);
        Log::out("RENAME");
        return $ret;


    }
    
    /**
     * Rmdir(delete) a directory
     *
     * @access public
     *
     * @param $path file path
     */
    function rmdir($path) {

        Log::in("RMDIR $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {

            $ret = PassThru::rmdir($path);

        } else {

            /* Check for a category directory*/
            $pi = Query::pathInfo($path);
            if ( ($pi[PI_LEVEL] != PL_CATEGORY)) {

                Log::out("RMDIR  - Invalid rmdir - failed not a category");
                return -FUSE_ENOENT;
            }

            Log::output("RMDIR  - calling query");
            $ret = Query::rmdir($pi);

        }

        Log::out("RMDIR");
        return $ret;

    }
    
    /**
     * List extended attributes of a file/directory
     *
     * @access public
     *
     * @param $path file path
     * @param $retval file name array of extended attributes
     */
    function listxattr($path, &$retval) {

        Log::in("LISTXATTR $path");

        /* Check for passthru */
        if ( PassThru::check($path) ) {
            
            $ret = PassThru::listxattr($path, $retval);
            
        } else {
        	
        	$pi = Query::pathInfo($path);
        	Query::fillContentId($pi, 0); 
        	$ret = Query::listxattr($pi, $retval);
        	if ( $ret == 0 ) {
        		Query::resolveAttributes($retval);
        	}
        	
        }
        
        Log::out("LISTXATTR");
        return $ret;
        
    }
           
}
