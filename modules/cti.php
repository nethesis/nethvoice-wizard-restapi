<?php

require_once(__DIR__. '/../lib/freepbxFwConsole.php');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*
 * Write cti users configuration
 *
 * @api /cti/usersConfiguration
 *
 */
$app->post('/cti/usersConfiguration', function (Request $request, Response $response, $args) {
    try {
        $json = array();

        $users = FreePBX::create()->Userman->getAllUsers();
        $dbh = FreePBX::Database();
        $freepbxVoicemails = FreePBX::Voicemail()->getVoicemail();
        $enabledVoicemails = array_keys($freepbxVoicemails['default']);

        foreach ($users as $user) {
            try {
                if ($user['default_extension'] !== 'none') {
                    $endpoints = array(
                        'mainextension' => (array($user['default_extension'] => (object)array()))
                    );

                    // Retrieve physical extensions
                    $stmt = $dbh->prepare('SELECT extension FROM rest_devices_phones WHERE user_id = ?');
                    $stmt->execute(array($user['id']));
                    $res = $stmt->fetchAll();

                    if (count($res) > 0) {
                        $extensions = array();
                        foreach ($res as $e) {
                            $extensions[$e['extension']] = (object)array();
                        }

                        $endpoints['extension'] = $extensions;
                    }

                    // Set voicemail
                    if (in_array($user['default_extension'], $enabledVoicemails)) {
                        $endpoints['voicemail'] = array($user['default_extension'] => (object)array());
                    }

                    // Set email
                    $endpoints['email'] = ($user['email'] ? array($user['email'] => (object) array()) : (object)array());

                    // Set cellphone
                    $endpoints['cellphone'] = ($user['cell'] ? array($user['cell'] => (object) array()) : (object)array());

                    // Retrieve webrtc, webrtc_mobile, profile id
                    $stmt = $dbh->prepare('SELECT webrtc, webrtc_mobile, profile_id FROM rest_users WHERE user_id = ?');
                    $stmt->execute(array($user['id']));
                    $profileRes = $stmt->fetch();

                    if (!profileRes || !isset($profileRes['profile_id'])) {
                        throw new Exception('no profile associated for '. $user['id']);
                    }

                    // Set webrtc
                    $endpoints['webrtc'] = ($profileRes['webrtc'] ?
                        array($profileRes['webrtc'] => (object)array()) : (object)array());

                    // Set mobile webrtc
                    $endpoints['webrtc_mobile'] = ($profileRes['webrtc_mobile'] ?
                        array($profileRes['webrtc_mobile'] => (object)array()) : (object)array());


                    // Join configuration
                    $userJson = array(
                        'name' => $user['displayname'],
                        'endpoints' => $endpoints,
                        'profile_id' => $profileRes['profile_id']
                    );

                    $json[$user['username']] = $userJson;
                    // error_log(print_r($user, true));
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
        // Write configuration file
        require(__DIR__. '/../config.inc.php');
        $res = file_put_contents($config['settings']['cti_config_path']. '/users.json',
            json_encode($json, JSON_PRETTY_PRINT));

        if ($res === FALSE) {
            throw new Exception('fail to write config');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }


    // return $response->withJson($json, 200);
    return $response->withStatus(200);
});
