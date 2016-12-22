<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../../admin/modules/core/functions.inc.php');

/**
 * @api {get} /providers  Retrieve all providers
 */
$app->get('/providers', function (Request $request, Response $response, $args) {
    try {
        global $db;
        $sql = 'SELECT * FROM `providers`';
        $results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
        if(DB::IsError($results)) {
            throw new Exception($results->getMessage());
        }
        return $response->withJson($results, 200);
    }
    catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson('An error occurred', 500);
    }
});
