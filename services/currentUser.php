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

class currentUser
{
	private $session = null;
	
    public function __construct($app)
    {
		$this->app = $app;
		$session = $app['request']->get('session');

		if( $session && $session!='undefined' )
		{
			$m = new \MongoClient(); // connect
			$db = $m->selectDB("baztille");

			$this->session = $db->sessions->findOne( array( '_id' => new \MongoId( $session ) ) );
		}
    }
    
    public function is_logged()
    {
    	return ($this->session !== null );
    }
    
    public function ensure_logged()
    {
    	if( !$this->is_logged() )
    		throw new \Exception( "Not logged" );
    }
    
 	public function __get($property) {
		if ($property=='id') {
			return (string)$this->session['user_id'];
		}
  	}
  	
  	public function getdatas()
  	{
  		return $this->session;
  	}

    public function getAllPlayersInfos()
    {
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        $session = $this->getdatas();

        $user = $db->users->findOne( array( '_id' => new \MongoId( $session['user_id'] ) ) , array(
            'username'=>true,'email'=>true,'lang'=>true,'registration_date'=>true,'verified'=>true,'email_verified'=>true,'optout_votes'=>true,'optout_news'=>true) );
        
        return $user;
    }

	public function login( $username, $password )
	{    
    	global $g_config;

		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		// Get username
		$db->users->createIndex( array('username' => 1) );
		$user = $db->users->findOne( array( 'username' => $username ) );

		if( $user === null )
		{
		    // Try to see if user didn't enter his email instead...
		    
		    $user = $db->users->findOne( array( 'email' => $username ) );
    		if( $user === null )
    			throw new \Exception( "Wrong credentials" );
    	    else
    	        $username = $user['username'];
	    }

		if( password_verify ( $username.$password , $user['password'] ) )
		{	
			// Success !
			// Create a session for this user
		
			$session = array(
				'username' => $username,
				'user_id' => (string)$user['_id'],
				'expire' => time()+3600*2	// 2 hours
			);
		
			$db->sessions->insert( $session );
			$this->session = $session;
			
			// TODO: change 'firstLogin' to false in DB
		
			return array(
			    'token' => (string)$session['_id'],
			    'points' => $user['points'],
			    'firstLogin' => isset( $user['firstLogin'] ) ? $user['firstLogin'] : true
			);
		}
		else
			throw new \Exception( "Wrong credentials" );
	}

	public function logout(  )
	{    
		global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		$user = $this->app['current_user'];


		$db->sessions->remove( array( '_id' => new \MongoId( (string)$this->session['_id'] ) ) );
	}
	
	// Return user status :
	// _ 'unverified' = email not verified
	// _ 'normal' = identity not verified
	// _ 'member' = identity verified (Baztille real member)
	public function getUserStatus()
	{
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        $session = $this->getdatas();
        	
        $user = $db->users->findOne( array( '_id' => new \MongoId( $session['user_id'] ) ) , array('email_verified') );
        
        if( isset( $user['email_verified'] ) && $user['email_verified'] == true )
            return 'normal';
        else
            return 'unverified';
            
        // Note: we do not manage "member" status for now
	}
	
	public function ensure_verified()
	{
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

	    $status = $this->getUserStatus();

	    if( $status == 'unverified' )
	    {
            $session = $this->getdatas();
            $user = $db->users->findOne( array( '_id' => new \MongoId( $session['user_id'] ) ) , array('email') );

            // Note : we are using abort instead of Exception because Exception turn all error code to 570
	        $this->app->abort( 570, "Vous devez confirmer votre email (".$user['email'].") avant de pouvoir effectuer cette action. Merci de vérifier votre boite email." );
        }
	}

    public function myranking()
    {
        $result = $this->getdatas();
        
        // Get ranking
 		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");
        $user = $db->users->findOne( array( '_id' => new \MongoId( $result['user_id'] ) ) );
        
        $nb_users = $db->users->count();
        $better_users = $db->users->find( array( 'points' => array( '$gt' => $user['points'] ) ) );
        $result['rank'] = $better_users->count()+1;
        $result['nb_users'] = $nb_users;
        $result['pointsdetails'] = isset( $user['pointsdetails'] ) ? $user['pointsdetails'] : array();
        
        $leaderboard = $db->users->find( array() );
        $leaderboard->sort( array( 'points' => -1 ) );
        $result['leaderboard'] = array();

	    $rank = 1;
		while( $user = $leaderboard->getNext() )
		{
		    $result['leaderboard'][] = array(
		        'rank' => $rank,
		        'name' => $user['username'],
		        'id' => (string)$user['_id'],
		        'points' => $user['points']
		    );
	        
	        $rank ++;
	        
	        if( $rank > 20 )
	            break;
        }
        return $result;    
    }
    
    function reconfirmEmail()
    {
    
    }
    
    public function changeOptin( $email, $value )
    {
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        if( $email != 'votes' && $email != 'news' ) 
    		throw new \Exception( "Invalid optin value" );
        
        // Get current user infos
        $currentUser = $this->app['current_user']->getdatas();
        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $currentUser['user_id'] ) ), array('optout_news'=>true, 'optout_votes'=>true) );

        if( $value == false )
        {
            if( !isset( $userdatas['optout_'.$email ] ) )
            {
                // Set optout
          		$db->users->update( 
          			array( '_id' => new \MongoId( $currentUser['user_id'] ) ),
          			array('$set' => array( 'optout_'.$email => true ) )
          			);
            }
        }
        else
        {
            if( isset( $userdatas['optout_'.$email ] ) )
            {
                // Unset this value
          		$db->users->update( 
          			array( '_id' => new \MongoId( $currentUser['user_id'] ) ),
          			array('$unset' => array( 'optout_'.$email => true ) )
          			);
            }
        
        }    
    }    

}
