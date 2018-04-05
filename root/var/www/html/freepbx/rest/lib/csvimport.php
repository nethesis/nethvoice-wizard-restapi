<?php

#
# Copyright (C) 2018 Nethesis S.r.l.
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

include_once('/var/www/html/freepbx/rest/lib/libUsers.php');
include_once('/var/www/html/freepbx/rest/lib/libExtensions.php');

try {
    // prepare a file for saving results
    $statusfile = '/var/run/nethvoice/csvimport.code';
    $base64csv = $argv[1];
    $str = base64_decode($base64csv);
    $rowarr = explode(PHP_EOL, trim($str));
    $csv = array();

    foreach ($rowarr as $r) {
         $csv[] = str_getcsv($r);
    }

    # create users
    $result = 0;
    $err = '';
    // calculate step/progress/total
    $numusers = count($csv);
    $step = 100/$numusers/2; //use /2 because we use 2 for cicle
    $progress = -$step; //start with negative progress because 

    foreach ($csv as $k => $row) {
        $progress += $step;
        file_put_contents($statusfile,json_encode(array('progress'=>round($progress))));
        # trim fields
        foreach ($row as $index => $field) {
            $row[$index] = trim($field);
        }

        # check that row has username and fullname field
        if (! $row['0'] || ! $row['1']) {
            $result += 1;
            $err .= "Error creating user: username and fullname can't be empty: ".implode(",",$row) ."\n";
            unset($csv[$k]);
            continue;
        }

        #lowercase username
        $row[0] = strtolower($row[0]);

        # create user 
        if (!userExists($row[0])) {
            exec("/usr/bin/sudo /sbin/e-smith/signal-event user-create ".escapeshellcmd($row[0])." '".escapeshellcmd($row[1])."' '/bin/false'", $out, $ret);
            $result += $ret;

            if ($ret > 0 ) {
                $err .= "Error creating user ".$row[0].": ".$out."\n";
                unset($csv[$k]);
                continue;
            }

            # Set password
            $tmp = tempnam("/tmp","ASTPWD");
            if (isset($row[3]) && ! empty($row[3]) ){
                $password = $row[3];
            } else {
                $password = generateRandomPassword();
            }
            file_put_contents($tmp, $password);
            exec("/usr/bin/sudo /sbin/e-smith/signal-event password-modify '".getUser($row[0])."' $tmp", $out, $ret);
            $result += $ret;
            if ($ret > 0 ) {
                $err .= "Error setting password for user ".$row[0].": ".$out['message']."\n";
                continue;
            } else {
                setPassword($row[0], $password);
            }
        }
        $csv[$k] = $row;
    }

    # sync users
    system("/usr/bin/scl enable rh-php56 '/usr/sbin/fwconsole userman --syncall --force' &> /dev/null");

     # create extensions
     foreach ($csv as $k => $row) {
        $progress += $step;
        if (round($progress)>99) $progress = 99;
        file_put_contents($statusfile,json_encode(array('progress'=>round($progress))));
        #create extension
        if (isset($row[2]) && preg_match('/^[0-9]*$/',$row[2])) {
            if (checkUsermanIsUnlocked()) {
                $create = createMainExtensionForUser($row[0],$row[2]);
                if ($create !== true) {
                    $result += 1;
                    $err .= "Error adding main extension ".$row[2]." to user ".$row[0].": ".$create['message']."\n";
                }
            } else {
                $err .= "Error adding main extension ".$row[2]." to user ".$row[0].": directory is locked";
                continue;
            }
        }
    }

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');

    if ($result > 0) {
        throw new Exception("Something went wrong: \n".$err);
    }
    file_put_contents($statusfile,json_encode(array('exitcode'=>0,'errors'=>$err,'progress'=>100)));
    exit(0);
} catch (Exception $e) {
    error_log($e->getMessage());
    file_put_contents($statusfile,json_encode(array('exitcode'=>1,'errors'=>$err,'progress'=>-1)));
    exit (1);
}

