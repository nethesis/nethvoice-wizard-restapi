<?php
function gateway_get_configuration($name){
    try{
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
        $sql = "SELECT `trunk`,`protocol` FROM `gateway_config_isdn` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($config['id']));
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)){
            $config['trunks_isdn'][] = $row;
        }

        $sql = "SELECT `trunk` FROM `gateway_config_pri` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($config['id']));
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)){
            $config['trunks_pri'][] = $row;
        }

        $sql = "SELECT `trunk`,`number` FROM `gateway_config_fxo` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($config['id']));
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)){
            $config['trunks_fxo'][] = $row;
        }
        $sql = "SELECT `extension`,`secret` FROM `gateway_config_fxs` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($config['id']));
        while ($config['trunks_fxs'][] = $sth->fetch(\PDO::FETCH_ASSOC)){
            $config['trunks_fxs'][] = $row;
        }

        return $config;
    } catch (Exception $e){
        error_log($e->getMessage());
        exit(1);
    }
}

function gateway_generate_configuration_file($name){
    try{
        global $amp_conf;
        $config = gateway_get_configuration($name);
        # read template
        $template = "/var/www/html/freepbx/rest/lib/gateway/templates/{$config['manufacturer']}/{$config['model']}.txt";
        $handle = fopen($template, "r");
        ob_clean();
        ob_start();
        if ($handle) {
            while (!feof($handle)) {
                $buffer = fgets($handle, 4096);
                $output .= $buffer;
            }
            fclose($handle);
        } else {
            error_log("Template $template not found");
            return false;
        }
        # replace variables in template
        $output = str_replace("ASTERISKIP",$config['ipv4_green'],$output);
        $output = str_replace("GATEWAYIP",$config['ipv4_new'],$output);
        $output = str_replace("DEFGATEWAY",$config['gateway'],$output);
        $output = str_replace("NETMASK",$config['netmask_green'],$output);
        $output = str_replace("DATE",date ('d/m/Y G:i:s'),$output);

        #Generate trunks config
        /*trunk number:
        * isdn = 1 + id
        * pri  = 2 + id
        * fxo  = 3 + id
        */
        if (!empty($config['trunks_fxo'])){
            $i = 1;
            $n_trunks = count($config['trunks_isdn']);
            if ($n_trunks>0) {
                $j = $n_trunks+1;
            } else {
                $j = 1;
            }
            foreach ($config['trunks_fxo'] as $trunk){
                $output = str_replace("LINENUMBER$i",$trunk['number'],$output);
                $output = str_replace("TRUNKNUMBER$j","3".$trunk['id'],$output);
                $i++;
                $j++;
            }
        }
        if (!empty($config['trunks_isdn'])){
            $i = 1;
            foreach ($config['trunks_isdn'] as $trunk) {
                $output = str_replace("TRUNKNUMBER$i","1".$trunk['id'],$output);
                if ($trunk['protocol']=="pp") {
                    if ($config['manufacturer'] == 'Sangoma') {
                        $output = str_replace("PROTOCOLTYPE$i","pp",$output);
                    } elseif ($config['manufacturer'] == 'Mediatrix') {
                        $output = str_replace("PROTOCOLTYPE$i","PointToPoint",$output);
                    } elseif ($config['manufacturer'] == 'Patton') {
                        $output = str_replace("PROTOCOLTYPE$i","protocol pp",$output);
                    }
                } elseif ($trunk['protocol']=="pmp") {
                    if ($config['manufacturer'] == 'Sangoma') {
                        $output = str_replace("PROTOCOLTYPE$i","pmp",$output);
                    } elseif ($config['manufacturer'] == 'Mediatrix') {
                        $output = str_replace("PROTOCOLTYPE$i","PointToMultiPoint",$output);
                    } elseif ($config['manufacturer'] == 'Patton' && preg_match ('/^TRI_/', $config['model'])) {
                        $output = str_replace("PROTOCOLTYPE$i","protocol pmp",$output);
                    } elseif ($config['manufacturer'] == 'Patton' && !preg_match ('/^TRI_/', $config['model'])){
                        $output = str_replace("PROTOCOLTYPE$i","",$output);
                    }
                }
                $i++;
            }
        }
        if (!empty($config['trunks_pri'])){
            $i = 1;
            foreach ($config['trunks_pri'] as $trunk){
                $output = str_replace("TRUNKNUMBER$i","2".$trunk['id'],$output);
                $i++;
            }
        }
        if (!empty($config['trunks_fxs'])){
            $i=0;
            foreach ($config['trunks_fxs'] as $trunk){
                $output = str_replace("FXSEXTENSION$i",$trunk['extension'],$output);
                $output = str_replace("FXSPASS$i",$trunk['secret'],$output);
                $i++;
            }
        }


    } catch (Exception $e){
        error_log($e->getMessage());
        exit(1);
    }
    return $output;
}


try {
    # Initialize FreePBX environment
    $bootstrap_settings['freepbx_error_handler'] = false;
    define('FREEPBX_IS_AUTH',1);
    require_once '/etc/freepbx.conf';

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
