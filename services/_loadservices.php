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

// Load services

require_once __DIR__.'/currentUser.php';
require_once __DIR__.'/question.php';
require_once __DIR__.'/user.php';
require_once __DIR__.'/questionFlow.php';
require_once __DIR__.'/jobs.php';
require_once __DIR__.'/gamification.php';
require_once __DIR__.'/wsrequest.php';
require_once __DIR__.'/trace.php';
require_once __DIR__.'/notifier.php';


$app['current_user'] = $app->share(function() use ($app) {
    return new baztille\currentUser($app);
});
$app['user'] = $app->share(function() use ($app) {
    return new baztille\user($app);
});

$app['question'] = $app->share(function() use ($app) {
    return new baztille\question($app);
});

$app['questionFlow'] = $app->share(function() use ($app) {
    return new baztille\questionFlow($app);
});


$app['jobs'] = $app->share(function() use ($app) {
    return new baztille\jobs($app);
});

$app['gamification'] = $app->share(function() use ($app) {
    return new baztille\gamification($app);
});

$app['wsrequest'] = $app->share(function() use ($app) {
    return new baztille\wsrequest($app);
});

$app['notifier'] = $app->share(function() use ($app) {
    return new baztille\notifier($app);
});

$app['trace'] = $app->share(function() use ($app) {
    return new baztille\trace($app);
});

