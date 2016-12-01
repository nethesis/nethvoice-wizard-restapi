<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once("lib/SystemTasks.php");


/*
*  Launch a scan: POST/devices/scan
*  Parameter: { "network": "192.168.0.0/24"}
*/

$app->post('/devices/scan', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $network = $params['network'];

    //launch long running process
    $st = new SystemTasks();
    $taskId = $st->startTask("/usr/bin/sudo /var/www/html/freepbx/rest/lib/scanHelper.py ".escapeshellarg($network));
    return $response->withJson(['result' => $taskId], 200);
});

$app->get('/devices/phones/list/{id}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $id = $route->getArgument('id');
    $basedir='/var/run/nethvoice';
    $res=array();
    $filename = "$basedir/$id.phones.scan";
    if (!file_exists($filename)){
       return $response->withJson(array("status"=>"Scan for network $id doesn't exist!"),400);
    }
    return $response->write(file_get_contents($filename),200);
});

$app->get('/devices/gateways/list/{id}', function (Request $request, Response $response, $args) {
    $route = $request->getAttribute('route');
    $id = $route->getArgument('id');
    $basedir='/var/run/nethvoice';
    $res=array();
    $filename = "$basedir/$id.gateways.scan";
    if (!file_exists($filename)){
       return $response->withJson(array("status"=>"Scan for network $id doesn't exist!"),400);
    }
    return $response->write(file_get_contents($filename),200);
});

$app->get('/devices/phones/list', function (Request $request, Response $response, $args) {
    $basedir='/var/run/nethvoice';
    $files = scandir($basedir);
    $res=array();
    foreach ($files as $file){
        if (preg_match('/\.phones\.scan$/', $file)) {
            foreach(json_decode(file_get_contents($basedir."/".$file)) as $element)
            {
                $res[]=$element;
            }
        }
    }
    return $response->withJson(array_unique($res,SORT_REGULAR),200);
});

$app->get('/devices/gateways/list', function (Request $request, Response $response, $args) {
    $basedir='/var/run/nethvoice';
    $files = scandir($basedir);
    $res=array();
    foreach ($files as $file){
        if (preg_match('/\.gateways\.scan$/', $file)) {
            foreach(json_decode(file_get_contents($basedir."/".$file)) as $element)
            {
                $res[]=$element;
            }
        }
    }
    return $response->withJson(array_unique($res,SORT_REGULAR),200);
});

$app->get('/devices/phones/manufacturers', function (Request $request, Response $response, $args) {
   $file='/var/www/html/freepbx/rest/lib/phone_model_map.json';
   $map=file_get_contents($file);
   return $response->write($map,200);
});

$app->get('/devices/gateways/manufacturers', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $sql = "SELECT * FROM gateway_models";
    $models = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
    $res=array();
    foreach ($models as $model){
        $res[$model['model']]=$model;
    }
    return $response->withJson($res,200);
});

