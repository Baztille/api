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

class questionFlow
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }
    
	public function selectHottestQuestion()
	{
		// Select hottest question: Take the one with the most votes

        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		// List questions with status "proposed"
		// (in case of equality, the most recent win)
		$cursor = $db->questions->find( array('status' => 'proposed' ) );
		$cursor->sort( array( 'vote' => -1, 'date_proposed' => -1 ) );
		
		$question = $cursor->getNext();

		// We got our question !
		
		// Increment all failedSelection for all proposed questions except this one
  		$db->questions->update( 
  			array( '_id' => array( '$ne' => new \MongoId( $question['_id'] ) ), 'status' => 'proposed' ),
  			array('$inc' => array("failedSelection" => 1)),
  			array( 'multiple' => true )
  			);
			
		// => Set its status as "vote"
		$db->questions->update(
	  			array( '_id' => new \MongoId( $question['_id'] )),
	  			array( '$set' => array( 
	  				'status' => 'vote',
	  				'date_vote' => time()
	  			) )
			);

        // Note: users are notified that this question is open to vote in daily report
        
        // Give +100 points to author of this question and notify him
        if( $question['author'] )
        {
		    $gamification = $this->app['gamification'];
            $gamification->onQuestionSelected( $question['author'] );
        }
        
        // DEPRECATED : now we leave question proposed until they are selected
        //$this->rejectProposed();

        // Then, notify everyone that this question is now available
	    $notifier = $this->app['notifier'];
        $notifier->onQuestionSelected( $question );

		return (string)$question['_id'];
	}

    // Reject questions proposed with too much failed selection
    // DEPRECATED : now we leave question proposed until they are selected
    private function rejectProposed()
    {
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

	    $notifier = $this->app['notifier'];

		$cursor = $db->questions->find( array('status' => 'proposed', 'failedSelection' => array( '$gte' => $g_config['proposed_questions_selection_max_attempts'] ) ) );
        	
		while( $question = $cursor->getNext() )
		{
			// This question should be rejected

		    $db->questions->update(
	      			array( '_id' => new \MongoId( $question['_id'] )),
	      			array( '$set' => array( 
	      				'status' => 'rejected',
	      				'date_rejected' => time()
	      			) )
			    );
			    
            $notifier->onQuestionRejected( $question );

		}    

    }

	public function closeVote()
	{
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );
		
        // Take only the first one
	    $question = $db->questions->findOne( array(
		    'status' => 'vote'
	    ) );

        if( $question === null )
		    throw new \Exception( "closeVote: no vote is open at now" );

		$responses = $this->getVotedResponse( $question['_id'] );
		
		$db->questions->update(
	  			array( '_id' => new \MongoId( $question['_id'] )),
	  			array( '$set' => array( 
	  				'status' => 'decided',
	  				'date_decided' => time(),
	  				'validanswers' => $responses
	  			) )
			);
		
		$question['validanswers'] = $responses;
		
        // Give +100 points to author of voted response and notify them
        $gamification = $this->app['gamification'];
        
        foreach( $responses as $response )
        {
            if( $response['author'] )
            {
                $gamification->onAnswerApproved( $question['author'] );
            }
        }

	    $notifier = $this->app['notifier'];
        $notifier->onAnswerApproved( $question );        		
	}
	
	// Return IDs of valid answers for the given question
	public function getVotedResponse( $question_id )
	{
	    // Valid answers =
	    //   _ best answer
	    //   _ + answers with more than 50% of votes (TODO)

        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		// Get all "root" args, and order then by votes, down
		$cursor = $db->args->find( array( 'question' => (string)$question_id, 'parent' => 0 ) );
		$cursor->sort( array( 'vote' => -1, 'date' => -1 ) );
	
	    $valid_reponse = array();

	    $response_no = 1;
		while( $response = $cursor->getNext() )
		{
	        if( $response_no == 1 )
	            $valid_reponse[] = $response;
	        
	        $response_no ++;
        }
        	
	    return $valid_reponse;
	}

    public function testjob()
    {
        echo "testjob (job for test purpose)";

   	    $notifier = $this->app['notifier'];
        $notifier->sendEmailToUniqueUser( '5614c5f2ce4248861c8b4567', 'test sub', 'test content' );
    }

    function getModerationPanel( $question_to_change=null, $bAccept=null )
    {
        $html = "<h1>Moderation panel</h1>";
    
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        if( $question_to_change !== null )
        {
            $change = $db->questionUpdateRequests->findOne( array( '_id' => new \MongoId((string)$question_to_change) ) ) ;

            if( $change !== null )
            {
                if( $bAccept )
                {
                   $question_id = $change['question_id'];
		           $question = $db->questions->findOne( array( '_id' => new \MongoId( $question_id )) );    
		
		           if( $question!==null )
                   {
                        // Save the previous text in "history" field
                        $db->questions->update(
                  			array( '_id' => new \MongoId( $question_id )),
                  			array(
                  			    '$push' => array( 'history' => array( 'text' => $question['text'], 'removed_time'=>time() ) )
                  			)            
                        );

                        // Replace the question
                  		$db->questions->update( 
                  			array( '_id' => new \MongoId( $question_id )),
                  			    array( '$set' => array( "text" => $change['after'], "category" => $change['after_category'] ) ) 
                  			);

                        // Remove this entry
                        $db->questionUpdateRequests->remove( array( '_id' => new \MongoId((string)$question_to_change) ) );

                   }

                }
                else
                {
                    // Remove this entry
                    $db->questionUpdateRequests->remove( array( '_id' => new \MongoId((string)$question_to_change) ) );
                }
            }
        }

        global $g_config;
        $cursor = $db->questionUpdateRequests->find();
		while( $request = $cursor->getNext() )
		{
		    $html .=  "- ".$request['before'];
		    
		    if( isset( $request['before_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['before_category'] ];
		    $html .= '<br/>';
		    
		    $html .=  "+ ".$request['after'];

		    if( isset( $request['after_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['after_category'] ];
		    $html .= '<br/>';

		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=0'>Refuse</a> / ";
		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=1'>Accept</a>";
		    
		    $html .= "<br><br>";
        }        
        
    
        return $html;
    }

}
