# REST API

Simple REST API framework for NethVoice.
This is based on Slim framework: http://www.slimframework.com/docs/

The code must be installed under `/var/www/html/freepbx/rest` directory.

## Authentication

Eeach request must include 2 HTTP header:

- `User`: must be a valid FreePBX administrator (ampusers table)
- `Secretkey`: must be a valid hash

The Secretkey must be calculated using the following parameters:
- user: the same value of `User` header
- password: password of the user in sha1 hash format
- secret: shared static secret between client and server; default is: `1234`

Javascript example:

```
<script type="text/javascript" src="rest/sha1.js"></script>
<script type="text/javascript">

var user = "admin";
var password = sha1("admin");
var secret = "1234";
var hash = sha1(user + password + secret);

console.log("Secretkey: " + hash);

</script>

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

$app->get('/magic/list', function (Request $request, Response $response, $args) {
    global $db;
    global $astman;

    ... do your magic here ..

    return $response->withJson($result);
});
```


## Login

Example:

- user: admin
- password: admin
- secret: 1234

```
curl -kvL  https://localhost/freepbx/rest/login -H "Content-Type: application/json" -H "User: admin" -H "Secretkey: 51b3bfeb54d746a8c8989e71dd8be757787d1adc"

```

## API

Retrieve all users

```GET /users```

Retrieve a specific user by id

```GET /users/{id}```

Retrieve all extensions

```GET /extensions```

Retrieve all virtual extensions

```GET /virtualextensions```

Retrieve a virtual extension by its own number

```GET /virtualextensions/{extnumber}```
