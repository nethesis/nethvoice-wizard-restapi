<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*
* /login - POST
* 
* @param user
* @param password
*
* @return { "success" : true } if authentication is valid
* @return { "success" : false } otherwise
*/
$app->post('/login', function (Request $request, Response $response) {
    $params = $request->getParsedBody();
    $user = new ampuser($params['user']);
    $res = $user->checkPassword($params['password']);
    return $response->withJson(array('success' => $res));
});

