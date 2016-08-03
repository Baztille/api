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

    public function getInfoReport($item) {
    	switch ($item) {
    		case '1':
    			$out="Proposition diffamatoire et insultante, Ceci n'a rien à faire sur Baztille";
    			break;
    		case '2':
    			$out="Proposition sans intérêt, Cela n'apporte rien au débat";
    			break;
    		case '3':
    			$out="Spam, Promotion abusive d'une idée ou d'un lien";
    			break;
    		case '4':
    			$out="Doublon";
    			break;
    		case '5':
    			$out="Très mauvaise rédaction, La proposition est incompréhensible";
    			break;
    		
    	}
    	return $out;
    }

    public function getModerationPanel( $object_to_change=null, $bAccept=null, $type=null )
    {

        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        if( $object_to_change !== null )
        {
            $change = $db->questionUpdateRequests->findOne( array( '_id' => new \MongoId((string)$object_to_change) ) ) ;

            if( $type == "question") {

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

	                        // Update this entry to accepted
	                        $db->questionUpdateRequests->update( 
	                  			array( '_id' => new \MongoId((string)$object_to_change) ),
	                  			    array( '$set' => array( "status" => 'accepted') ) 
	                  			);

	                   }

	                }
	                else
	                {
	                    // Update this entry to rejected
	                    $db->questionUpdateRequests->update( 
	                  			array( '_id' => new \MongoId((string)$object_to_change) ),
	                  			    array( '$set' => array( "status" => 'rejected') ) 
	                  			);
	                }
	            }

            }

            if( $type == "argument") {

	            if( $change !== null )
	            {
	                if( $bAccept )
	                {
	                   $arg_id = $change['arg_id'];
			           $arg = $db->args->findOne( array( '_id' => new \MongoId( $arg_id )) );    
			
			           if( $arg!==null )
	                   {
	                        // Save the previous text in "history" field
	                        $db->args->update(
	                  			array( '_id' => new \MongoId( $arg_id )),
	                  			array(
	                  			    '$push' => array( 'history' => array( 'text' => $arg['text'], 'removed_time'=>time() ) )
	                  			)            
	                        );

	                        // Replace the question
	                  		$db->args->update( 
	                  			array( '_id' => new \MongoId( $arg_id )),
	                  			    array( '$set' => array( "text" => $change['after']) ) 
	                  			);

	                        // Update this entry to accepted
	                        $db->questionUpdateRequests->update( 
	                  			array( '_id' => new \MongoId((string)$object_to_change) ),
	                  			    array( '$set' => array( "status" => 'accepted') ) 
	                  			);

	                   }

	                }
	                else
	                {
	                    // Update this entry to rejected
	                    $db->questionUpdateRequests->update( 
	                  			array( '_id' => new \MongoId((string)$object_to_change) ),
	                  			    array( '$set' => array( "status" => 'rejected') ) 
	                  			);
	                }
	            }

            }

        }
        // HTML
        // 
        // Tab QUESTION

        $otherdata = [];
        $html = '<div class="tab-content">';
        

        $cursor = $db->questionUpdateRequests->find(
        	array('question_id' => array( '$ne' => 0), 'status' => array('$exists'=> false) ) );
        $otherdata['question'] = $cursor->count();

        $html .= '<div role="tabpanel" class="tab-pane active" id="question">';
        $html .= "<h1>Questions <span class='badge'>".$cursor->count()."</span></h1><hr>";

		while( $request = $cursor->getNext() )
		{
		    
			$html.= $this->app['libDiff']->htmlDiff($request['before'], $request['after']).'';

			$html.='<a class="btn btn-link btn-sm" role="button" data-toggle="collapse" href="#collapseExample'.$request['_id'].'" aria-expanded="false" aria-controls="collapseExample"><span class="glyphicon glyphicon-zoom-in" aria-hidden="true"></span></a>';

			$html.='<div class="collapse" id="collapseExample'.$request['_id'].'">';

		    $html .=  "<div class='text-danger'>- ".$request['before']."</div>";
		    
		    if( isset( $request['before_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['before_category'] ];
		    
		    $html .=  "<div class='text-success'>+ ".$request['after']."</div>";

		    if( isset( $request['after_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['after_category'] ];
			
			$html .='</div>';
 			
 			$html .= '<br/>';
		    
		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=0&type=question' class='btn btn-danger btn-xs'>Refuse</a> ";
		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=1&type=question' class='btn btn-success btn-xs'>Accept</a>";
			
		    $html .= "<br><br>";
        }

        $html .= "</div>";  

        // Tab ARGUMENT

        $cursor = $db->questionUpdateRequests->find(array('question_id'=>0, 'status' => array('$exists'=> false) ));
        $otherdata['argument'] = $cursor->count();

        $html .= '<div role="tabpanel" class="tab-pane" id="argument">';
        $html .= "<h1>Réponses <span class='badge'>".$cursor->count()."</span></h1><hr>";

		while( $request = $cursor->getNext() )
		{
		    
			$html.= $this->app['libDiff']->htmlDiff($request['before'], $request['after']).'';

			$html.='<a class="btn btn-link btn-sm" role="button" data-toggle="collapse" href="#collapseExample'.$request['_id'].'" aria-expanded="false" aria-controls="collapseExample"><span class="glyphicon glyphicon-zoom-in" aria-hidden="true"></span></a>';
			
			$html.='<div class="collapse" id="collapseExample'.$request['_id'].'">';

		    $html .=  "<div class='text-danger'>- ".$request['before']."</div>";
		    
		    if( isset( $request['before_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['before_category'] ];
		    
		    $html .=  "<div class='text-success'>+ ".$request['after']."</div>";

		    if( isset( $request['after_category'] ) )
		        $html .= ' / '.$g_config['categories'][ $request['after_category'] ];
			
			$html .='</div>';
 			
 			$html .= '<br/>';
		    
		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=0&type=argument' class='btn btn-danger btn-xs'>Refuse</a> ";
		    $html .= "<a href='/admin/moderationpanel?key=".$g_config['moderation_password']."&id=".$request['_id']."&accept=1&type=argument' class='btn btn-success btn-xs'>Accept</a>";
			
		    $html .= "<br><br>";
        }

        $html .= "</div>";    

        // Tab Signalement

        $cursor = $db->reports->group(
		  array('type' => true, 'arg' => true, 'question' => true),
		  array('count' => 0), 
		  "function(doc, prev) { prev.count += 1 }",
		  array('date' => array('$exists' => true))
		);
        $otherdata['report'] = count($cursor['retval']);

        $html .= '<div role="tabpanel" class="tab-pane" id="report">';
        $html .= "<h1>Signalement <span class='badge'>".count($cursor['retval'])."</span></h1><hr>";

		foreach( $cursor['retval'] as $key=>$request )
		{ 
			if($request['type'] == "question") {
				// Get this question	
				$res = $db->questions->findOne( array( '_id' => new \MongoId( $request['question'] )) );  
				$detail = $db->reports->find(array('question'=>$request['question']) );
			} else {
				// Get this answer	
				$res = $db->args->findOne( array( '_id' => new \MongoId( $request['arg'] )) ); 
				$detail = $db->reports->find(array('arg'=>$request['arg']) ); 
			}

		    $html .=  "<span class='label label-primary'>".$request['type']."</span> ".$res['text']." <span class='badge'>".$request['count']."</span>";

			$html.='<a class="btn btn-link btn-sm" role="button" data-toggle="collapse" href="#collapseExample'.$key.'" aria-expanded="false" aria-controls="collapseExample"><span class="glyphicon glyphicon-zoom-in" aria-hidden="true"></span></a>';

			$html.='<div class="collapse" id="collapseExample'.$key.'">';
			
			while( $det = $detail->getNext() )
					{
						$html .=  "<div class='text-danger'>".$this->getInfoReport($det['level'])."</div>";
					}
			
			$html .='</div>';
 			
 			$html .= '<br/>';
			
		    $html .= "<br><br>";
        }

        $html .= "</div>";   

        $html .= "</div>";     
        
    	return $this->app['twig']->render('adminpanel.html.twig', array('body'=> $html, 'otherdata' => $otherdata));

    }

}
