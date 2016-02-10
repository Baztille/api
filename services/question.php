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

define( 'UX_QUESTION_SORTING_CHOOSE_BEST', 0 );
define( 'UX_QUESTION_SORTING_HOTTEST', 1 );
define( 'UX_QUESTION_SORTING_VOTED', 2 );
define( 'UX_QUESTION_SORTING_RECENT', 3 );
define( 'UX_QUESTION_SORTING_DATE_VOTED', 3 );
define( 'UX_QUESTION_SORTING_DATE_DECIDED', 3 );

define( 'UX_QUESTION_CATEGORY_ALL', 1 );
define( 'UX_QUESTION_CATEGORY_CULTURE', 2 );
define( 'UX_QUESTION_CATEGORY_ECONOMY', 3 );
define( 'UX_QUESTION_CATEGORY_EDUCATION', 4 );
define( 'UX_QUESTION_CATEGORY_ENVIRONMENT', 5 );
define( 'UX_QUESTION_CATEGORY_STATE', 6 );
define( 'UX_QUESTION_CATEGORY_INTERNATIONAL', 7 );
define( 'UX_QUESTION_CATEGORY_JUSTICE', 8 );
define( 'UX_QUESTION_CATEGORY_RESEARCH', 9 );
define( 'UX_QUESTION_CATEGORY_HEALTH', 10 );
define( 'UX_QUESTION_CATEGORY_SECURITY', 11 );
define( 'UX_QUESTION_CATEGORY_SOCIETY', 12 );
define( 'UX_QUESTION_CATEGORY_WORK', 13 );
define( 'UX_QUESTION_CATEGORY_OTHER', 14 );


class question
{
	
    public function __construct($app)
    {
    	$this->app = $app;
    }
    
