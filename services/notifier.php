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
    
        // Log this into a specific "mailsent" file
        $trace = $this->app['trace'];
        $trace->email_log( $email, $email, 'fr', $subject, $body );

        if( $g_config['send_real_emails'] ) {
            $message = \Swift_Message::newInstance()
            ->setCharset('utf-8')
            ->setSubject($subject)
            ->setTo($email)
            ->setFrom(array('contact@baztille.org' => 'Baztille'))
            ->setReplyTo(array('contact@baztille.org' => 'Baztille'))
            ->setBody($this->app['twig']->render('email.html.twig', array('body'=> $body, 'subject' => $subject, 'appbaseurl' => $g_config['app_base_url'])), 'text/html');

            $this->app['mailer']->send($message);
        }
            
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
        $body = "<h1 class='baz_toptitle'>Bravo !</h1><br>La question que vous avez proposée (<i>\"".$question['text']."\"</i>) a été celle jugée la plus intéressante par la communauté Baztille et vient d'être proposée au vote.";
        //$body .= "\n\n";
        $body .= "<br><a href=\"".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] )."\" class=\"btn-primary\">Voir la question</a>";
        $body .= "<br>... et vous avez gagné 100 points Baztille !";

        $this->sendEmailToUniqueUser( $question['author'], $title, $body );
    
    
    
        // 2°) Notify EVERYONE that it's now time to vote
    
    
        $subject = $question['text'];
        
        $body = "<h1 class='baz_toptitle'>C'est le moment de voter !</h1><br>La question suivante est en débat pour une durée de ".$g_config['current_question_vote_delay']." jours.";
        $body .= "<br><br><b>".$question['text']."</b>";
        $body .= "<br><a href=\"".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] )."\" class=\"btn-primary\">Votez</a>";
        
        //$body .= "<h2>Proposez la prochaine question</h2>";
        $body .= "<br><br>Vous pouvez aussi proposer et choisir la question qui sera posée la semaine prochaine.";
        $body .= "<br><a href=\"".$g_config['app_base_url']."/#/question/proposed\" class=\"btn-secondary\">Proposez une question</a>";
                
        // Last decisions
        
        $body .= "<h2>Résultats des derniers votes</h2><br>";

		// Last questions voted
        $days_ago = time() - 31*24*3600;
		$cursor = $db->questions->find( array('status' => 'decided' ) );
		$cursor->sort( array( 'date_decided' => -1 ) );

        $n=0;
        while( $lastDecidedItem = $cursor->getNext() )
        {
            $body .= "<b>\"".$lastDecidedItem['text']."\"</b>";
            foreach( $lastDecidedItem['validanswers'] as $validanswer )
            {
                $body .= "<br>".$validanswer['text'];
                $body .= "<br><br>";
            }

            $n++;
            if( $n>2 )
                break;
        }     
        $body .= "<br><a href=\"".$g_config['app_base_url']."/#/question/voted\" class=\"btn-secondary\">Tous les résultats</a> ";

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
                $body = "<h1 class='baz_toptitle'>Bravo !</h1><br>la réponse que vous avez proposée (\"<i>".$answer['text']."</i>\") pour la question \"<i>".$question['text']."</i>\" a été approuvée par la communauté Baztille.";

                $body .= "<br><a href=\"".$g_config['app_base_url'].'/#/question/questions/'.( (string)$question['_id'] )."\" class=\"btn-primary\">Voir la question</a>";
                $body .= "<br>... et vous avez gagné 100 points Baztille !";

                $this->sendEmailToUniqueUser( $answer['author'], $title, $body );
            }
        }   
        
        
        // 2°) Notify EVERYONE that the vote is over
        
        $subject = "Résultat du vote : ".$bestAnswer['text'];
        
        if( count( $question['validanswers'] ) == 1 )
            $body = "<h1 class='baz_toptitle'>Vote terminé</h1><br>Le vote sur la question \"".$question['text']."\" est maintenant terminé et la réponse ci-dessous a été approuvée par la communauté Baztille :";
        else
            $body = "<h1 class='baz_toptitle'>Vote terminé</h1><br>Le vote sur la question \"".$question['text']."\" est maintenant terminé et les réponses ci-dessous ont été approuvées par la communauté Baztille :";

        $body .= "<br>";
        foreach( $question['validanswers'] as $validanswer )
        {
            $body .= "<b>".$validanswer['text']."</b>";
            $body .= "<br>";
        }
        $body .= "<br><br>";

        $body .= "Il vous reste encore 24h pour décider de la prochaine question qui sera posée à la communauté :";
        $body .= "<br><a href=\"".$g_config['app_base_url']."/#/question/proposed\" class=\"btn-primary\">Voir les question</a>";

        // Last decisions
        
        $body .= "<h2>Résultats des derniers votes</h2><br>";

		// Last questions voted
        $days_ago = time() - 31*24*3600;
		$cursor = $db->questions->find( array('status' => 'decided' ) );
		$cursor->sort( array( 'date_decided' => -1 ) );

        $n=0;
        while( $lastDecidedItem = $cursor->getNext() )
        {
            $body .= "<b>\"".$lastDecidedItem['text']."\"</b>";

            foreach( $lastDecidedItem['validanswers'] as $validanswer )
            {
                $body .= "<br>".$validanswer['text'];
                $body .= "<br><br>";
            }

            $n++;
            if( $n>2 )
                break;
        }   
        $body .= "<br><a href=\"".$g_config['app_base_url']."/#/question/voted\" class=\"btn-secondary\">Tous les résultats</a> ";
        
        $this->sendEmailToAllUsers( $subject, $body );
    }



}
