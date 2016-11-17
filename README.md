# REST API

Simple REST API framework for NethVoice.
This is based on Slim framework: http://www.slimframework.com/docs/

The code must be installed under `/var/www/html/freepbx/rest` directory.

All requests must include the ``Secretkey`` HTTP header, the secret is shared between client and server. Example:
```
Secretkey: 1234
```

## Adding modules

To add a new module, create a php file inside the `modules` directory.
All modules automatically have access to all FreePBX variables
like `$db` and `$astman`.

Example:

```
<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once('/var/www/html/freepbx/admin/modules/_module_/functions.inc.php');

$app->get('/magic/list', function ($request, $response, $args) {
    global $db;
    global $astman;

    ... do your magic here ..

    return $response->withJson($result);
});
```


## Login

Example:

```
curl -kvL  https://localhost/freepbx/rest/login -H "Content-Type: application/json" -H "Secretkey: 1234" --data '{ "user"word": "admin"}'
````

