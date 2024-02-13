<?php

#
# Copyright (C) 2024 Nethesis S.r.l.
# SPDX-License-Identifier: GPL-3.0-or-later
#

# Change mobile app extensions from old version to new Acrobit version

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
	$sip_options=[
		'maximum_expiration' => '2678400',
		'qualifyfreq' => '0',
		'rewrite_contact' => 'no',
		'transport' => '0.0.0.0-tcp',
	];
} else {
	$sip_options=[
		'maximum_expiration' => '7200',
		'qualifyfreq' => '60',
		'rewrite_contact' => 'yes',
		'transport' => '0.0.0.0-tls',
	];
}

if (count($extensions) > 0) {
	$qm_string = str_repeat('?, ',count($extensions) - 1) . '?';
	foreach ($sip_options as $sip_option => $value)	{
		$sql = "UPDATE `asterisk`.`sip` SET `data` = ? WHERE `keyword` = ? AND `id` IN ($qm_string)";
		$stmt = $db->prepare($sql);
		$stmt->execute(array_merge([$value,$sip_option],$extensions));
	}
}
