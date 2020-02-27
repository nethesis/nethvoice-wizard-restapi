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
#
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../lib/SystemTasks.php');

function getLegacyMode() {
    exec("/usr/bin/sudo /sbin/e-smith/config getprop nethvoice LegacyMode", $out);
    return $out[0];
}

function setLegacyMode($value) {
    exec("/usr/bin/sudo /sbin/e-smith/config setprop nethvoice LegacyMode $value", $out, $ret);
}


$app->get('/configuration/userprovider', function (Request $request, Response $response, $args) {
    exec("/usr/bin/sudo /var/www/html/freepbx/rest/lib/getSSSD.pl", $out);
    $out = json_decode($out[0]);
    return $response->withJson($out,200);
});

# get enabled mode
$app->get('/configuration/mode', function (Request $request, Response $response, $args) {
    $mode = getLegacyMode();
    # return 'unknown' if LegacyMode prop is not set
    if ( $mode == "" ) {
        return $response->withJson(['result' => 'unknown'],200);
    }
    exec("/usr/bin/rpm -q nethserver-directory", $out, $ret);
    # return true, if LegacyMode is enabled and nethserver-directory is installed
    if ($mode == "enabled" && $ret === 0) {
        return $response->withJson(['result' => "legacy"],200);
    }
    return $response->withJson(['result' => "uc"],200);
});

# set mode to legacy or uc
#
# JSON body: { "mode" : <mode> } where <mode> can be: "legacy" or "uc"
#
$app->post('/configuration/mode', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    if ($params['mode'] == "legacy") {
        setLegacyMode('enabled');
        $st = new SystemTasks();
        $task = $st->startTask("/usr/bin/sudo /usr/libexec/nethserver/pkgaction --install nethserver-directory");
        return $response->withJson(['result' => $task], 200);
    } else if ($params['mode'] == "uc") {
        setLegacyMode('disabled');
        return $response->withJson(['result' => 'success'], 200);
    } else {
        return $response->withJson(['result' => 'Invalid mode'], 422);
    }
});

#
# GET /configuration/networks return green ip address and netmasks
#
$app->get('/configuration/networks', function (Request $request, Response $response, $args) {
    exec('/bin/sudo /sbin/e-smith/db networks getjson', $out, $ret);
    if ($ret!==0)    {
        return $response->withJson($out,500);
    }
    $networkDB = json_decode($out[0],true);
    $networks = array();
    $isRed = false;
    // searching red interfaces
    foreach ($networkDB as $key) {
        if($key['props']['role'] === 'red' && $key['type'] != 'xdsl-disabled') {
            $isRed = true;
        }
    }
    // create network obj
    foreach ($networkDB as $key){
        if($key['props']['role'] === 'green')
        {
            $networks[$key['name']] = array(
                "network"=>long2ip(ip2long($key['props']['ipaddr']) & ip2long($key['props']['netmask'])),
                "ip"=>$key['props']['ipaddr'],
                "netmask"=>$key['props']['netmask'],
                "gateway"=>$isRed ? $key['props']['ipaddr'] : $key['props']['gateway']
            );
        }
    }
    return $response->withJson($networks,200);
});

$app->get('/configuration/wizard', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SELECT * FROM rest_wizard';
        $wizard = $dbh->sql($sql, 'getAll', \PDO::FETCH_ASSOC);
        return $response->withJson($wizard, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

# JSON body: { "step" : <current_wizard_step>, "status": <true|false> } where <status> is the wizard status
$app->post('/configuration/wizard', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $step = $params['step'];
        $status = $params['status'];
        // clean table
        sql('TRUNCATE `rest_wizard`');
        // insert wizard data
        $dbh = FreePBX::Database();
        $sql = 'REPLACE INTO `rest_wizard` (`step`,`status`) VALUES (?,?)';
        $stmt = $dbh->prepare($sql);
        if ($res = $stmt->execute(array($step,$status))) {
            return $response->withStatus(200);
        } else {
            return $response->withStatus(500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/configuration/defaults', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $res = array();

        // get local green IP
        $sql = 'SELECT `value` FROM `admin` WHERE `variable` = "ip"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $res['localip'] = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0]['value'];

        $res['hostname'] = gethostname();

        $res['timezone'] = date_default_timezone_get();

        return $response->withJson($res, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

