<?php

#
# Copyright (C) 2017 Nethesis S.r.l.
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

function extensionExists($e, $extensions)
{
    foreach ($extensions as $extension) {
        if ($extension['extension'] == $e) {
            return true;
        }
    }
    return false;
}

function createExtension($mainextensionnumber,$delete=false){
    try {
        global $astman;
        $fpbx = FreePBX::create();
        $dbh = FreePBX::Database();

        if ($delete) {
            $stmt = $dbh->prepare('SELECT `extension` FROM `rest_devices_phones` WHERE `extension` LIKE ? AND `type` = "temporaryphysical"');
            $stmt->execute(array('%'.$mainextensionnumber));
            $res = $stmt->fetchAll();
            if (count($res) >0 ) {
                foreach ($res as $extension) {
                    // delete temporary extension
                    deletePhysicalExtension($extension[0]);
                    deleteExtension($extension[0]);
                }
            }
        }

        $stmt = $dbh->prepare("SELECT * FROM `rest_devices_phones` WHERE `extension` = ?");
        $stmt->execute(array($mainextensionnumber));
        $res = $stmt->fetchAll();
        if (count($res)===0) {
           //Main extension isn't already used use mainextension as extension
           $extension = $mainextensionnumber;
        } else {
            //create new extension
            $mainextensions = $fpbx->Core->getAllUsers();
            foreach ($mainextensions as $ve) {
                if ($ve['extension'] == $mainextensionnumber) {
                    $mainextension = $ve;
                    break;
                }
            }
            //get first free physical extension number for this main extension
            $extensions = $fpbx->Core->getAllUsersByDeviceType();
            for ($i=91; $i<=98; $i++) {
                if (!extensionExists($i.$mainextensionnumber, $extensions)) {
                    $extension = $i.$mainextensionnumber;
                    break;
                }
            }
            //error if there aren't available extension numbers
            if (!isset($extension)) {
                throw new Exception("There aren't available extension numbers");
            }

            //delete extension
            deletePhysicalExtension($extension);
            deleteExtension($extension);

            //create physical extension
            $data['name'] = $mainextension['name'];
            $mainextdata = $fpbx->Core->getUser($mainextension['extension']);
            $data['outboundcid'] = $mainextdata['outboundcid'];
            $res = $fpbx->Core->processQuickCreate('pjsip', $extension, $data);
            if (!$res['status']) {
                throw new Exception("Error creating extension");
            }
            //Set cid_masquerade (CID Num Alias)
            $astman->database_put("AMPUSER",$extension."/cidnum",$mainextensionnumber);

            //Add device to main extension devices
            $existingdevices = $astman->database_get("AMPUSER", $mainextensionnumber."/device");
            if (empty($existingdevices)) {
                $astman->database_put("AMPUSER", $mainextensionnumber."/device", $extension);
            } else {
                $existingdevices_array = explode('&', $existingdevices);
                if (!in_array($extension, $existingdevices_array)) {
                    $existingdevices_array[]=$extension;
                    $existingdevices = implode('&', $existingdevices_array);
                    $astman->database_put("AMPUSER", $mainextensionnumber."/device", $existingdevices);
                }
            }
        }

        //set accountcode = mainextension
        $sql = 'UPDATE IGNORE `sip` SET `data` = ? WHERE `id` = ? AND `keyword` = "accountcode"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($mainextensionnumber,$extension));

        //set callgroup and pickupgroup to 1
        $sql = 'UPDATE IGNORE `sip` SET `data` = "1" WHERE `id` = ? AND (`keyword` = "namedcallgroup" OR `keyword` = "namedpickupgroup")';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));

        // disable directmedia
        $sql = 'UPDATE IGNORE `sip` SET `data` = "no" WHERE `id` = ? AND `keyword` = "direct_media"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));

        return $extension;
    } catch (Exception $e) {
       error_log($e->getMessage());
       return false;
    }
}

