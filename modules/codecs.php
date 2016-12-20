<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*
* GET /codecs/voip return allowed codecs for VoIP trunks, ordered and enabled
*/

$app->get('/codecs/voip', function (Request $request, Response $response, $args) {
    try{
        //check if G729 is installed
        exec('/usr/sbin/asterisk -rx "module show like codec_g729.so"', $out, $ret);
        if ($ret === 0 && strpos("codec_g729.so",implode($out)) !== false ) {
            //codec g729 found
            $out = array(
                array ('codec' => 'g729', 'enabled' => true),
                array ('codec' => 'alaw', 'enabled' => false),
                array ('codec' => 'ulaw', 'enabled' => false)
              );
            return $response->withJson($out, 200);
        } else {
            $out = array(
                array ('codec' => 'alaw', 'enabled' => true),
                array ('codec' => 'ulaw', 'enabled' => true)
              );
            return $response->withJson($out, 200);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $response->withJson('An error occurred', 500);
    }
});

