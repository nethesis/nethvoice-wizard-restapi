<?php

require_once(__DIR__. '/../../admin/modules/endpointman/includes/functions.inc');
require_once(__DIR__. '/../lib/freepbxFwConsole.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function extensionExists($e, $extensions)
{
    foreach ($extensions as $extension) {
        if ($extension['extension'] == $e) {
            return true;
        }
    }
    return false;
}

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
        $fpbx = FreePBX::create();

      //get associated main extension
      $mainextensions = $fpbx->Core->getAllUsers();
        foreach ($mainextensions as $ve) {
            if ($ve['extension'] == $mainextensionnumber) {
                $mainextension = $ve;
                break;
            }
        }
      //error if main extension number doesn't exist
      if (!isset($mainextension)) {
          return $response->withJson(array("status"=>"Main extension ".$mainextensionnumber." doesn't exist"), 400);
      }

        if (isset($params['extension'])) {
            //use given extension number
         if (!preg_match('/9[1-7]'.$mainextensionnumber.'/', $params['extension'])) {
             return $response->withJson(array("status"=>"Wrong physical extension number supplied"), 400);
         } else {
             $extension = $params['extension'];
         }
        } else {
            //get first free physical extension number for this main extension
          $extensions = $fpbx->Core->getAllUsersByDeviceType();
            for ($i=91; $i<=97; $i++) {
                if (!extensionExists($i.$mainextensionnumber, $extensions)) {
                    $extension = $i.$mainextensionnumber;
                    break;
                }
            }
          //error if there aren't available extension numbers
          if (!isset($extension)) {
              return $response->withJson(array("status"=>"There aren't available extension numbers"), 500);
          }
        }

      //delete extension
      $fpbx->Core->delDevice($extension, true);
        $fpbx->Core->delUser($extension, true);

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
      // insert created physical extension in password table
      $created_extension = $res['ext'];
        $created_extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $created_extension . '" AND keyword="secret"', "getOne");
        $dbh = FreePBX::Database();
        $subquery = 'SELECT userman_users.id'.
          ' FROM userman_users'.
          ' WHERE userman_users.default_extension = ?';

        $sql = 'UPDATE `rest_devices_phones`'.
          ' SET user_id = ('. $subquery. '), extension = ?, secret= ?'.
          ' WHERE mac = \''. $mac. '\''.
          ((isset($line) && $line) ? ' AND line = '. $line : '');
        $stmt = $dbh->prepare($sql);
        if ($res = $stmt->execute(array($mainextensionnumber,$created_extension,$created_extension_secret))) {
            // Add extension to endpointman
          $endpoint = new endpointmanager();

          // Get model id by mac
          $brand = $endpoint->get_brand_from_mac($mac);
            $models = $endpoint->models_available(null, $brand['id']);

            $model_id = null;
            foreach ($models as $m) {
                if ($m['text'] === $model) {
                    $model_id = $m['value'];
                    break;
                }
            }

            if (!$model_id) {
                throw new Exception('model not found');
            } else {
                $mac_id = $dbh->sql('SELECT id FROM endpointman_mac_list WHERE mac = "'.preg_replace('/:/', '', $mac).'"', "getOne");
                if ($mac_id) {
                    // add line if device already exist
                  $endpoint->add_line($mac_id, $line, $created_extension, $mainextension['name']);
                } else {
                    // add device to endpointman module
                  $mac_id = $endpoint->add_device($mac, $model_id, $created_extension, null, $line, $mainextension['name']);
                }
            }

            fwconsole('r');
            return $response->withJson(array('extension'=>$created_extension), 200);
        } else {
            throw new Exception();
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->delete('/physicalextensions/{extension}', function (Request $request, Response $response, $args) {
    try {
        global $astman;
        $route = $request->getAttribute('route');
        $extension = $route->getArgument('extension');
        $mainextensions = substr($extension, 2);
        $dbh = FreePBX::Database();

        //Get device lines
        $mac = $dbh->sql('SELECT `mac` FROM `rest_devices_phones` WHERE `extension` = "'.$extension.'"', "getOne");
        $usedlinecount = $dbh->sql('SELECT COUNT(*) FROM `rest_devices_phones` WHERE `mac` = "'.$mac.'" AND `extension` != "" AND `extension`', "getOne");

        // clean extension
        $fpbx = FreePBX::create();
        $fpbx->Core->delUser($extension);
        $fpbx->Core->delDevice($extension);
        $sql = 'UPDATE rest_devices_phones SET user_id = NULL, extension = NULL, secret = NULL WHERE extension = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));

        //Remove device from main extension
        $existingdevices = $astman->database_get("AMPUSER", $mainextension."/device");
        if (!empty($existingdevices)) {
            $existingdevices_array = explode('&', $existingdevices);
            unset($existingdevices_array[$extension]);
            $existingdevices = implode('&', $existingdevices_array);
            $astman->database_put("AMPUSER", $mainextension."/device", $existingdevices);
        }


        // Remove endpoint from endpointman
        $endpoint = new endpointmanager();
        $mac_id = $dbh->sql('SELECT id FROM endpointman_mac_list WHERE mac = "'.preg_replace('/:/', '', $mac).'"', "getOne");
        $luid = $dbh->sql('SELECT luid FROM endpointman_line_list WHERE mac_id = "'.$mac_id.'" AND ext = "'.$extension.'"', "getOne");

        if ($usedlinecount > 1) {
            //There are other configured lines for this device
            $endpoint->delete_line($luid, false);
        } else {
            //last line, also remove device
            $endpoint->delete_line($luid, true);
        }

        fwconsole('r');
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
