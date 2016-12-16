<?php
try{
    $name = $argv[1];
    $tftpdir = "/var/lib/tftpboot";

    $bootstrap_settings['freepbx_error_handler'] = false;
    define('FREEPBX_IS_AUTH',1);
    require_once '/etc/freepbx.conf';

    $dbh = FreePBX::Database();
    /*Check if config exists*/
    $sql = "SELECT `id`,`model_id`,`ipv4`,`ipv4_new`,`gateway`,`ipv4_green`,`netmask_green`,`mac` FROM `gateway_config` WHERE `name` = ?";
    $sth = FreePBX::Database()->prepare($sql);
    $sth->execute(array($name));
    $config = $sth->fetch(\PDO::FETCH_ASSOC);
    if ($config === false){
        /*Configuration doesn't exist*/
        error_log("Configuration not found");
        exit(1);
    }
    $sql = "SELECT `model`,`manufacturer` FROM `gateway_models` WHERE `id` = ?";
    $sth = FreePBX::Database()->prepare($sql);
    $sth->execute(array($config['model_id']));
    $res = $sth->fetch(\PDO::FETCH_ASSOC);
    $config['model'] = $res['model'];
    $config['manufacturer'] = $res['manufacturer'];

    if ($config['manufacturer'] == 'Sangoma'){
        $filename = preg_replace('/:/','',$config['mac'])."config.txt";
        $scriptname = preg_replace('/:/','',$config['mac'])."script.txt";
        $script = "sangoma-tftp";
        $deviceUsername = 'admin';
        $devicePassword = 'admin';
    } elseif ($config['manufacturer'] == 'Patton'){
        $filename = preg_replace('/:/','',$config['mac']).".cfg";
        $script = "patton-tftp";
        $deviceUsername = '.';
        $devicePassword = '';
    } elseif ($config['manufacturer'] == 'Mediatrix'){
        $filename = preg_replace('/:/','',$config['mac']).".cfg";
        $script = "mediatrix-tftp";
        $deviceUsername = 'admin';
        $devicePassword = 'administrator';
    }
    
    $cmd='/var/www/html/freepbx/rest/lib/gateway/pushtftp/'.$script.' '.escapeshellarg($config['ipv4']).' '.escapeshellarg($config['ipv4_green']).' '.escapeshellarg($filename).' '.escapeshellarg($deviceUsername).' '.escapeshellarg($devicePassword);
    exec($cmd,$return);
} catch (Exception $e){
    error_log($e->getMessage());
    exit(1);
}
