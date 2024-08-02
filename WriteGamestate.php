<?php

if(!isset($filename) || !str_contains($filename, "gamestate.txt")) $filename = "./Games/" . $gameName . "/gamestate.txt";
$handler = fopen($filename, "w");

$lockTries = 0;
while (!flock($handler, LOCK_EX) && $lockTries < 10) {
  usleep(100000); //50ms
  ++$lockTries;
}

if ($lockTries == 10) { fclose($handler); exit; }

fwrite($handler, serialize($gamestate));

flock($handler, LOCK_UN);
fclose($handler);

if($useRedis) WriteCache($gameName . "GS", $gamestateContent);
