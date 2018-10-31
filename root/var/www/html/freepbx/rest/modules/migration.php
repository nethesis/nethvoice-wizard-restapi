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
#
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once('lib/libMigration.php');

$app->get('/migration/users', function (Request $request, Response $response, $args) {
    $res = getOldUsersCSV();
    return $response->withJson($res, 200);
});

// use csv import to import users from old installation
$app->post('/migration/importusers', function (Request $request, Response $response, $args) {
    try {
        $csv = '';
        foreach (getOldUsersCSV() as $row) {
            foreach ($row as $index => $field) {
                $row[$index] = preg_replace('/,/',' ',$field);
            }
            $csv .= implode(',',$row)."\n";
        }
        $base64csv = base64_encode($csv);
        system("/usr/bin/scl enable rh-php56 -- php /var/www/html/freepbx/rest/lib/csvimport.php ".escapeshellarg($base64csv)." &> /dev/null &");
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->post('/migration/importprofiles', function (Request $request, Response $response, $args) {
    try {
        $profiles = getOldCTIProfiles();
        if (!empty($profiles)) {
            $return = true;
            foreach ( $profiles as $old_profile) {
                $res = cloneOldCTIProfile($old_profile);
                if ($return && $res) {
                    $return = true;
                } else {
                    $return = false;
                }
            }
            if ($return) {
                return $response->withJson(array('status' => $return, 200));
            } else {
                return $response->withJson(array('status' => $return, 500));
            }
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/importoldvoiptrunks', function (Request $request, Response $response, $args) {
    try {
        $res = copyOldTrunks();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->get('/migration/oldgateways', function (Request $request, Response $response, $args) {
    try {
        $res = getOldGateways();
        if (isset($res['status']) && $res['status'] === FALSE) {
            return $response->withJson($res, 500);
        } else {
            return $response->withJson($res, 200);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/importoutboundroutes', function (Request $request, Response $response, $args) {
    try {
        $res = copyOldOutboundRoutes();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/trunksroutesassignements', function (Request $request, Response $response, $args) {
    try {
        $res = migrateRoutesTrunksAssignements();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/groups', function (Request $request, Response $response, $args) {
    try {
        $res = migrateGroups();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/queues', function (Request $request, Response $response, $args) {
    try {
        $res = migrateQueues();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/ivr', function (Request $request, Response $response, $args) {
    try {
        $res = migrateIVRs();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/cqr', function (Request $request, Response $response, $args) {
    try {
        $res = migrateCQRs();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/recordings', function (Request $request, Response $response, $args) {
    try {
        $res = migrateRecordings();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/announcements', function (Request $request, Response $response, $args) {
    try {
        $res = migrateAnnouncements();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/timegroups', function (Request $request, Response $response, $args) {
    try {
        $res = migrateTimegroups();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/timeconditions', function (Request $request, Response $response, $args) {
    try {
        $res = migrateTimeconditions();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});

$app->post('/migration/inboundroutes', function (Request $request, Response $response, $args) {
    try {
        $res = migrateInboundRoutes();
        if ($res['status']) {
            return $response->withJson($res, 200);
        } else {
            return $response->withJson($res, 500);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson(array('status' => false, 'errors' => array($e->getMessage())),500);
    }
});
