<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/**
 * @api {get} /trunks  Retrieve all trunks
 */
$app->get('/trunks', function (Request $request, Response $response, $args) {
    try {
        $result = array();
        $trunks = FreePBX::Core()->listTrunks();
        foreach($trunks as $trunk) {
            array_push($result, $trunk);
        }
        return $response->withJson($result,200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});

/**
 * @api {get} /trunks/{tech}  Retrieve all trunks by technology
 */
$app->get('/trunks/{tech}', function (Request $request, Response $response, $args) {
    try {
        $result = array();
        $trunks = FreePBX::Core()->listTrunks();
        $tech = $request->getAttribute('tech');
        $tech = strtolower($tech);

        foreach($trunks as $trunk) {
            if (strtolower($trunk['tech']) == $tech) {
                array_push($result, $trunk);
            }
        }
        return $response->withJson($result,200);
    }
    catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
});
