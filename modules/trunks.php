<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

# returns all trunks
$app->get('/trunks', function (Request $request, Response $response, $args) {
    $result = array();
    $trunks = FreePBX::Core()->listTrunks();
    foreach($trunks as $trunk) {
        array_push($result, $trunk);
    }
    return $response->withJson($result,200);
});

# returns trunks by tech
$app->get('/trunks/{tech}', function (Request $request, Response $response, $args) {
    $result = array();
    $trunks = FreePBX::Core()->listTrunks();
    $tech = $request->getAttribute('tech');
    $tech = strtolower($tech);

    foreach($trunks as $trunk) {
        if (strtolower($trunk['tech']) == $tech) {
            array_push($result, $trunk);
        }
    }
    return $response->withJson($result);
});
