<?php

function fwconsole($cmd, $args = array()) {
  //launch long running process
  $st = new SystemTasks();
  $taskId = $st->startTask("/usr/bin/scl enable rh-php56 '/usr/sbin/fwconsole ". $cmd. " ". implode(' ', $args). "'");
  if (!$taskId) {
      throw new Exception('fwconsole failed');
  }
  return $taskId;
}
