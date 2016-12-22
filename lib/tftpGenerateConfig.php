<?php

include_once(__DIR__. '/gateway/functions.inc.php');
require_once('/etc/freepbx.conf');

try {
    # Initialize FreePBX environment
    $bootstrap_settings['freepbx_error_handler'] = false;
    define('FREEPBX_IS_AUTH',1);

    #create configuration files
    $name = $argv[1];
    $tftpdir = "/var/lib/tftpboot";
    $config = gateway_get_configuration($name);
    if (!isset($config['mac'])|| $config['mac']==''){
        $config['mac'] = 'AAAAAAAAAAAA';
    }
    if ($config['manufacturer'] == 'Sangoma'){
        $filename = preg_replace('/:/','',$config['mac'])."config.txt";
        $scriptname = preg_replace('/:/','',$config['mac'])."script.txt";
        copy("/var/www/html/freepbx/rest/lib/gateway/templates/Sangoma/script.txt","$tftpdir/$scriptname");
    } else {
        $filename = preg_replace('/:/','',$config['mac']).".cfg";
    }
    file_put_contents($tftpdir."/".$filename,gateway_generate_configuration_file($name), LOCK_EX);
} catch (Exception $e){
        error_log($e->getMessage());
        exit(1);
}
