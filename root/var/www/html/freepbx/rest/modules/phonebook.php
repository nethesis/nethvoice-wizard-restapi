<?php
#
# Copyright (C) 2017 Nethesis S.r.l.
# http://www.nethesis.it - nethserver@nethesis.it
#
# This script is part of NethServer.
#
# NethServer is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License,
# or any later version.
#
# NethServer is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with NethServer.  If not, see COPYING.
#
#
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/phonebook/fields', function (Request $request, Response $response, $args) {
    $fields = array(
        'owner_id',
        'homeemail',
        'workemail',
        'homephone',
        'workphone',
        'cellphone',
        'fax',
        'title',
        'company',
        'notes',
        'name',
        'homestreet',
        'homepob',
        'homecity',
        'homeprovince',
        'homepostalcode',
        'homecountry',
        'workstreet',
        'workpob',
        'workcity',
        'workprovince',
        'workpostalcode',
        'workcountry',
        'url'
    );
    return $response->withJson($fields, 200);
});

$app->get('/phonebook/config', function (Request $request, Response $response, $args) {
    try {
        $config_dir = '/etc/phonebook/sources.d';
        $handle = opendir($config_dir);
        $config = array();
        while (false !== ($entry = readdir($handle))) {
            if (strpos($entry,'.json') !== false) {
                $c = (array) json_decode(file_get_contents($config_dir.'/'.$entry));
                foreach ($c as $sid => $conf) {
                    $config[$sid] = $conf;
                }
            }
        }
        closedir($handle);
        return $response->withJson($config, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

$app->post('/phonebook/config[/{id}]', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $newsource = $request->getParsedBody();
        $config_dir = '/etc/phonebook/sources.d';

        if (!isset($id) || empty($id)) {
            // Create a new id
            $i = 1;
            while (file_exists($config_dir.'/custom_'.$i.'.json')) {
                $i ++ ;
            }
            $id = 'custom_'.$i;
        }
        $file = $config_dir.'/'.$id.'.json';
        $res = file_put_contents($file, json_encode(array($id => $newsource)));
        if ($res === false) {
           throw new Exception("Error writing $file"); 
        }
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

$app->delete('/phonebook/config/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $file = '/etc/phonebook/sources.d/'.$id.'.json';
        $res = unlink($file);
        if (!$res) {
            throw new Exception("Error deleting $file");
        }
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

/* Test connection and query and get first 3 results*/
$app->post('/phonebook/test', function (Request $request, Response $response, $args) {
    try {
        $data = $request->getParsedBody();
        $cmd = "/usr/bin/python /usr/share/phonebooks/phonebook-import.py --check-db";
        if (isset($data['query']) && !empty($data['query'])) {
            $data['query'] = preg_replace('/;$|(LIMIT|limit) [0-9]*(;$|$)/','',$data['query']).' LIMIT 3;';
        }
        foreach ( array('dbtype','host','port','user','password','dbname','query') as $var) {
            if (!isset($data[$var]) || empty($data[$var])) {
                error_log("Missing value: $var");
                return $response->withJson(array("status"=>"Missing value: $var"), 400);
            }
            $cmd.= ' '.$var.'='.escapeshellarg($data[$var]);
        }
        error_log($cmd);
        exec($cmd,$output,$return);
        if ($return!=0) {
            return $response->withJson(array("status"=>false),200);
        }
        $res = json_decode($output[0]);
        return $response->withJson($res,200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

/* Sync now one configuration */
$app->post('/phonebook/syncnow/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $file = '/etc/phonebook/sources.d/'.$id.'.json';
        $cmd = "/usr/bin/python /usr/share/phonebooks/phonebook-import.py --source-id=".escapeshellarg($id);
        exec($cmd,$output,$return);
        if ($return!=0) {
            return $response->withJson(array("status"=>false),500);
        }
        return $response->withJson(array("status"=>true),200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

