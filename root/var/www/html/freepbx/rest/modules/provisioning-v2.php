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
    $token = $body['token'];
    $result = setFalconieriRPS($args['mac'],$token);
    error_log('Set RPS using Falconieri: '.json_encode($result));
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

$app->get('/provisioning/variables[/{extension}]', function (Request $request, Response $response, $args) {
    if (array_key_exists('extension',$args)) {
        return $response->withJson(getExtensionSpecificVariables($args['extension']), 200, JSON_FLAGS);
    } else {
        return $response->withJson(getGlobalVariables(), 200, JSON_FLAGS);
    }
});

$app->get('/provisioning/cloud', function (Request $request, Response $response, $args) {
    return $response->withJson(isCloud(), 200, JSON_FLAGS);
});

$app->post('/provisioning/cloud/{status}', function (Request $request, Response $response, $args) {
    if ($args['status'] == 'true') setCloud(TRUE);
    elseif ($args['status'] == 'false') setCloud(FALSE);
    else return $response->withStatus(400);
    return $response->withStatus(204);
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

function getGlobalVariables(){
    global $amp_conf;
    if (!isCloud()) {
        // Get local green address
        $dbh = FreePBX::Database();
        $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "ip"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];
        $host = $res['value'];
        $variables['provisioning_protocol'] = 'http';
        $variables['network_time_server'] = 'pool.ntp.org';
    } else {
        $host = gethostname();
        $variables['provisioning_protocol'] = 'https';
        $variables['network_time_server'] = $host;
    }
    $variables['ldap_server'] = $host;
    $variables['host'] = $host;
    $variables['provisioning_url'] = "provisioning";
    $variables['firmware_url'] = 'firmware';
    $variables['dect_firmware_url'] = 'firmware/dect';
    $variables['w52h_firmware_url'] = 'firmware/w52h';
    $variables['w53h_firmware_url'] = 'firmware/w53h';
    $variables['w56h_firmware_url'] = 'firmware/w56h';

    // get admin password
    $host = $res['value'];
    $variables['adminpw'] = '';
    $variables['tonezone'] = $amp_conf['TONEZONE'];
    $variables['ldap_port'] = '10389';
    $variables['ldap_user'] = '';
    $variables['ldap_password'] = '';
    $variables['ldap_tls'] = '';
    #$variables['language'] = '';
    $variables['timezone'] = $amp_conf['PHPTIMEZONE'];
    return $variables;
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

function isCloud() {
    // Get extension sip data
    $dbh = FreePBX::Database();
    $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "cloud"';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array());
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];
    if ($res['value'] === '1' || $res['value'] === 'true') return true;
    if ($res['value'] === '0' || $res['value'] === 'false') return false;
    return null;
}

function setCloud($enabled = TRUE) {
    $dbh = FreePBX::Database();
    $dbh->sql('DELETE IGNORE FROM `admin` WHERE `variable` = "cloud"');
    $sql = 'INSERT INTO `admin` (`variable`,`value`) VALUES ("cloud",?)';
    $stmt = $dbh->prepare($sql);
    if ($enabled) {
        $value = 'true';
    } else {
        $value = 'false';
    }
    $stmt->execute(array($value));
}



