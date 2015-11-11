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

//////////////////////////////////////////////////////////////////////////////////////////////
/////     Baztille controllers
/////

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/c_error.php';

//////////////////////////////////////////
// Webservice sections

// All that is related to session: login, logout...
$app->mount('/session', include 'c_session.php');

// All backoffice function, restricted to admin only (and cronjobs)
$app->mount('/admin', include 'c_admin.php');

// All that is related to questions (questions list of specific question), including arguments and votes
$app->mount('/question', include 'c_question.php');

// All that is related to user: signin, preferences, view
$app->mount('/user', include 'c_user.php');

// All that is related to user: signin, preferences, view
$app->mount('/news', include 'c_news.php');



// Generic services
$app->get('/', function( ) use ($app) {

    $options = getopt( 'p:j:' );

    if( $options === false || count( $options ) != 2 )
    {
        return ':)';
    }
    else
    {
        global $g_config;
        if( $options['p'] == $g_config['jobs_password'] )
        {
            // Trigger the corresponding job
            $app['jobs']->$options['j']();
            
            return "\n".date('Y-m-d G:i:s').'  '."Done\n";
        }
        else
            return ":(";
    }
} );


$app->get('/version', function( ) use ($app) {
    global $baztille_version;
    return $app->json( array( 'version' => $baztille_version ),201, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));
} );




