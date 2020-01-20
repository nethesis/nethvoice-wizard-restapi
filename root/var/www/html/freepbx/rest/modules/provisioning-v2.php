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

require_once '/var/www/html/freepbx/rest/vendor/autoload.php';

define('REBOOT_HELPER_SCRIPT','/var/www/html/freepbx/rest/lib/phonesRebootHelper.php');
define("JSON_FLAGS",JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once 'lib/CronHelper.php';
include_once 'lib/libExtensions.php';
include_once 'lib/libCTI.php';

//$app = new \Slim\App;
$container = $app->getContainer();
$container['cron'] = function ($container) {
    return new CronHelper();
};

$app->post('/phones/reboot', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $cron_response = $this->cron->write($body);
    return $response->withJson((object) $cron_response, 200, JSON_FLAGS);
});

$app->get('/phones/reboot[/{mac}]', function (Request $request, Response $response, $args) {
    if (array_key_exists('mac',$args)) {
        return $response->withJson((object) $this->cron->read($args['mac']), 200, JSON_FLAGS);
    } else {
        return $response->withJson((object) $this->cron->read(), 200, JSON_FLAGS);
    }
});

$app->delete('/phones/reboot', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $cron_response = $this->cron->delete($body);
    return $response->withJson((object) $cron_response, 200, JSON_FLAGS);
});

$app->post('/phones/rps/{mac}', function (Request $request, Response $response, $args) {
    $body = $request->getParsedBody();
    $token = $body['token'];
    $result = setFalconieriRPS($args['mac'],$token);
    error_log('Set RPS using Falconieri: '.json_encode($result));
    return $response->withStatus($result['httpCode']);
});

$app->get('/phones/account/{mac}', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $stmt = $dbh->prepare('SELECT `extension`,`secret` FROM `rest_devices_phones` WHERE `mac` = ?');
    $stmt->execute(array(str_replace('-',':',$args['mac'])));
    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $response->withJson((object) $res , 200, JSON_FLAGS);
});

$app->get('/provisioning/engine', function (Request $request, Response $response, $args) {
    return $response->withJson(getProvisioningEngine(), 200, JSON_FLAGS);
});

$app->get('/provisioning/variables[/{extension}]', function (Request $request, Response $response, $args) {
    if (array_key_exists('extension',$args)) {
        return $response->withJson(getExtensionSpecificVariables($args['extension']), 200, JSON_FLAGS);
    } else {
        return $response->withJson(getGlobalVariables(), 200, JSON_FLAGS);
    }
});

$app->get('/provisioning/cloud', function (Request $request, Response $response, $args) {
    return $response->withJson(isCloud(), 200, JSON_FLAGS);
});

$app->post('/provisioning/cloud/{status}', function (Request $request, Response $response, $args) {
    if ($args['status'] == 'true') setCloud(TRUE);
    elseif ($args['status'] == 'false') setCloud(FALSE);
    else return $response->withStatus(400);
    return $response->withStatus(204);
});

function getFeaturcodes(){
    $dbh = FreePBX::Database();
    $sql = 'SELECT modulename,featurename,defaultcode,customcode FROM featurecodes';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array());
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $featurecodes = array();
    foreach ($res as $featurecode) {
        $featurecodes[$featurecode['modulename'].$featurecode['featurename']] = (!empty($featurecode['customcode'])?$featurecode['customcode']:$featurecode['defaultcode']);
    }
    return $featurecodes;
}

