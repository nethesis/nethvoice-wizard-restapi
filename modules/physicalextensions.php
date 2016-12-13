<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function extensionExists($e, $extensions){
    foreach ($extensions as $extension) {
        if ($extension['extension'] == $e){
            return true;
        }
    }
    return false;
}

$app->get('/physicalextensions', function (Request $request, Response $response, $args) {
    $physicalextensions = FreePBX::create()->Core->getAllUsersByDeviceType('pjsip');
    return $response->withJson($physicalextensions,200);
});

$app->get('/physicalextensions/{extension}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $extension = $route->getArgument('extension');
    $physicalextensions = FreePBX::create()->Core->getAllUsersByDeviceType('pjsip');
    foreach ($physicalextensions as $e) {
        if ($e['extension'] == $extension){
            return $response->withJson($e,200);
        }
    }
    return $response->withStatus(404);
});

$app->post('/physicalextensions', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $virtualextensionnumber = $params['virtualextension'];
    $mac = $params['mac'];
    $fpbx = FreePBX::create();

    //get associated virtual extension
    $virtualextensions = $fpbx->Core->getAllUsersByDeviceType('virtual');
    foreach ($virtualextensions as $ve) {
        if ($ve['extension'] == $virtualextensionnumber){
	    $virtualextension = $ve;
            break;
        }
    }
    //error if virtual extension number doesn't exist
    if (!isset($virtualextension)){
        return $response->withJson(array("status"=>"Virtual extension ".$virtualextensionnumber." doesn't exist"),400);
    }

    if (isset($params['extension'])){
       //use given extension number
       if (!preg_match('/9[1-7]'.$virtualextensionnumber.'/', $params['extension'])){
           return $response->withJson(array("status"=>"Wrong physical extension number supplied"),400);
       } else {
           $extension = $params['extension'];
       }
    } else {
        //get first free physical extension number for this virtual extension
        $extensions = $fpbx->Core->getAllUsersByDeviceType();
        for ($i=91; $i<=97; $i++){
            if (!extensionExists($i.$virtualextensionnumber,$extensions)){
                $extension = $i.$virtualextensionnumber;
                break;
            }
        }
        //error if there aren't available extension numbers
        if (!isset($extension)){
            return $response->withJson(array("status"=>"There aren't available extension numbers"),500);
        }
    }

    //delete extension
    $fpbx->Core->delDevice($extension,true);
    $fpbx->Core->delUser($extension,true);

    //create physical extension
    $data['name'] = $virtualextension['name'];
    $res = $fpbx->Core->processQuickCreate('pjsip',$extension,$data);
    if (!$res['status']) {
        return $response->withJson(array('message'=>$res['message']),500);
    }

    //Configure Follow me for the extension
    $followmeconfig = $fpbx->Findmefollow->getSettingsById($virtualextensionnumber);
    $grouplist = explode("-",$followmeconfig['grplist']);
    $grouplist[] = $extension;
    $fpbx->Findmefollow->addSettingById($virtualextensionnumber, 'grplist',$grouplist);

    // insert created physical extension
    $created_extension = $res['ext'];
    $created_extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $created_extension . '" AND keyword="secret"', "getOne");
    $dbh = FreePBX::Database();
    $sql = 'UPDATE `rest_devices_phones` SET `virtualextension`= ?, `extension`= ?, `secret`= ? WHERE mac = "'.$mac.'"';
    $stmt = $dbh->prepare($sql);
    if ($res = $stmt->execute(array($virtualextensionnumber,$created_extension,$created_extension_secret))) {
        return $response->withStatus(200);
    } else {
        return $response->withStatus(500);
    }
});

$app->post('/physicalextensions/unlink', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $mac = $params['mac'];
    $dbh = FreePBX::Database();

    // clean extension
    $extension_to_delete = sql('SELECT extension FROM `rest_devices_phones` WHERE mac = "' . $mac . '"', "getOne");
    sql('DELETE FROM devices WHERE id =' . $extension_to_delete);
    sql('DELETE FROM sip WHERE id =' . $extension_to_delete);
    sql('DELETE FROM users WHERE extension =' . $extension_to_delete);

    $sql = 'UPDATE `rest_devices_phones` SET `virtualextension`= "", `extension`= "", `secret`= "" WHERE mac = "'.$mac.'"';
    $stmt = $dbh->prepare($sql);
    if ($res = $stmt->execute()) {
        return $response->withStatus(200);
    } else {
        return $response->withStatus(500);
    }
});
