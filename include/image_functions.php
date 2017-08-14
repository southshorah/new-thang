<?php
/**
 * @version        $Id: image_functions.php 153 2012-09-10 22:08:44Z ryan $
 * @package        mds
 * @copyright    (C) Copyright 2010 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @license        This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        http://www.milliondollarscript.com/
 *
 */

##################################################

function publish_image( $BID ) {

	if ( ! is_numeric( $BID ) ) {
		return false;
	}

	$imagine = new Imagine\Gd\Imagine();

	$BANNER_DIR = get_banner_dir();

	$file_path = SERVER_PATH_TO_ADMIN; // eg e:/apache/htdocs/ojo/admin/

	$p = preg_split( '%[/\\\]%', $file_path );
	array_pop( $p );
	array_pop( $p );

	$dest = implode( '/', $p );
	$dest = $dest . "/" . $BANNER_DIR;

	if ( OUTPUT_JPEG == 'Y' ) {
		copy( $file_path . "temp/temp$BID.jpg", $dest . "main$BID.jpg" );
	} elseif ( OUTPUT_JPEG == 'N' ) {
		copy( $file_path . "temp/temp$BID.png", $dest . "main$BID.png" );
	} elseif ( ( OUTPUT_JPEG == 'GIF' ) ) {
		copy( $file_path . "temp/temp$BID.gif", $dest . "main$BID.gif" );
	}

	// output the tile image

	$b_row = load_banner_row( $BID );

	if ( $b_row['tile'] == '' ) {
		$b_row['tile'] = get_default_image( 'tile' );
	}
	$tile = $imagine->load( base64_decode( $b_row['tile'] ) );
	$tile->save( $dest . "bg-main$BID.gif" );

	// update the records
	$sql = "SELECT * FROM blocks WHERE approved='Y' and status='sold' AND image_data <> '' AND banner_id='$BID' ";
	$r = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	while ( $row = mysqli_fetch_array( $r ) ) {
		// set the 'date_published' only if it was not set before, date_published can only be set once.
		$now = ( gmdate( "Y-m-d H:i:s" ) );
		$sql = "UPDATE orders set `date_published`='$now' where order_id='" . $row['order_id'] . "' AND date_published IS NULL ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		// update the published status, always updated to Y
		$sql = "UPDATE orders SET `published`='Y' WHERE order_id='" . $row['order_id'] . "'  ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		$sql = "UPDATE blocks set `published`='Y' where block_id='" . $row['block_id'] . "' AND banner_id='$BID'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	}

	//Make sure to un-publish any blocks that are not approved...
	$sql = "SELECT block_id, order_id FROM blocks WHERE approved='N' AND status='sold' AND banner_id='$BID' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result ) ) {
		$sql = "UPDATE blocks set `published`='N' where block_id='" . $row['block_id'] . "'  AND banner_id='$BID'  ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		$sql = "UPDATE orders set `published`='N' where order_id='" . $row['order_id'] . "'  AND banner_id='$BID'  ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	}

	// update the time-stamp on the banner
	$sql = "UPDATE banners SET time_stamp='" . time() . "' WHERE banner_id='" . $BID . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
}

###################################################

