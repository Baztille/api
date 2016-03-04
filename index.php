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


$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];


use Igorw\Middleware\Stack;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\SwiftmailerServiceProvider;

// Default configuration
require_once __DIR__.'/config/config.init.php';

// Actual configuration
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/version.php';


if( isset( $_SERVER['SERVER_NAME'] ) && isset( $g_config['instances'][ $_SERVER['SERVER_NAME'] ] ) )
    require_once $g_config['instances'][ $_SERVER['SERVER_NAME'] ];

// Require Silex
$loader = require_once $g_config['silex_autoload_location'];

$app = new Silex\Application();

// Require Swiftmailer
$app->register(new Silex\Provider\SwiftmailerServiceProvider());
$app['mailer'] = new \Swift_MailTransport;

// Require Twig
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/templates',
));

$app['debug'] = true;
$loader->add('baztille', __DIR__ . '/services');

// Loading controllers
require_once __DIR__.'/controllers/controllers.php';
require_once __DIR__.'/services/_loadservices.php';

$trace = $app['trace'];
$is_job = isset( $_SERVER['argv'] );



$trace->start_page(  $starttime, $is_job );


$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->run();


$trace->end_page( $is_job );






