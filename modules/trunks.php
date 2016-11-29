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
