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

$app->get('/webrtc/{mainextension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $mainextension = $route->getArgument('mainextension');

    $extension = getWebRTCExtension($mainextension);
    if (!empty($extension)) {
        return $response->withJson($extension, 200);
    } else {
        return $response->withStatus(404);
    }
});

$app->post('/webrtc', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $extensionnumber = $params['extension'];
        $fpbx = FreePBX::create();

        $extension = createExtension($extensionnumber,false);

        if ($extension === false ) {
            $response->withJson(array("status"=>"Error creating extension"), 500);
        }

        if (useExtensionAsWebRTC($extension) === false) {
            $response->withJson(array("status"=>"Error associating webrtc extension"), 500);
        }

        # Create extension for WebRTC Mobile
        $extensionm = createExtension($extensionnumber,false);

        if ($extensionm === false ) {
            $response->withJson(array("status"=>"Error creating webrtc mobile extension"), 500);
        }

        if (useExtensionAsWebRTCMobile($extensionm) === false) {
            $response->withJson(array("status"=>"Error associating webrtc mobile extension"), 500);
        }

        system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
        return $response->withJson(array('extension'=>$extension,'mobile_extension'=>$extensionm), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->delete('/webrtc/{mainextension}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $mainextension = $route->getArgument('mainextension');
        $extension = getWebRTCExtension($mainextension);
        $mobile_extension = getWebRTCMobileExtension($mainextension);
        if (deleteExtension($extension) && deleteExtension($mobile_extension)) {
            system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
            return $response->withStatus(200);
        } else {
            throw new Exception ("Error deleting extension");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
