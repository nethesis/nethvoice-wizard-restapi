<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/mainextensions', function (Request $request, Response $response, $args) {
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    return $response->withJson($mainextensions,200);
});

$app->get('/mainextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $mainextensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    foreach ($mainextensions as $e) {
        if ($e['extension'] == $extension){
            return $response->withJson($e,200);
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

    //Update user to add this extension as default extension
    //get uid
    $user = $fpbx->Userman->getUserByUsername($username);
    $uid = $user['id'];
    $data['name'] = $user['displayname'];

    if (!isset($uid)){
        return $response->withJson(array('message'=>'User not found' ),404);
    }

    //update user with $extension as default extension
    $fpbx->Userman->updateUser($uid, $username, $username, $extension);

    //delete extension
    $fpbx->Core->delDevice($extension,true);
    $fpbx->Core->delUser($extension,true);

    //create virtual extension
    $res = $fpbx->Core->processQuickCreate('virtual',$extension,$data);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']),500);
    }

    //Configure Follow me for the extension
    $data['fmfm']='yes';
    $fpbx->Findmefollow->delUser($extension,false);
    $fpbx->Findmefollow->processQuickCreate('virtual', $extension, $data);
    $fpbx->Findmefollow->addSettingById($extension, 'strategy','ringall');
    $fpbx->Findmefollow->addSettingById($extension, 'pre_ring','0');
    $fpbx->Findmefollow->addSettingById($extension, 'grptime','30');
    $fpbx->Findmefollow->addSettingById($extension, 'dring','<http://www.notused >;info=ring2');
    $fpbx->Findmefollow->addSettingById($extension, 'postdest','app-blackhole,hangup,1');

    return $response->withStatus(201);
});
