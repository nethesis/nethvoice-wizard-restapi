<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once("lib/SystemTasks.php");

# get the status of a running task

$app->get('/tasks/{task}', function (Request $request, Response $response, $args) {
    $taskId = $request->getAttribute('task');
    $ret = [ "task" => $taskId ];
    $code = 200;
    $st = new SystemTasks();
    $task = $st->getTaskStatus($taskId);
    $ret['action'] = $task['last']['title'];
    $ret['progress'] = ceil($task['progress'] * 100);

    # task has reached di end, set progress to 100
    if (isset($task['task_command_line'])) {
        $ret['progess'] = 100;

        # return server error if task has failed
        if ($task['exit_code'] > 0) {
            $code = 500;
        }
    }
    
    return $response->withJson($ret, $code);
});

