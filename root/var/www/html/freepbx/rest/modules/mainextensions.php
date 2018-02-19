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

$app->get('/mainextensions', function (Request $request, Response $response, $args) {
    fwconsole('userman sync');
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    return $response->withJson($mainextensions, 200);
});

$app->get('/mainextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    foreach ($mainextensions as $e) {
        if ($e['extension'] == $extension) {
            return $response->withJson($e, 200);
        }
    }
    return $response->withStatus(404);
});

$app->post('/mainextensions', function (Request $request, Response $response, $args) {
    global $astman;
    $params = $request->getParsedBody();
    $username = $params['username'];
    $mainextension = $params['extension'];
    $data['outboundcid'] = $params['outboundcid'];
    $fpbx = FreePBX::create();
    $dbh = FreePBX::Database();

    //Update user to add this extension as default extension
    //get uid
    $user = $fpbx->Userman->getUserByUsername($username);
    $uid = $user['id'];

    if (!isset($uid)) {
        return $response->withJson(array('message'=>'User not found' ), 404);
    }

    //Delete user old extension and all his extensions
    $sql = 'SELECT `default_extension` FROM `userman_users` WHERE `username` = ?';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($username));
    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (isset($res)){
        $oldmain = $res['default_extension'];
        $ext_to_del = array();
        $ext_to_del[] = $oldmain;
        //Get all associated extensions
        $all_extensions = $fpbx->Core->getAllUsers();
        foreach ($fpbx->Core->getAllUsers() as $ext) {
            if (substr($ext['extension'], 2) === $oldmain) {
                $ext_to_del[] = $ext['extension'];
            }
        }
        // clean extension and associated extensions
        foreach ($ext_to_del as $extension) {
            $fpbx->Core->delUser($extension);
            $fpbx->Core->delDevice($extension);

            // set values to NULL for physical devices
            $sql = 'UPDATE rest_devices_phones'.
              ' SET user_id = NULL'.
              ', extension = NULL'.
              ', secret = NULL'.
              ' WHERE user_id = ? AND mac IS NOT NULL';
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array($uid));

            // remove user's webrtc phone and custom devices
            $sql = 'DELETE FROM rest_devices_phones'.
              ' WHERE user_id = ? AND mac IS NULL';
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array($uid));
        }
        $fpbx->Userman->updateUser($uid, $username, $username);
    }


    //exit if extension is empty
    error_log(print_r($mainextension,true));
    if (!isset($mainextension) || empty($mainextension) || $mainextension=='none') {
        return $response->withStatus(200);
    }

    //Make sure extension is not in use
    $free = checkFreeExtension($mainextension);
    if ($free !== true ) {
        return $response->withJson(array('message'=>$free ), 422);
    }

    if (isset($user['displayname']) && $user['displayname'] != '') {
        $data['name'] = $user['displayname'];
    } else {
        $data['name'] = $user['username'];
    }

    //create main extension
    $res = $fpbx->Core->processQuickCreate('pjsip', $mainextension, $data);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']), 500);
    }

    //update user with $extension as default extension
    $res = $fpbx->Userman->updateUser($uid, $username, $username, $mainextension);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']), 500);
    }

    //Configure Follow me for the extension
    $data['fmfm']='yes';
    $fpbx->Findmefollow->processQuickCreate('pjsip', $mainextension, $data);
    $fpbx->Findmefollow->addSettingById($mainextension, 'strategy', $fpbx->Config->get('FOLLOWME_RG_STRATEGY'));
    $fpbx->Findmefollow->addSettingById($mainextension, 'pre_ring', '0');
    $fpbx->Findmefollow->addSettingById($mainextension, 'grptime', $fpbx->Config->get('FOLLOWME_TIME'));
    $fpbx->Findmefollow->addSettingById($mainextension, 'dring', '<http://www.notused >;info=ring2');
    $fpbx->Findmefollow->addSettingById($mainextension, 'postdest', 'app-blackhole,hangup,1');

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');
    return $response->withStatus(201);
});

function checkTableExists($table) {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SHOW TABLES LIKE ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($table));
        if($sth->fetch(\PDO::FETCH_ASSOC)) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function checkFreeExtension($extension){
    try {
        $dbh = FreePBX::Database();
        $extensions = array();
        $extensions[] = $extension;
        for ($i=90; $i<=99; $i++) {
            $extensions[]=$i.$extension;
        }
        foreach ($extensions as $extension) {
            //Check extensions
            $sql = 'SELECT * FROM `sip` WHERE `id`= ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use");
            }

            //Check ringgroups
            $sql = 'SELECT * FROM `ringgroups` WHERE `grpnum`= ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
               throw new Exception("Extension $extension already in use in groups");
            }

            //check custom featurecodes
            $sql = 'SELECT * FROM `featurecodes` WHERE `customcode` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as custom code");
            }

            //check defaul feturecodes
            if (checkTableExists("featurecodes")){
                $sql = 'SELECT * FROM `featurecodes` WHERE `defaultcode` = ? AND `customcode` IS NULL';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($extension));
                if($sth->fetch(\PDO::FETCH_ASSOC)) {
                    throw new Exception("Extension $extension already in use as default code");
                }
            }

            //check queues
            $sql = 'SELECT * FROM `queues_details` WHERE `id` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as queue");
            }

            //check trunks
            $sql = 'SELECT * FROM `trunks` WHERE `channelid` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as trunk");
            }

            //check parkings
            if (checkTableExists("parkplus")){
                $sql = 'SELECT * FROM `parkplus` WHERE `parkext` = ?';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($extension));
                if($sth->fetch(\PDO::FETCH_ASSOC)) {
                    throw new Exception("Extension $extension already in use as parking");
                }
            }
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $e->getMessage();
    }
}
