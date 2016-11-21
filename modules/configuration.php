<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


function getLegacyMode() {
    exec("/usr/bin/sudo /sbin/e-smith/config getprop nethvoice LegacyMode", $out);
    return $out[0];
}

function setLegacyMode() {
    exec("/usr/bin/sudo /sbin/e-smith/config setprop nethvoice LegacyMode enabled", $out, $ret);
}


# check if legacy mode is enabled

$app->get('/configuration/legacy', function (Request $request, Response $response, $args) {
    $mode = getLegacyMode();
    exec("/usr/bin/rpm -q nethserver-directory", $out, $ret);

    # return 'uknown' if LegacyMode prop is not set
    if ( $mode == "" ) {
        return $response->withJson(['result' => 'uknown']);
    }

    # return true, if LegacyMode is enabled and nethserver-directory is installed
    if ($mode == "enabled" && $ret === 0) {
        return $response->withJson(['result' => true]);
    }
    return $response->withJson(['result' => false]);
});


# set legacy mode to enabled

$app->post('/configuration/legacy', function (Request $request, Response $response, $args) {
    setLegacyMode();
    exec("/usr/bin/sudo /usr/libexec/nethserver/pkgaction --install nethserver-directory", $out, $ret);
    return $response->withJson(['result' => ($ret == 0)]);
});

