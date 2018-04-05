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

require_once(__DIR__. '/../lib/freepbxFwConsole.php');
include_once('lib/libExtensions.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/mainextensions', function (Request $request, Response $response, $args) {
    fwconsole('userman sync');
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    return $response->withJson($mainextensions, 200);
});

$app->get('/mainextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    foreach ($mainextensions as $e) {
        if ($e['extension'] == $extension) {
            return $response->withJson($e, 200);
        }
    }
    return $response->withStatus(404);
});

$app->post('/mainextensions', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $params['username'];
    $mainextension = $params['extension'];
    $outboundcid = $params['outboundcid'];

    // Check if user directory is locked, wait if it is and exit fail
    $locked=1;
    $dbh = FreePBX::Database();
    for ($i=0; $i<10; $i++) {
        $sql = 'SELECT `locked` FROM userman_directories WHERE `name` LIKE "NethServer %"';
        $sth = $dbh->prepare($sql);
        $sth->execute(array());
        $locked = $sth->fetchAll()[0][0];
        if ($locked == 0) {
            break;
        }
        sleep(1);
    }
    if (checkUsermanIsUnlocked()) {
        $ret = createMainExtensionForUser($username,$mainextension,$outboundcid);
    } else {
        return $response->withJson(array("status"=>'ERROR: directory is locked'), 500);
    }

    if ($ret !== true) {
        return $response->withJson($ret[0],$ret[1]);
    }

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
    return $response->withStatus(201);
});

