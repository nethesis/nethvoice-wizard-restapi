<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../lib/SystemTasks.php');
require_once(__DIR__. '/../lib/modelRetrieve.php');
require_once(__DIR__. '/../../admin/modules/core/functions.inc.php');
include_once(__DIR__. '/../lib/gateway/functions.inc.php');


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

$app->get('/devices/phones/list/{id}', function (Request $request, Response $response, $args) {
    try {
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $basedir='/var/run/nethvoice';
        $res=array();
        $filename = "$basedir/$id.phones.scan";
        if (!file_exists($filename)){
           return $response->withJson(array("status"=>"Scan for network $id doesn't exist!"),404);
        }

        // dhcp names
        exec('/bin/sudo /usr/libexec/nethserver/read-dhcp-leases', $out, $ret);
        $dhcp_map = array();
        if ($ret==0) {
            $dhcp_arr = json_decode($out[0], true);
            foreach($dhcp_arr as $key => $value) {
                $dhcp_map[$value['mac']] = $value['name'];
            }
        }

        $phones = json_decode(file_get_contents($filename), true);

        foreach ($phones as $key => $value) {
            // get model from db
            $model = sql('SELECT model FROM `rest_devices_phones` WHERE mac = "' . $phones[$key]['mac'] . '"', "getOne");
            if($model) {
                $phones[$key]['model'] = $model;
            } else { // read from other sources
                $modelNew = retrieveModel($phones[$key]['manufacturer'], $dhcp_map[strtolower($phones[$key]['mac'])], $phones[$key]['ipv4']);
                $phones[$key]['model'] = $modelNew;
                if($modelNew) {
                    $dbh = FreePBX::Database();
                    $sql = 'REPLACE INTO `rest_devices_phones` (`mac`,`vendor`, `model`) VALUES (?,?,?)';
                    $stmt = $dbh->prepare($sql);
                    if (!$res = $stmt->execute(array($phones[$key]['mac'],$phones[$key]['manufacturer'],$modelNew))) {
                        return $response->withStatus(500);
                    }
                }
            }
        }
        return $response->withJson($phones,200);
    } catch(Exception $e) {
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
              'pri' => array('`gateway_config_pri`.trunk AS linked_trunk'),
              'isdn' => array('`gateway_config_isdn`.trunk AS name', '`gateway_config_isdn`.protocol AS type'),
            );

            foreach($trunksMeta as $trunkPrefix=>$trunkAttr) {
              $sql = 'SELECT '. implode(',', $trunksMeta[$trunkPrefix]).
                ' FROM `gateway_config`'.
                ' JOIN `gateway_config_'. $trunkPrefix. '` ON `gateway_config_'. $trunkPrefix. '`.config_id = `gateway_config`.id'.
                ' WHERE `gateway_config`.mac = "' . $gateway['mac'] . '"';
              $obj = sql($sql, "getAll", \PDO::FETCH_ASSOC);

              if ($obj)
                $gateway['trunks_'. $trunkPrefix] = $obj;
            }

            $gateway['isConnected'] = array_key_exists($gateway['mac'], $gateways);

            // Merge results in list
            $gateways[$gateway['mac']] = $gateway['isConnected'] ?
              array_merge($gateways[$gateway['mac']], $gateway) :
              $gateway;
          }
        }

        return $response->withJson(array_values($gateways), 200);
    } catch(Exception $e) {
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
        foreach ($files as $file){
            if (preg_match('/\.phones\.scan$/', $file)) {
                if (file_exists($basedir."/".$file)){
                    $phones = json_decode(file_get_contents($basedir."/".$file),true);
                    foreach ($phones as $key => $value) {
                        $sql = 'SELECT model,mainextension,extension FROM `rest_devices_phones` WHERE mac = "' . $phones[$key]['mac'] . '"';
                        $obj = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC)[0];
                        $phones[$key]['model'] = $obj['model'];
                        $phones[$key]['mainextension'] = $obj['mainextension'];
                        $phones[$key]['extension'] = $obj['extension'];
                        if($phones[$key]['model']) {
                            $res[]=$phones[$key];
                        }
                    }
                }
            }
        }
        return $response->withJson(array_unique($res,SORT_REGULAR),200);
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
        foreach ($files as $file){
            if (preg_match('/\.gateways\.scan$/', $file)) {
                if (file_exists($basedir."/".$file)){
                    $decoded = json_decode(file_get_contents($basedir."/".$file));
                    foreach ($decoded as $element)
                    {
                        $res[]=$element;
                    }
                }
            }
        }
        return $response->withJson(array_unique($res,SORT_REGULAR),200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/devices/phones/manufacturers', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $sql = "select bl.name,ml.model from endpointman_brand_list bl join endpointman_model_list ml on bl.id=ml.brand";
    $models = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
    $res=array();
    foreach ($models as $model){
        if (!array_key_exists($model['name'], $res)) {
            $res[$model['name']] = array();
        }
        array_push($res[$model['name']], $model['model']);
    }
    return $response->withJson($res,200);
});

