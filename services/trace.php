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

//////// Manage Baztille points & trophy system /////////////////:

namespace baztille;

use Silex\ServiceProviderInterface;
use Silex\Application;

require_once("Log.php"); 

//////////////////////////////////
// Log levels:

define( "TLL_emergency", PEAR_LOG_EMERG );  // Not used for now, reserved
define( "TLL_alert", PEAR_LOG_ALERT );      // Not used for now, reserved
define( "TLL_critical", PEAR_LOG_CRIT );    // Not used for now, reserved
define( "TLL_error", PEAR_LOG_ERR );        // Standard error (ex: unexpected exception catched)
define( "TLL_warning", PEAR_LOG_WARNING );  // Non critical errors. Not supposed to happened in production. From this point a standard production environment error logs.
define( "TLL_notice", PEAR_LOG_NOTICE );    // Most important informations for an application without any error. From this point a standard production environment error logs.
define( "TLL_info", PEAR_LOG_INFO );        // Standard level for information in dev environment. From this point a standard dev environment write logs.
define( "TLL_debug", PEAR_LOG_DEBUG );      // Not supposed to be logged nowhere, except temporarly in dev environment when a specific need is required




class trace
{

    public function __construct($app)
    {
		$this->app = $app;
    }


    var $start_page_time;
    var $page_generation_time;
    var $exception_message = "";
    var $log = null;
    var $log_error = null;
    var $email_log = null;
    var $current_uri = '';
    var $request_status = 'OK-0';    
    var $profiling_output = '';
    var $is_job = false;
        
    function start_page( $start_time, $is_job )
    {
        global $g_config;
        $log_level = null;
        switch( $g_config['log_level'] )
        {
        case 'debug':   $log_level = TLL_debug;
                        break;
        case 'info':   $log_level = TLL_info;
                        break;
        case 'notice':   $log_level = TLL_notice;
                        break;
        case 'warning':   $log_level = TLL_warning;
                        break;
        case 'error':   $log_level = TLL_error;
                        break;
        }


        $log_config = array('mode' => 0666, 'timeFormat' => '%d/%m %H:%M:%S', 'lineFormat'=> '%1$s [%3$s] %4$s');
    
        error_reporting(error_reporting() & !E_STRICT);   
        $this->log = &\Log::singleton("file", $g_config['app_log_path']."baztille.log", 'main', $log_config, $log_level );
        $this->log_error = &\Log::singleton("file", $g_config['app_log_path']."errbaztille.log", 'main', $log_config, TLL_warning );
        $this->email_log = &\Log::singleton("file", $g_config['app_log_path']."mailsent.log", 'main', $log_config, TLL_info );
        error_reporting( $g_config['error_reporting_level'] ); 
        $this->start_page_time = $start_time;        

        if( $is_job )
        {
            $this->is_job = true;
            $this->current_uri = $_SERVER['argv'][2];
            $this->log( TLL_notice, "(job) ".$this->current_uri );       
        }
        else
        {
            $this->current_uri = $_SERVER['REQUEST_URI'];
            
            $this->log( TLL_info, $this->current_uri ); // We log current URI at "info" level because a complete log (with error state and execution time is log at "notice" level at the end       
        }
    }
        
    function end_page( $is_job )
    {
        global $g_config;
        $endtime = explode(' ', microtime());
        
        $this->page_generation_time = ceil( ( $endtime[0] + $endtime[1] - $this->start_page_time )*1000 ); 

        $log = $this->request_status.' '.$this->page_generation_time.' '.$this->current_uri;

        $this->log( TLL_notice, $log );
    }
    

    function logFatalException( $exception )
    {
    // TODO: distinguidh between expected/unexpected errors
/*        if( $exception->isExpected() )
        {
            // This error can happen.
            $this->log( TLL_notice, "Exception: ".$exception->getMessage() );
            $this->request_status = "EX-".$exception->getCode();
        }
        else*/
        {
            // This is a real error. We have to report an error in logs with stack trace
            $this->log( TLL_error, "Unexpected exception: ".$exception->getMessage()."\n".$exception->getTraceAsString() );
            $this->request_status = "ER-".$exception->getCode();
        }
    }
    
    
    // Main entry for logging
    function log( $category, $message )
    {        
        $log = '';
        
        if( $this->log === null )
            return;

        $log_error_in_additional_file = null;

        if( $this->is_job )
        {
            $log .= '[J] [job] '.$message;
        }
        else
        {
            
    	//	$user = $this->app['current_user'];
         //   $name = $user->is_logged() ? $user->id : 'visitor';;
         $name = '?';
            $log .= '['.$name.'] '.$message;
        }
            
            
        $timezone = date_default_timezone_get();
        date_default_timezone_set( 'Europe/Berlin' );
        $this->log->log( $log, $category );
        
        $this->log_error->log( $log, $category );
        
        
        date_default_timezone_set( $timezone );
    }
    
    // Specific logging for email
    function email_log( $email_adress, $email_label, $language, $subject, $body )
    {
        if( $this->email_log === null )
            return;

        $log = 'To: '.$email_label.'<'.$email_adress.">\n";
        $log .= 'Subject: '.$subject."\n";
        $log .= "Content (".$language."):\n".$body."\n\n";

        $timezone = date_default_timezone_get();
        date_default_timezone_set( 'Europe/Berlin' );        
        $this->email_log->log( $log, TLL_info );
        date_default_timezone_set( $timezone );
    }
    
   

}
  

