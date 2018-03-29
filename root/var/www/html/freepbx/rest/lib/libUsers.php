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

function getUser($username) {
    # add domain part if needed
    if (strpos($username, '@') === false) {
        exec('/usr/bin/hostname -d', $out, $ret);
        $domain = $out[0];
        return "$username@$domain";
    }
    return $username;
}

function userExists($username) {
    $needle = getUser($username);
    $users = shell_exec("/usr/bin/sudo /usr/libexec/nethserver/list-users");
    foreach (json_decode($users) as $user => $props) {
        if ($user == $needle) {
            return true;
        }
    }
    return false;
}

function getPassword($username) {
    return sql(
      'SELECT rest_users.password'.
      ' FROM rest_users'.
      ' JOIN userman_users ON rest_users.user_id = userman_users.id'.
      ' WHERE userman_users.username = \''. getUser($username). '\'', 'getOne'
    );
}

function setPassword($username, $password) {
    fwconsole('userman --syncall --force');
    $dbh = FreePBX::Database();
    $sql =  'INSERT INTO rest_users (user_id,password)'.
            ' SELECT id, ?'.
            ' FROM userman_users'.
            ' WHERE username = ?'.
            ' ON DUPLICATE KEY UPDATE password = ?';
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array($password, $username, $password));
}

function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