function useExtensionAsWebRTC($extension) {
    try {
        $dbh = FreePBX::Database();

        //disable call waiting
        global $astman;
        $astman->database_del("CW",$extension);

        //enable default codecs and video codecs
        $sql = 'UPDATE IGNORE `sip` SET `data` = ? WHERE `id` = ? AND `keyword` = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array('ulaw,alaw,gsm,g726,h264,vp8',$extension,'allow'));

        // insert WebRTC extension in password table
        $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        $sql = 'SELECT id FROM rest_devices_phones WHERE extension = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));
        $res = $stmt->fetchAll();
        $uidquery = 'SELECT userman_users.id'.
                ' FROM userman_users'.
                ' WHERE userman_users.default_extension = ? LIMIT 1';
        if (empty($res)) {
            $sql = 'INSERT INTO `rest_devices_phones`'.
                ' SET user_id = ('. $uidquery. '), extension = ?, secret= ?, type = "webrtc", mac = NULL, line = NULL';
            $stmt = $dbh->prepare($sql);

            if ($stmt->execute(array(getMainExtension($extension),$extension,$extension_secret))) {
                return true;
            }
        } else {
            $sql = 'UPDATE `rest_devices_phones`'. 
                ' SET user_id = ('. $uidquery. '), secret= ?, type = "webrtc"' .
                ' WHERE extension = ?';
            if ($stmt->execute(array(getMainExtension($extension),$extension_secret,$extension))) {
                return true;
            }
        } 
    } catch (Exception $e) {
       error_log($e->getMessage());
       return false;
    }
}

function useExtensionAsCustomPhysical($extension, $secret = false, $type = 'physical', $web_user = null ,$web_password = null) {
    try {
        //disable call waiting
        global $astman;
        $astman->database_del("CW",$extension);
        $dbh = FreePBX::Database();

        // Overwrite sip password with the one provided
        if ($secret != false) {
            $sql = 'UPDATE `sip` SET `data` = ? WHERE `id` = ? AND `keyword` = "secret"';
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array($secret,$extension));
        } else {
            $secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        }

        // insert created physical extension in password table
        $sql = 'INSERT INTO `rest_devices_phones` SET user_id = ( '.
               'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
               '), extension = ?, secret= ?, web_user = ?, web_password = ?, type = ?';
        $stmt = $dbh->prepare($sql);
        $res = $stmt->execute(array(getMainExtension($extension),$extension,$secret,$web_user,$web_password,$type));
        if (!$res) {
            throw new Exception("Error creating custom device");
        }
        return true;
     } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function useExtensionAsPhysical($extension,$mac,$model,$line=false,$provisioning_token=null) {
    $provisioningEngine = getProvisioningEngine();
    if ($provisioningEngine == 'freepbx') {
        return legacy_useExtensionAsPhysical($extension,$mac,$model,false);
    } elseif ($provisioningEngine == 'tancredi') {
        return tancredi_useExtensionAsPhysical($extension,$mac,$model,false,$provisioning_token);
    } else {
        throw new Exception('Unknow provisioning '.implode("\n",$out));
    }
}

