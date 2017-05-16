<?php

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
        $line = $params['line'];

        $extension = createExtension($mainextensionnumber);

        if ($extension === false ) {
            $response->withJson(array("status"=>"Error creating extension"), 500);
        }

        if (isset($mac) && isset($model) && isset($line)) {
            if (useExtensionAsPhysical($extension,$mac,$model,$line) === false) {
                $response->withJson(array("status"=>"Error associating physical extension"), 500);
            }
        } else {
            if (useExtensionAsCustomPhysical($extension) === false) {
                $response->withJson(array("status"=>"Error creating custom extension"), 500);
            }
        }

        fwconsole('r');
        return $response->withJson(array('extension'=>$created_extension), 200);
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
            fwconsole('r');
            return $response->withStatus(200);
        } else {
            throw new Exception("Error deleting extension");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
