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

function createExtension($mainextensionnumber){
    try {
        global $astman;
        $fpbx = FreePBX::create();
        $dbh = FreePBX::Database();
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
                throw ("There aren't available extension numbers");
            }
            //delete extension
            $fpbx->Core->delDevice($extension, true);
            $fpbx->Core->delUser($extension, true);
            //create physical extension
            $data['name'] = $mainextension['name'];
            $mainextdata = $fpbx->Core->getUser($mainextension['extension']);
            $data['outboundcid'] = $mainextdata['outboundcid'];
            $res = $fpbx->Core->processQuickCreate('pjsip', $extension, $data);
            if (!$res['status']) {
                throw ("Error creating extension");
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

        return $extension;
    } catch (Exception $e) {
       error_log($e->getMessage());
       return false;
    }
}

function useExtensionAsWebRTCMobile($extension) {
    return useExtensionAsWebRTC($extension,true);
}

function useExtensionAsWebRTC($extension,$isMobile = false) {
    try {
        if ($isMobile) {
            $type = 'webrtc_mobile';
        } else {
            $type = 'webrtc';
        }
        //disable call waiting
        global $astman;
        $dbh = FreePBX::Database();
        $astman->database_del("CW",$extension);

        //enable default codecs and vp8 video codec
        $sql = 'UPDATE IGNORE `sip` SET `data` = ? WHERE `id` = ? AND `keyword` = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array('ulaw,alaw,gsm,g726,vp8',$extension,'allow'));

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
                ' SET user_id = ('. $uidquery. '), extension = ?, secret= ?, type = ?, mac = NULL, line = NULL';
            $stmt = $dbh->prepare($sql);

            if ($stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$type))) {
                return true;
            }
        } else {
            $sql = 'UPDATE `rest_devices_phones`'. 
                ' SET user_id = ('. $uidquery. '), secret= ?, type = ?' .
                ' WHERE extension = ?';
            if ($stmt->execute(array(getMainExtension($extension),$extension_secret,$extension,$type))) {
                return true;
            }
        } 
    } catch (Exception $e) {
       error_log($e->getMessage());
       return false;
    }
}

function useExtensionAsCustomPhysical($extension,$web_user = null ,$web_password = null) {
    try {
        //enable call waiting
        global $astman;
        $astman->database_put("CW",$extension,"ENABLED");
        // insert created physical extension in password table
        $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        $dbh = FreePBX::Database();
        $sql = 'INSERT INTO `rest_devices_phones` SET user_id = ( '.
               'SELECT userman_users.id FROM userman_users WHERE userman_users.default_extension = ? '.
               '), extension = ?, secret= ?, web_user = ?, web_password = ?, type = "physical"';
        $stmt = $dbh->prepare($sql);
        $res = $stmt->execute(array(getMainExtension($extension),$extension,$extension_secret,$web_user,$web_password));
        if (!$res) {
            throw new Exception("Error creating custom device");
        }
        return true;
     } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }

}

function useExtensionAsPhysical($extension,$mac,$model,$line=false) {
    try {
        require_once(__DIR__. '/../lib/modelRetrieve.php');
        //enable call waiting
        global $astman;
        $astman->database_put("CW",$extension,"ENABLED");
        // insert created physical extension in password table
        $extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $extension . '" AND keyword="secret"', "getOne");
        $dbh = FreePBX::Database();
        $vendor = json_decode(file_get_contents(__DIR__. '/../lib/macAddressMap.json'), true);
        $vendor = $vendor[substr($mac,0,8)];
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
            $endpoint = new endpointmanager();
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


function deleteExtension($extension) {
    try {
        global $astman;
        $dbh = FreePBX::Database();
        if (isMainExtension($extension) === false) {
            $mainextensions = substr($extension, 2);
            // clean extension
            $fpbx = FreePBX::create();
            $fpbx->Core->delUser($extension);
            $fpbx->Core->delDevice($extension);

            //Remove device from main extension
            $existingdevices = $astman->database_get("AMPUSER", $mainextension."/device");
            if (!empty($existingdevices)) {
                $existingdevices_array = explode('&', $existingdevices);
                unset($existingdevices_array[$extension]);
                $existingdevices = implode('&', $existingdevices_array);
                $astman->database_put("AMPUSER", $mainextension."/device", $existingdevices);
            }
        }

        $sql = 'UPDATE rest_devices_phones SET user_id = NULL, extension = NULL, secret = NULL, type = NULL WHERE extension = ?';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array($extension));
        $sql = 'DELETE FROM `rest_devices_phones` WHERE user_id IS NULL AND mac IS NULL';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function deletePhysicalExtension($extension) {
    try {
        global $astman;
        $dbh = FreePBX::Database();
        //Get device lines
        $mac = $dbh->sql('SELECT `mac` FROM `rest_devices_phones` WHERE `extension` = "'.$extension.'"', "getOne");
        $usedlinecount = $dbh->sql('SELECT COUNT(*) FROM `rest_devices_phones` WHERE `mac` = "'.$mac.'" AND `extension` != "" AND `extension`', "getOne");

        // Remove endpoint from endpointman
        $endpoint = new endpointmanager();
        $mac_id = $dbh->sql('SELECT id FROM endpointman_mac_list WHERE mac = "'.preg_replace('/:/', '', $mac).'"', "getOne");
        if (!empty($mac_id)) {
            $luid = $dbh->sql('SELECT luid FROM endpointman_line_list WHERE mac_id = "'.$mac_id.'" AND ext = "'.$extension.'"', "getOne");
            if ($usedlinecount > 1) {
                //There are other configured lines for this device
                $endpoint->delete_line($luid, false);
            } else {
                //last line, also remove device
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