function legacy_useExtensionAsPhysical($extension,$mac,$model,$line=false) {
    try {
        require_once(__DIR__. '/../lib/modelRetrieve.php');
        //disable call waiting
        global $astman;
        $astman->database_del("CW",$extension);

        // insert created physical extension in password table
        $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        $dbh = FreePBX::Database();
        $vendors = json_decode(file_get_contents(__DIR__. '/../lib/macAddressMap.json'), true);
        $vendor = $vendors[substr($mac,0,8)];
        $stmt = $dbh->prepare('SELECT COUNT(*) AS num FROM `rest_devices_phones` WHERE mac = ?');
        $stmt->execute(array($mac));
        $res = $stmt->fetchAll()[0]['num'];
        if ($res == 0) {
            addPhone($mac, $vendor, $model);
        }
        if ( isset($line) && $line ) {
            $sql = 'UPDATE `rest_devices_phones` SET user_id = ( '.
                   'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
                   '), extension = ?, secret= ?, type = "physical" WHERE mac = ? AND line = ?';
            $stmt = $dbh->prepare($sql);
            $res = $stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$mac,$line));
        } else {
            $sql = 'UPDATE `rest_devices_phones` SET user_id = ( '.
                   'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
                   '), extension = ?, secret= ?, type = "physical" WHERE mac = ?';
            $stmt = $dbh->prepare($sql);
            $res = $stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$mac));
        }

        if ($res) {
            // Add extension to endpointman
            $endpoint = FreePBX::endpointmanager();
            // Get model id by mac
            $brand = $endpoint->get_brand_from_mac($mac);
            $models = $endpoint->models_available(null, $brand['id']);
            $model_id = null;
            foreach ($models as $m) {
                if ($m['text'] === $model) {
                    $model_id = $m['value'];
                    break;
                }
            }
            if (!$model_id) {
                throw new Exception('model not found');
            } else {
                $mac_id = $dbh->sql('SELECT id FROM endpointman_mac_list WHERE mac = "'.preg_replace('/:/', '', $mac).'"', "getOne");
                if ($mac_id) {
                     // add line if device already exist
                    $endpoint->add_line($mac_id, $line, $extension, $mainextension['name']);
                } else {
                    // add device to endpointman module
                    $mac_id = $endpoint->add_device($mac, $model_id, $extension, null, $line, $mainextension['name']);
                }
            }
        } else {
            throw new Exception("Error adding device");
        }
        return true;
     } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function tancredi_useExtensionAsPhysical($extension,$mac,$model,$line=false) {
    //disable call waiting
    global $astman;
    $astman->database_del("CW",$extension);

    // insert created physical extension in password table
    $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
    $dbh = FreePBX::Database();
    $vendors = json_decode(file_get_contents(__DIR__. '/../lib/macAddressMap.json'), true);
    $vendor = $vendors[substr($mac,0,8)];
    $stmt = $dbh->prepare('SELECT COUNT(*) AS num FROM `rest_devices_phones` WHERE mac = ?');
    $stmt->execute(array($mac));
    $res = $stmt->fetchAll()[0]['num'];
    if ($res == 0) {
        addPhone($mac, $vendor, $model);
    }
    $sql = 'UPDATE `rest_devices_phones` SET user_id = ( '.
           'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
           '), extension = ?, secret= ?, type = "physical" WHERE mac = ?';
    $stmt = $dbh->prepare($sql);
    $res = $stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$mac));
    if ($res) {
        return true;
    }
    return false;
}

function setFalconieriRPS($mac,$token) {
    $vendors = json_decode(file_get_contents(__DIR__. '/../lib/macAddressMap.json'), true);
    $vendor = $vendors[substr(str_replace('-',':',"$mac"),0,8)];
    $filename = '';
    switch ($vendor) {
    case 'Snom':
        $provider = 'snom';
        $filename = '{mac}.cfg';
        break;
    case 'Gigaset':
    case 'Fanvil':
        $provider = 'fanvil';
        $filename = '$mac.cfg';
        break;
    case 'Yealink/Dreamwave':
        $provider = 'yealink';
        break;
    default:
        return array("httpCode" => 400, "error" => "provider_not_supported");
        break;
    }

    //get LK
    exec("/usr/bin/sudo /sbin/e-smith/config getprop subscription SystemId", $tmp);
    $lk = $tmp[0];
    unset($tmp);
    //get secret
    exec("/usr/bin/sudo /sbin/e-smith/config getprop subscription Secret", $tmp);
    $secret = $tmp[0];
    unset($tmp);

    $queryUrl = 'https://rps.nethesis.it';
    $queryUrl .= '/providers';
    $queryUrl .= '/'.$provider;
    $queryUrl .= '/'.str_replace(':','-',"$mac");

    if (!isCloud()) {
        // Get local green address
        $dbh = FreePBX::Database();
        $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "ip"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];
        $provisioningUrl = 'http://';
        $provisioningUrl .= $res['value'];
    } else {
        $provisioningUrl = 'https://';
        $provisioningUrl .= getHostname();
    }

    $provisioningUrl .= '/provisioning';
    $provisioningUrl .= '/' . $token .'/' .$filename;

    $data = array("url" => $provisioningUrl);
    if ($provider === 'Gigaset') {
        $data['crc'] = crc32(str_replace(':','-',"$mac"));
    }
    $data = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $queryUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERPWD, $lk . ':' . $secret);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json;charset=utf-8",
        "Accept: application/json;charset=utf-8",
        "Content-length: ".strlen($data),
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result = array();
    $result['httpCode'] = $httpCode;
    if ($result['httpCode'] != 200) {
        error_log("got HTTP ".$result['httpCode']. " response from Falconieri RPS gateway. Try to execute\n    $ curl -kv '$queryUrl' --basic --user $lk:$secret -H 'Content-Type: application/json' -H 'Content-length: ".strlen($data)."' -X PUT --data '$data'");
    }
    return array_merge($result,(array) json_decode($response));
}

