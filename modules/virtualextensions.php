<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$tech='virtual';

$app->get('/virtualextensions', function (Request $request, Response $response, $args) {
    $virtualextensions = FreePBX::create()->Core->getAllUsersByDeviceType($tech);
    return $response->withJson($virtualextensions,200);
});

$app->get('/virtualextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $virtualextensions = FreePBX::create()->Core->getAllUsersByDeviceType($tech);
    foreach ($virtualextensions as $e) {
        if ($e['extension'] == $extension){
            return $response->withJson($e,200);
        }
    }
    return $response->withJson(array(),404);
});

$app->post('/virtualextensions', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $params['username'];
    $extension = $params['extension'];
    $data['outboundcid'] = $params['outboundcid'];
    $fpbx = FreePBX::create();

    //Update user to add this extension as default extension
    //get uid
    $users = $fpbx->Userman->getAllUsers();
    foreach ($users as $u) {
        if ($u['username'] == $username){
            $uid = $u['id'];
            $data['name'] = $u['displayname'];
        }
    }
    if (!isset($uid)){
        return $response->withJson(array('message'=>'User not found' ),500);
    }

    //update user with $extension as default extension
    $fpbx->Userman->updateUser($uid, $username, $username, $extension);

    //delete extension
    $fpbx->Core->delDevice($extension,true);
    $fpbx->Core->delUser($extension,true);

    //create virtual extension
    $res = $fpbx->Core->processQuickCreate($tech,$extension,$data);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']),500);
    }

    //Configure Follow me for the extension
    $data['fmfm']='yes';
    $fpbx->Findmefollow->delUser($extension,false);
    $fpbx->Findmefollow->processQuickCreate($tech, $extension, $data);
    $fpbx->Findmefollow->addSettingById($extension, 'strategy','ringall');
    $fpbx->Findmefollow->addSettingById($extension, 'pre_ring','0');
    $fpbx->Findmefollow->addSettingById($extension, 'grptime','30');
    $fpbx->Findmefollow->addSettingById($extension, 'dring','<http://www.notused >;info=ring2');
    $fpbx->Findmefollow->addSettingById($extension, 'postdest','app-blackhole,hangup,1');

    return $response->withJson(array("status"=>true),200);
});
