<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function __legacy() {
    exec("/usr/bin/rpm -q nethserver-directory", $out, $ret);
    return ($ret === 0);
}

$app->get('/configuration/islegacy', function (Request $request, Response $response, $args) {
    return $response->withJson(['result' => __legacy()]);
});

$app->post('/configuration/setlegacy', function (Request $request, Response $response, $args) {
    if (__legacy()) {
        return $response->withJson(['result' => 'true']);
    }
    exec("/usr/bin/sudo /usr/libexec/nethserver/pkgaction --install nethserver-directory", $out, $ret);
    return $response->withJson(['result' => ($ret == 0)]);
});

