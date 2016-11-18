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
$app->get('/login', function (Request $request, Response $response) {
    return $response->withJson(['success' => true]);
});

