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


$c_question = $app['controllers_factory'];


$c_question->get( '/list/{status}/{page}', function( $status, $page ) use ($app) {

	$questions = $app['question']->listQuestions( $status, $page );
    return $app->json( $app['wsrequest']->buildWsResponse( $questions ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );



$c_question->get( '/{id}', function( $id, Request $request ) use ($app) {

	$question = $app['question']->getQuestionContent( $id );
	return $app->json( $app['wsrequest']->buildWsResponse( $question ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );


$c_question->post( '/vote', function (Request $request) use($app) {
    $id = $request->get('id');
    
    $app['question']->voteForQuestion( $id );
    return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_question->post( '/vote/arg/{id}', function( $id, Request $request ) use ($app) {
   
   	$app['question']->voteForArg( $id );
    return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );


$c_question->post( '/propose', function (Request $request) use($app) {

    $text = $request->get('text');
	$question_id = $app['question']->proposeQuestion( $text );
	
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'id' => $question_id) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );



$c_question->post( '/{id}/postarg', function( $id, Request $request ) use ($app) {

    $text = $request->get('text');
    $parent = $request->get('parent');

	$arg_id = $app['question']->postArg( $id, $text, $parent );

	return $app->json( $app['wsrequest']->buildWsResponse( array( 'id' => $arg_id ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
});

$c_question->get( '/voters/{id}', function( $id, Request $request ) use ($app) {

	$voters = $app['question']->getQuestionVoters( $id );
	return $app->json( $app['wsrequest']->buildWsResponse( $voters ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );
$c_question->get( '/votersarg/{id}', function( $id, Request $request ) use ($app) {

	$voters = $app['question']->getVoters( $id );
	return $app->json( $app['wsrequest']->buildWsResponse( $voters ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

return $c_question;
