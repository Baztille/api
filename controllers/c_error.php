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
use Symfony\Component\HttpFoundation\Response;


$app->error(function (\Exception $e, $code) use ($app) {

	switch ($code) {
		case 404:
			$message = 'The requested page could not be found.';
			break;

		default:
			$message = $e->getMessage();
	}

    // Log error
    $trace = $app['trace'];
    $trace->logFatalException( $e );    
    
	$error = json_encode( array(
		'error' => 1,
		'error_code' => $code,
		'error_descr' => $message
	) );

	return $app->json( array(
		'error' => 1,
		'error_code' => $code,
		'error_descr' => $message
	) , 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers' => 'Content-Type', 'Content-Type' => 'application/json') );
	//return new Response( $error );
});


