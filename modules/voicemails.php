<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/voicemails', function (Request $request, Response $response, $args) {
    try {
        $res = FreePBX::Voicemail()->getVoicemail();

        return $response->withJson($res['default'] ? $res['default'] : array(), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withStatus(500);
    }
});

$app->get('/voicemails/{extension}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $extension = $route->getArgument('extension');
        $res = FreePBX::Voicemail()->getVoicemail();

        if (!array_key_exists($extension, $res['default'])) {
          return $response->withStatus(404);
        }

        return $response->withJson($res['default'][$extension], 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withStatus(500);
    }
});

$app->post('/voicemails', function (Request $request, Response $response, $args) {
    global $db;
    try {
        $params = $request->getParsedBody();
        $users = FreePBX::create()->Core->getAllUsersByDeviceType();
        foreach ($users as $e) {
            if ($e['extension'] === $params['extension']) {
                $extension = $e;
                break;
            }
        }

        if (!isset($extension)) {
            return $response->withJson(array('status' => 'Extension '.$params['extension']." doesn't exist"), 400);
        }

        if($params['state'] == 'yes') {
            $user = FreePBX::create()->Userman->getUserByDefaultExtension($extension['extension']);
            $tech = $extension['tech'];
            $data = array();
            $data['name'] = $extension['name'];
            $data['vmpwd'] = rand(0, 9).rand(0, 9).rand(0, 9).rand(0, 9);
            $data['email'] = $user['email'];
            $data['vm'] = 'yes';
            FreePBX::create()->Voicemail->processQuickCreate($tech, $extension['extension'], $data);
            $sql = 'UPDATE `rest_user_passwords` SET `voicemail_password`="'.$data['vmpwd'].'" WHERE `username`="'.$user['username'].'"';
            $db->query($sql);
        } else {
            FreePBX::create()->Voicemail->delMailbox($extension['extension']);
        }

        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withStatus(500);
    }
});