$app->get('/devices/gateways/manufacturers', function (Request $request, Response $response, $args) {
    $dbh = FreePBX::Database();
    $sql = "SELECT * FROM gateway_models";
    $models = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
    $res=array();
    foreach ($models as $model){
        if (!array_key_exists($model['manufacturer'], $res)) {
            $res[$model['manufacturer']] = array();
        }
        array_push($res[$model['manufacturer']], $model);
    }
    return $response->withJson($res,200);
});

$app->post('/devices/phones/model', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        $mac = $params['mac'];
        $vendor = $params['vendor'];
        $model = $params['model'];

        $dbh = FreePBX::Database();
        $sql = 'REPLACE INTO `rest_devices_phones` (`mac`,`vendor`, `model`) VALUES (?,?,?)';
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
    try{
        $fpbx = FreePBX::create();
        $params = $request->getParsedBody();
        $dbh = FreePBX::Database();
        /*Check if config exists*/
        $sql = "SELECT `id` FROM `gateway_config` WHERE `name` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($params['name']));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        if ($res !== false){
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
        if ($res === false){
            return $response->withJson(array("status"=>"Failed to create configuration"),500);
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
          'fxo' => $params['trunks_fxo']
        );

        foreach ($trunksByTypes as $type=>$trunks) {
          $port = (strtolower($res['manufacturer']) === 'patton' ? 0 : 1);

          foreach ($trunks as $trunk) {
            $trunkName = $vendor. '_'. $uid. '_'. $type. '_'. $port;

            $peerdetails = 'context=from-pstn'. "\n".
              'host=dynamic'. "\n".
              'insecure=very'. "\n".
              'qualify=yes'. "\n".
              'secret='. $trunkName. "\n".
              'type=friend'. "\n".
              'username='. $trunkName;

            $nextTrunkId = count(core_trunks_list());
            $dialoutprefix = intval('20'. str_pad(++$nextTrunkId, 3, '0', STR_PAD_LEFT));

            $trunkId = core_trunks_add(
              'pjsip', // tech
              $trunkName, // channelid as trunk name
              $dialoutprefix, // dialoutprefix TODO
              null, // maxchans
              null, // outcid
              $peerdetails, // peerdetails
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

            $port++;

            if ($type === 'isdn' && isset($params['trunks_isdn'])){
                /*Save isdn trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_isdn` (`config_id`,`trunk`,`protocol`) VALUES (?,?,?)";
                $sth = FreePBX::Database()->prepare($sql);
                $sth->execute(array($configId,$trunkId,$trunk['type']));
            }
            else if ($type === 'pri' && isset($params['trunks_pri'])){
                /*Save pri trunks parameters*/
                $sql = "REPLACE INTO `gateway_config_pri` (`config_id`,`trunk`) VALUES (?,?)";
                $sth = FreePBX::Database()->prepare($sql);
                $sth->execute(array($configId,$trunkId));
            }
            else if ($type === 'fxo' && isset($params['trunks_fxo'])){
                /*Save fxo trunks parameters*/return $response->withJson(array('id'=>$configId), 200);
                $sql = "REPLACE INTO `gateway_config_fxo` (`config_id`,`trunk`,`number`) VALUES (?,?,?)";
                $sth = FreePBX::Database()->prepare($sql);
                $sth->execute(array($configId,$trunkId,$trunk['number']));
            }
          }
        }

        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpGenerateConfig.php ".escapeshellarg($params['name']),$ret);

        if ($ret === 0 ) {
          return $response->withJson(array('id'=>$configId), 200);
        } else {
            throw new Exception('Error generating configuration');
        }
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
    }
});

/*
* Send gateway configuration to the device
*/
$app->post('/devices/gateways/push', function (Request $request, Response $response, $args) {
    try{
        #create configuration files
        $params = $request->getParsedBody();
        $name = $params['name'];

        #Launch configuration push
        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpPushConfig.php ".escapeshellarg($name));
        return $response->withJson(array('status'=>true), 200);
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
    }
});


/*
* Delete a gateway configuration
*/

$app->delete('/devices/gateways/{id}', function (Request $request, Response $response, $args) {
    try{
        $route = $request->getAttribute('route');
        $id = $route->getArgument('id');
        $sql = "SELECT `name` FROM `gateway_config` WHERE `id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id));
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        system("/usr/bin/sudo /usr/bin/php /var/www/html/freepbx/rest/lib/tftpDeleteConfig.php ".escapeshellarg($res['name']),$ret);
        //get all trunks for this gateway
        $sql = "SELECT `trunk` FROM `gateway_config_fxo` WHERE `config_id` = ? UNION SELECT `trunk` FROM `gateway_config_isdn` WHERE `config_id` = ? UNION SELECT `trunk` FROM `gateway_config_pri` WHERE `config_id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id,$id,$id));
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)){
            core_trunks_del($row['trunk']);
            core_trunks_delete_dialrules($row['trunk']);
            core_routing_trunk_delbyid($row['trunk']);
            needreload();
        }
        $sql = "DELETE IGNORE FROM `gateway_config` WHERE `id` = ?";
        $sth = FreePBX::Database()->prepare($sql);
        $sth->execute(array($id));
        return $response->withJson(array('status' => true), 200);
    } catch (Exception $e){
        error_log($e->getMessage());
        return $response->withJson(array("status"=>$e->getMessage()),500);
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

