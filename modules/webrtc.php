<?php

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

        $extension = createExtension($extensionnumber);

        if ($extension === false ) {
            $response->withJson(array("status"=>"Error creating extension"), 500);
        }

        if (useExtensionAsWebRTC($extension) === false) {
            $response->withJson(array("status"=>"Error associating webrtc extension"), 500);
        }

        system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh&');
        return $response->withJson(array('extension'=>$extension), 200);
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
        if (deleteExtension($extension)) {
            system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh&');
            return $response->withStatus(200);
        } else {
            throw new Exception ("Error deleting extension");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
