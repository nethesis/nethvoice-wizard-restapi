<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../lib/SystemTasks.php');
require_once(__DIR__. '/../lib/modelRetrieve.php');
require_once(__DIR__. '/../../admin/modules/core/functions.inc.php');
include_once(__DIR__. '/../lib/gateway/functions.inc.php');
require_once(__DIR__. '/../lib/freepbxFwConsole.php');
require_once(__DIR__. '/../../admin/modules/endpointman/includes/functions.inc');

/*
*  Launch a scan: POST/devices/scan
*  Parameter: { "network": "192.168.0.0/24"}
*/

$app->post('/devices/scan', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    $network = $params['network'];

    //launch long running process
    $st = new SystemTasks();
    $taskId = $st->startTask("/usr/bin/sudo /var/www/html/freepbx/rest/lib/scanHelper.py ".escapeshellarg($network));
    return $response->withJson(['result' => $taskId], 200);
});

$app->get('/devices/{mac}/brand', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $mac = $route->getArgument('mac');
        $brand = json_decode(file_get_contents(__DIR__. '/../lib/macAddressMap.json'), true);
        return $response->withJson($brand[$mac], 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/phones/list/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $basedir='/var/run/nethvoice';
        $res=array();
        $filename = "$basedir/$id.phones.scan";
        if (!file_exists($filename)) {
            return $response->withJson(array("status"=>"Scan for network $id doesn't exist!"), 404);
        }

        // dhcp names
        exec('/bin/sudo /usr/libexec/nethserver/read-dhcp-leases', $out, $ret);
        $dhcp_map = array();
        if ($ret==0) {
            $dhcp_arr = json_decode($out[0], true);
            foreach ($dhcp_arr as $key => $value) {
                $dhcp_map[$value['mac']] = $value['name'];
            }
        }

        $phones = json_decode(file_get_contents($filename), true);

        foreach ($phones as $key => $value) {
            // get model from db
            $model = sql('SELECT model FROM `rest_devices_phones` WHERE mac = "' . $phones[$key]['mac'] . '"', "getOne");
            if ($model) {
                $phones[$key]['model'] = $model;
            } else { // read from other sources
                $modelNew = retrieveModel($phones[$key]['manufacturer'], $dhcp_map[strtolower($phones[$key]['mac'])], $phones[$key]['ipv4']);
                $phones[$key]['model'] = $modelNew;
                if ($modelNew) {
                    if (!addPhone($phones[$key]['mac'], $phones[$key]['manufacturer'], $modelNew)) {
                        return $response->withStatus(500);
                    }
                }
            }
        }
        return $response->withJson($phones, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/gateways/list/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');

        $gateways = array();

        // Retrieve scanned gateways
        $basedir='/var/run/nethvoice';
        $res=array();
        $filename = "$basedir/$id.gateways.scan";
        if (file_exists($filename)) {
            $scannedGateways = json_decode(file_get_contents($filename), true);
            foreach ($scannedGateways as $val) {
                $val['isConnected'] = true;
                $val['isConfigured'] = false;
                $gateways[$val['mac']] = $val;
            }
        }

        // Retrieve configured gateways
        $query = 'SELECT `gateway_config`.*,'.
          ' `gateway_config`.model_id AS model,'.
          ' `gateway_models`.manufacturer'.
          ' FROM `gateway_config`'.
          ' LEFT JOIN `gateway_models` ON `gateway_models`.id = `gateway_config`.model_id';
        $res = sql($query, "getAll", \PDO::FETCH_ASSOC);

        if ($res) {
            foreach ($res as $gateway) {
                $gateway['isConfigured'] = true;

            // Add trunks info
            $trunksMeta = array(
              'fxo' => array('`gateway_config_fxo`.trunk AS linked_trunk', '`gateway_config_fxo`.number'),
              'fxs' => array('`gateway_config_fxs`.extension AS linked_extension'),
              'pri' => array('`gateway_config_pri`.trunk AS linked_trunk'),
              'isdn' => array('`gateway_config_isdn`.trunk AS name', '`gateway_config_isdn`.protocol AS type'),
            );

                foreach ($trunksMeta as $trunkPrefix=>$trunkAttr) {
                    $sql = 'SELECT '. implode(',', $trunksMeta[$trunkPrefix]).
                ' FROM `gateway_config`'.
                ' JOIN `gateway_config_'. $trunkPrefix. '` ON `gateway_config_'. $trunkPrefix. '`.config_id = `gateway_config`.id'.
                ' WHERE `gateway_config`.mac = "' . $gateway['mac'] . '"';
                    $obj = sql($sql, "getAll", \PDO::FETCH_ASSOC);

                    if ($obj) {
                        $gateway['trunks_'. $trunkPrefix] = $obj;
                    }
                }

                $gateway['isConnected'] = array_key_exists($gateway['mac'], $gateways);

            // Merge results in list
            $gateways[$gateway['mac']] = $gateway['isConnected'] ?
              array_merge($gateways[$gateway['mac']], $gateway) :
              $gateway;
            }
        }

        return $response->withJson(array_values($gateways), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/phones/list', function (Request $request, Response $response, $args) {
    try {
        $basedir='/var/run/nethvoice';
        $files = scandir($basedir);
        $res=array();
        $dbh = FreePBX::Database();
        foreach ($files as $file) {
            if (preg_match('/\.phones\.scan$/', $file)) {
                if (file_exists($basedir."/".$file)) {
                    $phones = json_decode(file_get_contents($basedir."/".$file), true);
                    foreach ($phones as $key => $value) {
                        $sql = 'SELECT userman_users.default_extension'.
                            ' , rest_devices_phones.model, rest_devices_phones.extension, rest_devices_phones.line'.
                          ' FROM `rest_devices_phones`'.
                          ' LEFT JOIN userman_users ON userman_users.id = rest_devices_phones.user_id'.
                          ' WHERE mac = "' . $phones[$key]['mac'] . '"';
                        $objs = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
                        $phones[$key]['lines'] = array();
                        foreach ($objs as $obj){
                            $phones[$key]['model'] = $obj['model'];
                            $phones[$key]['lines'][] = (object)array(
                                                        "extension"=>$obj['extension'],
                                                        "mainextension"=>$obj['default_extension'],
                                                        "line"=>$obj['line'],
                                                        );
                        }
                        if($phones[$key]['model']) {
                            $res[]=$phones[$key];
                        }
                    }
                }
            }
        }
        return $response->withJson($res,200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/gateways/list', function (Request $request, Response $response, $args) {
    try {
        $basedir='/var/run/nethvoice';
        $files = scandir($basedir);
        $res=array();
        foreach ($files as $file) {
            if (preg_match('/\.gateways\.scan$/', $file)) {
                if (file_exists($basedir."/".$file)) {
                    $decoded = json_decode(file_get_contents($basedir."/".$file));
                    foreach ($decoded as $element) {
                        $res[]=$element;
                    }
                }
            }
        }
        return $response->withJson(array_unique($res, SORT_REGULAR), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/phones/manufacturers', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $sql = "select bl.name,ml.model from endpointman_brand_list bl join endpointman_model_list ml on bl.id=ml.brand";
    $models = $dbh->sql($sql, "getAll", \PDO::FETCH_ASSOC);
    $res=array();
    foreach ($models as $model) {
        if (!array_key_exists($model['name'], $res)) {
            $res[$model['name']] = array();
        }
        array_push($res[$model['name']], $model['model']);
    }
    return $response->withJson($res, 200);
});

$app->get('/devices/gateways/manufacturers', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $sql = "SELECT * FROM gateway_models";
    $models = $dbh->sql($sql, "getAll", \PDO::FETCH_ASSOC);
    $res=array();
    foreach ($models as $model) {
        if (!array_key_exists($model['manufacturer'], $res)) {
            $res[$model['manufacturer']] = array();
        }
        array_push($res[$model['manufacturer']], $model);
    }
    return $response->withJson($res, 200);
});

$app->post('/devices/phones/model', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $mac = $params['mac'];
        $vendor = $params['vendor'];
        $model = $params['model'];

        $dbh = FreePBX::Database();
        $dbh->query('DELETE IGNORE FROM `rest_devices_phones` WHERE `mac` = "'.$mac.'"');
        $sql = 'INSERT INTO `rest_devices_phones` (`mac`,`vendor`, `model`) VALUES (?,?,?)';
        $stmt = $dbh->prepare($sql);
        if ($res = $stmt->execute(array($mac,$vendor,$model))) {
            return $response->withStatus(200);
        } else {
            return $response->withStatus(500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

/*
* Create or update a gateway configuration
*/

$app->post('/devices/gateways', function (Request $request, Response $response, $args) {
    try {
        $fpbx = FreePBX::create();
        $params = $request->getParsedBody();
        $dbh = FreePBX::Database();
        /*Check if config exists*/
        $sql = "SELECT `id` FROM `gateway_config` WHERE `name` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($params['name']));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        if ($res !== false) {
            /*Configuration exists, delete it*/
            $id = $res['id'];
            $sqls = array();
            $sqls[] = "DELETE IGNORE FROM `gateway_config` WHERE `id` = ?";
            foreach ($sqls as $sql) {
                $sth = FreePBX::Database()->prepare($sql);
                $sth->execute(array($id));
            }
        }
        /*Create configuration*/
        $sql = "INSERT INTO `gateway_config` (`model_id`,`name`,`ipv4`,`ipv4_new`,`gateway`,`ipv4_green`,`netmask_green`,`mac`) VALUES (?,?,?,?,?,?,?,?)";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($params['model'],$params['name'],$params['ipv4'],$params['ipv4_new'],$params['gateway'],$params['ipv4_green'],$params['netmask_green'],$params['mac']));
        /*get id*/
        $sql = "SELECT `id` FROM `gateway_config` WHERE `name` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($params['name']));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        if ($res === false) {
            return $response->withJson(array("status"=>"Failed to create configuration"), 500);
        }
        $configId = $res['id'];

        // create trunks
        $sql = "SELECT `manufacturer` FROM `gateway_models` WHERE `id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($params['model']));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);

        // Create unique smart name
        $vendor = $res['manufacturer'];
        $uid = strtolower(substr(str_replace(':', '', $params['mac']), -6, 6));

        $trunksByTypes = array(
          'isdn' => $params['trunks_isdn'],
          'pri' => $params['trunks_pri'],
          'fxo' => $params['trunks_fxo'],
          'fxs' => $params['trunks_fxs']
        );

        foreach ($trunksByTypes as $type=>$trunks) {
            $port = (strtolower($res['manufacturer']) === 'patton' ? 0 : 1);

            foreach ($trunks as $trunk) {
                $trunkName = $vendor. '_'. $uid. '_'. $type. '_'. $port;

                $nextTrunkId = count(core_trunks_list());

                $trunk['trunknumber'] = intval('20'. str_pad(++$nextTrunkId, 3, '0', STR_PAD_LEFT));
                if ($vendor==='Sangoma') {
                    $dialoutprefix = $trunk['trunknumber'];
                } else {
                    $dialoutprefix = null;
                }

                $srvip = sql('SELECT `value` FROM `endpointman_global_vars` WHERE `var_name` = "srvip"', "getOne");
                $secret = substr(md5(uniqid(rand(), true)),0,8);
                $defaults = getPjSipDefaults();
                $defaults['secret'] = $secret;
                $defaults['username'] = $trunk['trunknumber'];
                $defaults['extdisplay'] = 'OUT_'.$nextTrunkId;
                $defaults['sip_server'] = $params['ipv4_new'];
                $defaults['sv_channelid'] = $trunkName;
                $defaults['sv_trunk_name'] = $trunkName;
                $defaults['transport'] = $srvip.'-udp';
                $defaults['trunk_name'] = $trunkName;
                // set $_REQUEST params for pjsip
                foreach ($defaults as $k => $v) {
                    $_REQUEST[$k] = $v;
                }

                $trunkId = core_trunks_add(
              'pjsip', // tech
              $trunkName, // channelid as trunk name
              $dialoutprefix, // dialoutprefix
              null, // maxchans
              null, // outcid
              null, // peerdetails
              'from-pstn', // usercontext
              null, // userconfig
              null, // register
              'off', // keepcid
              null, // failtrunk
              'off', // disabletrunk
              $trunkName, // name
              null, // provider
              'off', // continue
              false   // dialopts
            );

                $dialpattern_inser = array('prepend_digits'=>'','match_pattern_prefix'=>'','match_pattern_pass'=>'','match_cid'=>'');
                core_trunks_update_dialrules($trunkId, $dialpattern_insert);
                $port++;

                if ($type === 'isdn' && isset($params['trunks_isdn'])) {
                    /*Save isdn trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_isdn` (`config_id`,`trunk`,`trunknumber`,`protocol`,`secret`) VALUES (?,?,?,?,?)";
                    $sth = FreePBX::Database()->prepare($sql);
                    $sth->execute(array($configId,$trunkId,$trunk['trunknumber'],$trunk['type'],$secret));
                } elseif ($type === 'pri' && isset($params['trunks_pri'])) {
                    /*Save pri trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_pri` (`config_id`,`trunk`,`trunknumber`,`secret`) VALUES (?,?,?,?)";
                    $sth = FreePBX::Database()->prepare($sql);
                    $sth->execute(array($configId,$trunkId,$trunk['trunknumber'],$secret));
                } elseif ($type === 'fxo' && isset($params['trunks_fxo'])) {
                    /*Save fxo trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_fxo` (`config_id`,`trunk`,`trunknumber`,`number`,`secret`) VALUES (?,?,?,?,?)";
                    $sth = FreePBX::Database()->prepare($sql);
                    $sth->execute(array($configId,$trunkId,$trunk['trunknumber'],$trunk['number'],$secret));
                } elseif ($type === 'fxs' && isset($params['trunks_fxs'])) {
                    /* create physical extension */
                //get associated main extension
                $mainextensions = $fpbx->Core->getAllUsers();
                    $mainextensionnumber = $trunk['linked_extension'];
                    foreach ($mainextensions as $ve) {
                        if ($ve['extension'] == $mainextensionnumber) {
                            $mainextension = $ve;
                            break;
                        }
                    }
                //error if main extension number doesn't exist
                if (!isset($mainextension)) {
                    return $response->withJson(array("status"=>"Main extension ".$mainextensionnumber." doesn't exist"), 400);
                }

                    if (isset($params['extension'])) {
                        //use given extension number
                    if (!preg_match('/9[1-7]'.$mainextensionnumber.'/', $params['extension'])) {
                        return $response->withJson(array("status"=>"Wrong physical extension number supplied"), 400);
                    } else {
                        $extension = $params['extension'];
                    }
                    } else {
                        //get first free physical extension number for this main extension
                    $extensions = $fpbx->Core->getAllUsersByDeviceType();
                        for ($i=91; $i<=97; $i++) {
                            if (!extensionExists($i.$mainextensionnumber, $extensions)) {
                                $extension = $i.$mainextensionnumber;
                                break;
                            }
                        }
                    //error if there aren't available extension numbers
                    if (!isset($extension)) {
                        return $response->withJson(array("status"=>"There aren't available extension numbers"), 500);
                    }
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
                        return $response->withJson(array('message'=>$res['message']), 500);
                    }

                    $created_extension = $res['ext'];
                    $created_extension_secret = sql('SELECT data FROM `sip` WHERE id = "' . $created_extension . '" AND keyword="secret"', "getOne");

                /*Save fxs trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_fxs` (`config_id`,`extension`,`physical_extension`,`secret`) VALUES (?,?,?,?)";
                    $sth = FreePBX::Database()->prepare($sql);
                    $sth->execute(array($configId,$trunk['linked_extension'],$created_extension,$created_extension_secret));
                }
            }
        }

        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpGenerateConfig.php ".escapeshellarg($params['name']), $ret);
        if ($ret === 0) {
            fwconsole('r');
            return $response->withJson(array('id'=>$configId), 200);
        } else {
            throw new Exception('Error generating configuration');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

/*
* Send gateway configuration to the device
*/
$app->post('/devices/gateways/push', function (Request $request, Response $response, $args) {
    try {
        #create configuration files
        $params = $request->getParsedBody();
        $name = $params['name'];

        #Launch configuration push
        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpPushConfig.php ".escapeshellarg($name));
        return $response->withJson(array('status'=>true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});


/*
* Delete a gateway configuration
*/

$app->delete('/devices/gateways/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $sql = "SELECT `name` FROM `gateway_config` WHERE `id` = ?";
        $fpbx = FreePBX::create();
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpDeleteConfig.php ".escapeshellarg($res['name']), $ret);
        //get all trunks for this gateway
        $sql = "SELECT `trunk` FROM `gateway_config_fxo` WHERE `config_id` = ? UNION SELECT `physical_extension` FROM `gateway_config_fxs` WHERE `config_id` = ? UNION SELECT `trunk` FROM `gateway_config_isdn` WHERE `config_id` = ? UNION SELECT `trunk` FROM `gateway_config_pri` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id,$id,$id,$id));
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            core_trunks_del($row['trunk']);
            core_trunks_delete_dialrules($row['trunk']);
            core_routing_trunk_delbyid($row['trunk']);
            $fpbx->Core->delDevice($row['trunk'], true);
            $fpbx->Core->delUser($row['trunk'], true);
        }
        $sql = "DELETE IGNORE FROM `gateway_config` WHERE `id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id));
        fwconsole('r');
        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()), 500);
    }
});

/**
 * Download gateway configuration
 *
 * @api devices/gateways/download/:name
 */
 $app->get('/devices/gateways/download/{name}', function (Request $request, Response $response, $args) {
     $route = $request->getAttribute('route');
     $name = $route->getArgument('name');

     try {
         $config = gateway_generate_configuration_file($name);
         $response->withHeader('Content-Type', 'application/octet-stream');
         $response->withHeader('Content-Disposition', 'attachment; filename='. $name. '.txt');

         return $response->write($config);
     } catch (Exception $e) {
         error_log($e->getMessage());
         return $response->withStatus(500);
     }
 });

/*
* Provision phone (using FreePBX endpointman https://github.com/FreePBX-ContributedModules/endpointman)
*/
$app->post('/devices/phones/provision', function (Request $request, Response $response, $args) {
    try {
        $body = $request->getParsedBody();

        $mac = $body['mac'];

        $endpoint = new endpointmanager();

        $mac_id = $endpoint->retrieve_device_by_mac($mac);

        if ($mac_id) {
            $phone_info = $endpoint->get_phone_info($mac_id);
            $res = $endpoint->prepare_configs($phone_info, false);

          // Copy provisioning file to correct destination
          system('/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/moveProvisionFiles.php');
        } else {
            throw new Exception('device not found');
        }

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('message' => $e->getMessage()), 500);
    }
});

/*
* Reboot phone (using FreePBX endpointman https://github.com/FreePBX-ContributedModules/endpointman)
*/
$app->post('/devices/phones/reboot', function (Request $request, Response $response, $args) {
    try {
        $body = $request->getParsedBody();

        $mac = $body['mac'];
        $phoneIp = $body['ip'];

        $endpoint = new endpointmanager();

        $mac_id = $endpoint->retrieve_device_by_mac($mac);
        if ($mac_id) {
            $phone_info = $endpoint->get_phone_info($mac_id);

          // define('PROVISIONER_BASE', PHONE_MODULES_PATH);
          if (file_exists(PHONE_MODULES_PATH . 'autoload.php')) {
              if (!class_exists('ProvisionerConfig')) {
                  require(PHONE_MODULES_PATH . 'autoload.php');
              }

            //Load Provisioner
            $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';
              $base_class = "endpoint_" . $phone_info['directory'] . '_base';
              $master_class = "endpoint_base";
              if (!class_exists($master_class)) {
                  ProvisionerConfig::endpointsAutoload($master_class);
              }
              if (!class_exists($base_class)) {
                  ProvisionerConfig::endpointsAutoload($base_class);
              }
              if (!class_exists($class)) {
                  ProvisionerConfig::endpointsAutoload($class);
              }

              if (class_exists($class)) {
                  $provisioner_lib = new $class();
                  $provisioner_lib->reboot($phoneIp);
              }
          }
        } else {
            throw new Exception('device not found');
        }

        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('message' => $e->getMessage()), 500);
    }
});