    public function listQuestions( $status, $page, $category=UX_QUESTION_CATEGORY_ALL, $sorting=UX_QUESTION_SORTING_CHOOSE_BEST, $questions_per_page=40 )
    {
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");
		
		// List questions with this status


        if( $sorting == UX_QUESTION_SORTING_CHOOSE_BEST )
        {
		    if( $status == 'proposed' )
			    $sorting = UX_QUESTION_SORTING_VOTED;
		    else if( $status == 'vote' )    // Vote in progress
			    $sorting = UX_QUESTION_SORTING_DATE_VOTED;
		    else if( $status == 'decided' ) // Vote ended
			    $sorting = UX_QUESTION_SORTING_DATE_DECIDED;
			else if( $status == 'rejected' )
			    $sorting = UX_QUESTION_SORTING_DATE_DECIDED;
			else
			    $sorting = UX_QUESTION_SORTING_VOTED;
        }
        
        if( $category == UX_QUESTION_CATEGORY_ALL )
            $matching =  array( 'status' => $status );
        else
            $matching = array( 'status' => $status, 'category' => intval($category) );
        
        if( $sorting == UX_QUESTION_SORTING_HOTTEST )
        {
            $two_hours_ahead = time() + ( 2*3600 );

		    $questions_ordered = $db->questions->aggregate( 
		        array( 
		        
		                array( '$match' => $matching ),
		                array( '$project' => array( 
                                              'score' => array( 
                                                    '$divide' => array( 
                                                         array(
                                                            '$subtract' => array( '$vote', 0 )    // Divide number of votes minus one (don't take into account first voter)
                                                         ),
                                                         array( 
                                                            '$subtract' => array( $two_hours_ahead, '$date_proposed' ) // By time since proposed, +2 hours
                                                         ) )
                                               )
                                          )
                        ),
                        array( '$sort' => array( 'score' => -1 ) )
                        
                     )
                );       


            // Make sure the most voted question is always first, anytime
            $cursor = $db->questions->find( $matching, array( 'vote' => 1 ) )->sort( array( 'vote' => -1 ) );
		    $question = $cursor->getNext();
            $first_question_id = (string)$question['_id'];

            $questions_ids = array( $first_question_id );

		    foreach( $questions_ordered['result'] as $question_in_order )
		    {		    
		        $question_id = (string) $question_in_order['_id'];
		        if( $question_id != $first_question_id )
    		        $questions_ids[] = $question_id;
            }

        }
        else if( $sorting == UX_QUESTION_SORTING_RECENT )
        {
            $cursor = $db->questions->find( $matching, array( 'date_proposed' => 1 ) )->sort( array( 'date_proposed' => -1 ) );
            $questions_ids = array();
		    while( $question = $cursor->getNext() )
		    {
                $questions_ids[] = (string)$question['_id'];
            }
        }        
        else if( $sorting == UX_QUESTION_SORTING_VOTED )
        {
            $cursor = $db->questions->find( $matching, array( 'vote' => 1 ) )->sort( array( 'vote' => -1 ) );
            $questions_ids = array();
		    while( $question = $cursor->getNext() )
		    {
                $questions_ids[] = (string)$question['_id'];
            }
        }
        else if( $sorting == UX_QUESTION_SORTING_DATE_VOTED )
        {
            $cursor = $db->questions->find( $matching, array( 'date_vote' => 1 ) )->sort( array( 'date_vote' => 1 ) );
            $questions_ids = array();
		    while( $question = $cursor->getNext() )
		    {
                $questions_ids[] = (string)$question['_id'];
            }
        }
        else if( $sorting == UX_QUESTION_SORTING_DATE_DECIDED )
        {
            $cursor = $db->questions->find( $matching, array( 'date_decided' => 1 ) )->sort( array( 'date_decided' => 1 ) );
            $questions_ids = array();
		    while( $question = $cursor->getNext() )
		    {
                $questions_ids[] = (string)$question['_id'];
            }
        }
        else
        {
		    throw new \Exception( "Unknow sorrting method : ".$sorting );        
        }
        
        // Get all questions
        $cursor = $db->questions->find( $matching );
        $questions_data = array();
		while( $question = $cursor->getNext() )
		{
		    $questions_data[ (string)$question['_id'] ] = $question;
        }
        
		// Build answer
		$questions = array( 'list' => array() );
		
		// Pagination management
        $question_position = 1;
		$from = 1 + ( ( $page - 1 ) * $questions_per_page );
		$to = $from + $questions_per_page-1;
		
		
		foreach( $questions_ids as $question_id )
		{
		    if( $question_position >= $from && $question_position <= $to )
		    {		
		        if( isset( $questions_data[ $question_id ] ) )
		        {		    
		            $question = $questions_data[ $question_id ];
		            
			        if( $status == 'vote' || $status == 'proposed' )
			        {
				        // Retrieve current best answer
				        $argcursor = $db->args->find( array( 'question' => (string)$question['_id'], 'parent' => 0 ) );
				        $argcursor->sort( array( 'vote' => -1 ) );
				        $question['bestAnswer'] = array( $argcursor->getNext() );
				        $question['nbReponse'] = $argcursor->count();
			        }
			
			        if( $status == 'proposed' )
			        {
			            if( ! isset( $question['failedSelection'] ) )
			                $question['failedSelection'] = 0;

			            $question['remaining_attempts'] = ( $g_config['proposed_questions_selection_max_attempts'] - $question['failedSelection'] );
			        }
			        if( $status == 'vote' )
			        {
			            $question['date_vote_end'] = $question['date_vote'] + $g_config['current_question_vote_delay']*24*3600;
			        }


		            $questions['list'][] = $question;
                }
            }
            
            $question_position ++;
		}    

		$user = $this->app['current_user'];
		$questions['myvotes'] = array();
		if( $user->is_logged() )
		{
			// Get our own votes
			$db->questionvotes->createIndex( array( 'user' => 1 ) );
			$cursor = $db->questionvotes->find( array( 'user' => $user->id ) );
			while( $myvote = $cursor->getNext() )
			{
				$questions['myvotes'][ $myvote['question'] ] = 1;
			}
		} 
		
		return $questions;
    }
    
