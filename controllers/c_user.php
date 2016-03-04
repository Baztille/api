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

$c_user = $app['controllers_factory'];




$c_user->post( '/signin', function( Request $request ) use ($app) {

    $username = $request->get('username');
    $password = $request->get('password');
    $email = $request->get('email');
	// TODO: check argument validity

	$auth_token = $app['user']->createUser( $username, $password, $email );

	return $app->json( $app['wsrequest']->buildWsResponse(  array( 'auth' => $auth_token ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );


$c_user->post( '/test', function( Request $request ) use ($app) {

	return $app->json( $app['wsrequest']->buildWsResponse( array( 'id' => 0 ) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_user->post( '/notifwebhook', function( Request $request ) use ($app) {
    if( 0 === strpos( $request->headers->get('Content-Type'), 'application/json') ) {

        $data = json_decode( $request->getContent(), true );
        $request->request->replace( is_array( $data ) ? $data : array() );
        
        // Store it in DB
        global $g_config;
		$m = new \MongoClient(); // connect
		$db = $m->selectDB( $g_config['db_name'] );
        $db->webhook->insert( $data );
        
    	return $app->json( $app['wsrequest']->buildWsResponse( $data ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
    }
} );


$c_user->post( '/forgetpassword', function( Request $request ) use ($app) {

    $email = $request->get('email');

	$app['user']->forgetpassword( $email );
	return $app->json( $app['wsrequest']->buildWsResponse(  array('id'=>1) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_user->post( '/changepassword', function( Request $request ) use ($app) {

    $password = $request->get('password');
    $code = $request->get('code');

	$app['user']->changepassword( $code, $password );
	return $app->json( $app['wsrequest']->buildWsResponse(  array('id'=>1) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_user->post( '/sendcontactmessage', function( Request $request ) use ($app) {

    $text = $request->get('text');
    
    $app['user']->sendContactMessage( $text );

	return $app->json( $app['wsrequest']->buildWsResponse(  array('id'=>1) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );

$c_user->get( '/verifyemail/{code}', function( $code,Request $request ) use ($app) {

    $app['user']->confirmEmail( $code );
    
    return $app->redirect('http://baztille.org/thankyou');
} );

$c_user->get( '/reconfirmemail', function( Request $request ) use ($app) {

    $app['user']->reconfirmEmail( );

	return $app->json( $app['wsrequest']->buildWsResponse(  array('id'=>1) ), 201, array('Access-Control-Allow-Origin' => '*','Access-Control-Allow-Headers'=>'Content-Type', 'Content-Type' => 'application/json') );
} );


return $c_user;
