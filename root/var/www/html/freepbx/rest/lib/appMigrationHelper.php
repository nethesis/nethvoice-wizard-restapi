<?php

#
# Copyright (C) 2023 Nethesis S.r.l.
# SPDX-License-Identifier: GPL-3.0-or-later
#

include_once '/etc/freepbx_db.conf';
$sql = "SELECT extension
        FROM `asterisk`.`rest_devices_phones`
        WHERE `type` = 'mobile' 
        AND extension IS NOT NULL";

$stmt = $db->prepare($sql);
$stmt->execute();
$res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$extensions = [];
foreach ($res as $row) {
	$extensions[] = $row['extension'];
}

if (in_array('--restore', $argv)) {
	$options=[
		'transport' => '0.0.0.0-tcp',
		'rewrite_contact' => 'no',
	];
} else {
	$options=[
		'transport' => '0.0.0.0-udp',
		'rewrite_contact' => 'yes',
	];
}


if (count($extensions) > 0) {
	$qm_string = str_repeat('?, ',count($extensions) - 1) . '?';
	foreach ($options as $option => $value)	{
		$sql = "UPDATE `asterisk`.`sip` SET `data` = ? WHERE `keyword` = ? WHERE `id` IN ($qm_string)";
		$stmt = $db->prepare($sql);
		$stmt->execute(array_merge([$value,$option],$extensions));
	}
}
