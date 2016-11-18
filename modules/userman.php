<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/users/', function ($request, $response, $args) {
    $users = FreePBX::create()->Userman->getAllUsers();
    return $response->withJson($users);
});


$app->get('/users/{id}', function ($request, $response, $args) {
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




