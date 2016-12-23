<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once(__DIR__. '/../../admin/modules/core/functions.inc.php');

/**
 * @api {get} /inboundroutes/count  Retrieve inbound routes (incoming) count
 */
$app->get('/inboundroutes/count', function (Request $request, Response $response, $args) {
    try {
      $routes = FreePBX::Core()->getAllDIDs('extension');
      $destinations = FreePBX::Modules()->getDestinations();
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withJson(count($routes), 200);
});

/**
 * @api {get} /inboundroutes  Retrieve inbound routes (incoming)
 */
$app->get('/inboundroutes', function (Request $request, Response $response, $args) {
    try {
      $routes = FreePBX::Core()->getAllDIDs('extension');
      $destinations = FreePBX::Modules()->getDestinations();
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withJson(array("destinations" => $destinations, "routes" => $routes), 200);
});

/**
 * @api {post} /inboundroutes  Create an inbound routes (incoming)
 */
$app->post('/inboundroutes', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();

    try {
      $res = FreePBX::Core()->addDID($params);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    needreload();
    return $response->withStatus(200);
});


/**
 * @api {delete} /inboundroutes/:id Delete an inbound route
 */
$app->delete('/inboundroutes/{id}', function (Request $request, Response $response, $args) {
  $route = $request->getAttribute('route');
  $cidex = explode('-', $route->getArgument('id'));
  $extension = $cidex[0];
  $cidnum = $cidex[1];

  try {
    $res = FreePBX::Core()->getDID($extension, $cidnum ? $cidnum : '');

    if ($res === false)
      return $response->withStatus(404);

    FreePBX::Core()->delDID($extension, $cidnum ? $cidnum : '');
  } catch (Exception $e) {
    error_log($e->getMessage());
    return $response->withJson('An error occurred', 500);
  }

  needreload();
  return $response;
});

/**
 * @api {get} /outboundroutes/count  Retrieve inbound routes (incoming) count
 */
$app->get('/outboundroutes/count', function (Request $request, Response $response, $args) {
    try {
      $routes = FreePBX::Core()->getAllRoutes();
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }

    return $response->withJson(count($routes), 200);
});

/**
 * @api {get} /outboundroutes  Retrieve outbound routes.
 */
 $app->get('/outboundroutes', function (Request $request, Response $response, $args) {
     $routes = [];
     try {
       $allRoutes = FreePBX::Core()->getAllRoutes();
       foreach($allRoutes as $route) {
           $route_trunks = core_routing_getroutetrunksbyid($route['route_id']);
           $route['trunks'] = [];
           foreach($route_trunks as $trunk) {
               $route['trunks'][] = array("id" => $trunk, "name" => core_trunks_getTrunkTrunkName($trunk));
           }
           $routes[] = $route;
       }
     } catch (Exception $e) {
       error_log($e->getMessage());
       return $response->withJson('An error occurred', 500);
     }
     return $response->withJson($routes, 200);
 });

/**
 * @api {get} /outboundroutes/defaults Retrieves defaults outbound routes
 */
 $app->get('/outboundroutes/defaults', function (Request $request, Response $response, $args) {
     try {
        $dbh = FreePBX::Database();
        $sql = "SELECT DISTINCT `locale` FROM `outbound_routes_locales`";
        $locales = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
        $sql = "SELECT DISTINCT `key` FROM `outbound_routes_locales`";
        $keys = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

        $trunks = array();
        $alltrunks = FreePBX::Core()->listTrunks();
        foreach($alltrunks as $tr) {
            // this is the dahdi trunk automatically created by freepbx on the first time. So it is not added
            if ($tr["name"] == "DAHDI/g0" && $tr["trunkid"] == "1" && $tr["tech"] == "dahdi" && $tr["channelid"] == "g0") {
                continue;
            }
            array_push($trunks, array("name" => $tr["name"], "id" => $tr["trunkid"]));
        }

        $res = array();
        foreach($locales as $locale) {
            if (!array_key_exists($locale["locale"], $res)) {
                $res[$locale["locale"]] = array();
            }
            foreach($keys as $key) {
                array_push($res[$locale["locale"]], array(
                        "name" => $key["key"]."_".$locale["locale"],
                        "trunks" => $trunks
                    )
                );
            }
        }
        return $response->withJson($res,200);

     } catch (Exception $e) {
       error_log($e->getMessage());
       return $response->withJson('An error occurred', 500);
     }
 });

 /**
<<<<<<< 1d5a1150f4fb104d5b2db0589b002ba97d7f1cf6
 * @api {post} /outboundroutes  Create an outbound routes (incoming)
 */
 $app->post('/outboundroutes', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
=======
 * @api {post} /outboundroutes Creates outbound routes. If the routes passed as argument have not the "route_id", then
 *                             default outbound route will be created into the FreePBX db tables. Otherwise it updates
 *                             the sequences of routes and their trunks.
 */
 $app->post('/outboundroutes', function (Request $request, Response $response, $args) {
    try {
        $params = $request->getParsedBody();
        reset($params);
        $locale = key($params);

        // check if the routes are present into the freepbx db
        $route_keys = array_keys($params[$locale][0]);
        $created = false;
        foreach($route_keys as $key) {
            if ($key == "route_id") {
                $created = true;
            }
        }

        if ($created) {
            // update data into the freepbx db tables
            foreach($params[$locale] as $index => $route) {
                $trunks = array();
                foreach($route["trunks"] as $tr) {
                    array_push($trunks, $tr["id"]);
                }
                core_routing_setrouteorder($route["route_id"], strval($index));
                core_routing_updatetrunks($route["route_id"], $trunks, true);
            }
        } else {
            // initialize data into the freepbx db tables using data of table "outbound_routes_locales"
            $dbh = FreePBX::Database();
            $sql = "SELECT * FROM `outbound_routes_locales` where locale=\"$locale\"";
            $dblocales = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

            foreach($params[$locale] as $route) {

                $trunks = array();
                foreach($route["trunks"] as $tr) {
                    array_push($trunks, $tr["id"]);
                }

                $patterns = array();
                foreach($dblocales as $dbloc) {
                    if ($dbloc["locale"] == $locale && $route["name"] == $dbloc["key"]."_".$locale) {
                        array_push($patterns, array(
                            "match_pattern_prefix" => $dbloc["prefix_value"],
                            "match_pattern_pass" => $dbloc["pattern_value"],
                            "match_cid" => "",
                            "prepend_digits" => ""
                        ));
                    }
                }

                core_routing_addbyid(
                    $route["name"], // name
                    "", // outcid
                    "", // outcid_mode
                    "", //password
                    "", // emergency_mode
                    "", // intracompany_route
                    "default", // mohclass
                    NULL, //time_group_id
                    $patterns, // array of patterns
                    $trunks, // array of trunks id
                    NULL, // seq
                    "" // dest
                );
            }
        }
        needreload();
        return $response->withStatus(200);
>>>>>>> adjust POST /outboundroutes. task #358 2

    try {
      core_routing_addbyid(
        $params['name'],
        $params['outcid'],
        $params['outcid_mode'],
        $params['password'],
        $params['emergency_route'],
        $params['intracompany_route'],
        $params['mohclass'] ? $params['mohclass'] : 'default',
        $params['time_group_id'],
        $params['patterns'], // array of patterns
        $params['trunks'], // array of trunks id
        $params['seq'],
        $params['dest']);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $response->withJson('An error occurred', 500);
    }
<<<<<<< 1d5a1150f4fb104d5b2db0589b002ba97d7f1cf6

    needreload();
    return $response->withStatus(200);
=======
>>>>>>> adjust POST /outboundroutes. task #358 2
});

 /**
  * @api {delete} /outboundroutes/:id Delete an outbound route
  */
 $app->delete('/outboundroutes/{id}', function (Request $request, Response $response, $args) {
   $route = $request->getAttribute('route');
   $id = $route->getArgument('id');

   try {
     $res = core_routing_get($id);

     if ($res === false)
       return $response->withStatus(404);

     core_routing_delbyid($id);
   } catch (Exception $e) {
     error_log($e->getMessage());
     return $response->withJson('An error occurred', 500);
   }

   needreload();
   return $response;
 });
