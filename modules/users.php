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

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function getUser($username) {
    # add domain part if needed
    if (strpos($username, '@') === false) {
        exec('/usr/bin/hostname -d', $out, $ret);
        $domain = $out[0];
        return "$username@$domain";
    }
    return $username;
}

function userExists($username) {
    $needle = getUser($username);
    $users = shell_exec("/usr/bin/sudo /usr/libexec/nethserver/list-users");
    foreach (json_decode($users) as $user => $props) {
        if ($user == $needle) {
            return true;
        }
    }
    return false;
}

function getPassword($username) {
    return sql(
      'SELECT rest_users.password'.
      ' FROM rest_users'.
      ' JOIN userman_users ON rest_users.user_id = userman_users.id'.
      ' WHERE userman_users.username = \''. getUser($username). '\'', 'getOne'
    );
}

function setPassword($username, $password) {
    sync();
    $dbh = FreePBX::Database();
    $sql =  'INSERT INTO rest_users (user_id,password)'.
            ' SELECT id, ?'.
            ' FROM userman_users'.
            ' WHERE username = ?'.
            ' ON DUPLICATE KEY UPDATE password = ?';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($password, $username, $password));
}

function sync() {
    $userman = FreePBX::create()->Userman;
    $auth = $userman->getAuthObject();
    $auth->sync($output);
}

# Get final wizard report for created users

$app->get('/final', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $sql = ' SELECT u.username, u.displayname, r.password, u.default_extension
                 FROM userman_users u JOIN rest_users r ON r.user_id = u.id
                 WHERE u.default_extension != "none"
                 ORDER BY u.default_extension ';
        $final = $dbh->sql($sql, 'getAll', \PDO::FETCH_ASSOC);
        //get voicemail password
        $vm = FreePBX::Voicemail();
        foreach ($final as $key => $value) {
            $vmpwd = $vm->getVoicemailBoxByExtension($value['default_extension'])['pwd'];
            if (isset($vmpwd) && !is_null($vmpwd)) {
                $final[$key]['voicemailpwd'] = $vmpwd;
            } else {
                $final[$key]['voicemailpwd'] = '';
            }
        }
        return $response->withJson($final, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

# Count all users

$app->get('/users/count', function (Request $request, Response $response, $args) {
    $blacklist = ['admin', 'administrator', 'guest', 'krbtgt'];
    $users = FreePBX::create()->Userman->getAllUsers();
    $dbh = FreePBX::Database();
    $i = 0;
    foreach ($users as $user) {
        if (in_array(strtolower($users[$i]['username']), $blacklist)) {
            unset($users[$i]);
        }
        $i++;
    }
    return $response->withJson(count(array_values($users)),200);
});

# List all users

$app->get('/users/{all}', function (Request $request, Response $response, $args) {
    $all = $request->getAttribute('all');
    $blacklist = ['admin', 'administrator', 'guest', 'krbtgt'];
    if($all == "true") {
        sync(); // force FreePBX user sync
    }
    $users = FreePBX::create()->Userman->getAllUsers();
    $dbh = FreePBX::Database();
    $i = 0;
    foreach ($users as $user) {
        if (in_array(strtolower($users[$i]['username']), $blacklist)) {
            unset($users[$i]);
        } else {
            if($all == "false" && $users[$i]['default_extension'] == 'none') {
                unset($users[$i]);
            } else {
                $users[$i]['password'] = getPassword(getUser($users[$i]['username']));
                $sql = 'SELECT rest_devices_phones.*'.
                  ' FROM rest_devices_phones'.
                  ' JOIN userman_users ON rest_devices_phones.user_id = userman_users.id'.
                  ' WHERE userman_users.default_extension = ?';
                $stmt = $dbh->prepare($sql);
                $stmt->execute(array($users[$i]['default_extension']));
                $users[$i]['devices'] = array();
                while ($d = $stmt->fetch(\PDO::FETCH_ASSOC))
                    $users[$i]['devices'][] = $d;
                $sql = 'SELECT rest_users.profile_id'.
                  ' FROM rest_users'.
                  ' JOIN userman_users ON rest_users.user_id = userman_users.id'.
                  ' WHERE userman_users.username = ?';
                $stmt = $dbh->prepare($sql);$stmt->execute(array($users[$i]['username']));
                $users[$i]['profile'] = $stmt->fetch(\PDO::FETCH_ASSOC)['profile_id'];
            }
        }
        $i++;
    }
    return $response->withJson(array_values($users),200);
});


# Return the selected user

/*$app->get('/users/{username}', function (Request $request, Response $response, $args) {
    $username = $request->getAttribute('username');
    if (userExists($username)) {
        $users = FreePBX::create()->Userman->getAllUsers();
        foreach ($users as $u) {
            if ($u['username'] == $username) {
                $u['password'] = getPassword(getUser($u['username']));
                return $response->withJson($u);
            }
        }
    }
    return $response->withStatus(404);
});*/


# Create or edit a system user inside OpenLDAP
# Should be used only in legacy mode.
#
# JSON body:
#
# {"username" : "myuser", "fullname" : "my full name"}


$app->post('/users', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $params['username'];
    $fullname = $params['fullname'];
    if ( ! $username || ! $fullname ) {
        return $response->withJson(['result' => 'User name or full name invalid'], 422);
    }
    $username = strtolower($username);
    if ( userExists($username) ) {
        exec("/usr/bin/sudo /sbin/e-smith/signal-event user-modify '$username' '$fullname' '/bin/false'", $out, $ret);
    } else {
        exec("/usr/bin/sudo /sbin/e-smith/signal-event user-create '$username' '$fullname' '/bin/false'", $out, $ret);
    }
    if ( $ret === 0 ) {
        return $response->withStatus(201);
    } else {
        return $response->withStatus(422);
    }
});


# Set the password of a given user
# Should be used only in legacy mode.
#
# JSON body:
#
# {"password" : "mypassword"}

$app->post('/users/{username}/password', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $request->getAttribute('username');
    $password = $params['password'];

    if ($username === 'admin') { # change freepbx admin password
        $dbh = FreePBX::Database();
        $dbh->sql('UPDATE ampusers SET password_sha1 = sha1(\''. $password. '\')'.
            ' WHERE username = \'admin\'');
    } else {
        if ( ! userExists($username) ) {
            return $response->withJson(['result' => "$username user doesn't exist"], 422);
        } else {
            $tmp = tempnam("/tmp","ASTPWD");
            file_put_contents($tmp, $password);

            exec("/usr/bin/sudo /sbin/e-smith/signal-event password-modify '".getUser($username)."' $tmp", $out, $ret);
            if ($ret === 0) {
                setPassword($username, $password);
                return $response->withStatus(201);
            }
        }

        return $response->withStatus(422);
    }

    return $response->withStatus(201);
});

# Return the password givent user in clear text
# Should be used only in legacy mode.

$app->get('/users/{username}/password', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $request->getAttribute('username');
    $password = getPassword($username);
    if ($password) {
        return $response->withJson(['result' => $password]);
    } else {
        return $response->withStatus(404);
    }
});


#
# Sync users from user provider to FreePBX db.
#

$app->post('/users/sync', function (Request $request, Response $response, $args) {
    sync();
    return $response->withStatus(200);
});

