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

//////// Manage contacts with users through emails, mobile notifications and application notifications /////////////////:

namespace baztille;

class notifier
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }
    
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////  UTILITY FUNCTIONS //////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    
    public function sendEmailToUniqueUser( $user_id, $title, $body )
    {
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");

        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $user_id ) ), array('email', 'username') );
        $this->sendEmail( $userdatas['email'], $title, $body );
    }
    
    public function sendEmailToAdmin( $title, $body, $reply_to='Baztille <contact@baztille.org>' )
    {
        $this->sendEmail( 'contact@baztille.org', $title, $body, $reply_to );
    }

    public function sendEmailToAllUsers( $title, $body )
    {
        // Send emails to the whole database
        // TODO: filter user who unsubsribe the emails from this type

		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");
        
        $cursor = $db->users->find( array(), array( 'email', 'username', 'lang' ) );
        while( $user = $cursor->getNext() )
        {
            if( $user['email'] != '' )
            {
                $this->sendEmail( $user['email'], $title, $body );
            }
        }        
    }
    
    private function sendEmail( $email, $subject, $body, $reply_to='Baztille <contact@baztille.org>' )
    {
        global $g_config;
    
        $body .= "\n\n\n\n";
        $body .= "Cet email a été envoyé par Baztille";   
        $body .= "\n";
        $body .= "http://baztille.org";

        $headers = 'From: Baztille <contact@baztille.org>' . "\r\n" .
            'Reply-To: '.$reply_to . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Log this into a specific "mailsent" file
        $trace = $this->app['trace'];
        $trace->email_log( $email, $email, 'fr', $subject, $body );

        if( $g_config['send_real_emails'] )
            mail($email, $subject, $body, $headers);     
    }

    public function sendMobileNotifToUniqueUser( $user_id, $body )
    {
        // TODO
    }

    public function sendMobileNotifToAllUsers( $body )
    {
        // TODO
    }


    public function recordNotifForUniqueUser( $user_id, $notif )
    {
        // TODO
    }

    public function recordNotifForAllUsers( $notif )
    {
        // TODO
    }


    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////  HOOK FUNCTIONS ////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////

    
    
    // Called when a question has been selected to open to vote
    // (used to notify question author than the question is selected + EVERYONE that it's time to vote)
    public function onQuestionSelected( $question )
    {
        global $g_config;

        if( $question === null )
    		throw new \Exception( "onQuestionSelected: Question empty" );
        
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");


        // 1°) Notify question author that his question has been selected
    
        $title = "Félicitations : votre question a été sélectionnée !";
        $body = "Bravo : la question que vous avez proposée (`".$question['text']."`) a été celle jugée la plus intéressante par la communauté Baztille et vient d'être proposée au vote.";
        $body .= "\n\n";
        $body .= "Vous avez gagné 100 points Baztille !";
        $body .= "\n\n";
        $body .= "Voir la question : ".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] );

        $this->sendEmailToUniqueUser( $question['author'], $title, $body );
    
    
    
        // 2°) Notify EVERYONE that it's now time to vote
    
    
        $subject = $question['text'];
        
        $body = "La question ci-dessous est en débat pour une durée de ".$g_config['current_question_vote_delay']." jours. Proposez, débattez, décidez et votez !";
        $body .= "\n\n";
        $body .= '  "'.$question['text'].'"';
        $body .= "\n";
        $body .= "  Votez: ".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] );
        $body .= "\n\n\n";
        
        
        $body .= "\n\n\n";
        $body .= "Vous pouvez aussi proposer et choisir la question qui sera posée la semaine prochaine :";
        $body .= "\n";
        $body .= $g_config['app_base_url'].'/#/question/proposed';
        $body .= "\n\n\n";

                
        // Last decisions
        
        $body .= "Résultats des derniers votes:";
        $body .= "\n";
        $body .= "\n";

		// Last questions voted
        $days_ago = time() - 31*24*3600;
		$cursor = $db->questions->find( array('status' => 'decided' ) );
		$cursor->sort( array( 'date_decided' => -1 ) );

        $n=0;
        while( $lastDecidedItem = $cursor->getNext() )
        {
            $body .= '  "'.$lastDecidedItem['text'].'"';
            $body .= "\n";
            foreach( $lastDecidedItem['validanswers'] as $validanswer )
            {
                $body .= "    Réponse votée: ".$validanswer['text'];
                $body .= "\n";
            }
            $body .= "\n";

            $n++;
            if( $n>2 )
                break;
        }     
        $body .= "  Tous les résultats: ".$g_config['app_base_url'].'/#/question/voted';

        $this->sendEmailToAllUsers( $subject, $body );
    }

    // Called when a proposed question is rejected (after X attemps)
    // (used to notify approved answer author)
    public function onQuestionRejected( $question )
    {
        // TODO
    }


    // Called when a question vote is closed
    // (used to notify approved answer(s) author(s) + EVERYONE that the the vote is over + EVERYONE that it's time to choose the next question)
    public function onAnswerApproved( $question )
    {
        // 1°) Notify answers authors
        
        global $g_config;

        if( $question === null )
    		throw new \Exception( "onQuestionSelected: Question empty" );
        
		$m = new \MongoClient(); // connect
		$db = $m->selectDB("baztille");
		
		$bestAnswer = null;

        foreach( $question['validanswers'] as $answer )
        {
            if( $bestAnswer === null )
                $bestAnswer = $answer;
        
            if( $answer['author'] )
            {
                $title = "Félicitations : votre réponse a été approuvée !";
                $body = "Bravo : la réponse que vous avez proposée (`".$answer['text']."`) pour la question `".$question['text']."` a été approuvée par la communauté Baztille.";
                $body .= "\n\n";
                $body .= "Vous avez gagné 100 points Baztille !";
                $body .= "\n\n";
                $body .= "Voir la question : ".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] );

                $this->sendEmailToUniqueUser( $answer['author'], $title, $body );
            }
        }   
        
        
        
        // 2°) Notify EVERYONE that the vote is over
        
        $subject = "Résultat du vote : ".$bestAnswer['text'];
        
        if( count( $question['validanswers'] ) == 1 )
            $body = "Le vote sur la question `".$question['text']."` est maintenant terminé et la réponse ci-dessous a été approuvée par la communauté Baztille :";
        else
            $body = "Le vote sur la question `".$question['text']."` est maintenant terminé et les réponses ci-dessous ont été approuvées par la communauté Baztille :";

        $body .= "\n\n";
        foreach( $question['validanswers'] as $validanswer )
        {
            $body .= "    ".$validanswer['text'];
            $body .= "\n";
        }
        $body .= "\n";
        $body .= "\n";
        $body .= "\n";

        $body .= "Il vous reste encore 24h pour décider de la prochaine question qui sera posée à la communauté :";
        $body .= "\n";
        $body .= "    ".$g_config['app_base_url'].'/#/question/proposed';
        $body .= "\n\n\n";

        // Last decisions
        
        $body .= "Résultats des derniers votes:";
        $body .= "\n";
        $body .= "\n";

		// Last questions voted
        $days_ago = time() - 31*24*3600;
		$cursor = $db->questions->find( array('status' => 'decided' ) );
		$cursor->sort( array( 'date_decided' => -1 ) );

        $n=0;
        while( $lastDecidedItem = $cursor->getNext() )
        {
            $body .= '  "'.$lastDecidedItem['text'].'"';
            $body .= "\n";
            foreach( $lastDecidedItem['validanswers'] as $validanswer )
            {
                $body .= "    Réponse votée: ".$validanswer['text'];
                $body .= "\n";
            }
            $body .= "\n";

            $n++;
            if( $n>2 )
                break;
        }     
        $body .= "  Tous les résultats: ".$g_config['app_base_url'].'/#/question/voted';
        
        $this->sendEmailToAllUsers( $subject, $body );
    }



}
