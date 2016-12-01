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
    return $response->withJson($taskId, 200);
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
    return $response->withJson($res,200);
});
