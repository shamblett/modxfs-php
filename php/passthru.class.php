<?php
/*
* Passthru class
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
 * This class implements handling for directories nominated as pass through
 * directories, that is directories whose contents are to be treated as 'normal'
 * file systems, not modxfs-php ones. Subdirectories of the type '.svn' created
 * by subversion are ans example.
 * In effect this impements a normal FS layer in PHP.
 *
 * The class is static so no object references are made in the extended FUSE
 * class.
 */
class PassThru {


    /**
    * @var _passThru pass through directory array
    * @access private
    */
    private static $_passThru = array();

    /**
    * @var _passThruSet no pass thruogh directories have been set
    * @access private
    */
    private static $_passThruSet;

    /**
    * @var _realPath the real path of the mount point
    * @access private
    */
    private static $_realPath;

    /**
     * Initalize the pass through class
     *
     * @access public
     * @param $passthrough the directory pass through array
     *
     */
    public static function initialize($passthru, $realpath) {

        Log::in("Passthru initialize");

        if ( empty($passthru) ) {

            PassThru::$_passThruSet = false;
            return;
        }

        PassThru::$_passThru = $passthru;
        PassThru::$_realPath = $realpath;
        PassThru::$_passThruSet = true;

        Log::out("PassThru initialize success");

    }

    /**
     * Check for a pass through directory
     *
     * @access public
     * @param $path the path to check
     *
     */
    public static function check(&$path) {

        Log::in("Passthru check");

        /* If we are in checkout mode, we always pass through */
        if ( PassThru::checkoutMode() === true ) {
        	Log::out("passthru - check - we are in checkout mode");
        	/* Ok, we have passthru, mangle the path from
             * mount point relative as supplied by FUSE into
             * a real path.
             */
        	$path = PassThru::$_realPath.$path;
            return true; 	
        }
        
        /* Check for directories, If non set return */
        If ( !PassThru::$_passThruSet) return false;

        /* Otherwise check for them */
        foreach ( PassThru::$_passThru as $passpath) {

            if ( strstr($path, $passpath) ) {

                /* Ok, we have passthru, mangle the path from
                 * mount point relative as supplied by FUSE into
                 * a real path.
                 */
                $path = PassThru::$_realPath.$path;
                return true;
            }

        }

        Log::out("Passthru check");
        return false;

    }

     /**
     * Get the file stats
     *
     * @access public
     *
     * @param $path path string
     * @param $st file statistics
     */
     public static function fileStats($path, &$st) {

        $stats = array();
        
        Log::in("PassThru - File stats - $path");

		array_splice($st,0);
		
        /* Clear the cache */
        clearstatcache();
        
        /* Get the stats */
        $stats = stat($path);
        if ( $stats === false ) {

            Log::out("PassThru - File stats - failed");
            return -FUSE_ENOENT;

        }
        
        /* Remove the integer indexes */
        $noindexst = array_chunk($stats, 13, true);
        
        /* Remove device, blocksize and inode for the fuse interface */
        unset($noindexst[1]['dev']);
        unset($noindexst[1]['ino']);
        unset($noindexst[1]['blksize']);
 
        $st = $noindexst[1];
              
        Log::out("PassThru - File stats");
        return 0;

    }

    /**
     * Read a directory
     *
     * @access public
     *
     * @param $path path information
     * @param $retval file names in the directory
     */
    public static function getdir($path, &$retval) {

        Log::in("passthru - Getdir - $path");

        /* Clear the return value */
        array_splice($retval, 0);
		
		/* Dont use opendir and family here, too constrained, just
		 * exec out
		 */
        exec("ls -at $path", $files);
        foreach ($files as $file ) {
        	if ( is_dir($file)) {
               $retval[$file] = array('type' => FUSE_DT_DIR);
           } else {
               $retval[$file] = array('type' => FUSE_DT_REG);
           }
        }
        Log::out("passthru - Getdir");
        return 0;

    }

    /**
     * Make a file node
     *
     * @access public
     *
     * @param $path path information
     * @param $mode the file mode
     */
    public static function mknod($path, $mode) {

        Log::in("passthru - mknod");
        
        $retarray = array();

		/* First create the directory path needed for the file
		 * or directory creation.
		 */
		PassThru::_makePath($path, $mode);
		
        /* Check for a file or directory */
        if ( is_dir($path)) {

            if ( mkdir($path, $mode)=== false ) {

                Log::out("passthru - mknod - failed to create directory");
                return -FUSE_ENOENT;
            }
        } else {

            /* Just 'touch' the file and chmod it */
            if ( touch($path) === false ) {

                Log::out("passthru - mknod - failed to create file");
                return -FUSE_ENOENT;

            }

            if ( chmod($path, $mode) === false ) {

                Log::out("passthru - mknod - failed to chmod file");
                return -FUSE_ENOENT;

            }
        }

        Log::out("passthru - mknod");
        return 0;

    }

