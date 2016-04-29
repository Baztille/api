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


use Symfony\Component\HttpFoundation\Request;

$c_admin = $app['controllers_factory'];

$c_admin->post( '/selectHottestQuestion', function( Request $request ) use ($app) {
	$question_id = $app['questionFlow']->selectHottestQuestion();
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'id' => $question_id ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_admin->post( '/closeVote', function( Request $request ) use ($app) {

	$app['questionFlow']->closeVote();
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_admin->post( '/rejectProposed', function( Request $request ) use ($app) {

	$app['questionFlow']->rejectProposed();
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_admin->post( '/dailyNotification', function( Request $request ) use ($app) {

	$app['questionFlow']->dailyNotification();
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_admin->get( '/moderationpanel', function( Request $request ) use ($app) {

    global $g_config;

    if( $request->get('key') != $g_config['moderation_password'] )
        die(':(');
    
    $id = $request->get('id');
    $bAccept = $request->get('accept')==1;
    
    return $app['questionFlow']->getModerationPanel( $id, $bAccept );
} );

$c_admin->post( '/updatetopic', function( Request $request ) use ($app) {

    global $g_config;

    if( $request->get('key') != $g_config['apiKey'] )
        die(':(');
    
    $question_id = $request->get('id');
    $topics = $request->get('topics');
    $question_id = $app['question']->updateQuestionTopics( $question_id, $topics );
    
    return $app->json( $app['wsrequest']->buildWsResponse( array( 'id' => $question_id) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );

} );


return $c_admin;
