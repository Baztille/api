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

//////// Manage Baztille points & trophy system /////////////////:

namespace baztille;

class gamification
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }
    
    public function updateTrophies( $user_id )
    {
        // TODO: get points details and see if there is a need to attribute some trophy
    }
    

    ///////// The following methods are called when something happens to a user that may give him some points ////////////////
    
    // A user vote for a proposition from $user_id
    public function onVoteFor( $user_id )
    {
        // +1 point by vote for a contribution
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $db->users->update(
          			array( '_id' => new \MongoId( $user_id )),
          			array(
          			    '$inc' => array( "pointsdetails.vote_for_me" => 1, 'points' => 1 ),
              			)
          			);
		
		$this->updateTrophies( $user_id );
    } 

    // A user unvote for a proposition from $user_id
    public function onUnvoteFor( $user_id )
    {
        // -1 point by vote for a contribution
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $db->users->update(
          			array( '_id' => new \MongoId( $user_id )),
          			array(
          			    '$inc' => array( "pointsdetails.vote_for_me" => -1, 'points' => -1 ),
              			)
          			);

		$this->updateTrophies( $user_id );
    } 
    
    // This user post a contribution
    public function onContribute( $user_id )
    {
        // +3 points if first contribution today
        
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $this->app['current_user'];
        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $user->id ) ), array('last_contribution') );
        
        $bFirstContributionOfTheDay = false;
        
        if( ! isset( $userdatas['last_contribution'] ) )
        {
            // Not set yet => correct !
            $bFirstContributionOfTheDay = true;
        }
        else if( date('Ymd') == date('Ymd', $userdatas['last_contribution'] ) )
        {
            // Already contributed today !
            $bFirstContributionOfTheDay = false;
        }
        else
        {
            $bFirstContributionOfTheDay = true;
        }

        if( $bFirstContributionOfTheDay )
        {
            $db->users->update(
              			array( '_id' => new \MongoId( $user_id )),
              			array(
              			    '$inc' => array( "pointsdetails.contribution_day" => 1, 'points' => 3 ),
              			    '$set' => array( "last_contribution" => time() )
                  			)
              			);
              			
    		$this->updateTrophies( $user_id );        
        }
    }
    
    // A question from $user_id has been selected for voting
    public function onQuestionSelected( $user_id )
    {
        // +100 points
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $db->users->update(
          			array( '_id' => new \MongoId( $user_id )),
          			array(
          			    '$inc' => array( "pointsdetails.validated_question" => 1, 'points' => 100 ),
              			)
          			);
		
		$this->updateTrophies( $user_id );
    }     

    // An answer from $user_id has been approved by the communty
    public function onAnswerApproved( $user_id )
    {
        // +100 points
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $db->users->update(
          			array( '_id' => new \MongoId( $user_id )),
          			array(
          			    '$inc' => array( "pointsdetails.validated_answer" => 1, 'points' => 100 ),
              			)
          			);
		
		$this->updateTrophies( $user_id );
    }     

}
