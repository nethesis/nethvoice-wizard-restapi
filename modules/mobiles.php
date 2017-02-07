<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/mobiles', function (Request $request, Response $response, $args) {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SELECT rest_users.mobile, userman_users.username'.
          ' FROM rest_users'.
          ' JOIN userman_users ON userman_users.id = rest_users.user_id';
        $mobiles = $dbh->sql($sql, 'getAll', \PDO::FETCH_ASSOC);

        return $response->withJson($mobiles, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withStatus(500);
    }
});

$app->get('/mobiles/{username}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $username = $route->getArgument('username');
        $dbh = FreePBX::Database();
        $sql = 'SELECT rest_users.mobile'.
          ' FROM rest_users'.
          ' JOIN userman_users ON userman_users.id = rest_users.user_id'.
          ' WHERE userman_users.username = \''. $username. '\'';
        $mobile = $dbh->sql($sql, 'getOne', \PDO::FETCH_ASSOC);
        if ($mobile == false) {
            return $response->withStatus(404);
        }

        return $response->withJson($mobile, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return $response->withStatus(500);
    }
});

$app->post('/mobiles', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $dbh = FreePBX::Database();
        $sql = 'UPDATE rest_users'.
          ' JOIN userman_users ON userman_users.id = rest_users.user_id'.
          ' SET rest_users.mobile = ?'.
          ' WHERE userman_users.username = ?';
        $stmt = $dbh->prepare($sql);
        $mobile = preg_replace('/^\+/', '00', $params['mobile']);
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        $res = $stmt->execute(array($params['username'], $mobile));
        if (!res || $stmt->affected_rows < 1) {
            throw new Exception('db error');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }

    return $response->withStatus(200);
});
