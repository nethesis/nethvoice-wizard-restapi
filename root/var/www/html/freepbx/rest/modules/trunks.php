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
 * @api {delete} /trunk Delete a trunk
 */
$app->delete('/trunk/{trunkid}/{tech}', function (Request $request, Response $response, $args) {
  $route = $request->getAttribute('route');
  $trunkid = $route->getArgument('trunkid');
  $tech = $route->getArgument('tech');
  try {
    // call core function to delete sip trunk
    core_trunks_del($trunkid, $tech);

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
    return $response->withStatus(200);
  } catch (Exception $e) {
    error_log($e->getMessage());
    return $response->withStatus(500);
  }
});

/**
 * @api {patch} /trunk Change trunk secret
 */
$app->patch('/trunk/secret', function (Request $request, Response $response, $args) {
  $params = $request->getParsedBody();
  try {
    $dbh = FreePBX::Database();
    $secret = $params["secret"];
    $peerKeyword = 'tr-peer-'.$params["trunkid"];
    $userKeyword = 'tr-user-'.$params["trunkid"];
    $sql =  'UPDATE sip'.
            ' SET data = ?'.
            ' WHERE (id = ?'.
            ' OR id = ?)'.
            ' AND keyword = "secret"';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($secret, $peerKeyword, $userKeyword));

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
    return $response->withStatus(200);
  } catch (Exception $e) {
    error_log($e->getMessage());
    return $response->withStatus(500);
  }
});

/**
 * @api {post} /trunks Create a new trunks
 */
$app->post('/trunks', function (Request $request, Response $response, $args) {
  $params = $request->getParsedBody();
// {"provider":"vivavox","name":"nomefascccio","username":"nomeutente","password":"password","phone":"1234567890","codecs":["ulaw","g729"],"forceCodec":true}

    $params['provider'];
    $params['name'];
    $params['username'];
    $params['password'];
    $params['phone'];
    $params['codecs'];

    $dbh = FreePBX::Database();

    // Get trunk id
    $sql = 'SELECT trunkid FROM trunks';
    $sth = $dbh->prepare($sql);
    $sth->execute();
    $trunkid = 1;
    while ($res = $sth->fetchColumn()) {
        if ($res > $trunkid) {
            break;
        }
        $trunkid++;
    }
    if ($res == $trunkid) {
        $trunkid++;
    }

    // Insert data into trunks table
    $sql = "INSERT INTO `trunks` (`trunkid`,`tech`,`channelid`,`name`,`outcid`,`keepcid`,`maxchans`,`failscript`,`dialoutprefix`,`usercontext`,`provider`,`disabled`,`continue`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $sth = $dbh->prepare($sql);
    $sth->execute(array(
        $trunkid,
        'pjsip',
        $params['name'],
        $params['name'],
        $params['phone'],
        'off',
        '',
        '',
        '',
        '',
        '',
        'off',
        'off'
    ));

    // Insert data into pjsip table
    // Get static provider data
    $sql = 'SELECT `keyword`,`data` FROM `rest_pjsip_trunks_defaults` WHERE `provider_id` IN (SELECT `id` FROM `rest_pjsip_providers` WHERE `provider` = ?)';
    $sth = $dbh->prepare($sql);
    $sth->execute([$params['provider']]);
    $pjsip_data = $sth->fetchAll(\PDO::FETCH_ASSOC);

    // Add dynamic data
    $pjsip_data[] = array( "keyword" => "contact_user", "data" => $params['phone']);
    $pjsip_data[] = array( "keyword" => "extdisplay", "OUT_".$trunkid);
    $pjsip_data[] = array( "keyword" => "from_user", "data" => $params['phone']);
    $pjsip_data[] = array( "keyword" => "sv_channelid", "data" => $params['name']);
    $pjsip_data[] = array( "keyword" => "sv_trunk_name", "data" => $params['name']);
    $pjsip_data[] = array( "keyword" => "trunk_name", "data" => $params['name']);
//    $pjsip_data[] = array( "keyword" => "codecs", "data" => implode(',',$params['codec']));

    // Set codecs
    if (!empty($params['codec'])) {
        foreach ($pjsip_data as $index => $data) {
            if ($data['keyword'] !== "codecs") {
                continue;
            } else {
                $default_codecs = $data;
                unset($pjsip_data[$index]);
            }
        }
        if ($params['forceCodec']) {
            $pjsip_data[] = array( "keyword" => "codecs", "data" => implode(',',$params['codec']));
        } else {
            $pjsip_data[] = array( "keyword" => "codecs", "data" => implode(',',array_unique(array_merge($params['codec'],explode(',',$default_codecs)))));
        }
    }

    if ($params['provider'] === 'OpenSolution_GNR') {
        $pjsip_data[] = array( "keyword" => "username", "data" => "");
        $pjsip_data[] = array( "keyword" => "secret", "data" => "");
    } else {
        $pjsip_data[] = array( "keyword" => "username", "data" => $params['username']);
        $pjsip_data[] = array( "keyword" => "secret", "data" => $params['password']);
    }

    $insert_data = array();
    $insert_qm = array();
    foreach ($pjsip_data as $data) {
        $insert_data = array_merge($insert_data,[$trunkid,$data['keyword'],$data['data'],0]);
        $insert_qm[] = '(?,?,?,?)';
    }
    //return $response->withJson($insert_data,200);
    $sql = 'INSERT INTO `pjsip` (`id`,`keyword`,`data`,`flags`) VALUES '.implode(',',$insert_qm);
    $sth = $dbh->prepare($sql);
    $res = $sth->execute($insert_data);
    if (!$res) {
        return $response->withStatus(500);
    }
    return $response->withStatus(200);
    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
});
