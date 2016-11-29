<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function nethvoice_handler($exception) {
  header('Content-type: application/json');
  echo json_encode(['error' => $exception->getMessage()]);
  exit(0);
}

set_exception_handler('nethvoice_handler');

require 'vendor/autoload.php';

# Initialize FreePBX environment
$bootstrap_settings['freepbx_error_handler'] = false;
define('FREEPBX_IS_AUTH',1);
require_once '/etc/freepbx.conf';

# Load middleware classess
require('lib/AuthMiddleware.php');

# Load configuration
require_once('config.inc.php');

$app = new \Slim\App($config);

# Add authentication
$app->add(new AuthMiddleware($config['settings']['secretkey']));

foreach (glob("modules/*.php") as $filename)
{
    require($filename);
}

$app->run();
