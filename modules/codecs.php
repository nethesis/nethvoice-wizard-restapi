<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*
* GET /codecs/voip return allowed codecs for VoIP trunks, ordered and enabled
*/

$app->get('/codecs/voip', function (Request $request, Response $response, $args) {
    $codecs = array(
        array ('codec' => 'alaw', 'enabled' => true),
        array ('codec' => 'ulaw', 'enabled' => true)
    );

    try{
        //check if G729 is installed
        exec('/usr/sbin/asterisk -rx "module show like codec_g729.so"', $out, $ret);
        if ($ret === 0 && strpos(implode($out), 'codec_g729.so') !== false) {
            //codec g729 found
            $codecs = array_map(function($a) { $a['enabled'] = false; return $a; }, $codecs);
            $codecs[] = array('codec' => 'g729', 'enabled' => true);
        }

        return $response->withJson($codecs, 200);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson('An error occurred', 500);
    }
});