function process_image( $BID ) {

	if ( ! is_numeric( $BID ) ) {
		return false;
	}

	load_banner_constants( $BID );

	$imagine = new Imagine\Gd\Imagine();

	$file_path = SERVER_PATH_TO_ADMIN;

	$progress = 'Please wait.. Processing the Grid image with GD';

	// grid size
	$size = new Imagine\Image\Box( G_WIDTH * BLK_WIDTH, G_HEIGHT * BLK_HEIGHT );

	// create empty grid
	$map = $imagine->create( $size );

	// load block and resize it
	$block = $imagine->load( GRID_BLOCK );
	$block->resize( new Imagine\Image\Box( BLK_WIDTH, BLK_HEIGHT ) );

	// initialise the map, tile it with blocks
	$x_pos = $y_pos = 0;
	for ( $i = 0; $i < G_HEIGHT; $i ++ ) {
		for ( $j = 0; $j < G_WIDTH; $j ++ ) {
			$map->paste( $block, new Imagine\Image\Point( $x_pos, $y_pos ) );
			$x_pos += BLK_WIDTH;
		}
		$x_pos = 0;
		$y_pos += BLK_HEIGHT;

	}

	# copy the NFS blocks.
	$nfs_block = $imagine->load( NFS_BLOCK );
	$nfs_block->resize( new Imagine\Image\Box( BLK_WIDTH, BLK_HEIGHT ) );

	$sql = "select * from blocks where status='nfs' AND banner_id='$BID' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	while ( $row = mysqli_fetch_array( $result ) ) {
		$map->paste( $nfs_block, new Imagine\Image\Point( $row['x'], $row['y'] ) );
	}

	# blend in the background
	if ( file_exists( SERVER_PATH_TO_ADMIN . "temp/background$BID.png" ) ) {

		// open background image
		$background = $imagine->open( SERVER_PATH_TO_ADMIN . "temp/background$BID.png" );

		// calculate coords to paste at
		$bgsize = $background->getSize();
		$bgx    = ( $size->getHeight() / 2 ) - ( $bgsize->getHeight() / 2 );
		$bgy    = ( $size->getWidth() / 2 ) - ( $bgsize->getWidth() / 2 );

		// paste background image into grid
		$map->paste( $background, new Imagine\Image\Point( $bgx, $bgy ) );
	}

	// crate a map form the images in the db
	$sql = "select * from blocks where approved='Y' and status='sold' AND image_data <> '' AND banner_id='$BID' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	while ( $row = mysqli_fetch_array( $result ) ) {

		$data = $row['image_data'];

		if ( strlen( $data ) != 0 ) {
			$block = $imagine->load( base64_decode( $data ) );
		} else {
			$block = $imagine->open( $file_path . "temp/block.png" );
		}

		$block->resize( new Imagine\Image\Box( BLK_WIDTH, BLK_HEIGHT ) );
		$map->paste( $block, new Imagine\Image\Point( $row['x'], $row['y'] ) );
	}

	// save
	if ( ( OUTPUT_JPEG == 'Y' ) && ( function_exists( "imagejpeg" ) ) ) {
		if ( INTERLACE_SWITCH == 'YES' ) {
			$map->interlace( Imagine\Image\ImageInterface::INTERLACE_LINE );
		}
		if ( ! touch( $file_path . "temp/temp$BID.jpg" ) ) {
			$progress .= "<b>Warning:</b> The script does not have permission write to " . $file_path . "temp/temp" . $BID . ".jpg or the directory does not exist<br>";

		}
		$map->save( $file_path . "temp/temp$BID.jpg", array( 'jpeg_quality' => JPEG_QUALITY ) );
		$progress .= "<br>Saved as " . $file_path . "temp/temp$BID.jpg<br>";

	} elseif ( OUTPUT_JPEG == 'N' ) {

		if ( INTERLACE_SWITCH == 'YES' ) {
			$map->interlace( Imagine\Image\ImageInterface::INTERLACE_LINE );
		}
		if ( ! touch( $file_path . "temp/temp$BID.png" ) ) {
			$progress .= "<b>Warning:</b> The script does not have permission write to " . $file_path . "temp/temp" . $BID . ".png or the directory does not exist<br>";

		}
		$map->save( $file_path . "temp/temp$BID.png", array( 'png_compression_level' => 9 ) );
		$progress .= "<br>Saved as " . $file_path . "temp/temp$BID.png<br>";

	} elseif ( OUTPUT_JPEG == 'GIF' ) {

		if ( INTERLACE_SWITCH == 'YES' ) {
			$map->interlace( Imagine\Image\ImageInterface::INTERLACE_LINE );
		}

		if ( ! touch( $file_path . "temp/temp$BID.gif" ) ) {
			$progress .= "<b>Warning:</b> The script does not have permission write to " . $file_path . "temp/temp" . $BID . ".gif or the directory does not exist<br>";

		}
		$map->save( $file_path . "temp/temp$BID.gif" );
		$progress .= "<br>Saved as " . $file_path . "temp/temp$BID.gif<br>";
	}

	return $progress;
}

###################################################

function get_html_code( $BID ) {

	$sql = "SELECT * FROM banners WHERE banner_id='" . $BID . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$b_row = mysqli_fetch_array( $result );

	if ( ! $b_row['block_width'] ) {
		$b_row['block_width'] = 10;
	}
	if ( ! $b_row['block_height'] ) {
		$b_row['block_height'] = 10;
	}

	return "<iframe width=\"" . ( $b_row['grid_width'] * $b_row['block_width'] ) . "\" height=\"" . ( $b_row['grid_height'] * $b_row['block_height'] ) . "\" frameborder=0 marginwidth=0 marginheight=0 VSPACE=0 HSPACE=0 SCROLLING=no  src=\"" . BASE_HTTP_PATH . "display_map.php?BID=$BID\"></iframe>";

}

####################################################
function get_stats_html_code( $BID ) {
	return "<iframe width=\"150\" height=\"50\" frameborder=0 marginwidth=0 marginheight=0 VSPACE=0 HSPACE=0 SCROLLING=no  src=\"" . BASE_HTTP_PATH . "display_stats.php?BID=$BID\" allowtransparency=\"true\" ></iframe>";
}

#########################################################
