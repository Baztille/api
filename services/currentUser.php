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
            global $g_config;
		    $m = new \MongoClient(); // connect
		    $db = $m->selectDB( $g_config['db_name'] );

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
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $session = $this->getdatas();

        $user = $db->users->findOne( array( '_id' => new \MongoId( $session['user_id'] ) ) , array(
            'username'=>true,'email'=>true,'lang'=>true,'registration_date'=>true,'verified'=>true,'email_verified'=>true,'optout_votes'=>true,'optout_news'=>true) );
        
        return $user;
    }

    public function getUserContent($type)
    {

        global $g_config;
        $m = new \MongoClient();
        $db = $m->selectDB( $g_config['db_name'] );

        $session = $this->getdatas();

        // Retreive ranking position

        $user = $db->users->findOne( array( '_id' => new \MongoId( $session['user_id'] ) ) , array(
            'username'=>true,'email'=>true,'lang'=>true,'registration_date'=>true,'verified'=>true,'email_verified'=>true,'points'=>true) );
        $nb_users = $db->users->count();
        $better_users = $db->users->find( array( 'points' => array( '$gt' => $user['points'] ) ) );
        $user['rank'] = $better_users->count()+1;
        $user['nb_users'] = $nb_users;

        // Retrieve user questions

        if($type == "contents") {

            $cursor = $db->questions->find( array( 'author' => $session['user_id'] ) , array() )->sort( array( 'date_proposed' => -1 ) );
            $session['questions'] = array();
            
            while( $question = $cursor->getNext() )
            {
                $argcursor = $db->args->find( array( 'question' => (string)$question['_id'], 'parent' => 0 ) );
                $question['nbReponse'] = $argcursor->count();
                $session['questions'][] = $question;
            }   

            // Get user's votes

            $session['myvotes'] = array();
            $db->questionvotes->createIndex( array( 'user' => 1 ) );
            $cursorV = $db->questionvotes->find( array( 'user' => $session['user_id'] ) );

            while( $myvote = $cursorV->getNext() )
            {
                $session['myvotes'][ $myvote['question'] ] = 1;
            }

        }  
        
        // Retrieve user answers

        else if($type == "args") {

            $cursor = $db->args->find( array( 'author' => $session['user_id'] ) , array() )->sort( array( 'date_proposed' => 1 ) );
            $session['args'] = array();

            while( $arg = $cursor->getNext() )
            {
                $argcursor = $db->args->find( array( 'question' => (string)$arg['question'], 'parent' => (string)$arg['_id'] ) );
                $arg['nbReponse'] = $argcursor->count();
                $session['args'][] = $arg;
            } 

            // Get user's votes
            
            $session['myvotes'] = array();
            $db->votes->createIndex( array( 'user' => 1 ) );
            $cursorV = $db->votes->find( array( 'user' => $session['user_id'] ) );

            while( $myvote = $cursorV->getNext() )
            {
                $session['myvotes'][ $myvote['arg'] ] = 1;
            }  
        
        }

        // TODO

        else if($type == "votes") {

            $cursor = $db->votes->find( array( 'user' => $session['user_id'] ) , array() )->sort( array( 'time' => 1 ) );
            $session['votes'] = array();

            while( $vote = $cursor->getNext() )
            {
                $session['votes'][ (string) $vote['_id'] ] = $vote;
            }   

            $cursor = $db->questionvotes->find( array( 'user' => $session['user_id'] ) , array() )->sort( array( 'time' => 1 ) );
            $session['questionvotes'] = array();

            while( $questionvote = $cursor->getNext() )
            {
                $session['questionvotes'][ (string) $questionvote['_id'] ] = $questionvote;
            }   
            
        }    

        return array_merge($session, $user);
    }

	public function login( $username, $password )
	{    
    	global $g_config;

		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

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
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $this->app['current_user'];


		$db->sessions->remove( array( '_id' => new \MongoId( (string)$this->session['_id'] ) ) );
	}
	
	// Return user status :
	// _ 'unverified' = email not verified
	// _ 'normal' = identity not verified
	// _ 'member' = identity verified (Baztille real member)
	public function getUserStatus()
	{
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

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
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

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
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

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
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

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
    
    public function removeAccount()
    {
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        // Get current user infos
        $currentUser = $this->app['current_user']->getdatas();
        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $currentUser['user_id'] ) ), array('optout_news'=>true, 'optout_votes'=>true) );
    
        $db->users->update(
      			array( '_id' => new \MongoId( $currentUser['user_id'] ) ),
      			array('$set' => array( 
      			        'username' => '(removed)',
      			        'email' => '(removed)',
      			        'points' => 0,
      			        'removed' => 1,
      			        'optout_votes' => true,
      			        'optout_news' => true
      			     ) )
        );
    }

}
