<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once('lib/libCTI.php');

/* GET /cti/profiles
Return: [{id:1, name: admin, macro_permissions [ oppanel: {value: true, permissions [ {name: "foo", description: "descrizione...", value: false},{..} ]}
*/
$app->get('/cti/profiles', function (Request $request, Response $response, $args) {
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
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $profile = $request->getParsedBody();
        if (postCTIProfile($profile,$id)) {
            fwconsole('r');
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
        $profile = $request->getParsedBody();
        $id = postCTIProfile($profile);
        if ($id) {
            fwconsole('r');
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
        fwconsole('r');
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
        fwconsole('r');
        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* GET /cti/groups
Return: [{id:1, name: support}, {id:2, name:development}]
*/
$app->get('/cti/groups', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SELECT id, name FROM `rest_cti_groups`';
        $res = $dbh->sql($sql, 'getAll', \PDO::FETCH_ASSOC);

        return $response->withJson($res, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* GET /cti/groups/users/:id
Return: {id:1, name: support}
*/
$app->get('/cti/groups/users/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');

        $dbh = FreePBX::Database();
        $sql = 'SELECT rest_cti_groups.id, rest_cti_groups.name'.
            ' FROM rest_cti_groups'.
            ' JOIN rest_cti_users_groups ON rest_cti_users_groups.group_id = rest_cti_groups.id'.
            ' WHERE rest_cti_users_groups.user_id = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($id));

        $data = array();

        while ($res = $sth->fetchObject()) {
            $data[] = $res->id;
        }

        return $response->withJson($data, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* POST /cti/groups {"name": "sviluppo"}
*/
$app->post('/cti/groups', function (Request $request, Response $response, $args) {
    try {
        $data = $request->getParsedBody();
        $dbh = FreePBX::Database();
        $sql = 'INSERT INTO rest_cti_groups VALUES (NULL, ?)';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($data['name']));

        $sql = 'INSERT INTO rest_cti_permissions VALUES (NULL, ?, ?, ?)';
        $sth = $dbh->prepare($sql);
        $sth->execute(array("grp_".trim(strtolower(preg_replace('/[^a-zA-Z0-9]/','',$data['name']))), "Group: ".trim($data['name']), "Group: ".trim($data['name']).": of presence panel"));

        $query = 'SELECT id FROM rest_cti_macro_permissions WHERE name = "presence_panel"';
        $sth = $dbh->prepare($query);
        $sth->execute();
        $macro_group_id = $sth->fetchObject();

        $query = 'SELECT id FROM rest_cti_permissions WHERE name = ?';
        $sth = $dbh->prepare($query);
        $sth->execute(array("grp_".trim(strtolower(preg_replace('/[^a-zA-Z0-9]/','',$data['name'])))));
        $perm_id = $sth->fetchObject();

        $sql = 'INSERT INTO rest_cti_macro_permissions_permissions VALUES (?, ?)';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($macro_group_id->id, $perm_id->id));

        $query = 'SELECT id FROM rest_cti_groups WHERE name = ?';
        $sth = $dbh->prepare($query);
        $sth->execute(array($data['name']));
        $group_id = $sth->fetchObject();
        fwconsole('r');

        return $response->withJson($group_id, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/* DELETE /cti/groups/{id} */
$app->delete('/cti/groups/{id}', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');

        $query = 'SELECT name FROM rest_cti_groups WHERE id = ?';
        $sth = $dbh->prepare($query);
        $sth->execute(array($id));
        $group_name = $sth->fetchObject();

        $sql = 'DELETE FROM `rest_cti_groups` WHERE `id` = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($id));

        $sql = 'DELETE FROM `rest_cti_permissions` WHERE `name` = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array("grp_".trim(strtolower(preg_replace('/[^a-zA-Z0-9]/','',$group_name->name)))));

        fwconsole('r');

        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * POST /cti/groups/users/3 groups: [1, 4, 5]
*/
$app->post('/cti/groups/users/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $user_id = $route->getArgument('id');
        $data = $request->getParsedBody();

        // Delete previous assignments
        $dbh = FreePBX::Database();
        $sql = 'DELETE FROM rest_cti_users_groups WHERE user_id = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($user_id));

        // Add groups for user
        foreach ($data['groups'] as $group_id) {
            $sql = 'INSERT INTO rest_cti_users_groups VALUES (NULL, ?, ?)';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($user_id, $group_id));
        }

        fwconsole('r');

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * POST /cti/dbconn { host: string, port: numeric, type: string, user: string, pass: string, name: string }
*/
$app->post('/cti/dbconn', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $data = $request->getParsedBody();

        // Delete previous assignments
        $dbh = NethCTI::Database();
        $sql = 'INSERT INTO user_dbconn(host, port, type, user, pass, name, creation)'.
            ' VALUES (?, ?, ?, ?, ?, ?, NOW())';
        $sth = $dbh->prepare($sql);
        $res = $sth->execute(array(
            $data['host'],
            $data['port'],
            $data['type'],
            $data['user'],
            $data['pass'],
            $data['name']
        ));

        if ($res === FALSE) {
            throw new Exception($sth->errorInfo()[2]);
        }

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * GET /cti/dbconn { host: string, port: numeric, type: string, user: string, pass: string, name: string }
*/
$app->get('/cti/dbconn', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $data = $request->getParsedBody();

        // Delete previous assignments
        $dbh = NethCTI::Database();
        $sql = 'SELECT * FROM user_dbconn';
        $sth = $dbh->prepare($sql);
        $sth->execute();

        $res = $sth->fetchAll(PDO::FETCH_ASSOC);

        return $response->withJson($res);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * DELETE /cti/dbconn/:id
*/
$app->delete('/cti/dbconn/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $data = $request->getParsedBody();

        // Delete previous assignments
        $dbh = NethCTI::Database();
        $sql = 'DELETE FROM user_dbconn WHERE id = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($id));

        if ($res === FALSE) {
            throw new Exception($sth->errorInfo()[2]);
        }

        return $response->withJson($res);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * GET /cti/dbconn/type
*/
$app->get('/cti/dbconn/type', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $data = $request->getParsedBody();

        return $response->withJson(array(
            'mysql' => 'MySQL',
            'postgres' => 'PostgreSQL',
            'mssql:7_4' => 'SQL Server 2012/2014',
            'mssql:7_3_A' => 'SQL Server 2008 R2',
            'mssql:7_3_B' => 'SQL Server 2008',
            'mssql:7_2' => 'SQL Server 2005',
            'mssql:7_1' =>  'SQL Server 2000'
        ));
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * GET /cti/template { name: string, custom: bool, html: string }
*/
$app->get('/cti/customer_card/template', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $data = $request->getParsedBody();

        $tpl_path = '/var/lib/nethserver/nethcti/templates/customer_card';

        $templates = array();

        if ($handle = opendir($tpl_path)) {
            while (false !== ($name = readdir($handle))) {
                if ($name != "." && $name != "..") {
                    $templates[] = array(
                        'name' => str_replace('_custom', '', $name),
                        'custom' => (strpos($name, '_custom') !== FALSE),
                        'html' => base64_encode(file_get_contents($tpl_path. '/'. $name))
                    );
                }
            }
            closedir($handle);
        }

        return $response->withJson($templates);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * POST /cti/template { name: string, custom: bool, html: string }
*/
$app->post('/cti/customer_card/template', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $data = $request->getParsedBody();

        $custom = $data['custom'];
        $name = trim(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['name']))).
            ($custom ? '_custom' : ''). '.ejs';
        $html = $data['html'];
        $tpl_path = '/var/lib/nethserver/nethcti/templates/customer_card';

        if (!is_writable($tpl_path) || !file_put_contents($tpl_path. '/'. $name, $html)) {
            throw new Exception('template write error');
        }

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
 * DELETE /cti/template/:name
*/
$app->delete('/cti/customer_card/template/{name}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $name = $route->getArgument('name');

        $tpl_path = '/var/lib/nethserver/nethcti/templates/customer_card';

        if (file_exists($tpl_path. '/'. $name. '.ejs')) {
            unlink($tpl_path. '/'. $name. '.ejs');
        } else {
            throw new Exception('template not found');
        }

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