    public function getQuestionContent( $id, $sorting=UX_QUESTION_SORTING_HOTTEST )
    {
        global $g_config;
    
		$user = $this->app['current_user'];
		$user_id = null;
        if( $user->is_logged() )
        {
            $user_id = $user->id;
        }

		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");
		
		

		// Get this question	
		$res = $db->questions->findOne( array( '_id' => new \MongoId( $id )) );    

		if( $res ==null )
			return null;
	  	else
	  	{
			$result = array();
			$result['question'] = $res ;


            if( $sorting == UX_QUESTION_SORTING_HOTTEST )
            {
                $two_hours_ahead = time() + ( 2*3600 );

		        $args_ordered = $db->args->aggregate( 
		            array( 
		            
		                    array( '$match' => array( 'question' => $id ) ),
		                    array( '$project' => array( 
                                                  'score' => array( 
                                                        '$divide' => array( 
                                                             array(
                                                                '$subtract' => array( '$vote', 0 )    // Divide number of votes minus one (don't take into account first voter)
                                                             ),
                                                             array( 
                                                                '$subtract' => array( $two_hours_ahead, '$date' ) // By time since proposed, +2 hours
                                                             ) )
                                                   )
                                              )
                            ),
                            array( '$sort' => array( 'score' => -1 ) )
                            
                         )
                    );       


                // Make sure the most voted arg is always first, anytime
                $cursor = $db->args->find( array( 'question' => $id ), array( 'vote' => 1, 'date' => 1 ) )->sort( array( 'vote' => -1, 'date' => -1 ) );
		        $arg = $cursor->getNext();
		        $first_arg_id = null;
		        if( $arg )
		        {
                    $first_arg_id = (string)$arg['_id'];

                    $args_ids = array( $first_arg_id );
                }
                else
                    $args_ids = array();
                
		        foreach( $args_ordered['result'] as $arg_in_order )
		        {		    
		            $arg_id = (string) $arg_in_order['_id'];
		            if( $arg_id != $first_arg_id )
        		        $args_ids[] = $arg_id;
                }

            }
            else if( $sorting == UX_QUESTION_SORTING_RECENT )
            {
                $cursor = $db->args->find( array( 'question' => $id ), array( 'date' => 1 ) )->sort( array( 'date' => -1 ) );
                $args_ids = array();
		        while( $arg = $cursor->getNext() )
		        {
                    $args_ids[] = (string)$arg['_id'];
                }
            }        
            else if( $sorting == UX_QUESTION_SORTING_VOTED )
            {
                $cursor = $db->args->find( array( 'question' => $id ), array( 'date' => 1 ) )->sort( array( 'vote' => -1 ) );
                $args_ids = array();
		        while( $arg = $cursor->getNext() )
		        {
                    $args_ids[] = (string)$arg['_id'];
                }
            }

			// Get all args
			$cursor = $db->args->find( array( 'question' => $id ) );
            $args_datas = array();
			while( $arg = $cursor->getNext() )
			{
                $args_datas[ (string) $arg['_id'] ] = $arg;
            }            
		
			$parent_to_arglist = array();	// Parent to args

            foreach( $args_ids as $arg_id )
            {				
                $arg = $args_datas[ $arg_id ];
                
                if( $arg['author'] == $user_id )
                    $arg['author_is_you'] = true;

				if( ! isset( $parent_to_arglist[ $arg['parent'] ] ) )
					$parent_to_arglist[ $arg['parent'] ] = array();
				$parent_to_arglist[ $arg['parent'] ][] = $arg;
			}		
		
			// Now, build the answer structure
			$result['args'] = $this->buildArgStructure( 0, $parent_to_arglist );
		
			$result['myvotes'] = array();
			if( $user->is_logged() )
			{
				// Get our own votes
				$db->votes->createIndex( array( 'question' => 1, 'user' => 1 ) );
				$cursor = $db->votes->find( array( 'question' => $id, 'user' => $user->id ) );
				while( $myvote = $cursor->getNext() )
				{
					$result['myvotes'][ $myvote['arg'] ] = 1;
				}
			} 
			
			$questionFlow = $this->app['questionFlow'];
			$voted = $questionFlow->getVotedResponse( $id );
			$result['valid_answers'] = array();
			foreach( $voted as $voted_answer )
			{
			    $result['valid_answers'][(string)$voted_answer['_id']] = 1 ;
			}
			
			if( $result['question']['status'] == 'proposed' )
			{
			    if( ! isset( $result['question']['failedSelection'] ) )
			        $result['question']['failedSelection'] = 0;
			    $result['question']['remaining_attempts'] = ( $g_config['proposed_questions_selection_max_attempts'] - $result['question']['failedSelection'] );


                // Get also current user vote on this question if any

		        $result['questionvoted'] = false;
		        if( $user->is_logged() )
		        {
			        // Get our own votes
			        $db->questionvotes->createIndex( array( 'user' => 1 ) );
			        $anyvote = $db->questionvotes->findOne( array( 'user' => $user->id, 'question' => $id ) );
			        if( $anyvote )
			        {
				        $result['questionvoted'] = true;
			        }
		        } 
                
			}
			if( $result['question']['status'] == 'vote' )
			{
			    $result['question']['date_vote_end'] = $result['question']['date_vote'] + $g_config['current_question_vote_delay']*24*3600;
			}
			
			
			if( $result['question']['author'] == $user_id )
			    $result['question']['author_is_you'] = true;
			
			return $result;
		}   
    }
    
