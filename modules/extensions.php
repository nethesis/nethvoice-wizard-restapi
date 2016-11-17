<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


require_once('/var/www/html/freepbx/admin/modules/core/functions.inc.php');

$app->get('/extensions/list', function ($request, $response, $args) {
    global $db;
    global $astman;
    /*get contexts array*/
    $sql = 'SELECT `id`,`keyword`,`data` FROM `sip`';
    $results = $db->getAll($sql,DB_FETCHMODE_ASSOC);
    if (DB::isError($results)){
        $error[]="Error: $sql -- ".$results->getMessage()."\n";
    }
    foreach ($results as $r)
        $sip[$r['id']][$r['keyword']]=$r['data'];

    foreach (core_users_list() as $user){
        $extension=core_users_get($user[0]);
        foreach ($sip[$extension['extension']] as $k => $v)
            if (!isset($extension[$k]))
                $extension[$k]=$v;
        $extension['context'] = $contexts[$extension['extension']];
        if ($astman->database_get("CW",$extension['extension'])=="ENABLED") $extension['callwaiting']=1;
        else $extension['callwaiting']=0;
        $results = $db->query($sql);
        if (DB::isError($results)){
            $error[]="Error: $sql -- ".$results->getMessage()."\n";
        }
        $extensions[$extension['extension']]=$extension;
    }

    $response->getBody()->write(json_encode($extensions));
});

