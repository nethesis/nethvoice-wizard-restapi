<?php

/*Get All Available macro permissions*/
function getAllAvailableMacroPermissions() {
    try {
        $dbh = FreePBX::Database();
        $sql = 'SELECT * FROM `rest_cti_macro_permissions`';
        return $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/*Get All permissions*/
function getAllAvailablePermissions($minified=false) {
    try {
        $dbh = FreePBX::Database();
        if ($minified) {
            $sql = 'SELECT `id`,`name` FROM `rest_cti_permissions`';
        } else {
            $sql = 'SELECT * FROM `rest_cti_permissions`';
        }
        $tmp_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
        foreach ($dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC) as $perm) {
            $permissions[$perm['id']] = $perm;
        }

        return $permissions;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/*Get all available permissions for all available macro permissions*/
function getAllAvailableMacroPermissionsPermissions() {
    try {
        $dbh = FreePBX::Database();
        foreach (getAllAvailableMacroPermissions() as $macro_permission) {
            $sql = 'SELECT `permission_id` FROM `rest_cti_macro_permissions_permissions` WHERE `macro_permission_id` = '.$macro_permission['id'];
            $macro_permissions_permissions[$macro_permission['id']] = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);
        }
        return $macro_permissions_permissions;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }

}

function getCTIPermissionProfiles($profileId=false, $minified=false){
    try {
        $dbh = FreePBX::Database();

        // Get all profiles
        $sql = 'SELECT * FROM `rest_cti_profiles`';
        $profiles = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

        // Get all available macro permissions
        $macro_permissions = getAllAvailableMacroPermissions();

        // Get all available permissions
        $permissions = getAllAvailablePermissions($minified);

        // Get all available permissions for all available macro permissions
        $macro_permissions_permissions = getAllAvailableMacroPermissionsPermissions();

        foreach ($profiles as $profile) {
            $id = $profile['id'];
            // Get profile macro permissions
            $sql = 'SELECT `macro_permission_id` FROM `rest_cti_profiles_macro_permissions` WHERE `profile_id` = '.$id;
            $profile_macro_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);
            $results[$id] = array('id' => $id, 'name' => $profile['name'], 'macro_permissions' => array());
            foreach ($macro_permissions as $macro_permission) {
                // Write macro permission name
                $results[$id]['macro_permissions'][$macro_permission['name']] = array();
                // Write macro permission state for this profile
                if (in_array($macro_permission['id'], $profile_macro_permissions)) {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['value'] = true;
                } else {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['value'] = false;
		}
                if (!$minified) {
                    // Write macro permission displayname
                    $results[$id]['macro_permissions'][$macro_permission['name']]['displayname'] = $macro_permission['displayname'];
		    // Write macro permission description
		    $results[$id]['macro_permissions'][$macro_permission['name']]['description'] = $macro_permission['description'];
                }
                // write permissions in this macro permission
                $sql = 'SELECT `permission_id` FROM `rest_cti_profiles_permissions` WHERE `profile_id` = '.$id;
                $enabled_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);

                foreach ($macro_permissions_permissions[$macro_permission['id']] as $macro_permissions_permission) {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['permissions'][$macro_permissions_permission] = $permissions[$macro_permissions_permission];
                    if (in_array($macro_permissions_permission, $enabled_permissions)) {
                        $results[$id]['macro_permissions'][$macro_permission['name']]['permissions'][$macro_permissions_permission]['value'] = true;
                    } else {
                        $results[$id]['macro_permissions'][$macro_permission['name']]['permissions'][$macro_permissions_permission]['value'] = false;
                    }
                }
                //Convert permissions into an array
                if (isset($results[$id]['macro_permissions'][$macro_permission['name']]['permissions'])) {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['permissions'] = array_values($results[$id]['macro_permissions'][$macro_permission['name']]['permissions']);
                } else {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['permissions'] = array();
                }
            }
        }
        if (!$profileId) {
            return array_values($results);
        } else {
            return $results[$profileId];
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function getCTIPermissions(){
    try {
        $dbh = FreePBX::Database();

        // Get all profiles
        $sql = 'SELECT * FROM `rest_cti_profiles`';
        $profiles = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

        // Get all available macro permissions
        $macro_permissions = getAllAvailableMacroPermissions();

        // Get all available permissions
        $permissions = getAllAvailablePermissions();

        // Get all available permissions for all available macro permissions
        foreach ($macro_permissions as $macro_permission) {
            $results[$macro_permission['name']] = $macro_permission;
        }

        foreach ($macro_permissions as $macro_permission) {
            $sql = 'SELECT `permission_id` FROM `rest_cti_macro_permissions_permissions` WHERE `macro_permission_id` = '.$macro_permission['id'];
            $macro_permissions_permissions[$macro_permission['id']] = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);

            // Write macro permission name
            $results[$macro_permission['name']] = array();

            // Write macro permission state as false
            $results[$macro_permission['name']]['value'] = false;

            // write permissions in this macro permission
            $sql = 'SELECT `permission_id` FROM `rest_cti_profiles_permissions` WHERE `profile_id` = '.$id;

            foreach ($macro_permissions_permissions[$macro_permission['id']] as $macro_permissions_permission) {
                $results[$macro_permission['name']]['permissions'][$macro_permissions_permission] = $permissions[$macro_permissions_permission];
                $results[$macro_permission['name']]['permissions'][$macro_permissions_permission]['value'] = false;

                //Convert permissions into an array
                if (isset($results[$macro_permission['name']]['permissions'])) {
                    $results[$macro_permission['name']]['permissions'] = array_values($results[$macro_permission['name']]['permissions']);
                } else {
                    $results[$macro_permission['name']]['permissions'] = array();
                }
            }
        }
        return $results;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}



function postCTIProfile($profile, $id=false){
    try {
        $dbh = FreePBX::Database();
        if (!$id){
            //Creating a new profile
            $sql = 'INSERT INTO `rest_cti_profiles` VALUES (NULL, ?)';
            $sth = $dbh->prepare($sql);
            $sth->execute(array($profile['name']));

            //Get id
            $sql = 'SELECT LAST_INSERT_ID()';
            $id = $dbh->sql($sql,"getOne");
        }
        //set macro_permissions
        foreach (getAllAvailableMacroPermissions() as $macro_permission) {
            if (!$profile['macro_permissions'][$macro_permission['name']]['value']) {
                $sql = 'DELETE IGNORE FROM `rest_cti_profiles_macro_permissions` WHERE `profile_id` = ? AND `macro_permission_id` = ?';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($id, $macro_permission['id']));
            } else {
                $sql = 'INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (?, ?)';
                $sth = $dbh->prepare($sql);
                $sth->execute(array($id, $macro_permission['id']));
            }
            if (!empty($profile['macro_permissions'][$macro_permission['name']]['permissions'])) {
                foreach ($profile['macro_permissions'][$macro_permission['name']]['permissions'] as $permission ) {
                    if ($permission['value']) {
                        $sql = 'INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (?, ?)';
                        $sth = $dbh->prepare($sql);
                        $sth->execute(array($id, $permission['id']));
                    } else {
                        $sql = 'DELETE IGNORE FROM `rest_cti_profiles_permissions` WHERE `profile_id` = ? AND `permission_id` = ?';
                        $sth = $dbh->prepare($sql);
                        $sth->execute(array($id, $permission['id']));
                    }
                }
            }
        }
        return $id;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

