<?php
#
# Copyright (C) 2019 Nethesis S.r.l.
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
        $data = $request->getParsedBody();
        $config_dir = '/etc/phonebook/sources.d';

        if (!isset($id) || empty($id)) {
            // Create a new id
            $i = 1;
            while (file_exists($config_dir.'/custom_'.$i.'.json')) {
                $i ++ ;
            }
            $id = 'custom_'.$i;
            $new = true;
        }
        // mandatory parameters
        foreach ( array('dbtype','host','port','user','password','dbname','query','mapping') as $var) {
            if (!isset($data[$var]) || empty($data[$var])) {
                error_log("Missing value: $var");
                return $response->withJson(array("status"=>"Missing value: $var"), 400);
            }
            $newsource[$var] = $data[$var];
        }
        // optional parameters
        $newsource['interval'] = empty($data['interval']) ? 1440 : $data['interval'];
        $newsource['type'] = empty($data['type']) ? $id : $data['type'];
        $newsource['enabled'] = empty($data['enabled']) ? false : $data['enabled'];

        $file = $config_dir.'/'.$id.'.json';
        $res = file_put_contents($file, json_encode(array($id => $newsource)));
        if ($res === false) {
           throw new Exception("Error writing $file"); 
        }

        if (!isset($data['interval']) || empty($data['interval']) || $data['interval'] < 1 || $data['interval'] >= 1440) {
            $cron_time_interval = '0 0 * * *' ;
        } elseif ($data['interval'] < 60) {
            $cron_time_interval = '*/'.$data['interval'].' * * * *';
        } elseif ($data['interval'] >= 60 || $data['interval'] < 1440 ) {
            $cron_time_interval = '0 */'.intval($data['interval']/60).' * * *';
        }

        // Delete interval in cron if it exists
        $res = delete_import_from_cron($id);
        if (!$res) {
            throw new Exception("Error deleting $file from crontab!");
        }

        // Write new configuration in cron
        $res = write_import_in_cron($cron_time_interval, $id);
        if (!$res) {
            throw new Exception("Error adding $file to crontab!");
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
        $res = delete_import_from_cron($id);
        if (!$res) {
            throw new Exception("Error deleting $file from crontab!");
        }

        // delete from phonebook
        $cmd = "/usr/share/phonebooks/phonebook-import --deleteonly ".escapeshellarg($file);
        exec($cmd,$output,$return);
        if ($return !== 0 ) {
            throw new Exception("Error deleting $id entries from phonebook");
        }

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
        // write a temporary configuration file
        $id = uniqid('phonebook_test_');
        $file = '/tmp/'.$id.'.json';
        $newsource = array();
        foreach ( array('type','dbtype','host','port','user','password','dbname','query') as $var) {
            if (!isset($data[$var]) || empty($data[$var])) {
                error_log("Missing value: $var");
                return $response->withJson(array("status"=>"Missing value: $var"), 400);
            }
            $newsource[$id][$var] = $data[$var];
        }

        $res = file_put_contents($file, json_encode($newsource));
        if ($res === false) {
           throw new Exception("Error writing $file");
        }

        $cmd = "/usr/share/phonebooks/phonebook-import --check ".escapeshellarg($file);
        exec($cmd,$output,$return);

        // remove temporary file
        unlink($file);

        if ($return!=0) {
            return $response->withJson(array("status"=>false),200);
        }
        $res = json_decode($output[0]);
        return $response->withJson(array_slice($res, 0, 3),200);
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
        $cmd = "/usr/share/phonebooks/phonebook-import ".escapeshellarg($file);
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

function delete_import_from_cron($id) {
    try {
        $file = '/etc/phonebook/sources.d/'.$id.'.json';

        // Read crontab content
        exec('/usr/bin/crontab -l 2>/dev/null', $output, $ret);
        if ($ret != 0) {
            throw new Exception("Error reading crontab");
        }

        // Open crontab in a pipe
        if(!file_exists('/var/log/pbx/www-error.log')) {
            touch('/var/log/pbx/www-error.log');
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("file", "/var/log/pbx/www-error.log", "a") // stderr
        );

        $process = proc_open('/usr/bin/crontab -', $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Error opening crontab pipe");
        }

        foreach ($output as $row) {
            if (strpos( $row , '/usr/share/phonebooks/phonebook-import ') !== FALSE && strpos( $row , $file) !== FALSE ) {
                continue;
            }
            fwrite($pipes[0], $row."\n");
        }
        fclose($pipes[0]);
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}


function write_import_in_cron($cron_time_interval, $id) {
     try {
        $file = '/etc/phonebook/sources.d/'.$id.'.json';

        // Read crontab content
        exec('/usr/bin/crontab -l 2>/dev/null', $output, $ret);
        if ($ret != 0) {
            throw new Exception("Error reading crontab");
        }

        // Open crontab in a pipe
        if(!file_exists('/var/log/pbx/www-error.log')) {
            touch('/var/log/pbx/www-error.log');
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("file", "/var/log/pbx/www-error.log", "a") // stderr
        );

        $process = proc_open('/usr/bin/crontab -', $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Error opening crontab pipe");
        }

        $output[] = $cron_time_interval.' '.'/usr/share/phonebooks/phonebook-import '.escapeshellarg($file);

        fwrite($pipes[0], join("\n", $output)."\n");
        fclose($pipes[0]);
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