function getGlobalVariables(){
    global $amp_conf;
    $variables = array();
    $featurecodes = getFeaturcodes();

    if (!isCloud()) {
        // Get local green address
        $dbh = FreePBX::Database();
        $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "ip"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];

        // ldap_server
        $variables['ldap_server'] = $res['value'];

        // srtp_encryption
        $variables['srtp_encryption'] = 0;

        // network_time_server
        $variables['network_time_server'] = $res['value'];
    } else {
        // ldap_server
        $variables['ldap_server'] = gethostname();

        // srtp_encryption
        $variables['srtp_encryption'] = 1;

        // network_time_server
        $variables['network_time_server'] = gethostname();
    }


    // ldap_base
    $variables['ldap_base'] = 'dc=phonebook,dc=nh';

    // ldap_port
    $variables['ldap_port'] = '10389';


    // ldap_user
    $variables['ldap_user'] = '';

    // ldap_password
    $variables['ldap_password'] = '';

    // ldap_tls
    $variables['ldap_tls'] = '';

    // ldap_name_display
    $variables['ldap_name_display'] = '%cn %o';

    // ldap_number_attr
    $variables['ldap_number_attr'] = 'telephoneNumber mobile homePhone';

    // ldap_name_attr
    $variables['ldap_name_attr'] = 'cn o';

    // ldap_number_filter
    $variables['ldap_number_filter'] = '(|(telephoneNumber=%)(mobile=%)(homePhone=%))';

    // ldap_name_filter
    $variables['ldap_name_filter'] = '(|(cn=%)(o=%))';

    // cftimeout
    $variables['cftimeout'] = $amp_conf['CFRINGTIMERDEFAULT'];

    // cftimeouton featurecodeadmin Call Forward No Answer/Unavailable Activate
    $variables['cftimeouton'] = $featurecodes['callforwardcfuon'];

    // cftimeoutoff featurecodeadmin Call Forward No Answer/Unavailable Deactivate
    $variables['cftimeoutoff'] = $featurecodes['callforwardcfuoff'];

    // cfbusyoff featurecodeadmin Call Forward Busy Deactivate
    $variables['cfbusyoff'] = $featurecodes['callforwardcfboff'];

    // cfbusyon featurecodeadmin Call Forward Busy Activate
    $variables['cfbusyon'] = $featurecodes['callforwardcfbon'];

    // cfalwaysoff (featurecodeadmin) Call Forward All Deactivate
    $variables['cfalwaysoff'] = $featurecodes['callforwardcfoff'];

    // cfalwayson (featurecodeadmin) Call Forward All Activate
    $variables['cfalwayson'] = $featurecodes['callforwardcfon'];

    // dndoff featurecodeadmin DND Deactivate
    $variables['dndoff'] = $featurecodes['donotdisturbdnd_off'];

    // dndon featurecodeadmin DND Activate
    $variables['dndon'] = $featurecodes['donotdisturbdnd_on'];

    // call_waiting_off featurecodeadmin Call Waiting - Deactivate
    $variables['call_waiting_off'] = $featurecodes['callwaitingcwoff'];

    // call_waiting_on featurecodeadmin Call Waiting - Activate
    $variables['call_waiting_on'] = $featurecodes['callwaitingcwon'];

    // pickup_direct featurecodeadmin Directed Call Pickup
    $variables['pickup_direct'] = $featurecodes['corepickup'];

    // pickup_group featurecodeadmin Asterisk General Call Pickup
    $variables['pickup_group'] = $featurecodes['corepickupexten'];



    // dnd_allow 0|1
    $variables['dnd_allow'] = '1';

    // fwd_allow 0|1
    $variables['fwd_allow'] = '1';


    return $variables;
}

