<?php
/*********************************************************************************

    Baztille
        
    Copyright (C) 2015  Grégory Isabelli, Thibaut Villemont and contributors.

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
***********************************************************************************/

use Silex\ServiceProviderInterface;
use Silex\Application;

namespace baztille;

class libDiff
{
	// credits http://pastebin.com/dU45FKNL	
	
	public function __construct($app)
    {
		$this->app = $app;
    }

	public function diff($old, $new){
		$matrix = array();
		$maxlen = 0;
		foreach($old as $oindex => $ovalue){
			$nkeys = array_keys($new, $ovalue);
			foreach($nkeys as $nindex){
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
					$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
				if($matrix[$oindex][$nindex] > $maxlen){
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}	
		}
		if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
		return array_merge(
			$this->diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			$this->diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
	}

	public function htmlDiff($old, $new){

		// put space in front and behind tags, or they are seen as a word when replacing the one behind it or in front
		$old = str_replace(array("<",">"), array(" <", "> "), $old);
		$new = str_replace(array("<",">"), array(" <", "> "), $new);
		
		$ret = '';
		$diff = $this->diff(explode(' ', $old), explode(' ', $new));
		foreach($diff as $k){
			if(is_array($k))
				$ret .= (!empty($k['d'])?"<del>".implode(' ',$k['d'])."</del> ":'').
					(!empty($k['i'])?"<mark>".implode(' ',$k['i'])."</mark> ":'');
			else $ret .= $k . ' ';
		}
		return $ret;
	}
}