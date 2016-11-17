<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/login/{user}/{password}', function (Request $request, Response $response) {
    $user = new ampuser($request->getAttribute('user'));
    $res = $user->checkPassword($request->getAttribute('password'));
    $response->getBody()->write(json_encode(array('result' => $res)));
    return $response;
});

