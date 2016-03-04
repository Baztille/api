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

class user
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }
    
    function passwordHash( $username, $password )
	{
		global $g_config;
        return password_hash( $username.$password, PASSWORD_DEFAULT );
	}

   
   	public function createUser( $username, $password, $email )
   	{
		// Check if there is no existing user with this username/email
	
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		if( $db->users->findOne( array( 'username' => $username ))  !== null )
			throw new \Exception( "Ce nom d'utilisateur est déjà pris" );
		if( $db->users->findOne( array( 'email' => $email ))  !== null )
			throw new \Exception(  "Un utilisateur avec le même email est déjà enregistré" );
        if( ! $this->checkValidEmail( $email ) )
			throw new \Exception(  "Il est important pour nous de pouvoir vous contacter : merci d'entrer votre véritable email." );

   	    $randomCode = $this->generateRandomString(32);

		// Alright, insert new user
		$user = array(
			'username' => $username,
			'email' => $email,
			'password' => $this->passwordHash( $username, $password ),
			'registration_date' => time(),
			'lang' => 'fr',	// TODO
			'points' => 0,	// Start with 0 points !
			'pointsdetails' => array(
			    'vote_for_me' => 0,         // Number of times someone vote for me
			    'contribution_day' => 0,    // Number of days with a contribution from me
			    'referee' => 0,             // Number of users recruted for Baztille
			    'validated_question' => 0,  // Number of questions validated by the community
			    'validated_answer' => 0,    // Number of answers validated by the community
			    'money_contribution' => 0,  // Amount of money gived to the community
			    'trophy_bonus' => 0,        // Points bring by trophies
			    'trophies' => array()       // List of trophies 
			),
			'last_contribution' => null,    // Date of the last published contribution (question/answer/date)
			'verified' => false,	// verified = member
			'email_verified' => false,
			'email_verified_code' => $randomCode,
			'firstLogin' => true
		);
		$db->users->insert( $user );
		
		// Now, log user in automatically
		$currentUser = $this->app['current_user'];
		$res = $currentUser->login( $username, $password );

        // Finally, send user verification email
        $this->sendConfirmationEmailToUser( $user['_id'] );

		return $res;   	
   	}
   	
   	public function reconfirmEmail()
   	{
        $currentUser = $this->app['current_user']->getdatas();
   	
        $this->sendConfirmationEmailToUser( $currentUser['user_id'] );
   	}
   	
   	private function sendConfirmationEmailToUser( $id )
   	{
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $id ) ), array('email', 'username', 'email_verified_code') );

        if( $userdatas === null )
            throw new \Exception( "User not found" ); 
            
        $randomCode = $userdatas['email_verified_code'];
            
        // Finally, send user verification email
        global $g_config;
        $url = $g_config['app_webservice_url']. "/user/verifyemail/".$randomCode;

        // Send email
        
        $title = "Confirmation de votre adresse email";
        
        $body = "Merci d'avoir rejoint Baztille.";
        $body .= "\n\n";
        $body .= "Afin de pouvoir voter et contribuer, merci de confirmer votre email en cliquant sur le lien ci-dessous :";
        $body .= "\n";
        $body .= $url;
        $body .= "\n\n";

	    $notifier = $this->app['notifier'];
        $notifier->sendEmailToUniqueUser( $id, $title, $body  );        		
   	}
   	
   	private function checkValidEmail(  $email )
    {
        // Check email domain in order to see if this is not
        $var =explode('@', $email);
        $domain = array_pop($var);

        // List of temporary emails:
        // http://torvpn.com/temporaryemail.html
        
        if( in_array( $domain, array(
            '0-mail.com','0815.ru','0clickemail.com',
            '10minutemail.com','20minutemail.com','2prong.com',
            '30minutemail.com','3d-painting.com','4warding.com',
            '4warding.net','4warding.org','60minutemail.com',
            'amilegit.com','anonbox.net','anonymbox.com',
            'antispam.de','beefmilk.com','binkmail.com',
            'bio-muesli.net','bobmail.info','bofthew.com',
            'bootybay.de','brefmail.com','bsnow.net',
            'bugmenot.com','bumpymail.com','cosmorph.com',
            'courrieltemporaire.com','cubiclink.com','curryworld.de',
            'cust.in','dacoolest.com','dandikmail.com',
            'dayrep.com','deadaddress.com','despam.it',
            'devnullmail.com','discardmail.com','discardmail.de',
            'disposemail.com','dispostable.com','dodgeit.com',
            'dodgit.com','dodgit.org','doiea.com',
            'donemail.ru','dontreg.com','dontsendmespam.de',
            'drdrb.net','dump-email.info','dumpyemail.com',
            'e4ward.com','email60.com','emailigo.de',
            'emailinfive.com','emailmiser.com','emailsensei.com',
            'emailtemporario.com.br','emailwarden.com','emailx.at.hm',
            'evopo.com','fakeinbox.com','fakeinformation.com',
            'fastacura.com','filzmail.com','fizmail.com',
            'fr33mail.info','get1mail.com','get2mail.fr',
            'getonemail.com','getonemail.net','gishpuppy.com',
            'great-host.in','guerillamail.com','guerrillamail.com',
            'guerrillamailblock.com','h.mintemail.com','haltospam.com',
            'hochsitze.com','hotpop.com','hulapla.de',
            'ieatspam.eu','ieatspam.info','ieh-mail.de',
            'imails.info','incognitomail.com','incognitomail.net',
            'incognitomail.org','insorg-mail.info','ipoo.org',
            'jetable.com','jetable.net','jetable.org',
            'jnxjn.com','junk1e.com','keepmymail.com',
            'kir.ch.tc','klzlk.com','kulturbetrieb.info',
            'lhsdv.com','litedrop.com','lol.ovpn.to',
            'lookugly.com','lopl.co.cc','m4ilweb.info',
            'mail-temporaire.fr','mail.by','mail4trash.com',
            'mailcatch.com','maileater.com','mailexpire.com',
            'mailimate.com','mailin8r.com','mailinator.com',
            'mailinator.net','mailinator2.com','mailismagic.com',
            'mailmate.com','mailme.ir','mailme.lv',
            'mailmetrash.com','mailnator.com','mailnesia.com',
            'mailnull.com','mailslite.com','mailtemp.info',
            'mailzilla.org','mbx.cc','meltmail.com',
            'messagebeamer.de','mierdamail.com','mintemail.com',
            'moburl.com','monemail.fr.nf','msa.minsmail.com',
            'mt2009.com','mypartyclip.de','myphantomemail.com',
            'mytrashmail.com','nepwk.com','no-spam.ws',
            'nobulk.com','noclickemail.com','nogmailspam.info',
            'nomail2me.com','nomorespamemails.com','nospam4.us',
            'nospamfor.us','nospamthanks.info','notmailinator.com',
            'nowmymail.com','nus.edu.sg','nwldx.com',
            'onewaymail.com','online.ms','opayq.com',
            'ovpn.to','owlpic.com','pjjkp.com',
            'plexolan.de','politikerclub.de','pookmail.com',
            'prtnx.com','qq.com','quickinbox.com',
            'recode.me','regbypass.com','rmqkr.net',
            'rppkn.com','rtrtr.com','s0ny.net',
            'safe-mail.net','safetymail.info','safetypost.de',
            'sandelf.de','saynotospams.com','selfdestructingmail.com',
            'sendspamhere.com','sharklasers.com','shitmail.me',
            'skeefmail.com','slopsbox.com','smellfear.com',
            'snakemail.com','sofimail.com','sofort-mail.de',
            'sogetthis.com','spam.la','spam.su',
            'spamavert.com','spambob.net','spambob.org',
            'spambog.com','spambog.de','spambog.net',
            'spambog.ru','spambox.info','spambox.irishspringrealty.com',
            'spambox.us','spamcero.com','spamday.com',
            'spamfree24.com','spamfree24.de','spamfree24.eu',
            'spamfree24.info','spamfree24.net','spamfree24.org',
            'spamgourmet.com','spamherelots.com','spamhole.com',
            'spamify.com','spaminator.de','spamkill.info',
            'spaml.com','spaml.de','spammotel.com',
            'spamobox.com','spamspot.com','spamthis.co.uk',
            'spamthisplease.com','supergreatmail.com','supermailer.jp',
            'suremail.info','teewars.org','teleworm.com',
            'teleworm.us','tempalias.com','tempe-mail.com',
            'tempemail.biz','tempemail.com','tempemail.net',
            'tempinbox.co.uk','tempinbox.com','tempmail.it',
            'tempmail2.com','tempomail.fr','temporarioemail.com.br',
            'temporaryemail.net','temporaryinbox.com','thanksnospam.info',
            'thankyou2010.com','thisisnotmyrealemail.com','throwawayemailaddress.com',
            'tmailinator.com','toiea.com','tradermail.info',
            'trash-amil.com','trash-mail.com','trash-mail.de',
            'trash2009.com','trashemail.de','trashmail.at',
            'trashmail.com','trashmail.net','trashmail.ws',
            'trashmailer.com','trashymail.com','trashymail.net',
            'trillianpro.com','tyldd.com','uggsrock.com',
            'veryrealemail.com','webm4il.info','wegwerfemail.de',
            'wh4f.org','whyspam.me','willselfdestruct.com',
            'wuzupmail.net','yopmail.com','yuurok.com',
            'zehnminutenmail.de','zippymail.info','zoaxe.com'        
        ) ) )
            return false;
        else
            return true;
    }
   	
   	public function confirmEmail( $code )
   	{
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $db->users->findOne( array( 'email_verified_code' => $code ));
   	
		if( $user === null )
			throw new \Exception(  "Erreur. Merci de cliquer sur le lien contenu dans le dernier email reçu." );
			
        // Set this user as verified
   	    $db->users->update( 
   	         			array( '_id' => new \MongoId( $user['_id'] )),
	          			array('$set' => array("email_verified" => true ))
	          			);
        
   	}
   	
   	public function forgetpassword( $email )
   	{
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $db->users->findOne( array( 'email' => $email ));
		
		if( $user === null )
			throw new \Exception(  "We have no one using this email" );
   	
   	    // Okay, create a password recovery code
   	    $randomCode = $this->generateRandomString(32);
   	    $db->users->update( 
   	         			array( '_id' => new \MongoId( $user['_id'] )),
	          			array('$set' => array("passwordrecovery" => $randomCode ))
	          			);

        $url = $g_config['app_base_url']. "/#/newpassword/".$randomCode;

        // Send email
        
        $title = "Récupération de votre mot de passe";
        
        $body = "Quelqu'un (peut être vous) a demandé la modification de votre mot de passe Baztille (compte `".$user['username']."`).";
        $body .= "\n\n";
        $body .= "Si vous n'êtes pas à l'origine de cette demande, merci d'ignorer cet email.";
        $body .= "\n\n";
        $body .= "Sinon, cliquez ici pour modifier votre mot de passe :";
        $body .= "\n";
        $body .= $url;
        $body .= "\n\n";

	    $notifier = $this->app['notifier'];
        $notifier->sendEmailToUniqueUser( $user['_id'], $title, $body  );        		

   	}
   	
   	private function generateRandomString($length = 10) 
   	{
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    public function changepassword( $code, $password )
    {
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

		$user = $db->users->findOne( array( 'passwordrecovery' => $code ));
		
		if( $user === null )
			throw new \Exception( "Erreur. Merci de recliquer sur le lien dans le DERNIER email reçu." );
			
	    // Okay, update password !
	    
   	    $db->users->update( 
   	         			array( '_id' => new \MongoId( $user['_id'] )),
	          			array(
	          			    '$set' => array("password" => $this->passwordHash( $user['username'], $password ) ),
	          			    '$unset' => array("passwordrecovery"=>true)
	          			    )
	          			);
	    
    
    }
    
    public function sendContactMessage( $text )
    {
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );

        // Get current user infos
        $currentUser = $this->app['current_user']->getdatas();
        $userdatas = $db->users->findOne( array( '_id' => new \MongoId( $currentUser['user_id'] ) ), array('email', 'username') );


        $username = $userdatas['username'];
        $email = $userdatas['email'];


        // Send email
        
        $title = "Nouveau message depuis le formulaire de contact";
        
        $body = "Message envoyé par $username ($email).";
        $body .= "\n\n";
        $body .= $text;
        $body .= "\n\n";

	    $notifier = $this->app['notifier'];
        $notifier->sendEmailToAdmin( $title, $body, $email  );      
    }
    


}
