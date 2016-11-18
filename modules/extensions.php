<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/extensions', function ($request, $response, $args) {
    $extensions = FreePBX::create()->Core->getAllUsersByDeviceType();

    return $response->withJson($extensions);
});

$app->get('/virtualextensions', function ($request, $response, $args) {
    $extensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    return $response->withJson($extensions);
});

$app->get('/virtualextensions/{extnumber}', function ($request, $response, $args) {
    $route = $request->getAttribute('route');
    $extnumber = $route->getArgument('extnumber');
    $extensions = FreePBX::create()->Core->getAllUsersByDeviceType('virtual');
    foreach ($extensions as $e) {
        if ($e['extension'] == $extnumber){
            return $response->withJson($e);
        }
    }
    return $response->withJson(array());
});