    public function proposeQuestion( $text, $category )
    {
		$user = $this->app['current_user'];
		$user->ensure_logged();
        $user->ensure_verified();
        
        $category = intval( $category );

		$gamification = $this->app['gamification'];
		
		// TODO: add a question submission limit (ex: maximum of 1 question per day per user)
		
		// Perform checks (ex: length)
		if( strlen( $text ) < 10 )
		    throw new \Exception( "Votre question doit au moins faire 10 caractères." );
		if( strlen( $text ) > 200 )
		    throw new \Exception( "Votre question est trop longue." );
		
		$timestamp = time();
		
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		// Check if a identical question does not exists already as "proposed"
        $existing = $db->questions->findOne( array(
		        'text' => $text,
		        'adm_area' => array(    // For now => France
		           'id' => 456,
		           'name' => 'France',
		           'lang' => 'fr'
		        ),
		        'status' => 'proposed', 
        ) );
	 	if( $existing !== null )
	 	    throw new \Exception( "Désolé, cette question a déjà été proposée." );
        
	
	    // TODO in the future: check if the question has been voted. If yes, we should ask user to confirm that he wants to re-propose a question.
	
		// Insert this new question
		//   check users limits
		$question = array(
		        'text' => $text,
		        'adm_area' => array(    // For now => France
		           'id' => 456,
		           'name' => 'France',
		           'lang' => 'fr'
		        ),
		        'status' => 'proposed', // proposed / ignored / vote / decided
		        'date_proposed' => $timestamp,
		        'category' => $category,
		        'failedSelection' => 0,   // Number of times this questions has not been selected during a selection
		        'author' => $user->id,
		        'vote' => 0              // number of citizen who wants/wanted to vote on this one
		);
		$res = $db->questions->insert( $question );
		
		if( $res )
		{
			$question_id = $question['_id'];
			
			$gamification->onContribute( $user->id );
			
			// Make current user vote for this question
			$this->voteForQuestion( (string)$question_id );
			
			return $question_id;
		}
		else
		{
			throw new \Exception( "Erreur durant l'insertion de la question" );
		}
		
	}
	
	public function updateQuestion( $question_id, $text, $category )
    {
		$user = $this->app['current_user'];
		$user->ensure_logged();
        $user->ensure_verified();

		$m = new  \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        $user_id = $user->id;

     
        // Get the current question
		$question = $db->questions->findOne( array( '_id' => new \MongoId( $question_id )) );    
		
		if( $question===null )
			return "Question not found";

        // We can only modify question that has not been voted yet
		if( $question['status'] != 'proposed' && $question['status'] != 'vote' )
			return "Le débat sur cette question est maintenant terminé";

		// Perform checks (ex: length)
		//   check the answer does not exists already TODO
		//   check users limits
		if( strlen( $text ) < 10 )
		    throw new \Exception( "Votre contribution doit au moins faire 10 caractères." );
		if( strlen( $text ) > 200 )
		    throw new \Exception( "Votre contribution est trop longue." );
        
        // I can do an immediate update in the following case:
        //  1. I am the author   
        //  2. Nobody except me has voted for this question

        $bImmediateModif = false;
        if( $question['author'] == (string)$user_id )
        {
            // Who votes for this question ???
            $count_votes = $db->questionvotes->find( array( 'question' => $question_id, 'user' => array( '$ne' => $user_id ) ), array( 'user' ) )->count();

            if( $count_votes == 0 )
            {
                $bImmediateModif = true;
            }
        }
        
        if( $bImmediateModif )
        {
            // Save the previous text in "history" field
            $db->questions->update(
      			array( '_id' => new \MongoId( $question_id )),
      			array(
      			    '$push' => array( 'history' => array( 'text' => $question['text'] ) )
      			)            
            );

      		$db->questions->update( 
      			array( '_id' => new \MongoId( $question_id )),
      			    array( '$set' => array("text" => $text, "category" => intval($category)) )
      			);
      			
            return $question_id;
        }
        else
        {
            // Add this update to the moderators panel
            $db->questionUpdateRequests->insert(
            
                array(
                    'question_id' => $question_id,
                    'author' => $user_id,
                    'before' => $question['text'],
                    'after' => $text,
                    'date' => time()
                )
            
            );
        
            return 0;
        }
    }
	
