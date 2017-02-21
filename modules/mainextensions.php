<?php

require_once(__DIR__. '/../lib/freepbxFwConsole.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/mainextensions', function (Request $request, Response $response, $args) {
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
    $params = $request->getParsedBody();
    $username = $params['username'];
    $extension = $params['extension'];
    $data['outboundcid'] = $params['outboundcid'];
    $fpbx = FreePBX::create();
    $dbh = FreePBX::Database();

    //Make sure extension is not in use
    $free = checkFreeExtension($extension);
    if ($free !== true) {
        return $response->withJson(array('message'=>$free ), 500);
    }

    //Update user to add this extension as default extension
    //get uid
    $user = $fpbx->Userman->getUserByUsername($username);
    $uid = $user['id'];

    if (isset($user['displayname']) && $user['displayname'] != '') {
        $data['name'] = $user['displayname'];
    } else {
        $data['name'] = $user['username'];
    }

    if (!isset($uid)) {
        return $response->withJson(array('message'=>'User not found' ), 404);
    }

    //update user with $extension as default extension
    $fpbx->Userman->updateUser($uid, $username, $username, $extension);

    //delete extension
    $fpbx->Core->delDevice($extension, true);
    $fpbx->Core->delUser($extension, true);

    //create main extension
    $res = $fpbx->Core->processQuickCreate('pjsip', $extension, $data);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']), 500);
    }

    //Configure Follow me for the extension
    $data['fmfm']='yes';
    $fpbx->Findmefollow->processQuickCreate('pjsip', $extension, $data);
    $fpbx->Findmefollow->addSettingById($extension, 'strategy', $fpbx->Config->get('FOLLOWME_RG_STRATEGY'));
    $fpbx->Findmefollow->addSettingById($extension, 'pre_ring', '0');
    $fpbx->Findmefollow->addSettingById($extension, 'grptime', $fpbx->Config->get('FOLLOWME_TIME'));
    $fpbx->Findmefollow->addSettingById($extension, 'dring', '<http://www.notused >;info=ring2');
    $fpbx->Findmefollow->addSettingById($extension, 'postdest', 'app-blackhole,hangup,1');

    fwconsole('r');
    return $response->withStatus(201);
});

$app->delete('/mainextensions/{extension}', function (Request $request, Response $response, $args) {
    try {
        global $astman;
        $route = $request->getAttribute('route');
        $mainextension = $route->getArgument('extension');
        $fpbx = FreePBX::create();

        $ext_to_del = array();
        $ext_to_del[] = $mainextension;
        //Get all associated extensions
        $all_extensions = $fpbx->Core->getAllUsers();
        foreach ($fpbx->Core->getAllUsers() as $ext) {
            if (substr($ext['extension'], 2) === $mainextension) {
                $ext_to_del[] = $ext['extension'];
            }
        }

        $dbh = FreePBX::Database();
        // clean extension and associated extensions
        foreach ($ext_to_del as $extension) {
            $fpbx->Core->delUser($extension);
            $fpbx->Core->delDevice($extension);
            $sql = 'UPDATE rest_devices_phones'.
              ' LEFT JOIN userman_users ON rest_devices_phones.user_id = userman_users.id'.
              ' SET userman_users.default_extension = NULL'.
              ', rest_devices_phones.extension = NULL'.
              ', rest_devices_phones.secret = NULL'.
              ' WHERE rest_devices_phones.extension = ?';
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array($extension));
        }
        fwconsole('r');
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
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
