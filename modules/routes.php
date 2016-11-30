<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

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