function isCloud() {
    // Get extension sip data
    $dbh = FreePBX::Database();
    $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "cloud"';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array());
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];
    if ($res['value'] === '1' || $res['value'] === 'true') return true;
    return false;
}

function setCloud($enabled = TRUE) {
    $dbh = FreePBX::Database();
    $dbh->sql('DELETE IGNORE FROM `admin` WHERE `variable` = "cloud"');
    $sql = 'INSERT INTO `admin` (`variable`,`value`) VALUES ("cloud",?)';
    $stmt = $dbh->prepare($sql);
    if ($enabled) {
        $value = 'true';
    } else {
        $value = 'false';
    }
    $stmt->execute(array($value));
}


function useExtensionAsApp($extension,$mac,$model) {
    try {
        //disable call waiting
        global $astman;
        $astman->database_del("CW",$extension);

        // insert created physical extension in password table
        $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        $dbh = FreePBX::Database();
        $sql = 'UPDATE `rest_devices_phones` SET user_id = ( '.
               'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
               '), extension = ?, secret= ?, type = "physical" WHERE mac = ?';
        $stmt = $dbh->prepare($sql);
        $res = $stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$mac));

        if ($res) {
            // Add extension to endpointman
            $endpoint = \FreePBX::Endpointmanager();
            // brand id hardcoded to "App"
            $brand = array('id' => 23, 'name' => 'App');
            $models = $endpoint->models_available(null, $brand['id']);
            $model_id = null;
            foreach ($models as $m) {
                if ($m['text'] === $model) {
                    $model_id = $m['value'];
                    break;
                }
            }
            if (!$model_id) {
                throw new Exception('model not found');
            } else {
                // add device to endpointman module
                $mac_id = $endpoint->add_device($mac, $model_id, $extension, null, $line, $mainextension['name']);
            }
        } else {
            throw new Exception("Error adding device");
        }

        //Allows unencrypted SRTP
        $sql = 'UPDATE IGNORE `sip` SET `data` = ? WHERE `id` = ? AND `keyword` = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array('yes',$extension,'media_encryption_optimistic'));

        return true;
     } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function isMainExtension($extension) {
    try {
        if ($extension == "") {
            throw new Exception("Error: empty extension");
        }
        $dbh = FreePBX::Database();
        $sql = 'SELECT `username` FROM `userman_users` WHERE `default_extension` = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (isset($res) && !empty($res)) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return -1;
    }
}

