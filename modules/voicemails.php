<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/voicemails', function (Request $request, Response $response, $args) {
    try {
        $res = FreePBX::Voicemail()->getVoicemail();

        return $response->withJson($res['default'], 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withJson(array('status' => $e->getMessage()), 500);
    }
});

$app->get('/voicemails/{extension}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $extension = $route->getArgument('extension');
        $res = FreePBX::Voicemail()->getVoicemail();

        return $response->withJson($res['default'][$extension], 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withJson(array('status' => $e->getMessage()), 500);
    }
});

$app->post('/voicemails', function (Request $request, Response $response, $args) {
    global $db;
    try {
        $params = $request->getParsedBody();
        foreach (FreePBX::create()->Core->getAllUsersByDeviceType() as $e) {
            if ($e['extension'] === $params['extension']) {
                $extension = $e;
            }
            break;
        }
        if (!isset($extension)) {
            return $response->withJson(array('status' => 'Extension '.$params['extension']." doesn't exist"), 400);
        }
        foreach (FreePBX::create()->Userman->getAllUsers() as $u) {
            if ($u['default_extension'] = $extension['extension']) {
                $user = $u;
            }
            break;
        }
        $tech = $extension['tech'];
        $data = array();
        $data['name'] = $extension['name'];
        $data['vmpwd'] = rand(0, 9).rand(0, 9).rand(0, 9).rand(0, 9);
        $data['email'] = $user['email'];
        $data['vm'] = 'yes';
        FreePBX::create()->Voicemail->processQuickCreate($tech, $extension['extension'], $data);
        $sql = 'UPDATE `rest_user_passwords` SET `voicemail_password`="'.$data['vmpwd'].'" WHERE `username`="'.$user['username'].'"';
        $db->query($sql);

        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withJson(array('status' => $e->getMessage()), 500);
    }
});
