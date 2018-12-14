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

require_once(__DIR__. '/../../admin/modules/endpointman/includes/functions.inc');
require_once(__DIR__. '/../lib/freepbxFwConsole.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once('lib/libExtensions.php');

$app->get('/physicalextensions', function (Request $request, Response $response, $args) {
    $physicalextensions = FreePBX::create()->Core->getAllUsersByDeviceType('pjsip');
    return $response->withJson($physicalextensions, 200);
});

$app->get('/physicalextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $physicalextensions = FreePBX::create()->Core->getAllUsersByDeviceType('pjsip');
    foreach ($physicalextensions as $e) {
        if ($e['extension'] == $extension) {
            return $response->withJson($e, 200);
        }
    }
    return $response->withStatus(404);
});

$app->post('/physicalextensions', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $mainextensionnumber = $params['mainextension'];
        $mac = $params['mac'];
        $model = $params['model'];
        $web_user = $params['web_user'];
        $web_password = $params['web_password'];
        $line = $params['line'];

        $delete = false;
        if (isset($mac) && isset($model)) {
            $delete = true;
        }

        $extension = createExtension($mainextensionnumber,$delete);

        if ($extension === false ) {
            $response->withJson(array("status"=>"Error creating extension"), 500);
        }

        if (isset($mac) && isset($model)) {
            if ($model === 'GS Wave') {
                if (useExtensionAsApp($extension,$mac,$model) === false) {
                    $response->withJson(array("status"=>"Error associating app extension"), 500);
                }
            } else {
                if (useExtensionAsPhysical($extension,$mac,$model,$line) === false) {
                    $response->withJson(array("status"=>"Error associating physical extension"), 500);
                }
            }
        } else {
            if (useExtensionAsCustomPhysical($extension,false,'physical',$web_user,$web_password) === false) {
                $response->withJson(array("status"=>"Error creating custom extension"), 500);
            }
        }
        system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
        return $response->withJson(array('extension' => $extension), 200);
   } catch (Exception $e) {
       error_log($e->getMessage());
       return $response->withJson(array("status"=>$e->getMessage()), 500);
   }
});

$app->delete('/physicalextensions/{extension}', function (Request $request, Response $response, $args) {
    try {
        global $astman;
        $route = $request->getAttribute('route');
        $extension = $route->getArgument('extension');

        if (deletePhysicalExtension($extension) && deleteExtension($extension)) {
            system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
            return $response->withStatus(200);
        } else {
            throw new Exception("Error deleting extension");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
