<?php

function getCTIPermissionProfiles($profileId=false){
    try {
        $dbh = FreePBX::Database();

        // Get all profiles
        $sql = 'SELECT * FROM `rest_cti_profiles`';
        $profiles = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

        // Get all available macro permissions
        $sql = 'SELECT * FROM `rest_cti_macro_permissions`';
        $macro_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);

        // Get all available permissions
        $sql = 'SELECT * FROM `rest_cti_permissions`';
        $tmp_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC);
        foreach ($dbh->sql($sql,"getAll",\PDO::FETCH_ASSOC) as $perm) {
            $permissions[$perm['id']] = $perm;
        }

        // Get all available permissions for all available macro permissions
        foreach ($macro_permissions as $macro_permission) {
            $sql = 'SELECT `permission_id` FROM `rest_cti_macro_permissions_permissions` WHERE `macro_permission_id` = '.$macro_permission['id'];
            $macro_permissions_permissions[$macro_permission['id']] = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);
        }

        foreach ($profiles as $profile) {
            $id = $profile['id'];
            // Get profile macro permissions
            $sql = 'SELECT `macro_permission_id` FROM `rest_cti_profiles_macro_permissions` WHERE `profile_id` = '.$id;
            $profile_macro_permissions = $dbh->sql($sql,"getAll",\PDO::FETCH_COLUMN);
            $results[$id] = array('id' => $id, 'name' => $profile['name'], 'macro_permissions' => array());
            foreach ($macro_permissions as $macro_permission) {
                // Write macro permission name
                $results[$id]['macro_permissions'][$macro_permission['name']] = array();
                // Write macrop permission state for this profile
                if (in_array($macro_permission['id'], $profile_macro_permissions)) {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['value'] = true;
                } else {
                    $results[$id]['macro_permissions'][$macro_permission['name']]['value'] = false;
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
        return array_values($results);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

