# REST API

Simple REST API framework for NethVoice.
This is based on Slim framework: http://www.slimframework.com/docs/
API design inspired by http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api

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

Generate the secret key from bash:

```
user=admin;
pass=admin;
secret=$(cat /var/lib/nethserver/secrets/nethvoice); \
pass=$(echo -n $pass | sha1sum | awk '{print $1}'); \
echo -n $user$pass$secret | sha1sum | awk '{print $1}'
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

## Long running processes

You can spawn a long running process using ptrack command.

How it works:
- the library invokes ptrack along with the command to be executed
- ptrack creates a new socket, forks in background and executes the command
- the command must write its own status inside ptrack socket (many NethServer commands already support it)
- a client can read from ptrack socket the status of the running task

Start a new task:
```
require_once("lib/SystemTasks.php");

$st = new SystemTasks();
$taskId = $st->startTask("/usr/bin/sudo /usr/libexec/nethserver/pkgaction --install nethserver-directory");

```

Read the status of a task:
```
require_once("lib/SystemTasks.php");

$st = new SystemTasks();
$task = $st->getTaskStatus($taskId);
print_r($task);
```


## API

### Login

Example:

- user: admin
- password: admin
- secret: 1234

```
curl -kvL  https://localhost/freepbx/rest/login -H "Content-Type: application/json" -H "User: admin" -H "Secretkey: 51b3bfeb54d746a8c8989e71dd8be757787d1adc"

```

### Users

Retrieve all users

```
GET /users
```

Retrieve a specific user by username

```
GET /users/{id}
```

Create a new user or edit an existing one.

JSON body:
``{"username" : "myuser", "fullname" : "my full name"}``

```
POST /users
```

Set user password

```
POST /users/{username}/password

Parameter: { "password": "mypass" }
```

Get user password in clear-text
```
GET /users/{username}/password
```

Force user synchronization
```
POST /users/sync
```

### Configuration

Check  mode status.

Legacy mode is enabled if:
- nethserver-directory is installed
- nethvoice{LegacyMode} prop is set to enabled

Result:
  - `{'result' => "legacy" }` if legacy mode is enabled
  - `{'result' => "uc" }` if UC  mode is enabled
  - `{'result' => 'unknown' }` if mode isn't set

```
GET configuration/mode
```

Enable selected `<mode>`.
Valid modes are:
- `legacy`: set nethvoice{LegacyMode} to enabled, start nethserver-directory installation using ptrack
- `uc`: set nethvoice{LegacyMode} to disabled

JSON Body:
```
{
   "mode" : <mode>
}
```

Legacy mode can't be reverted.

Result:
 - `{'result' => <task_id> }` if legacy mode is set
 - `{'result' => "success" }` if uc mode is set


The `task_id` is a md5 hash.
It can be used to retrieve the task progress using the tasks module.

```
POST /configuration/mode

```


```
GET /configuration/networks
```

Return green ip addresses and their netmasks


### Tasks

Get the status of the given `task_id`

Result:

- task: the task id
- action: current executing action
- progress: total progress
```
{
  "task": "d56c79f9373a2f2f9ccd8a0ac3c46eb4",
  "action": "S10nethserver-directory-conf",
  "progess": 59
}
```

```
GET /tasks/{task_id}
```


### Virtual Extensions

Retrieve all Virtual Extensions

```
GET /virtualextensions
```

Retrieve a Virtual Extension by its own extension number

```
GET /virtualextensions/{extnumber}
```

Create a new Virtual Extension

```
POST /virtualextensions
```

```
Parameter: { "username": "myuser", "extension": "extnumber"[,"outboundcid" : "outboundcid" ] }
```

### Physical Extensions

Retrieve all Physical Extensions

```
GET /physicalextensions
```

Retrieve a Physical Extension by its own extension number

```
GET /physicalextensions/{extnumber}
```

Create a new Physical Extension. If extension number is given, API is going to create/edit the requested extension number, otherwise a new extension with first available extension number between 9[1-7] virtualextnumber.

```
POST /physicalextensions
```

```
Parameter: { "virtualextension": "virtualextnumber" [, "extension": "extensionnumber"]}
```

### Devices

Launch a network scan creating two files: MD5.phones.scan and MD5.gateways.scan in /var/run/nethvoice, where MD5 is the MD5 hash of given network. Return long run process id

```
POST /devices/scan
```

```
Parameter: { "network": "192.168.0.0/24"}
```


Get phones scanned from all netwotks

```
GET /devices/phones/list
```


Get gateways scanned from all netwotks

```
GET /devices/gateways/list
```


Get phones scanned from specific network

```
GET /devices/phones/list/{id}
```

{id} is md5(NETWORK) where NETWORK is the network in cidr format (example: 192.168.1.0/24)



Get gateways scanned from specific network

```
GET /devices/gateways/list/{id}
```

{id} is md5(NETWORK) where NETWORK is the network in cidr format (example: 192.168.1.0/24)


### Routes

Retrieve inbound routes

```
GET /inboundroutes
```

Retrieve outbound routes

```
GET /outboundroutes


=======
### Trunks

Retrieve all Trunks

```
GET /trunks
```

Result:

```
[
  {
    "trunkid":"1",
    "name":"2001",
    "tech":"dahdi",
    "outcid":"",
    "keepcid":"off",
    "maxchans":"",
    "failscript":"",
    "dialoutprefix":"",
    "channelid":"g0",
    "usercontext":"notneeded",
    "provider":"",
    "disabled":"off",
    "continue":"off"
  }
]
```

Retrieve all Trunks by used technology (e.g. "sip", "dahdi", ...)

```
GET /trunks/{tech}
```

Result:

```
[
  {
    "trunkid":"1",
    "name":"2001",
    "tech":"dahdi",
    "outcid":"",
    "keepcid":"off",
    "maxchans":"",
    "failscript":"",
    "dialoutprefix":"",
    "channelid":"g0",
    "usercontext":"notneeded",
    "provider":"",
    "disabled":"off",
    "continue":"off"
  }
]
```

### Providers

Retrieve all providers

```
GET /providers
```

Result:

```
[
  {
    "descrizione": "Eutelia",
    "dettpeer": "disallow=all↵allow...",
    "dettuser": "allow=CODECS↵conte...",
    "provider": "eutelia",
    "registration": "USERNAME:PASSW..."
  }
]
```