	public function postArg( $id, $text, $parent )
	{
		$user = $this->app['current_user'];
		$user->ensure_logged();
        $user->ensure_verified();

		$gamification = $this->app['gamification'];

		$m = new  \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		// Perform checks (ex: length)
		//   check the answer does not exists already TODO
		//   check users limits
		if( strlen( $text ) < 10 )
		    throw new \Exception( "Votre contribution doit au moins faire 10 caractères." );
		if( strlen( $text ) > 200 )
		    throw new \Exception( "Votre contribution est trop longue." );

		$question = $db->questions->findOne( array( '_id' => new \MongoId( $id )) );    
		
		if( $question===null )
			return "Question not found";

		if( $question['status'] != 'proposed' && $question['status'] != 'vote' )
			return "Le débat sur cette question est maintenant terminé";
		
		$timestamp = time();
		
		$db->args->createIndex( array('question' => 1) );
	 
	 	// Get parent
	 	if( $parent == 0 )
	 	{
	 		// Okay, parent = question
			$level = 1;
	 	}
	 	else
	 	{
	 		// Parent = another argument. Check level
	 		$level = 1;
	 		$ancestor_id = $parent;
	 		while( $ancestor_id != 0 && $level < 5 )
	 		{
	 			$level ++;
				$ancestor = $db->args->findOne( array( '_id' => new \MongoId( $ancestor_id )) );    
		
				if( $ancestor===null )
					throw new \Exception( "Ancestor not found" );
				else
				{
					$ancestor_id = $ancestor['parent'];
				}
			} 	
	 	}
	 	
		if( $level >= 4 )
			throw new \Exception( "Too much sub argument levels" );
	 	
	 	// Check that this argument does not exists already
	 	$existing = $db->args->findOne( array(
		        'text' => $text,
		        'parent' => $parent,
		        'question' => $id, 	
	 	) );
	 	if( $existing !== null )
	 	    throw new \Exception( "Désolé, cet argument a déjà été proposé." );
	 	
		// Insert new arg
		$arg = array(
		        'text' => $text,
		        'parent' => $parent,
		        'question' => $id,
		        'date' => $timestamp,
		        'author' => $user->id,
		        'vote' => 0              // number of citizen who votes for this argument
		);
		$res = $db->args->insert( $arg ); 

		if( $res )
		{
			$arg_id = $arg['_id'];
			
    		$gamification->onContribute( $user->id );

			// Make current user vote for this arg
			$this->voteForArg( (string)$arg_id );
			return  $arg_id ;
		}
		else
		{
			throw new \Exception( "Error during argument insertion" );
		}	
	}
    
