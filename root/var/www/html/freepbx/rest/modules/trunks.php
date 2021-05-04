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
 * @api {get} /trunks Retrieve all trunks by technology
 */
$app->get('/trunks', function (Request $request, Response $response, $args) {
    try {
        return $response->withJson(\FreePBX::Core()->listTrunks(),200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});

/**
 * @api {delete} /trunk Delete a trunk
 */
$app->delete('/trunk/{trunkid}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $trunkid = $route->getArgument('trunkid');
    try {
        $ret = \FreePBX::Core()->deleteTrunk($trunkid);
        if ($ret !== true) {
            throw new Exception("Error deleting trunk: ". print_r($ret,1));
        }
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
    try {
        $params = $request->getParsedBody();
        $peerdetails = "host=***provider ip address***\nusername=***userid***\nsecret=***password***\ntype=peer";
        $userconfig = "secret=***password***\ntype=user\ncontext=from-trunk";
        $dbh = FreePBX::Database();
        $sql = 'SELECT * FROM `providers` WHERE `provider` = ? LIMIT 1';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($params['provider']));
        $provider_param = $sth->fetchAll(\PDO::FETCH_ASSOC)[0];

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

        if($params['forceCodec'] !== true) {
            $peerdetails  = str_replace("disallow=all\n", '', $peerdetails);
        }

        $outcid = $params['phone'];
        $codecs = array_flip($params['codecs']);

        $provider_data = parse_ini_string($provider_param['dettpeer']);
        // settings array merged with data sent to FreePBX page on POST
        $settings = array(
            "channelid" => $channelid,
            "peerdetails" => $peerdetails,
            "userconfig" => $userconfig,
            "register" => $register,
            "dialopts" => $dialopts,
            "extdisplay" => "",
            "sv_trunk_name" => "",
            "sv_usercontext" => "",
            "sv_channelid" => "",
            "npanxx" => "",
            "trunk_name" => $params['name'],
            "hcid" => "on",
            "dialoutopts_cb" => "",
            "disabletrunk" => "off",
            "failtrunk_enable" => "off",
            "username" => $params['username'],
            "secret" => $params['password'],
            "authentication" => "outbound",
            "registration" => "send",
            "language" => "",
            "sip_server" => $provider_data['host'],
            "sip_server_port" => (string) $provider_data['port'],
            "context" => "from-pstn",
            "transport" => "0.0.0.0-udp",
            "dtmfmode" => "auto",
            "auth_rejection_permanent" => "on",
            "forbidden_retry_interval" => "10",
            "fatal_retry_interval" => "0",
            "retry_interval" => "60",
            "expiration" => "3600",
            "max_retries" => "10",
            "qualify_frequency" => "60",
            "outbound_proxy" => "",
            "contact_user" => "",
            "from_domain" => "",
            "from_user" => "",
            "client_uri" => "",
            "server_uri" => "",
            "media_address" => "",
            "aors" => "",
            "aor_contact" => "",
            "match" => "",
            "support_path" => "no",
            "t38_udptl" => "no",
            "t38_udptl_ec" => "none",
            "t38_udptl_nat" => "no",
            "t38_udptl_maxdatagram" => "",
            "fax_detect" => "no",
            "trust_rpid" => "no",
            "sendrpid" => "no",
            "identify_by" => "default",
            "inband_progress" => "no",
            "direct_media" => "no",
            "rewrite_contact" => "yes",
            "rtp_symmetric" => "yes",
            "media_encryption" => "sdes",
            "force_rport" => "yes",
            "message_context" => "",
            "codec" => $codecs
        );
        $trunknum = \FreePBX::Core()->addTrunk($params['name'], 'pjsip', $settings);
        if (!is_numeric($trunknum)) {
            throw new Exception("Error creating trunk");
        }
        system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
        return $response->withJson(["trunkid" => $trunknum], 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
