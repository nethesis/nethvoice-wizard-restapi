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

define('REBOOT_HELPER_SCRIPT','/var/www/html/freepbx/rest/lib/phonesRebootHelper.php');
define("JSON_FLAGS",JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

$app->post('/phones/reboot/{mac}[/{hours}/{minutes}]', function (Request $request, Response $response, $args) {
    if (!array_key_exists('mac',$args)
        || empty($args['mac'])
        || !preg_match('/^[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}$/', $args['mac']))
    {
        //Missing or malformed mac address
        $results = array('title' => 'Missing or malformed MAC address'.$args['mac']);
        $response = $response->withJson($results,400,JSON_FLAGS);
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response;
    }
    if (!array_key_exists('hours',$args) || empty($args['hours'])) {
        // No time provided, launch reboot now
        $out = system(REBOOT_HELPER_SCRIPT.' '.$args['mac'], $return);
        if ($return != 0) {
            $results = array(
                'title' => 'Unknown error',
                'detail' => $out
            );
            $response = $response->withJson($results,500,JSON_FLAGS);
            $response = $response->withHeader('Content-Type', 'application/problem+json');
            $response = $response->withHeader('Content-Language', 'en');
            return $response;
        }
    }
    // Write command for reboot in cron
    if (!empty(get_planned_reboot_from_cron($args['mac']))) {
        delete_from_cron($args['mac']);
    }
    if (write_in_cron($args['mac'],$args['hours'],$args['minutes'])) {
        $response = $response->withStatus(201);
        return $response;
    } 
    $results = array(
        'title' => 'Unknown error',
        'detail' => 'Error writing cron'
    );
    $response = $response->withJson($results,500,JSON_FLAGS);
    $response = $response->withHeader('Content-Type', 'application/problem+json');
    $response = $response->withHeader('Content-Language', 'en');
    return $response;
});

$app->get('/phones/reboot', function (Request $request, Response $response, $args) {
    return $response->withJson(get_planned_reboot_from_cron(),200);
});

$app->delete('/phones/reboot/{mac}', function (Request $request, Response $response, $args) {
    if (!array_key_exists('mac',$args)
        || empty($args['mac'])
        || !preg_match('/^[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}$/', $args['mac']))
    {
        //Missing or malformed mac address
        $results = array('title' => 'Missing or malformed MAC address');
        $response = $response->withJson($results,400,JSON_FLAGS);
        $response = $response->withHeader('Content-Type', 'application/problem+json');
        $response = $response->withHeader('Content-Language', 'en');
        return $response;
    }
    // Delete command for reboot from cron
    if (delete_from_cron($args['mac'])) {
        $response = $response->withStatus(204);
        return $response;
    }
    $results = array(
        'title' => 'Unknown error',
        'detail' => 'Error deleting from cron'
    );
    $response = $response->withJson($results,500,JSON_FLAGS);
    $response = $response->withHeader('Content-Type', 'application/problem+json');
    $response = $response->withHeader('Content-Language', 'en');
    return $response;
});

function write_in_cron($mac, $hours, $minutes) {
     try {
        if (!preg_match('/[0-9]{1,2}/',$hours) 
            || !preg_match('/[0-9]{2}/',$minutes)
            || !preg_match('/^[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}$/',$mac)) 
        {
            throw new Exception("Wrong arguments: mac = $mac, hours = $hours, minutes = $minutes");
        }

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

        $output[] = "$minutes $hours * * * ".REBOOT_HELPER_SCRIPT." $mac";

        fwrite($pipes[0], join("\n", $output)."\n");
        fclose($pipes[0]);
        return true;
    } catch (Exception $e) {
        error_log(__FUNCTION__ . ' ' . $e->getMessage());
        return false;
    }
}

function delete_from_cron($mac) {
    try {
        if (!preg_match('/^[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}$/',$mac)) {
            throw new Exception("Wrong argument: mac = $mac");
        }
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
            if (strpos( $row , " ".REBOOT_HELPER_SCRIPT." $mac") !== FALSE) {
                continue;
            }
            fwrite($pipes[0], $row."\n");
        }
        fclose($pipes[0]);
        return true;
    } catch (Exception $e) {
        error_log(__FUNCTION__ . ' ' . $e->getMessage());
        return false;
    }
}

function get_planned_reboot_from_cron($mac = null){
    try {
        // Read crontab content
        exec('/usr/bin/crontab -l 2>/dev/null', $output, $ret);
        if ($ret != 0) {
            throw new Exception("Error reading crontab");
        }
        
        $ret = array();
        foreach ($output as $row) {
            if (preg_match('/^([0-9]+) ([0-9]+) \* \* \* '.str_replace('/','\/',REBOOT_HELPER_SCRIPT).' ([0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2}\-[0-9A-F]{2})$/',$row,$matches)) {
                if (!is_null($mac) && $mac != $matches[3]) {
                    continue;
                }
                $ret[$matches[3]] = array("hours" => $matches[2], "minutes" => $matches[1]);
            }
        }
        return $ret;
    } catch (Exception $e) {
        error_log(__FUNCTION__ . ' ' . $e->getMessage());
        return false;
    }
}
