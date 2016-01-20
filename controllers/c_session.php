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

$c_session = $app['controllers_factory'];


$c_session->post( '/login', function( Request $request ) use ($app) {

    $username = $request->get('username');
    $password = $request->get('password');

	$auth_token = $app['current_user']->login( $username, $password );
	
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'auth' => $auth_token ) ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );

$c_session->post( '/logout', function( Request $request ) use ($app) {

	$app['current_user']->logout();

	return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_session->get( '/who_am_i', function( Request $request ) use ($app) {

	$user = $app['current_user'];
	
	return $app->json( $app['wsrequest']->buildWsResponse( $user->getdatas() ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_session->get( '/getMyInfos', function( Request $request ) use ($app) {

	$user = $app['current_user'];

	return $app->json( $app['wsrequest']->buildWsResponse( $user->getAllPlayersInfos() ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );


$c_session->get( '/myranking', function( Request $request ) use ($app) {

	$user = $app['current_user'];
	
	return $app->json( $app['wsrequest']->buildWsResponse( $user->myranking() ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );


$c_session->post( '/changeOptin', function( Request $request ) use ($app) {

    $email = $request->get('email');
    $value = $request->get('optin');

	$app['current_user']->changeOptin( $email, $value );
	
	return $app->json( $app['wsrequest']->buildWsResponse( array( 'ok' => 1 ) ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );



return $c_session;
