<?php
include_once('/var/www/html/freepbx/rest/lib/libUsers.php');
include_once('/var/www/html/freepbx/rest/lib/libExtensions.php');

try {
    $base64csv = $argv[1];
    $str = base64_decode($base64csv);
    $rowarr = explode(PHP_EOL, trim($str));
    $csv = array();
    foreach ($rowarr as $r) {
         $csv[] = str_getcsv($r);
    }

    # create users
    $result = 0;
    $err = '';
    foreach ($csv as $k => $row) {
        # trim fields
        foreach ($row as $index => $field) {
            $row[$index] = trim($field);
        }

        # check that row has username and fullname field
        if (! $row['0'] || ! $row['1']) {
            $result += 1;
            $err .= "Error creating user: username and fullname can't be empty: ".implode(",",$row) ."\n";
            unset($csv[$k]);
            continue;
        }

        #lowercase username
        $row[0] = strtolower($row[0]);

        # create user 
        if (!userExists($row[0])) {
            exec("/usr/bin/sudo /sbin/e-smith/signal-event user-create ".escapeshellcmd($row[0])." '".escapeshellcmd($row[1])."' '/bin/false'", $out, $ret);
            $result += $ret;

            if ($ret > 0 ) {
                $err .= "Error creating user ".$row[0].": ".$out."\n";
                unset($csv[$k]);
                continue;
            }
        }
        $csv[$k] = $row;
    }

    # sync users
    system("/usr/bin/scl enable rh-php56 '/usr/sbin/fwconsole userman --syncall --force' &> /dev/null");

     # create extensions
     foreach ($csv as $k => $row) {

        # Set password
        $tmp = tempnam("/tmp","ASTPWD");
        if (isset($row[3]) && ! empty($row[3]) ){
            $password = $row[3];
        } else {
             $password = generateRandomPassword();
        }
        file_put_contents($tmp, $password);
        exec("/usr/bin/sudo /sbin/e-smith/signal-event password-modify '".getUser($row[0])."' $tmp", $out, $ret);
        $result += $ret;
        if ($ret > 0 ) {
            $err .= "Error setting password for user ".$row[0].": ".print_r($out,true)."\n";
            unset($csv[$k]);
            continue;
        } else {
            setPassword($row[0], $password);
        }

        #create extension
        if (isset($row[2]) && preg_match('/^[0-9]*$/',$row[2])) {
            if (checkUsermanIsUnlocked()) {
                $create = createMainExtensionForUser($row[0],$row[2]);
                if ($create !== true) {
                    $result += 1;
                    $err .= "Error adding main extension ".$row[3]." to user ".$row[0].":". print_r($create,true)."\n";
                }
            } else {
                $err .= "Error adding main extension ".$row[3]." to user ".$row[0].": directory is locked";
                continue;
            }
        }
    }

    system('/var/www/html/freepbx/rest/lib/retrieveHelper.sh > /dev/null &');

    if ($result > 0) {
        throw new Exception("Something went wrong: \n".$err);
    }
    #return $response->withJson($csv);
    exit(0);
} catch (Exception $e) {
    error_log($e->getMessage());
    exit (1);
    #return $response->withJson(['result' => $e->getMessage()], 500);
}

