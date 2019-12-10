#!/usr/bin/env php
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

if ($argc != 2) {
    echo "usage: $argv[0] [MAC_ADDRESS]\n";
    exit(127);
}
$mac = $argv[1];

include_once('/etc/freepbx_db.conf');
include_once('/var/www/html/freepbx/rest/lib/CronHelper.php');

// Get extension and brand from mac address
$sql = 'SELECT vendor,extension FROM rest_devices_phones WHERE mac = ?';
$sth = $db->prepare($sql);
$sth->execute(array(str_replace('-',':',$mac)));
while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
    if(empty($row['extension'])) {
        $emsg = $argv[0].': '."ERROR. No extension associated with device $mac";
	error_log($emsg);
	echo($emsg."\n");
	exit(126);
    }
    switch ($row['vendor']) {
        case 'Aastra':
            $notify_string = 'aastra-check-cfg';
            break;
        case 'Alcatel':
            $notify_string = 'reboot-alcatel';
            break;
        case 'Temporis':
            $notify_string = 'reboot-alcatel';
            break;
        case 'Cisco/Linksys':
            $notify_string = 'cisco-restart';
            break;
        case 'Digium':
            $notify_string = 'polycom-reboot';
            break;
        case 'Polycom':
            $notify_string = 'polycom-check-cfg';
            break;
        case 'Snom':
            $notify_string = 'reboot-snom';
            break;
        case 'Fanvil':
        case 'Gigaset':
        case 'Panasonic':
        case 'Sangoma':
        case 'Thomson':
        case 'Xorcom':
        case 'Yealink/Dreamwave':
        case 'Yealink/Wildix':
            $notify_string = 'reboot-yealink';
            break;
        default:
            error_log($argv[0].': '."ERROR. Reboot for ".$row['vendor']." is not supported");
            exit(126);
            break;
    }

    $cmd = "/usr/sbin/asterisk -rx 'pjsip send notify $notify_string endpoint ".$row['extension']."'";
    $out = system($cmd);
    if (preg_match('/failed.$/',$out) || preg_match('/^Unable/',$out)) {
         $emsg = $argv[0].': '.'ERROR rebooting phone '.$mac.': '.$out;
         error_log($emsg);
         echo($emsg."\n");
         exit(126);
    }
    echo $argv[0].': '.$row['vendor']." phone $mac rebooted!: " . $out . "\n";
    CronHelper::delete_same_time($mac);
    exit(0);
}

$emsg = $argv[0].': '."ERROR. Can't find phone $mac in rest_devices_phone";
error_log($emsg);
echo($emsg."\n");
exit(126);

