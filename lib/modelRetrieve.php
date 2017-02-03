<?php

function addPhone($mac, $vendor, $model)
{
    $dbh = FreePBX::Database();
    $multiline_phones = array(
        array('Snom'=>'M300'),
        array('Snom'=>'M700')
    );
    $lines = 0;
    if (in_array(array($vendor => $model), $multiline_phones)){
        $sql = 'SELECT max_lines from `endpointman_model_list` WHERE `model`="'.$model.'" AND `brand` = (SELECT `id` FROM `endpointman_brand_list` WHERE `name` = "'.$vendor.'")';
        $lines = $dbh->sql($sql, 'getOne');
    }
    $dbh->query('DELETE IGNORE FROM `rest_devices_phones` WHERE `mac` = "'.$mac.'"');
    if ($lines === 0) {
        $sql = 'INSERT INTO `rest_devices_phones` (`mac`,`vendor`, `model`) VALUES (?,?,?)';
        $stmt = $dbh->prepare($sql);
        return $stmt->execute(array($mac,$vendor,$model));
    } else {
        $ret = true;
        for ($i=1; $i<=$lines; $i++) {
            $sql = 'INSERT INTO `rest_devices_phones` (`mac`,`vendor`, `model`,`line`) VALUES (?,?,?,?)';
            $stmt = $dbh->prepare($sql);
            if (!$stmt->execute(array($mac,$vendor,$model,$i))) {
                $ret = false ;
            }
        }
        return $ret;
    }
}

function retrieveModel($manufacturer, $name, $ip)
{
    switch ($manufacturer) {
        case 'Cisco/Linksys':
            // use curl
            return doNastyCurl($ip, $manufacturer);
        break;

        case 'Grandstream':
            // use curl
            return doNastyCurl($ip, $manufacturer);
        break;

        case 'Sangoma':
            $model = explode("-", $name)[0];
            if ($model) {
                return $model;
            } else {
                return doNastyCurl($ip, $manufacturer);
            }
        break;

        case 'Snom':
            if (strpos($name, 'IPDECT')!==false) {
                /*MXXX*/
                return doNastyCurl($ip, 'SnomMXXX');
            }
            $model = substr(explode("-", $name)[0], 4);
            if ($model) {
                return $model;
            } else {
                return doNastyCurl($ip, $manufacturer);
            }
        break;

        case 'Yealink/Dreamwave':
            $model = explode("-", $name)[1];
            if ($model) {
                return $model;
            } else {
                return doNastyCurl($ip, $manufacturer);
            }
        break;
    }
}

function doNastyCurl($ip_address, $manufacturer)
{
    switch ($manufacturer) {
        case "SnomMXXX":
            $url='http://'.$ip_address.'/main.html';
            $username="admin";
            $password="admin";
            $cmd = 'curl -s -u "'.$username.'":"'.$password.'" '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $regexp='/.*<title>(M[3,7]00)<\/title>.*/';
            preg_match($regexp, $output, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        break;
        case "Snom":
            $url='http://'.$ip_address.'/info.htm';
            $username="admin";
            $password="admin";
            $cmd = 'curl -s -u "'.$username.'":"'.$password.'" '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $regexp='/.*<TR><TD><\/TD><TD class="normalText">Firmware-Version:<\/TD><td class="normalText">snom([0-9]*)-SIP ([^ ]*)<\/td><\/TR>.*/';
            preg_match($regexp, $output, $matches);
            $model = "";
            $firmware = "";
            if (isset($matches[1])) {
                $model = $matches[1];
            }
            if (isset($matches[2])) {
                $firmware = $matches[2];
            }
            return $model;
        break;
        case "Yealink/Dreamwave":
            $url='http://'.$ip_address.'/cgi-bin/ConfigManApp.com?Id=1';
            $username="admin";
            $password="admin";
            $cmd = 'curl -s -u "'.$username.'":"'.$password.'" '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $regexp='/.*var devtype="([^\"]*)".*/';
            preg_match($regexp, $output, $matches);
            $model = "";
            if (isset($matches[1])) {
                $model = strtoupper($matches[1]);
                $matches=array();
                $regexp = '/.*var Cfgdata="(^\")*".*/';
                preg_match($regexp, $output, $matches);
                if (isset($matches[1])) {
                    $firmware = $matches[1];
                }
                if (isset($matches[2])) {
                    $hw_version = $matches[2];
                }
            } else {
                $url='http://'.$ip_address.'/servlet?p=login&q=loginForm&jumpto=status';
                $cmd = 'curl -s "'.$url.'"';
                unset($output);
                exec($cmd, $output);
                $output= implode($output);
                $regexp='/>[^<>]*((Enterprise|Gigabit)[^<>"]*)/';
                preg_match($regexp, $output, $matches);
                if (isset($matches[1])) {
                    $model = preg_replace('/.*(SIP-| )([TW][0-9]*)[PWG]/', '${2}', $matches[1]);
                }
            }
            if (!isset($matches[1])) {
                $url='http://'.$ip_address.'/cgi-bin/cgiServer.exx?page=Status.htm';
                $cmd = 'curl -s -u "'.$username.'":"'.$password.'" \''.$url.'\' | grep \'tdFirmware.*\"38.70.0.125\"\'';
                unset($output);
                exec($cmd, $output);
                $output= implode($output);
                $regexp='/tdFirmware.*\"([0-9]{2})\.[0-9]{2}\.[0-9]+\.[0-9]+\"/';
                if (preg_match($regexp, $output, $matches)) {
                    $model = "T".$matches[1];
                } else {
                    $model = "";
                }
            }
            if (substr($model, 0, 1) == "T") {
                return substr($model, 0, 3);
            } else {
                return $model;
            }
        break;
        case "Cisco/Linksys":
            $url='http://'.$ip_address.'/';
            $cmd = 'curl -s '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $regexp='/.*<head><title>(SPA[0-9]*) Configuration Utility<\/title><\/head>.*/';
            preg_match($regexp, $output, $matches);
            $model = "";
            $firmware = "";
            if (isset($matches[1])) {
                $model = $matches[1];
            }
            return $model;
        break;
        case "Sangoma":
            $url='http://'.$ip_address.'/index.htm';
            $cmd = 'curl -s '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $model = "";
            $cmd = 'curl -s --user admin:admin '.$url;
            exec($cmd, $output);
            $output= implode($output);
            $matches=array();
            $regexp='/.*>(S[3,5,7]00)<.*/';
            preg_match($regexp, $output, $matches);
            if (isset($matches[1])) {
                $model = $matches[1];
            }
            return $model;
        break;
    }
    return "";
}
