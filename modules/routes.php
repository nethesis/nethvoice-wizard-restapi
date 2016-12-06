<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../../admin/modules/core/functions.inc.php');

/**
 * @api {get} /inboundroutes  Retrieve inbound routes (incoming)
 */
$app->get('/inboundroutes', function (Request $request, Response $response, $args) {
    try {
      $routes = FreePBX::Core()->getAllDIDs('extension');
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withJson($routes, 200);
});

/**
 * @api {get} /outboundroutes  Retrieve outbound routes.
 */
 $app->get('/outboundroutes', function (Request $request, Response $response, $args) {
     try {
       $routes = FreePBX::Core()->getAllRoutes();
     } catch (Exception $e) {
       error_log($e->getMessage());
       return $response->withJson('An error occurred', 500);
     }

     return $response->withJson($routes, 200);
 });

 /**
 * @api {post} /outboundroutes  Create an outbound routes (incoming)
 */
$app->post('/outboundroutes', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();

    try {
      core_routing_addbyid(
        $params['name'],
        $params['outcid'],
        $params['outcid_mode'],
        $params['password'],
        $params['emergency_route'],
        $params['intracompany_route'],
        $params['mohclass'] ? $params['mohclass'] : 'default',
        $params['time_group_id'],
        $params['patterns'], // array of patterns
        $params['trunks'], // array of trunks id
        $params['seq'],
        $params['dest']);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withStatus(200);
});