function getMainExtension($extension) {
    try {
        if (isMainExtension($extension)) {
            return $extension;
        } else {
            return substr($extension, 2);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return -1;
    }
}


function deleteExtension($extension,$wipemain=false) {
    try {
        global $astman;
        $dbh = FreePBX::Database();
        if (isMainExtension($extension) === false || $wipemain) {
            $mainextension = substr($extension, 2);
            // clean extension
            $fpbx = FreePBX::create();
            $fpbx->Core->delUser($extension);
            $fpbx->Core->delDevice($extension);

            //Remove device from main extension
            $existingdevices = $astman->database_get("AMPUSER", $mainextension."/device");
            if (!empty($existingdevices)) {
                $existingdevices_array = explode('&', $existingdevices);
                $arraykey = array_search($extension,$existingdevices_array);
                if ($arraykey !== FALSE ) {
                    unset($existingdevices_array[$arraykey]);
                    $existingdevices = implode('&', $existingdevices_array);
                    $astman->database_put("AMPUSER", $mainextension."/device", $existingdevices);
                }
            }
        }

        $sql = 'UPDATE rest_devices_phones SET user_id = NULL, extension = NULL, secret = NULL WHERE extension = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));
        $sql = 'DELETE FROM `rest_devices_phones` WHERE user_id IS NULL AND mac IS NULL';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $sql = 'DELETE FROM `pin` WHERE extension = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function deletePhysicalExtension($extension) {
    try {
        if (getProvisioningEngine() == 'freepbx') {
            $dbh = FreePBX::Database();
            $mac = $dbh->sql('SELECT `mac` FROM `rest_devices_phones` WHERE `extension` = "'.$extension.'"', "getOne");
            // Remove endpoint from endpointman
            $endpoint = FreePBX::endpointmanager();
            $mac_id = $dbh->sql('SELECT id FROM endpointman_mac_list WHERE mac = "'.preg_replace('/:/', '', $mac).'"', "getOne");
            if (!empty($mac_id)) {
                $luid = $dbh->sql('SELECT luid FROM endpointman_line_list WHERE mac_id = "'.$mac_id.'" AND ext = "'.$extension.'"', "getOne");
                $endpoint->delete_line($luid, true);
            }
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function getWebRTCExtension($mainextension) {
    $dbh = FreePBX::Database();
    $uidquery = 'SELECT userman_users.id'.
       ' FROM userman_users'.
       ' WHERE userman_users.default_extension = ?';
    $sql = 'SELECT extension FROM `rest_devices_phones` WHERE user_id = ('. $uidquery. ') AND type = "webrtc" AND `extension`';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($mainextension));
    return $stmt->fetchAll()[0][0];
}

function getWebRTCMobileExtension($mainextension) {
    $dbh = FreePBX::Database();
    $uidquery = 'SELECT userman_users.id'.
       ' FROM userman_users'.
       ' WHERE userman_users.default_extension = ?';
    $sql = 'SELECT extension FROM `rest_devices_phones` WHERE user_id = ('. $uidquery. ') AND type = "webrtc_mobile" AND `extension`';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($mainextension));
    return $stmt->fetchAll()[0][0];
}

function createMainExtensionForUser($username,$mainextension,$outboundcid='') {
    $fpbx = FreePBX::create();
    $dbh = FreePBX::Database();

    //Update user to add this extension as default extension
    //get uid
    if (checkUsermanIsUnlocked()) {
        $user = $fpbx->Userman->getUserByUsername($username);
        $uid = $user['id'];
    }
    if (!isset($uid)) {
        return [array('message'=>'User not found' ), 404];
    }

    //Delete user old extension and all his extensions
    $sql = 'SELECT `default_extension` FROM `userman_users` WHERE `username` = ?';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($username));
    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (isset($res)){
        $oldmain = $res['default_extension'];
        $ext_to_del = array();
        $ext_to_del[] = $oldmain;
        //Get all associated extensions
        $all_extensions = $fpbx->Core->getAllUsers();
        foreach ($fpbx->Core->getAllUsers() as $ext) {
            if (substr($ext['extension'], 2) === $oldmain) {
                $ext_to_del[] = $ext['extension'];
            }
        }
        // clean extension and associated extensions
        foreach ($ext_to_del as $extension) {
            deletePhysicalExtension($extension);
            deleteExtension($extension,true);
        }
        // set values to NULL for physical devices
        $sql = 'UPDATE rest_devices_phones'.
          ' SET user_id = NULL'.
          ', extension = NULL'.
          ', secret = NULL'.
          ' WHERE user_id = ? AND mac IS NOT NULL';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($uid));

        // remove user's webrtc phone and custom devices
        $sql = 'DELETE FROM rest_devices_phones'.
          ' WHERE user_id = ? AND mac IS NULL';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($uid));

        // remove user from CTI groups
        $sql = 'DELETE FROM rest_cti_users_groups'.
          ' WHERE user_id = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($uid));

        if (checkUsermanIsUnlocked()) {
            $fpbx->Userman->updateUser($uid, $username, $username);
        }
    }

    //exit if extension is empty
    error_log(print_r($mainextension,true));
    if (!isset($mainextension) || empty($mainextension) || $mainextension=='none') {
        return [array('message'=>'No extension specified' ), 200];
    }

    //Make sure extension is not in use
    $free = checkFreeExtension($mainextension);
    if ($free !== true ) {
        return [array('message'=>$free ), 422];
    }

    if (isset($user['displayname']) && $user['displayname'] != '') {
        $data['name'] = $user['displayname'];
    } else {
        $data['name'] = $user['username'];
    }

    $data['outboundcid']=$outboundcid;
    //create main extension
    $res = $fpbx->Core->processQuickCreate('pjsip', $mainextension, $data);
    if (!$res['status']) {
        return [array('message'=>$res['message']), 500];
    }

    //disable call waiting
    global $astman;
    $astman->database_del("CW",$mainextension);

    //update user with $extension as default extension
    $res['status'] = false;
    if (checkUsermanIsUnlocked()) {
        $res = $fpbx->Userman->updateUser($uid, $username, $username, $mainextension);
    }
    if (!$res['status']) {
        //Can't assign extension to user, delete extension
        deleteExtension($mainextension);
        $fpbx = FreePBX::create();
        $fpbx->Core->delUser($extension);
        $fpbx->Core->delDevice($extension);

        return [array('message'=>$res['message']), 500];
    }
    return true;
}

