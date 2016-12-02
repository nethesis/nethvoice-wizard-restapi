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
 * @api {post} /inboundroutes  Create an inbound routes (incoming)
 */
$app->post('/inboundroutes', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();

    try {
      $res = FreePBX::Core()->addDID($params);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withStatus(200);
});


/**
 * @api {delete} /inboundroutes/:id Delete an inbound route
 */
$app->delete('/inboundroutes/{id}', function (Request $request, Response $response, $args) {
  $route = $request->getAttribute('route');
  $cidex = explode('-', $route->getArgument('id'));
  $extension = $cidex[0];
  $cidnum = $cidex[1];

  try {
    $res = FreePBX::Core()->getDID($extension, $cidnum ? $cidnum : '');

    if ($res === false)
      return $response->withStatus(404);

    FreePBX::Core()->delDID($extension, $cidnum ? $cidnum : '');
  } catch (Exception $e) {
    error_log($e->getMessage());
    return $response->withJson('An error occurred', 500);
  }

  return $response;
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