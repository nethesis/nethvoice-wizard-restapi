<?php

require_once(__DIR__. '/../../admin/modules/endpointman/includes/functions.inc');
require_once(__DIR__. '/../lib/freepbxFwConsole.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/webrtc/{mainextension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $mainextension = $route->getArgument('mainextension');
    $dbh = FreePBX::Database();
    $stmt = $dbh->prepare('SELECT id FROM sip WHERE id = ?');
    $stmt->execute(array("99".$mainextension));
    $res = $stmt->fetchAll();

    if (count($res) > 0) {
        return $response->withJson(array("99".$mainextension), 200);
    } else {
        return $response->withStatus(404);
    }
});

$app->post('/webrtc', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $mainextensionnumber = $params['extension'];
        $fpbx = FreePBX::create();

      //get associated main extension
      $mainextensions = $fpbx->Core->getAllUsers();
        foreach ($mainextensions as $ve) {
            if ($ve['extension'] == $mainextensionnumber) {
                $mainextension = $ve;
                break;
            }
        }

      //delete extension
      $fpbx->Core->delDevice($extension, true);
      $fpbx->Core->delUser($extension, true);

      // webrtc is 99XXX
      $extension = "99".$mainextensionnumber;

      //create physical extension
      $data['name'] = $mainextension['name'];
        $mainextdata = $fpbx->Core->getUser($mainextension['extension']);
        $data['outboundcid'] = $mainextdata['outboundcid'];
        $res = $fpbx->Core->processQuickCreate('pjsip', $extension, $data);
        if (!$res['status']) {
            return $response->withJson(array('message'=>$res['message']), 500);
        }

      //Add device to main extension
      global $astman;
        $existingdevices = $astman->database_get("AMPUSER", $mainextensionnumber."/device");
        if (empty($existingdevices)) {
            $astman->database_put("AMPUSER", $mainextensionnumber."/device", $extension);
        } else {
            $existingdevices_array = explode('&', $existingdevices);
            if (!in_array($extension, $existingdevices_array)) {
                $existingdevices_array[]=$extension;
                $existingdevices = implode('&', $existingdevices_array);
                $astman->database_put("AMPUSER", $mainextensionnumber."/device", $existingdevices);
            }
        }

        fwconsole('r');
        return $response->withJson(array('extension'=>$extension), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->delete('/webrtc/{mainextension}', function (Request $request, Response $response, $args) {
    try {
        global $astman;
        $route = $request->getAttribute('route');
        $mainextension = $route->getArgument('mainextension');
        $extension = "99".$mainextension;
        $dbh = FreePBX::Database();

        // clean extension
        $fpbx = FreePBX::create();
        $fpbx->Core->delUser($extension);
        $fpbx->Core->delDevice($extension);

        //Remove device from main extension
        $existingdevices = $astman->database_get("AMPUSER", $mainextension."/device");
        if (!empty($existingdevices)) {
            $existingdevices_array = explode('&', $existingdevices);
            unset($existingdevices_array[$extension]);
            $existingdevices = implode('&', $existingdevices_array);
            $astman->database_put("AMPUSER", $mainextension."/device", $existingdevices);
        }

        fwconsole('r');
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
