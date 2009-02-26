<?php
# OpenStreetMap Simple Map - MediaWiki extension
# 
# This defines what happens when <map> tag is placed in the wikitext
# 
# We show a map based on the lat/lon/zoom data passed in. This extension brings in
# image generated by the static map image service called 'GetMap' maintained by OJW.  
#
# Usage example:
# <map lat=51.485 lon=-0.15 z=11 w=300 h=200 format=jpeg /> 
#
# Images are not cached local to the wiki.
# To acheive this (remove the OSM dependency) you might set up a squid proxy,
# and modify the requests URLs here accordingly.
#
##################################################################################
#
# Copyright 2008 Harry Wood, Jens Frank, Grant Slater, Raymond Spekking and others
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# @addtogroup Extensions
#


class SimpleMap {

	function SimpleMap() {
	}

	# The callback function for converting the input text to HTML output
	function parse( $input, $argv ) {
		global $wgScriptPath, $wgMapOfServiceUrl;

		wfLoadExtensionMessages( 'SimpleMap' );
		
		
		//Support old style parameters from $input
		//Parse the pipe separated name value pairs (e.g. 'aaa=bbb|ccc=ddd')
		//With the new syntax we expect nothing in the $input, so this will result in '' values
		$oldStyleParamStrings=explode('|',$input);
		foreach ($oldStyleParamStrings as $oldStyleParamString) {
			$oldStyleParamString = trim($oldStyleParamString);
			$eqPos = strpos($oldStyleParamString,"=");
			if ($eqPos===false) {
				$oldStyleParams[$oldStyleParamString] = "true";
			} else {
				$oldStyleParams[substr($oldStyleParamString,0,$eqPos)] = trim(htmlspecialchars(substr($oldStyleParamString,$eqPos+1)));
			}
		}	
		
		//Receive new style args: <map aaa=bbb ccc=ddd></map>
		if ( isset( $argv['lat'] ) ) { 
			$lat		= $argv['lat'];
		} else {
			$lat		= $oldStyleParams['lat'];
		}
		if ( isset( $argv['lon'] ) ) { 
			$lon		= $argv['lon'];
		} else {
			$lon		= $oldStyleParams['lon'];
		}
		if ( isset( $argv['z'] ) ) { 
			$zoom		= $argv['z'];
		} else {
			$zoom		= $oldStyleParams['z'];
		}
		if ( isset( $argv['w'] ) ) { 
			$width		= $argv['w'];
		} else {
			$width		= $oldStyleParams['w'];
		}
		if ( isset( $argv['h'] ) ) { 
			$height		= $argv['h'];
		} else {
			$height		= $oldStyleParams['h'];
		}
		if ( isset( $argv['format'] ) ) { 
			$format		= $argv['format'];
		} else {
			$format		= '';
		}
		if ( isset( $argv['marker'] ) ) { 
			$marker		= $argv['marker'];
		} else {
			$marker		= '';
		}

		$error='';

		//default values (meaning these parameters can be missed out)
		if ($width=='')		$width ='450'; 
		if ($height=='')	$height='320'; 
		if ($format=='')	$format='jpeg'; 

		if ($zoom=='' && isset( $argv['zoom'] ) ) {
			$zoom = $argv['zoom']; //see if they used 'zoom' rather than 'z' (and allow it)
		}

		$marker = ( $marker != '' && $marker != '0' );
		
		//trim off the 'px' on the end of pixel measurement numbers (ignore if present)
		if (substr($width,-2)=='px')	$width = (int) substr($width,0,-2);
		if (substr($height,-2)=='px')	$height = (int) substr($height,0,-2);


		if (trim($input)!='' && sizeof($oldStyleParamStrings)<3) {
			$error = 'map tag contents. We expect the map tag to have no inner text';
			$showkml = false;
		} else {
			$showkml = false;
		}
		
		
		if ($marker) $error = 'marker support is disactivated on the OSM wiki pending discussions about wiki syntax';
	

		//Check required parameters values are provided
		if ( $lat==''  ) $error .= wfMsg( 'simplemap_latmissing' );
		if ( $lon==''  ) $error .= wfMsg( 'simplemap_lonmissing' );
		if ( $zoom=='' ) $error .= wfMsg( 'simplemap_zoommissing' );
		
		if ($error=='') {
			//no errors so far. Now check the values	
			if (!is_numeric($width)) {
				$error = wfMsg( 'simplemap_widthnan', $width );
			} else if (!is_numeric($height)) {
				$error = wfMsg( 'simplemap_heightnan', $height );
			} else if (!is_numeric($zoom)) {
				$error = wfMsg( 'simplemap_zoomnan', $zoom );
			} else if (!is_numeric($lat)) {
				$error = wfMsg( 'simplemap_latnan', $lat );
			} else if (!is_numeric($lon)) {
				$error = wfMsg( 'simplemap_lonnan', $lon );
			} else if ($width>1000) {
				$error = wfMsg( 'simplemap_widthbig' );
			} else if ($width<100) {
				$error = wfMsg( 'simplemap_widthsmall' );
			} else if ($height>1000) {
				$error = wfMsg( 'simplemap_heightbig' );
			} else if ($height<100) {
				$error = wfMsg( 'simplemap_heightsmall' );
			} else if ($lat>90) {
				$error = wfMsg( 'simplemap_latbig' );
			} else if ($lat<-90) {
				$error = wfMsg( 'simplemap_latsmall' );
			} else if ($lon>180) {
				$error = wfMsg( 'simplemap_lonbig' );
			} else if ($lon<-180) {
				$error = wfMsg( 'simplemap_lonsmall' );
			} else if ($zoom<0) {
				$error = wfMsg( 'simplemap_zoomsmall' );
			} else if ($zoom==18) {
				$error = wfMsg( 'simplemap_zoom18' );
			} else if ($zoom>17) {
				$error = wfMsg( 'simplemap_zoombig' );
			}
		}

		
		if ($error!="") {
			//Something was wrong. Spew the error message and input text.
			$output  = '';
			$output .= "<span class=\"error\">". wfMsg( 'simplemap_maperror' ) . ' ' . $error . "</span><br />";
			$output .= htmlspecialchars($input);
		} else {
			//HTML for the openstreetmap image and link:
			$output  = "";
			$output .= "<a href=\"http://www.openstreetmap.org/?lat=".$lat."&lon=".$lon."&zoom=".$zoom."\" title=\"See this map on OpenStreetMap.org\">";
			$output .= "<img src=\"";
			$output .= $wgMapOfServiceUrl . "lat=".$lat."&long=".$lon."&z=".$zoom."&w=".$width."&h=".$height."&format=".$format;
			$output .= "\" width=\"". $width."\" height=\"".$height."\" border=\"0\">";
			$output .= "</a>";
			
			//commenting this out, because maybe we can get zwobot to change them all over without having to display this message
			if (sizeof($oldStyleParamStrings) >2 )  $output .= '<div style="font-size:0.8em;"><i>please change to <a href="http://wiki.openstreetmap.org/wiki/Simple_image_MediaWiki_Extension">new syntax</a></i></div>';
		}
		return $output;
	}
}