    public function voteForQuestion( $id )
	{
		$user = $this->app['current_user'];
		$user->ensure_logged();
        $user->ensure_verified();
		
		$gamification = $this->app['gamification'];
		
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		$question = $db->questions->findOne( array( '_id' => new \MongoId( $id )) );    
		
		if( $question===null )
			throw new \Exception( "Question not found" );
	  	else
	  	{
	  		if( $question['status'] != 'proposed' )
	  		{
	  			throw new \Exception( "This question is not proposed anymore: cannot vote for it" );
	  		}
	  	
	  		// Check if this user already vote before
	  		$vote = array(
	  			'user' => $user->id,
	  			'question' => $id,
	  			'time' => time()
	  		);
	  		
	  		$db->questionvotes->createIndex( array('user'=>1,'question'=>1), array('unique'=>1 ) );
	  		
	  		try 
	  		{
	  		    $db->questionvotes->insert( $vote );	// Note: break if more than 1 vote
	  		}
	  		catch(\Exception $e)
	  		{
	  	        if( $e->getCode() == 11000 )        // Duplicate ID
	  	        {
	  	           // In this case, we muse "unvote"

                    $db->questionvotes->remove( array(
	          			'user' => $user->id,
	          			'question' => $id 
	          	    ) );

	          		$db->questions->update( 
	          			array( '_id' => new \MongoId( $id )),
	          			array('$inc' => array("vote" => -1))
	          			);
	          		
	          		if( isset( $question['author'] ) && $question['author'] != $user->id )
	          		{
	          			// -1 point for question author
                        $gamification->onUnvoteFor( $question['author'] );
			        }  		



                    return true;
	  	        }
	  		}
	  		
	  		$db->questions->update( 
	  			array( '_id' => new \MongoId( $id )),
	  			array('$inc' => array("vote" => 1))
	  			);
	  		
	  		if( isset( $question['author'] ) && $question['author'] != $user->id )
	  		{
	  			// +1 point for question author
                $gamification->onVoteFor( $question['author'] );
			}  		
	  		
	  		return true;
	  	}

	}
    
    
	public function voteForArg( $id )
	{
		$user = $this->app['current_user'];
		$user->ensure_logged();

        $user->ensure_verified();

		$gamification = $this->app['gamification'];
		
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

		$arg = $db->args->findOne( array( '_id' => new \MongoId( $id )) );    

		if( $arg===null )
    		throw new \Exception( "Arg not found: ".$id );
	  	else
	  	{
	  		$question_id = $arg['question'];

			$question = $db->questions->findOne( array( '_id' => new \MongoId( $question_id )) );    

			if( $question['status'] != 'proposed' && $question['status'] != 'vote' )
			    throw new \Exception( "Le débat sur cette question est maintenant terminé" );
	  	
	  		// Check if this user already vote before
	  		$vote = array(
	  			'user' => $user->id,
	  			'arg' => $id,
	  			'question' => $question_id,
	  			'time' => time()
	  		);
	 
	  		
	  		$db->votes->createIndex( array('user'=>1,'arg'=>1), array('unique'=>1 ) );
	  		
	  		try {
	  		    $db->votes->insert( $vote );
	  		}
	  		catch(\Exception $e)
	  		{
	  	        if( $e->getCode() == 11000 )        // Duplicate ID
	  	        {
	  	           // In this case, we muse "unvote"

                    $db->votes->remove( array(
	          			'user' => $user->id,
	          			'arg' => $id,
	          			'question' => $question_id,
	          	    ) );

	          		$db->args->update( 
	          			array( '_id' => new \MongoId( $id )),
	          			array('$inc' => array("vote" => -1))
	          			);

	          		if( isset( $arg['author'] ) && $arg['author'] != $user->id )
	          		{
                        $gamification->onUnvoteFor( $arg['author'] );
			        }  	



                    return true;
	  	        }
	  		}
	  	
	  		$db->args->update( 
	  			array( '_id' => new \MongoId( $id )),
	  			array('$inc' => array("vote" => 1))
	  			);

	  		if( isset( $arg['author'] ) && $arg['author'] != $user->id )
	  		{
                $gamification->onVoteFor( $arg['author'] );
			}  		

	  		
	  		return "Done";
		}
	}
    
    // Return the arguments tree which starts at node "parent_id"
	private function buildArgStructure( $parent_id, $parent_to_arglist )
	{
		if( ! isset( $parent_to_arglist[ $parent_id ] ) )
			return array();
		else
		{
			$result = $parent_to_arglist[ $parent_id ];
		
			foreach( $result as $id => $arg )
			{
				$result[$id]['args'] = $this->buildArgStructure( (string)$arg['_id'], $parent_to_arglist );
			}
		
			return $result;
		}
	}
	
	// Return all users that vote for that question
	public function getQuestionVoters( $question_id )
	{
	
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        // Get this question
		$result = $db->questions->findOne( array( '_id' => new \MongoId( $question_id )) );    

		if( $result ==null )
			return null;

        $result['voters'] = array();

        $cursor = $db->questionvotes->find( array( 'question' => $question_id ), array( 'user' ) );
        
		while( $voter = $cursor->getNext() )
		{
		    // Get username
		    $username = $db->users->findOne( array( '_id' => new \MongoId( $voter['user'] ) ), array( 'username' ) );
		    
		    $result['voters'][] = array(
		        'id' => $voter['user'],
		        'name' => $username['username']
		    );
		}

        
        return $result;
	}
	
	// Return all users that vote for that answer/arg
	public function getVoters( $arg_id )
	{
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        // Get this question
		$result = $db->args->findOne( array( '_id' => new \MongoId( $arg_id )) );    

		if( $result ==null )
			return null;

        $result['voters'] = array();

        $cursor = $db->votes->find( array( 'arg' => $arg_id ), array( 'user' ) );
        
		while( $voter = $cursor->getNext() )
		{
		    // Get username
		    $username = $db->users->findOne( array( '_id' => new \MongoId( $voter['user'] ) ), array( 'username' ) );
		    
		    $result['voters'][] = array(
		        'id' => $voter['user'],
		        'name' => $username['username']
		    );
		}

        
        return $result;
	}	
}
