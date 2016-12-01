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
    return $response->withJson($res,200);
});

