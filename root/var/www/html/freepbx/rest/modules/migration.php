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

$app->get('/migration/ismigration', function (Request $request, Response $response, $args) {
    if (isMigration()) {
        return $response->withJson(true, 200);
    }
    return $response->withJson(false, 200);
});

$app->post('/migration/endmigration', function (Request $request, Response $response, $args) {
    $res = setMigration();
    if ($res['status']) {
        return $response->withJson($res,200);
    } else {
        return $response->withJson($res,500);
    }
});

$app->get('/migration/migrationstatus', function (Request $request, Response $response, $args) {
    $res = getMigrationStatus();
    if ($res !== false) {
        return $response->withJson($res, 200);
    } else {
        return $response->withJson($res,500);
    }

});

$app->get('/migration/oldusers', function (Request $request, Response $response, $args) {
    $res = getOldUsers();
    return $response->withJson($res, 200);
});

$app->get('/migration/report', function (Request $request, Response $response, $args) {
    $res = getMigrationReport();
    return $response->withJson($res, 200);
});


$app->post('/migration/importusers', function (Request $request, Response $response, $args) {
    try {
        //get users json
        $params = $request->getParsedBody();
        $csv = '';
        $profiles = getOldCTIProfiles();
        $users_groups = getOldCTIUsersGroups();
        foreach ($params as $user) {
            if (isset($user['username']) && isset($user['name'])) {
                // username
                $row[0] = (string) $user['username'];
                // fullname
                $row[1] = (string) $user['name'];
                // extension
                $row[2] = (string) $user['extension']; // get lower extension as mainextension
                // empty password
                $row[3] = '';
                // cellphone
                if (isset($user['cellphone']) && !empty($user['cellphone'])) {
                    $row[4] = (string) $user['cellphone'];
                } else {
                    $row[4] = '';
                }
                // voicemail
                if (isset($user['voicemails']) && $user['voicemails'] === 'default') {
                    $row[5] = 'TRUE';
                } else {
                     $row[5] = 'FALSE';
                }
                $row[6] = 'FALSE';
                // CTI groups
                if (isset($users_groups[$user['username']])) {
                    $row[7] = (string) implode('|',$users_groups[$user['username']]);
                } else {
                    $row[7] = '';
                }
                //CTI profiles
                $profiles = getOldCTIProfiles();
                if (isset($profiles[$user['profile_id']])) {
                    $row[8] = $profiles[$user['profile_id']];
                } else {
                    $row[8] = '';
                }
                $csv .= implode(',',$row)."\n";
            }
        }
        $base64csv = base64_encode($csv);
        system("/usr/bin/scl enable rh-php56 -- php /var/www/html/freepbx/rest/lib/csvimport.php ".escapeshellarg($base64csv)." &> /dev/null &");
        setMigration('importusers');
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->post('/migration/importprofiles', function (Request $request, Response $response, $args) {
    try {
        $profiles = getOldCTIProfiles();
        $errors = array();
        $infos = array();
        $returncode = 422;
        if (!empty($profiles)) {
            $return = true;
            foreach ( $profiles as $old_profile) {
                $res = cloneOldCTIProfile($old_profile);
                if ($res) {
                    $infos[] = 'Profile "'.$old_profile . '": migrated';
                } else {
                    $errors[] = 'Error migrating profile "'.$old_profile.'"';
                }
                if ($return && $res) {
                    $return = true;
                } else {
                    $return = false;
                }
            }
            if ($return) {
                $returncode = 200;
            } else {
                $returncode = 500;
            }
        }
        setMigration('importprofiles');
        return $response->withJson(array('status' => $return, 'errors' => $errors, 'infos' => $infos), $returncode);
    } catch (Exception $e) {
        error_log($e->getMessage());
        $errors[] = $e->getMessage();
        return $response->withJson(array('status' => false, 'errors' => $errors, 'infos' => $infos), 500);
    }
});

$app->post('/migration/importoldvoiptrunks', function (Request $request, Response $response, $args) {
    try {
        $res = copyOldTrunks();
        setMigration('importoldvoiptrunks');
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
        setMigration('importoutboundroutes');
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
        setMigration('trunksroutesassignements');
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
        setMigration('groups');
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
        setMigration('queues');
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
        setMigration('ivr');
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
        setMigration('cqr');
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
        setMigration('recordings');
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
        setMigration('announcements');
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
        setMigration('timegroups');
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
        setMigration('timeconditions');
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
        setMigration('inboundroutes');
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

$app->get('/migration/cdrrowcount', function (Request $request, Response $response, $args) {
    try {
        $res = getCdrRowCount();
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

$app->post('/migration/cdr', function (Request $request, Response $response, $args) {
    try {
        # launch migration task
        $statusfile = '/var/run/nethvoice/cdrmigration';
        system("/usr/bin/scl enable rh-php56 -- php /var/www/html/freepbx/rest/lib/cdrmigration.php &> /dev/null &");
        setMigration('cdr');
        return $response->withStatus(200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withStatus(500);
    }
});

$app->get('/migration/cdr', function (Request $request, Response $response, $args) {
    try {
        $statusfile = '/var/run/nethvoice/cdrmigration';
        if (file_exists($statusfile)) {
            $status = json_decode(file_get_contents($statusfile));
            if (isset($status->status) && $status->status == false) {
                unlink($statusfile);
                return $response->withJson($status,500);
            } elseif (isset($status->status) && $status->status == true) {
                unlink($statusfile);
                return $response->withJson($status,200);
            }
            return $response->withJson($status,200);
        } else {
            return $response->withJson(['status' => 'false', 'warnings' => 'No cdr migration active'],422);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson($status,500);
    }
});
