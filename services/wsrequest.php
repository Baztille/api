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

//////// Generic process on Baztille ws request /////////////////:

namespace baztille;

class wsrequest
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }
    
    
    public function buildWsResponse( $data )
    {
        // Add a common part to all Baztille requests
        $data['_bzcom'] = array( );

        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $this->app['current_user'];
		if( $user->is_logged() )
		{
            $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $user->id ) ), array('points', 'pointsdetails') );
            $data['_bzcom']['user'] = $userdatas;
        }
    
        return $data;
    }
}
