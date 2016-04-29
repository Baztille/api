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


$g_config = array();




$g_config['silex_autoload_location'] =  __DIR__.'/../vendor/autoload.php';



////////// Logs & error configuration //////////////

$g_config['log_level'] = 'info';  // Note: info for dev, notice for production
$g_config['app_log_path'] = __DIR__.'/../../log/';
$g_config['error_reporting_level'] = ( E_ALL | E_STRICT ) & ~E_DEPRECATED;
error_reporting( $g_config['error_reporting_level'] );


////////// Application paths and URLs //////////////

$g_config['app_domain'] = "baztille.dev";
$g_config['app_base_url'] = "http://localhost:8100/";
$g_config['app_website_name'] = "Baztille";
$g_config['app_webservice_url'] = "http://baztille.dev";

$g_config['password_salt'] = 'TO_BE_CHANGED';

$g_config['jobs_password'] = 'TO_BE_CHANGED';


$g_config['db_name'] = 'baztille';

/////////// Question flow ////////////////////


// Delay (days) during which a current question can be voted
$g_config['current_question_vote_delay'] = 6;

// Number of attempts for a question to be selected (afterwards it is removed)
$g_config['proposed_questions_selection_max_attempts'] = 4;


/////////////////////////

$g_config['send_real_emails'] = true;


$g_config['apiKey'] = '<YOURAPIKEY>';
$g_config['moderation_password'] = '12345';

$g_config['moderators_emails'] = array();


// Categories
// (note : must be identical to the list in APP ux_question.js)

$g_config['categories'] = array(
    1 => 'aucune',
    2 => 'Culture', 
    3 => 'Economie', 
    4 => 'Education', 
    5 => 'Environnement',
    6 => 'Etat',
    7 => 'International',
    8 => 'Justice',
    9 => 'Recherche',
    10 => 'Santé',
    11 => 'Sécurité',
    12 => 'Societé',
    13 => 'Travail',
    14 => 'Baztille',
    15 => 'Autres'
);


//// Additional instances
// (WS servers names => additional configuration file)

$g_config['instances'] = array(

    // Ex :
    // 'ws.myotherinstance.org' => '/path/to/config/file'

);


