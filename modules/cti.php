<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


/* GET /cti/profiles 
Return: [{id:1, name: admin, macro_permissions [ oppanel: {value: true, permissions [ {name: "foo", description: "descrizione...", value: false},{..} ]}
*/
$app->get('/cti/profiles', function (Request $request, Response $response, $args) {
    include_once('lib/libCTI.php');
    $results = getCTIPermissionProfiles();
    if (!$results) {
        return $response->withStatus(500);
    }
    return $response->withJson($results,200);
});


/* GET /cti/profiles/{id} 
Return: {id:1, name: admin, permissions: [{name: "foo", type: customer_card, value: false},{name: "bar", type: standard, value: true}]}
*/
$app->get('/cti/profiles/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
	$dbh = FreePBX::Database();
//return $response->withJson(
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* GET /cti/permissions 
Return: [{id:1, name: NAME, type: TYPE}]
*/
$app->get('/cti/permissions', function (Request $request, Response $response, $args) {
    try {
	$dbh = FreePBX::Database();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* POST /cti/profiles/{id} {name: admin, permissions: [{name: "foo", type: customer_card, value: false}
*/
$app->post('/cti/profiles/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
	$dbh = FreePBX::Database();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});


/* POST /cti/profiles {name: admin, permissions: [{name: "foo", type: customer_card, value: false} return id
*/
$app->post('/cti/profiles', function (Request $request, Response $response, $args) {
    try {
	$dbh = FreePBX::Database();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});
