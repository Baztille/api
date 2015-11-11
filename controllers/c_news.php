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

$c_news = $app['controllers_factory'];


$c_news->get( '/list', function(  ) use ($app) {

    $res = file_get_contents( '/var/baztille-data/news/news.json');

    if( $res === false )
    {
        $news = array( 'error' => 'error during news retrieval' );
    }
    else
    {
        $clean_res = strstr($res, '{');   
        
        $news = json_decode( $clean_res, true );
        $news = $news['payload']['latestPosts'];
    }

    return $app->json( $app['wsrequest']->buildWsResponse( $news ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );

$c_news->get( '/read/{id}', function( $id, Request $request ) use ($app) {

    $res = file_get_contents( '/var/baztille-data/news/news_'.$id.'.json');

    if( $res === false )
    {
        $news = array( 'error' => 'error during one news retrieval' );
    }
    else
    {
        $clean_res = strstr($res, '{');   
        
        $news = json_decode( $clean_res, true );
        $news = $news['payload']['value'];
    }

    return $app->json( $app['wsrequest']->buildWsResponse( $news ), 201, array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers'=>'Content-Type','Content-Type' => 'application/json') );
} );



return $c_news;