function checkUsermanIsUnlocked(){
    // Check if user directory is locked, wait if it is and exit fail
    $locked=1;
    $dbh = FreePBX::Database();
    for ($i=0; $i<30; $i++) {
        $sql = 'SELECT `locked` FROM userman_directories WHERE `name` LIKE "NethServer %"';
        $sth = $dbh->prepare($sql);
        $sth->execute(array());
        $locked = $sth->fetchAll()[0][0];
        if ($locked == 0) {
            return true;
        }
        sleep(1+0.2*$i);
    }
    if ($locked == 1) {
        return false;
    }
}

function checkTableExists($table) {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SHOW TABLES LIKE ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($table));
        if($sth->fetch(\PDO::FETCH_ASSOC)) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function checkFreeExtension($extension){
    try {
        $dbh = FreePBX::Database();
        $extensions = array();
        $extensions[] = $extension;
        for ($i=90; $i<=99; $i++) {
            $extensions[]=$i.$extension;
        }
        foreach ($extensions as $extension) {
            //Check extensions
            $sql = 'SELECT * FROM `sip` WHERE `id`= ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use");
            }

            //Check ringgroups
            $sql = 'SELECT * FROM `ringgroups` WHERE `grpnum`= ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
               throw new Exception("Extension $extension already in use in groups");
            }

            //check custom featurecodes
            $sql = 'SELECT * FROM `featurecodes` WHERE `customcode` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as custom code");
            }

            //check defaul feturecodes
            if (checkTableExists("featurecodes")){
                $sql = 'SELECT * FROM `featurecodes` WHERE `defaultcode` = ? AND `customcode` IS NULL';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($extension));
                if($sth->fetch(\PDO::FETCH_ASSOC)) {
                    throw new Exception("Extension $extension already in use as default code");
                }
            }

            //check queues
            $sql = 'SELECT * FROM `queues_details` WHERE `id` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as queue");
            }

            //check trunks
            $sql = 'SELECT * FROM `trunks` WHERE `channelid` = ?';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($extension));
            if($sth->fetch(\PDO::FETCH_ASSOC)) {
                throw new Exception("Extension $extension already in use as trunk");
            }

            //check parkings
            if (checkTableExists("parkplus")){
                $sql = 'SELECT * FROM `parkplus` WHERE `parkext` = ?';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($extension));
                if($sth->fetch(\PDO::FETCH_ASSOC)) {
                    throw new Exception("Extension $extension already in use as parking");
                }
            }
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $e->getMessage();
    }
}

function setSipData($extension,$keyword,$data) {
    $dbh = \FreePBX::Database();
    $sql = 'REPLACE INTO `sip` SET `id` = ?, `keyword` = ?, data = ?';
    $stmt = $dbh->prepare($sql);
    $res = $stmt->execute(array($extension,$keyword,$data));
    return $res;
}

function getSipData() {
    $dbh = \FreePBX::Database();
    $sql = 'SELECT `id`,`keyword`,`data` FROM `sip`';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array());
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $result = array();
    foreach ($res as $row) {
        if (!array_key_exists($row['id'],$result)) $result[$row['id']] = array();
        $result[$row['id']][$row['keyword']] = $row['data'];
    }

    return $result;
}

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

function getProvisioningEngine() {
    exec("/usr/bin/sudo /sbin/e-smith/config getprop nethvoice ProvisioningEngine", $out);
    return $out[0];
}
