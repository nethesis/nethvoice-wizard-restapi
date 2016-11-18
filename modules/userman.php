<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function __user($username) {
    exec('/usr/bin/hostname -d', $out, $ret);
    $domain = $out[0];
    return "$username@$domain";
}

function __exists($username) {
    exec("/usr/bin/getent passwd '".__user($username)."'", $out, $ret);
    return ($ret === 0);
}

$app->get('/users', function (Request $request, Response $response, $args) {
    $users = FreePBX::create()->Userman->getAllUsers();
    return $response->withJson($users);
});


$app->get('/users/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $id = $route->getArgument('id');
    $users = FreePBX::create()->Userman->getAllUsers();
    foreach ($users as $u) {
        if ($u['id'] == $id){
            return $response->withJson($u);
        }
    }
    return $response->withJson(array());
});


$app->get('/users/exists/{username}', function (Request $request, Response $response, $args) {
print_r($params);
    $username = $request->getAttribute('username');
    return $response->withJson(['result' => __exists($username)]);
});

$app->put('/users/create/{username}/{fullname}/{extension}', function (Request $request, Response $response, $args) {
    $username = $request->getAttribute('username');
    $fullname = $request->getAttribute('fullname');
    exec("/usr/bin/sudo /sbin/e-smith/signal-event user-create '$username' '$fullname' '/bin/false'", $out, $ret);
    if ( $ret === 0 ) {
        return $response->withJson(['result' => true]);
    }
    return $response->withJson(['result' => false]);
});

$app->post('/users/setpassword', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $username = $params['username'];
    $password = $params['password'];
    if (__exists($username)) {
        $tmp = tempnam("/tmp","ASTPWD");
        file_put_contents($tmp, $password);

        exec("/usr/bin/sudo /sbin/e-smith/signal-event password-modify '".__user($username)."' $tmp", $out, $ret);
        return $response->withJson(['result' => ($ret === 0)]);
    }
    return $response->withJson(['result' => false]);
}); 

