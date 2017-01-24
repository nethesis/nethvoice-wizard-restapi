<?php

function fwconsole($cmd, $args = array()) {
  exec("/usr/bin/scl enable rh-php56 '/usr/sbin/fwconsole ". $cmd. " ". implode(' ', $args). "'", $out, $ret);

  if ($ret !== 0)
    throw new Exception('fwconsole failed');
}
