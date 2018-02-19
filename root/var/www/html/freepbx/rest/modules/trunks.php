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

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/**
 * @api {get} /trunks/count  Retrieve count of all trunks
 */
$app->get('/trunks/count', function (Request $request, Response $response, $args) {
    try {
        $result = array();
        $trunks = FreePBX::Core()->listTrunks();
        foreach($trunks as $trunk) {
            array_push($result, $trunk);
        }
        return $response->withJson(count($result),200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});

/**
 * @api {get} /trunks  Retrieve all trunks
 */
$app->get('/trunks', function (Request $request, Response $response, $args) {
    try {
        $result = array();
        $trunks = FreePBX::Core()->listTrunks();
        foreach($trunks as $trunk) {
            array_push($result, $trunk);
        }
        return $response->withJson($result,200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});

/**
 * @api {get} /trunks/{tech}  Retrieve all trunks by technology
 */
$app->get('/trunks/{tech}', function (Request $request, Response $response, $args) {
    try {
        $result = array();
        $trunks = FreePBX::Core()->listTrunks();
        $tech = $request->getAttribute('tech');
        $tech = strtolower($tech);

        foreach($trunks as $trunk) {
            if (strtolower($trunk['tech']) == $tech) {
                array_push($result, $trunk);
            }
        }
        return $response->withJson($result,200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});

/**
 * @api {post} /trunks Create a new trunks
 */
$app->post('/trunks', function (Request $request, Response $response, $args) {
  $params = $request->getParsedBody();

  $peerdetails = "host=***provider ip address***\nusername=***userid***\nsecret=***password***\ntype=peer";
  $userconfig = "secret=***password***\ntype=user\ncontext=from-trunk";
  $tech = 'sip';

  try {
    $dbh = FreePBX::Database();
    $provider_param = $dbh->sql('SELECT * FROM `providers` WHERE `provider` = \''. $params['provider']. '\'',
      "getRow", DB_FETCHMODE_ASSOC);

    //Make sure name is != username by adding provider name to it if necessary
    if ($params['name'] == $params['username']){
        $params['name'] = $params['provider'].'_'.$params['name'];
    }

    $channelid    = $params['name'];
    $peerdetails  = str_replace("USERNAME", $params['username'], $provider_param['dettpeer']);
    $peerdetails  = str_replace("PASSWORD", $params['password'], $peerdetails);
    $peerdetails  = str_replace("CODECS", implode(',',$params['codecs']), $peerdetails);
    $peerdetails  = str_replace("NUMERO", $params['phone'], $peerdetails);
    $usercontext  = $params['username'];
    $userconfig   = str_replace("PASSWORD", $params['password'], $provider_param['dettuser']);
    $userconfig   = str_replace("CODECS", implode(',',$params['codecs']), $userconfig);
    $register     = str_replace("USERNAME", $params['username'], $provider_param['registration']);
    $register     = str_replace("PASSWORD", $params['password'], $register);
    $register     = str_replace("NUMERO", $params['phone'], $register);

    if($params['forceCodec'] !== true)
      $peerdetails  = str_replace("disallow=all\n", '', $peerdetails);

    $outcid = $params['phone'];

    $trunknum = core_trunks_add(
      $tech,
      $channelid,
      '',
      '',
      $outcid,
      $peerdetails,
      $usercontext,
      $userconfig,
      $register,
      'off',
      '',
      'off',
      '',
      array()
    );
  } catch (Exception $e) {
    error_log($e->getMessage());

    return $response->withStatus(500);
  }

  system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');

  return $response->withStatus(200);
});