    /**
     * Unlink a file
     *
     * @access public
     *
     * @param $path path information
     */
    public static function unlink($path) {

        Log::in("passthru - unlink");

        if ( unlink($path) === false ) {
            
            Log::out("passthru - unlink - failed to unlink file");
            return -FUSE_ENOENT;
        }
        
        Log::out("passthru - unlink");
        return 0;
        
    }

    /**
     * Make a directory
     *
     * @access public
     *
     * @param $path path information
     * @param $mode the directory mode
     */
    public static function mkdir($path, $mode) {

        Log::in("passthru - mkdir - $path - $mode");

        /* Make directories recursively */
        if ( mkdir($path, $mode, true) === false ) {

            Log::out("passthru - mkdir - failed to make directory");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - mkdir");
        return 0;

    }

    /**
     * Chmod a file/directory
     *
     * @access public
     *
     * @param $path path information
     * @param $mode the file/directory mode
     */
    public static function chmod($path, $mode) {

        Log::in("passthru - chmod");

        if ( chmod($path, $mode) === false ) {

            Log::out("passthru - chmod - failed");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - chmod");
        return 0;

    }

    /**
     * Chown a file/directory
     *
     * @access public
     *
     * @param $path path information
     * @param $uid the user id
     * @param $gid the group id
     */
    public static function chown($path, $uid, $gid) {

        Log::in("passthru - chown");

        /* Owner */
        if ( chown($path, $uid) === false ) {

            Log::out("passthru - chown - chown failed");
            return -FUSE_ENOENT;
        }

        /* Group */
        if ( chgrp($path, $gid) === false ) {

            Log::out("passthru - chown - chgrp failed");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - chown");
        return 0;

    }

    /**
     * Utime a file/directory
     *
     * @access public
     *
     * @param $path path information
     * @param $atime access time
     * @param $mtime moduification time
     */
    public static function utime($path, $atime, $mtime) {

        Log::in("passthru - utime");

        /* Simply use 'touch' */
        if ( touch($path, $mtime, $atime) === false ) {

            Log::out("passthru - utime - failed");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - utime");
        return 0;

    }

    /**
     * Truncate a file
     *
     * @access public
     *
     * @param $path path information
     * @param $offset offset into the file, ie its truncated size
     */
    public static function truncate($path, $offset) {

        Log::in("passthru - truncate");

        /* Open the file for reading/writing */
        $handle = fopen($path, "r+");
        if ( $handle === false ) {
            Log::out("passthru - truncate - cannot open file");
            return -FUSE_ENOENT;
        }

        /* Truncate it */
        if ( ftruncate($handle, $offset) === false ) {
            Log::out("passthru - truncate - cannot truncate file");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - truncate");
        return 0;

    }

    /**
     * Open a file/directory
     *
     * @access public
     *
     * @param $path path information
     * @param $mode the file/directory mode
     */
    public static function open($path, $mode) {

        Log::in("passthru - open");
			
	// Return 0 for now, TODO zero file length fault";
		
        Log::out("passthru - open");
        return 0;

    }

    /**
     * Read a file
     *
     * @access public
     *
     * @param $path path information
     * @param $buf read bytes
     * @param $size size to read
     * @param $offset offset to read from
     */
    public static function read($path, $offset, $size,  &$buf) {

        Log::in("passthru - read");

        /* Need to use the offset and size, just read the file and
         * return its length.
         */
        $buf = file_get_contents($path, false, null, $offset, $size);
        if ( $buf === false ) {
            Log::out("passthru - read - failed");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - read");
        
        /* TODO zero file length fault, check for a zero file length,
         * if so add a space to the file till we sort out whats going in here
         */
        $length = strlen($buf);
        if ( ($length == 0) && ($offset == 0 ) ) {
        	$buf .= " "; 
            $length++;
        }
        return $length;

    }

    /**
     * Write a file
     *
     * @access public
     *
     * @param $path path information
     * @param $buf write bytes
     * @param $size size to write
     * @param $offset offset to write to
     */
    public static function write($path, $offset, $size,  $buf) {

        Log::in("passthru - write");

       /* Open the file for reading/writing */
        $handle = fopen($path, "r+");
        if ( $handle === false ) {
            Log::out("passthru - write - cannot open file");
            return -FUSE_ENOENT;
        }

        /* Seek to Offset */
        $result = fseek($handle, $offset);
        if ( $result == -1 ) {
            Log::out("passthru - write - cannot seek file");
            return -FUSE_ENOENT;
        }

        /* Write to the file */
        $len = fwrite($handle, $buf, $size);
        if ( $len === false ) {
            Log::out("passthru - write - cannot write to file");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - write");
        return $len;

    }

    /**
     * Hard link files
     *
     * @access public
     *
     * @param $pathFrom path of the from file
     * @param $pathTo path of the to file
     */
    public static function link($pathFrom, $pathTo) {

        Log::in("passthru - link");

        if ( link($pathTo, $pathFrom) === false ) {
            Log::out("passthru - link - cannot link files");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - link");
        return 0;

    }

    /**
     * Sym link files
     *
     * @access public
     *
     * @param $pathFrom path of the from file
     * @param $pathTo path of the to file
     */
    public static function symlink($pathFrom, $pathTo) {

        Log::in("passthru - symlink");

        if ( symlink($pathTo, $pathFrom) === false ) {
            Log::out("passthru - symlink - cannot symlink files");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - symlink");
        return 0;

    }

    /**
     * Read a file link
     *
     * @access public
     *
     * @param $path path information
     * @param $retval the returned link
     */
    public static function readlink($path, &$retval) {

        Log::in("passthru - readlink");

        $retval = readlink($path);
        if ( $retval === false ) {
            Log::out("passthru - readlink - failed");
            return -FUSE_ENOENT;
        }

         Log::out("passthru - readlink");
         return 0;

    }

    /**
     * Rename a file
     *
     * @access public
     *
     * @param $pathFrom path of the old file name
     * @param $pathTo path of the new file name
     */
    public static function rename($pathFrom, $pathTo) {

        Log::in("passthru - rename");

        if ( rename($pathFrom, $pathTo) === false ) {
            Log::out("passthru - rename - failed");
            return -FUSE_ENOENT;
        }

        Log::out("passthru - rename");
        return 0;

    }
    
    /**
     * Rmdir a directory
     *
     * @access public
     *
     * @param $path path information
     */
    public static function rmdir($path) {

        Log::in("passthru - rmdir");

        if ( rmdir($path) === false ) {
            
            Log::out("passthru - rmdir - failed to remove directory");
            return -FUSE_ENOENT;
        }
        
        Log::out("passthru - rmdir");
        return 0;
        
    }
    
    /**
     * Get a files extended attributes 
     *
     * @access public
     *
     * @param $path path information
     * @param $retval the returned attributes
     */
    public static function listxattr($path, &$retval) {

        $attributes = array();
        
        Log::in("passthru - listxattr");
				
        /* If no xattr package installed, say so */
        if ( function_exists(xattr_list) ) {
        
        	$attributes = xattr_list($path);
        
        } else {
        	
        	$attributes['Message'] = "You do not have the PHP xattr library installed";
        	$attributes['Solution'] = "Please install this package for passthru attributes to work";
        }
        
        $retval = $attributes;

         Log::out("passthru - listxattr");
         return 0;

    }


    /**
     * Return the passthru adjusted path
     *
     * @access public
     *
     * @param $path path information
     */
    public static function getpassthrupath($path) {

        $adjustedpath = PassThru::$_realPath.$path;
        return $adjustedpath;
    }
    
    /**
     * Are we in create(VCS checkout) mode
     *
     * @access public
     *
     */
    public static function checkoutMode() {

    	if ( file_exists(PassThru::$_realPath.'/checkoutmode') ) {
    		Log::out("passthru - checkoutMode positive");
    		return true;
    	}
    
    	return false;
    
    }
    
    /**
     * Create a file path in passthru
     *
     * @access public
     *
     */
    private static function _makePath($pathToMake, $mode) {

		$fullpath = '';
    
		/* Explode into path parts */
		$pathparts = explode('/', $pathToMake);
    
		/* Remove the end one */
		array_pop($pathparts);
    
		/* Create recursively */
		$createpath = implode('/', $pathparts);
		mkdir($createpath, 0755, true);

	}
}