function getExtensionSpecificVariables($extension){
    global $astman;
    $variables = array();
    $featurecodes = getFeaturcodes();

    // Get main extension
    if (isMainExtension($extension)) {
        $mainextension = $extension;
    } else {
        $mainextension = getMainExtension($extension);
    }

    // dnd_allow 0|1
    $variables['dnd_allow'] = '1';

    // fwd_allow 0|1
    $variables['fwd_allow'] = '1';

    // Get CTI profile id
    $users = getAllUsers();
    $profileid = null;
    foreach ($users as $user) {
         if ($user['default_extension'] == $mainextension) {
             $profileid = $user['profile'];
             break;
         }
    }

    // Get CTI profile permissions
    if (!empty($profileid)) {
        $permissions = getCTIPermissionProfiles($profileid);
        if ( array_key_exists('permissions',$permissions['macro_permissions']['settings'])) {
            foreach ($permissions['macro_permissions']['settings']['permissions'] as $permission) {
                if ($permission['name'] == 'dnd') {
                    $variables['dnd_allow'] = (string)(int) $permission['value'];
                }
                if ($permission['name'] == 'call_forward') {
                    $variables['fwd_allow'] = (string)(int) $permission['value'];
                }
            }
        }
    }

    // call_waiting (astdb extension 0|1)
    $variables['call_waiting_1'] = '0';
    if ($astman->database_get("CW", $mainextension) === "ENABLED") $variables['call_waiting'] = '1';

    // dnd_enable_ astdb
    $variables['dnd_enable_1'] = '0';

    // timeout_fwd_target_ astdb
    $variables['timeout_fwd_target_1'] = $astman->database_get("CFU", $mainextension);

    // timeout_fwd_enable_ astdb
    $variables['timeout_fwd_enable_1'] = (string) (int) !empty($variables['timeout_fwd_target_1']);

    // busy_fwd_target_ astdb
    $variables['busy_fwd_target_1'] = $astman->database_get("CFB", $mainextension);

    // busy_fwd_enable_ astdb
    $variables['busy_fwd_enable_1'] = (string) (int) !empty($variables['busy_fwd_target_1']);

    // always_fwd_target_ astdb
    $variables['always_fwd_target_1'] = $astman->database_get("CF", $mainextension);

    // always_fwd_enable_ astdb
    $variables['always_fwd_enable_1'] = (string) (int) !empty($variables['always_fwd_target_1']);

    // cftimeout
    $variables['cftimeout_1'] = $astman->database_get("AMPUSER",$mainextension.'/followme/prering');

    // Get extension sip data
    $dbh = FreePBX::Database();
    $sql = 'SELECT `keyword`,`data` FROM `sip` WHERE `id` = ?';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($extension));
    $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $sip = array();

    $variables['line_active_1'] = '0';
    if (!empty($res)) {
        foreach ($res as $row) {
            $sip[$row['keyword']] = $row['data'];
        }

        // line_active_
        $variables['line_active_1'] = '1';

        // displayname_
        $variables['displayname_1'] = $sip['callerid'];

        // username_
        $variables['username_1'] = (string) $extension;

        // secret_
        $variables['secret_1'] = (string) $sip['secret'];

        // dtmf_type_
        $variables['dtmf_type_1'] = $sip['dtmfmode'];
    }

    if (!isCloud()) {
        // Get local green address
        $dbh = FreePBX::Database();
        $sql = 'SELECT `variable`,`value` FROM `admin` WHERE `variable` = "ip"';
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array());
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC)[0];

        // server_host
        $variables['server_host_1'] = $res['value'];

        // server_port
        $variables['server_port_1'] = '5060';

        // srtp_encryption_
        $variables['srtp_encryption_1'] = 0;
    } else {
        // server_host
        $variables['server_host_1'] = gethostname();

        // server_port
        $variables['server_port_1'] = '5061';

        // srtp_encryption_
        $variables['srtp_encryption_1'] = 1;
    }

    // transport_type_
    $variables['transport_type_1'] = ''; // empty = auto

    // server_host2_
    $variables['server_host2_1'] = '';

    // server_port2_
    $variables['server_port2_1'] = '';

    // transport_type2_
    $variables['transport_type2_1'] = '';


    // voicemail_number_
    if (isMainExtension($extension)) {
        $variables['voicemail_number_1'] = $featurecodes['voicemailmyvoicemail'];
    } else {
        $variables['voicemail_number_1'] = $featurecodes['voicemaildialvoicemail'].$mainextension;
    }

    return $variables;
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



