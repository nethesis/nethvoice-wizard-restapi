<?php
#
# Copyright (C) 2019 Nethesis S.r.l.
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
#

require_once '/var/www/html/freepbx/rest/vendor/autoload.php';

define('REBOOT_HELPER_SCRIPT','/var/www/html/freepbx/rest/lib/phonesRebootHelper.php');
define("JSON_FLAGS",JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once 'lib/CronHelper.php';
include_once 'lib/libExtensions.php';
include_once 'lib/libCTI.php';

//$app = new \Slim\App;
$container = $app->getContainer();
$container['cron'] = function ($container) {
    return new CronHelper();
};

$app->post('/phones/reboot', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $cron_response = $this->cron->write($body);
    return $response->withJson((object) $cron_response, 200, JSON_FLAGS);
});

$app->get('/phones/reboot[/{mac}]', function (Request $request, Response $response, $args) {
    if (array_key_exists('mac',$args)) {
        return $response->withJson((object) $this->cron->read($args['mac']), 200, JSON_FLAGS);
    } else {
        return $response->withJson((object) $this->cron->read(), 200, JSON_FLAGS);
    }
});

$app->delete('/phones/reboot', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $cron_response = $this->cron->delete($body);
    return $response->withJson((object) $cron_response, 200, JSON_FLAGS);
});

$app->post('/phones/rps/{mac}', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    if(!$body['url']) {
        return $response->withStatus(400);
    }
    $result = setFalconieriRPS($args['mac'], $body['url']);
    return $response->withStatus($result['httpCode']);
});

$app->get('/phones/account/{mac}', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $stmt = $dbh->prepare('SELECT `extension`,`secret` FROM `rest_devices_phones` WHERE `mac` = ?');
    $stmt->execute(array(str_replace('-',':',$args['mac'])));
    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $response->withJson((object) $res , 200, JSON_FLAGS);
});

$app->get('/provisioning/engine', function (Request $request, Response $response, $args) {
    return $response->withJson(getProvisioningEngine(), 200, JSON_FLAGS);
});

$app->get('/provisioning/variables/{extension}', function (Request $request, Response $response, $args) {
    return $response->withJson(getExtensionSpecificVariables($args['extension']), 200, JSON_FLAGS);
});

$app->get('/phones/state', function (Request $request, Response $response, $args) {
    global $astman;
    $res = array();
    foreach (FreePBX::Core()->getAllUsersByDeviceType() as $extension) {
        $ext = $extension['extension'];
        $state = $astman->ExtensionState($ext,'');
        $res[$ext] = $state;
    }
    return $response->withJson($res, 200, JSON_FLAGS);
});

$app->get('/extensions/{extension}/srtp', function (Request $request, Response $response, $args) {
    $sip = getSipData();
    if (array_key_exists($args['extension'], $sip) && array_key_exists('media_encryption',$sip[$args['extension']])) {
        $media_encryption = $sip[$args['extension']]['media_encryption'];
        if ($media_encryption == 'sdes' || $media_encryption == 'dtls') {
            return $response->withJson(TRUE, 200, JSON_FLAGS);
        }
    }
    return $response->withJson(FALSE, 200, JSON_FLAGS);
});

$app->post('/extensions/{extension}/srtp/{enabled}', function (Request $request, Response $response, $args) {
    $media_encryption = ($args['enabled'] == 'true') ? 'sdes' : 'no';
    if (setSipData($args['extension'],'media_encryption',$media_encryption)) {
        system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
        return $response->withStatus(200);
    }
    return $response->withStatus(500);
});

function getFeaturcodes(){
    $dbh = FreePBX::Database();
    $sql = 'SELECT modulename,featurename,defaultcode,customcode FROM featurecodes';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array());
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $featurecodes = array();
    foreach ($res as $featurecode) {
        $featurecodes[$featurecode['modulename'].$featurecode['featurename']] = (!empty($featurecode['customcode'])?$featurecode['customcode']:$featurecode['defaultcode']);
    }
    return $featurecodes;
}

function getExtensionSpecificVariables($extension){
    global $astman;
    $variables = array();

    // Get main extension
    if (isMainExtension($extension)) {
        $mainextension = $extension;
    } else {
        $mainextension = getMainExtension($extension);
    }

    // dnd_allow 0|1
    $variables['dnd_allow'] = '1';

    // fwd_allow 0|1
    $variables['fwd_allow'] = '1';

    // Get CTI profile id
    $users = getAllUsers();
    $profileid = null;
    foreach ($users as $user) {
         if ($user['default_extension'] == $mainextension) {
             $profileid = $user['profile'];
             break;
         }
    }

    // Get CTI profile permissions
    if (!empty($profileid)) {
        $permissions = getCTIPermissionProfiles($profileid);
        if ( array_key_exists('permissions',$permissions['macro_permissions']['settings'])) {
            foreach ($permissions['macro_permissions']['settings']['permissions'] as $permission) {
                if ($permission['name'] == 'dnd') {
                    $variables['dnd_allow'] = (string)(int) $permission['value'];
                }
                if ($permission['name'] == 'call_forward') {
                    $variables['fwd_allow'] = (string)(int) $permission['value'];
                }
            }
        }
    }
    return $variables;
}

