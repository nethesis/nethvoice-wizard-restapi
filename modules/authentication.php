<?php
#
# Copyright (C) 2017 Nethesis S.r.l.
# http://www.nethesis.it - nethserver@nethesis.it
#
# This script is part of NethServer.
#
# NethServer is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License,
# or any later version.
#
# NethServer is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with NethServer.  If not, see COPYING.
#

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*
* /login - POST
*
* @param user
* @param password
*
* @return { "success" : true } if authentication is valid
* @return { "success" : false } otherwise
*/
$app->get('/login', function (Request $request, Response $response) {
    return $response->withJson(['success' => true]);
});

$app->post('/testauth', function (Request $request, Response $response, $args) { //TODO: ??
    $params = $request->getParsedBody();
    $username = $params['username'];
    $password = $params['password'];

    $url = "https://nethpanico.nethesis.it/freepbx/admin/config.php?" .
        "username=" . $username . "&password=" . $password;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    preg_match("/<li>Invalid/",$output, $matches);
    curl_close($ch);

    if(count($matches) > 0) {
        return $response->withJson(['result' => 'not authorized'], 401);
    } else {
        require(__DIR__.'/../config.inc.php');
        $secret = $config['settings']['secretkey'];
        return $response->withJson(['result' => $secret], 200);
    }
});

