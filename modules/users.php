<?php
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
    $users = shell_exec("/usr/bin/sudo /usr/libexec/nethserver/ldap-list-users");
    foreach (json_decode($users) as $user => $props) {
        if ($user == $needle) {
            return true;
        }
    }
    return false;
}

function getPassword($username) {
    return sql('SELECT password FROM `rest_user_passwords` WHERE username = "' . getUser($username) . '"', "getOne");
}

function setPassword($username, $password) {
    global $db;
    $sql = 'REPLACE INTO `rest_user_passwords` (`username`, `password`) VALUES ("' . getUser($username) . '","' . $password . '")';
    $db->query($sql);
}

function sync() {
    $userman = FreePBX::create()->Userman;
    $auth = $userman->getAuthObject();
    $auth->sync($output);
}

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

$app->get('/users', function (Request $request, Response $response, $args) {
    $blacklist = ['admin', 'administrator', 'guest', 'krbtgt'];
    sync(); // force FreePBX user sync
    $users = FreePBX::create()->Userman->getAllUsers();
    $dbh = FreePBX::Database();
    $i = 0;
    foreach ($users as $user) {
        if (in_array(strtolower($users[$i]['username']), $blacklist)) {
            unset($users[$i]);
        } else {
            $users[$i]['password'] = getPassword(getUser($users[$i]['username']));
            $users[$i]['devices'] = $dbh->sql('SELECT * FROM `rest_devices_phones` WHERE virtualextension = "' . $users[$i]['default_extension'] . '"',"getAll",\PDO::FETCH_ASSOC);
        }
        $i++;
    }
    return $response->withJson(array_values($users),200);
});


# Return the selected user

$app->get('/users/{username}', function (Request $request, Response $response, $args) {
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
});


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

    if ( ! userExists($username) ) {
        return $response->withJson(['result' => "$username user doesn't exist"], 422);
    } else {
        $tmp = tempnam("/tmp","ASTPWD");
        file_put_contents($tmp, $password);

        exec("/usr/bin/sudo /sbin/e-smith/signal-event password-modify '".getUser($username)."' $tmp", $out, $ret);
        if ($ret === 0) {
            setPassword(getUser($username), $password);
            return $response->withStatus(201);
        }
    }
    return $response->withStatus(422);
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

