/*
* modxfs-gatattr
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
 * This module is a small C helper function to list the extended attributes
 * of a modxfs-php entity, such as a resource say. The standar linux tool getfattr
 * doesn't work on fuse mounts, even if you set the FS name to say 'ext3', so
 * we need our own.
 * 
 */
#include <stdio.h>
#include <string.h>
#include <sys/types.h>
#include <attr/xattr.h>

/* Corresponds to the ext3 size I believe, more than enough for us */
#define MODXFS_LIST_SIZE 2048

int main(int argc, char **argv){

    
    char list[MODXFS_LIST_SIZE];
	
	memset(list, 0, MODXFS_LIST_SIZE);
	
	/* Use listxattr */
    int res = listxattr (*++argv, list, MODXFS_LIST_SIZE);
   
    /* Check for failure and print the results */
    if ( res < 0 )
    {
    	printf("Failed - Result code is %d: ", res);
    	printf("\n");
    }
    else
    {
		printf("%s", list);
		printf("\n");
    }
    
    return res;

}
