<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


/* GET /cti/profiles
Return: [{id:1, name: admin, macro_permissions [ oppanel: {value: true, permissions [ {name: "foo", description: "descrizione...", value: false},{..} ]}
*/
$app->get('/cti/profiles', function (Request $request, Response $response, $args) {
    include_once('lib/libCTI.php');
    $results = getCTIPermissionProfiles();
    if (!$results) {
        return $response->withStatus(500);
    }
    return $response->withJson($results,200);
});


/* GET /cti/profiles/{id}
Return: {id:1, name: admin, macro_permissions [ oppanel: {value: true, permissions [ {name: "foo", description: "descrizione...", value: false}
*/
$app->get('/cti/profiles/{id}', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $results = getCTIPermissionProfiles($id);
        if (!$results) {
            return $response->withStatus(500);
        }
        return $response->withJson($results,200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* GET /cti/permissions
Return: [{"cdr": {"permissions": [{"description": "descrizione...", "id": "2", "name": "sms", "value": true  },  { ...}]},{"phonebook": {"permissions": [{"description": "descrizione...", "id": "2", "name": "sms", "value": true  },  { ...}]}]
*/
$app->get('/cti/permissions', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
       	$results = getCTIPermissions();
        if (!$results) {
            return $response->withStatus(500);
        }
        return $response->withJson($results,200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* POST /cti/profiles/{id} {"id":"1","name":"base","macro_permissions":{"phonebook":{"value":false,"permissions":[]},"oppanel":{"value":true,"permissions":[{"id":"1","name":"intrude","description":"descrizione...","value":false}
*/
$app->post('/cti/profiles/{id}', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $profile = $request->getParsedBody();
        if (postCTIProfile($profile,$id)) {
            return $response->withJson(array('status' => true), 200);
        } else {
            throw new Exception('Error editing profile');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* POST /cti/profiles {name: admin, permissions: [{name: "foo", type: customer_card, value: false} return id */
$app->post('/cti/profiles', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        $profile = $request->getParsedBody();
        $id = postCTIProfile($profile);
        if ($id) {
            return $response->withJson(array('id' => $id ), 200);
        } else {
            throw new Exception('Error creating new profile');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* GET /cti/profiles/users/{user_id} Return profile id of the user*/
$app->get('/cti/profiles/users/{user_id}', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $route = $request->getAttribute('route');
        $user_id = $route->getArgument('user_id');
        $sql = 'SELECT `profile_id` FROM `rest_users` WHERE `user_id` = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($user_id));
        $profile_id = $sth->fetchAll()[0][0];
        if (!$profile_id) {
            return $response->withStatus(404);
        }
        return $response->withJson(array('id' => $profile_id),200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* POST /cti/profiles/users/{user_id} => {profile_id: <profile_id>} */
$app->post('/cti/profiles/users/{user_id}', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $route = $request->getAttribute('route');
        $user_id = $route->getArgument('user_id');
        $data = $request->getParsedBody();
        $profile_id = $data['profile_id'];
        $sql =  'INSERT INTO rest_users (user_id,profile_id)'.
                ' VALUES (?,?)'.
                ' ON DUPLICATE KEY UPDATE profile_id = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($user_id, $profile_id, $profile_id));
        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* DELETE /cti/profiles/{id} */
$app->delete('/cti/profiles/{id}', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $sql = 'DELETE FROM `rest_cti_profiles` WHERE `id` = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($id));
        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * Write cti users configuration
 *
 * @api /cti/usersConfiguration
 *
 */
$app->post('/cti/configuration/users', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
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
                    $stmt = $dbh->prepare('SELECT extension, type FROM rest_devices_phones WHERE user_id = ?');
                    $stmt->execute(array($user['id']));
                    $res = $stmt->fetchAll();

                    if (count($res) > 0) {
                        $extensions = array();
                        foreach ($res as $e) {
                            $settings = array(
                                'type' => $e['type']
                            );

                            if ($e['type'] === 'physical') {
                                $settings['web_user'] = 'admin';
                                $settings['web_password'] = 'admin';
                            }
                            else if ($e['type'] === 'webrtc' || $e['type'] === 'webrtc_mobile') {
                                // Retrieve webrtc sip credentials
                                $stmt = $dbh->prepare('SELECT data FROM sip WHERE keyword IN ("account", "secret") AND id = ?');
                                $stmt->execute(array($e['extension']));
                                $sipres = $stmt->fetchAll();

                                if ($sipres[0]['data'] && $sipres[1]['data']) {
                                    $settings['user'] = $sipres[0]['data'];
                                    $settings['password'] = $sipres[1]['data'];
                                }
                            }

                            $extensions[$e['extension']] = (object)$settings;
                        }

                        $endpoints['extension'] = $extensions;
                    }

                    // Set voicemail
                    if (in_array($user['default_extension'], $enabledVoicemails)) {
                        $endpoints['voicemail'] = array($user['default_extension'] => (object)array());
                    }

                    // Set email
                    $endpoints['email'] = ($user['email'] ? array($user['email'] => (object) array()) : (object)array());

                    // Retrieve profile id and mobile
                    $stmt = $dbh->prepare('SELECT profile_id,mobile FROM rest_users WHERE user_id = ?');
                    $stmt->execute(array($user['id']));
                    $profileRes = $stmt->fetch();

                    if (!profileRes || !isset($profileRes['profile_id'])) {
                        throw new Exception('no profile associated for '. $user['id']);
                    }

                    // Set cellphone
                    $endpoints['cellphone'] = ($profileRes['mobile'] ? array($profileRes['mobile'] => (object) array()) : (object)array());

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
        $res = writeCTIConfigurationFile('/users.json',$json);

        if ($res === FALSE) {
            throw new Exception('fail to write config');
        } else {
            //Restart nethcti-server
            system("/usr/bin/sudo /usr/bin/systemctl restart nethcti-server &");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }


    // return $response->withJson($json, 200);
    return $response->withStatus(200);
});


/*
 * Write cti profiles configuration
 *
 * @api /cti/configuration/profiles
 *
 */

$app->post('/cti/configuration/profiles', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        $results = getCTIPermissionProfiles(false,true);
        if (!$results) {
            throw new Exception('Empty profile config');
        }
        foreach ($results as $r) {
            $out[$r['id']] = $r;
        }
        // Write configuration file
        $res = writeCTIConfigurationFile('/profiles.json',$out);
        if ($res === FALSE) {
            throw new Exception('fail to write config');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
    return $response->withStatus(200);
});

/*
 * Write cti configuration: allow CTI server to access CDR, CEL and voicemail DBs
 *
 * @api /cti/configuration/asteriskcdrdb
 *
 */
$app->post('/cti/configuration/asteriskcdrdb', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        global $amp_conf;
        $asteriskcdrdb = array();
        foreach (array('history_call','cel','voicemail') as $dbobj) {
            $asteriskcdrdb[$dbobj] = array();
            $asteriskcdrdb[$dbobj]['dbhost'] = 'localhost';
            $asteriskcdrdb[$dbobj]['dbport'] = '/var/lib/mysql/mysql.sock';
            $asteriskcdrdb[$dbobj]['dbtype'] = 'mysql';
            $asteriskcdrdb[$dbobj]['dbuser'] = ($amp_conf['CDRDBUSER'] ? $amp_conf['CDRDBUSER'] : $amp_conf['AMPDBUSER']);
            $asteriskcdrdb[$dbobj]['dbpassword'] = ($amp_conf['CDRDBPASS'] ? $amp_conf['CDRDBPASS'] : $amp_conf['AMPDBPASS']);
            $asteriskcdrdb[$dbobj]['dbname'] = $amp_conf['CDRDBNAME'];
        }
 
        $res = writeCTIConfigurationFile('/dbstatic.d/asteriskcdrdb.json',$asteriskcdrdb);
        if ($res === FALSE) {
            throw new Exception('fail to write config');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
    return $response->withStatus(200);
});

/*
 * Write cti configuration: trunk file
 *
 * @api /cti/configuration/trunks
 *
 */
$app->post('/cti/configuration/trunks', function (Request $request, Response $response, $args) {
    try {
        include_once('lib/libCTI.php');
        $trunks = getTrunksConfiguration();
        $res = writeCTIConfigurationFile('/ast_trunks.json',$trunks);
        if ($res === FALSE) {
            throw new Exception('fail to write config');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
    return $response->withStatus(200);
});

