<?php

include_once "Classes/Gamestate.php";

function GetStringArray($line)
{
  $line = trim($line);
  if($line == "") return [];
  return explode(" ", $line);
}

if(!isset($filename) || !str_contains($filename, "gamestate.txt")) $filename = "./Games/" . $gameName . "/gamestate.txt";
if(!isset($filepath)) $filepath = "./Games/" . $gameName . "/";

ParseGamestate();

function GamestateSanitize($input)
{
  $output = str_replace(",", "<44>", $input);
  $output = str_replace(" ", "_", $output);
  return $output;
}

function GamestateUnsanitize($input)
{
  $output = str_replace("<44>", ",", $input);
  $output = str_replace("_", " ", $output);
  return $output;
}

function ParseGamestate($useRedis = false)
{
  global $gameName, $gamestate, $playerID, $filename, $mainPlayerGamestateStillBuilt, $mpgBuiltFor, $myStateBuiltFor;

  $mainPlayerGamestateStillBuilt = 0;
  $mpgBuiltFor = -1;
  $myStateBuiltFor = -1;

  $fileTries = 0;
  $targetTries = ($playerID == 1 ? 10 : 100);
  $waitTime = 1000000;
  while (!file_exists($filename) && $fileTries < $targetTries) {
    usleep($waitTime); //1 second
    ++$fileTries;
  }
  if ($fileTries == $targetTries) {
    $response = new stdClass();
    $response->error = "Unable to create the game after 10 seconds. Please try again.";
    echo(json_encode($response));
    exit;
  }

  if (!file_exists($filename)) exit;
  $handler = fopen($filename, "r");

  if (!$handler) {
    exit;
  } //Game does not exist

  $lockTries = 0;
  while (!flock($handler, LOCK_SH) && $lockTries < 10) {
    usleep(100000); //100ms
    ++$lockTries;
  }

  if ($lockTries == 10) exit;

  $gamestateContent = "";
  if($useRedis) $gamestateContent = ReadCache($gameName . "GS");
  if($gamestateContent == "") $gamestateContent = file_get_contents($filename);
  
  $gamestate = unserialize($gamestateContent);

  fclose($handler);
  BuildMyGamestate($playerID);
}

function DoGamestateUpdate()
{
  global $mainPlayerGamestateStillBuilt, $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt == 1) UpdateMainPlayerGameStateInner();
  else if ($myStateBuiltFor != -1) UpdateGameStateInner();
}

function BuildMyGamestate($playerID)
{
  global $gamestate;
  global $myDeck, $myHand, $myResources, $myCharacter, $myDamage, $myClassState;
  global $myDiscard, $myCardStats, $myTurnStats;
  global $theirDeck, $theirHand, $theirResources, $theirCharacter, $theirDamage, $theirClassState;
  global $theirDiscard, $theirCardStats, $theirTurnStats;
  global $myMaterial, $theirMaterial;
  global $myStateBuiltFor, $mainPlayerGamestateStillBuilt;
  DoGamestateUpdate();
  $mainPlayerGamestateStillBuilt = 0;
  $myStateBuiltFor = $playerID;
  $myHand = $playerID == 1 ? $gamestate->p1Hand : $gamestate->p2Hand;
  $myDeck = $playerID == 1 ? $gamestate->p1Deck : $gamestate->p2Deck;
  $myMaterial = $playerID == 1 ? $gamestate->p1Material : $gamestate->p2Material;
  $myResources = $playerID == 1 ? $gamestate->p1Resources : $gamestate->p2Resources;
  $myCharacter = $playerID == 1 ? $gamestate->p1CharEquip : $gamestate->p2CharEquip;
  $myDamage = $playerID == 1 ? $gamestate->playerDamageValues[0] : $gamestate->playerDamageValues[1];
  $myDiscard = $playerID == 1 ? $gamestate->p1Discard : $gamestate->p2Discard;
  $myClassState = $playerID == 1 ? $gamestate->p1ClassState : $gamestate->p2ClassState;
  $myCardStats = $playerID == 1 ? $gamestate->p1CardStats : $gamestate->p2CardStats;
  $myTurnStats = $playerID == 1 ? $gamestate->p1TurnStats : $gamestate->p2TurnStats;
  $theirHand = $playerID == 1 ? $gamestate->p2Hand : $gamestate->p1Hand;
  $theirDeck = $playerID == 1 ? $gamestate->p2Deck : $gamestate->p1Deck;
  $theirMaterial = $playerID == 1 ? $gamestate->p2Material : $gamestate->p1Material;
  $theirResources = $playerID == 1 ? $gamestate->p2Resources : $gamestate->p1Resources;
  $theirCharacter = $playerID == 1 ? $gamestate->p2CharEquip : $gamestate->p1CharEquip;
  $theirDamage = $playerID == 1 ? $gamestate->playerDamageValues[1] : $gamestate->playerDamageValues[0];
  $theirDiscard = $playerID == 1 ? $gamestate->p2Discard : $gamestate->p1Discard;
  $theirClassState = $playerID == 1 ? $gamestate->p2ClassState : $gamestate->p1ClassState;
  $theirMaterial = $playerID == 1 ? $gamestate->p2Material : $gamestate->p1Material;
  $theirCardStats = $playerID == 1 ? $gamestate->p2CardStats : $gamestate->p1CardStats;
  $theirTurnStats = $playerID == 1 ? $gamestate->p2TurnStats : $gamestate->p1TurnStats;
}

function BuildMainPlayerGameState()
{
  global $gamestate;
  global $mainPlayerGamestateStillBuilt, $mpgBuiltFor, $defPlayer;
  global $mainHand, $mainDeck, $mainResources, $mainCharacter, $mainArsenal, $mainPlayerDamage, $mainClassState;
  global $mainDiscard;
  global $defHand, $defDeck, $defResources, $defCharacter, $defArsenal, $defPlayerDamage, $defClassState;
  global $defDiscard;

  DoGamestateUpdate();
  $mpgBuiltFor = $gamestate->mainPlayer;
  $mainHand = $gamestate->mainPlayer == 1 ? $gamestate->p1Hand : $gamestate->p2Hand;
  $mainDeck = $gamestate->mainPlayer == 1 ? $gamestate->p1Deck: $gamestate->p2Deck;
  $mainResources = $gamestate->mainPlayer == 1 ? $gamestate->p1Resources : $gamestate->p2Resources;
  $mainCharacter = $gamestate->mainPlayer == 1 ? $gamestate->p1CharEquip: $gamestate->p2CharEquip;
  $mainPlayerDamage = $gamestate->mainPlayer == 1 ? $gamestate->playerDamageValues[0] : $gamestate->playerDamageValues[1];
  $mainClassState = $gamestate->mainPlayer == 1 ? $gamestate->p1ClassState : $gamestate->p2ClassState;
  $mainDiscard = $gamestate->mainPlayer == 1 ? $gamestate->p1Discard : $gamestate->p2Discard;
  $mainMaterial = $gamestate->mainPlayer == 1 ? $gamestate->p1Material : $gamestate->p2Material;
  $mainCardStats = $gamestate->mainPlayer == 1 ? $gamestate->p1CardStats : $gamestate->p2CardStats;
  $mainTurnStats = $gamestate->mainPlayer == 1 ? $gamestate->p1TurnStats : $gamestate->p2TurnStats;
  $defHand = $gamestate->mainPlayer == 1 ? $gamestate->p2Hand : $gamestate->p1Hand;
  $defDeck = $gamestate->mainPlayer == 1 ? $gamestate->p2Deck : $gamestate->p1Deck;
  $defResources = $gamestate->mainPlayer == 1 ? $gamestate->p2Resources : $gamestate->p1Resources;
  $defCharacter = $gamestate->mainPlayer == 1 ? $gamestate->p2CharEquip : $gamestate->p1CharEquip;
  $defPlayerDamage = $gamestate->mainPlayer == 1 ? $gamestate->playerDamageValues[1] : $gamestate->playerDamageValues[0];
  $defClassState = $gamestate->mainPlayer == 1 ? $gamestate->p2ClassState : $gamestate->p1ClassState;
  $defDiscard = $gamestate->mainPlayer == 1 ? $gamestate->p2Discard : $gamestate->p1Discard;
  $defMaterial = $gamestate->mainPlayer == 1 ? $gamestate->p2Material : $gamestate->p1Material;
  $defCardStats = $gamestate->mainPlayer == 1 ? $gamestate->p2CardStats : $gamestate->p1CardStats;
  $defTurnStats = $gamestate->mainPlayer == 1 ? $gamestate->p2TurnStats : $gamestate->p1TurnStats;

  $mainPlayerGamestateStillBuilt = 1;
}

function UpdateGameStateInner()
{
  global $gamestate, $myStateBuiltFor;
  global $myDeck, $myHand, $myResources, $myCharacter, $myDamage, $myClassState;
  global $myDiscard, $myCardStats, $myTurnStats;
  global $theirDeck, $theirHand, $theirResources, $theirCharacter, $theirDamage, $theirClassState;
  global $theirDiscard, $theirCardStats, $theirTurnStats;
  global $myMaterial, $theirMaterial;
  $activePlayer = $myStateBuiltFor;
  if ($activePlayer == 1) {
    $gamestate->p1Deck = $myDeck;
    $gamestate->p1Hand = $myHand;
    $gamestate->p1Resources = $myResources;
    $gamestate->p1CharEquip = $myCharacter;
    $gamestate->playerDamageValues[0] = $myDamage;
    $gamestate->p1ClassState = $myClassState;
    $gamestate->p1Discard = $myDiscard;
    $gamestate->p1Material = $myMaterial;
    $gamestate->p1CardStats = $myCardStats;
    $gamestate->p1TurnStats = $myTurnStats;
    $gamestate->p2Deck = $theirDeck;
    $gamestate->p2Hand = $theirHand;
    $gamestate->p2Resources = $theirResources;
    $gamestate->p2CharEquip = $theirCharacter;
    $gamestate->playerDamageValues[1] = $theirDamage;
    $gamestate->p2ClassState = $theirClassState;
    $gamestate->p2Discard = $theirDiscard;
    $gamestate->p2Material = $theirMaterial;
    $gamestate->p2CardStats = $theirCardStats;
    $gamestate->p2TurnStats = $theirTurnStats;
  } else {
    $gamestate->p2Deck = $myDeck;
    $gamestate->p2Hand = $myHand;
    $gamestate->p2Resources = $myResources;
    $gamestate->p2CharEquip = $myCharacter;
    $gamestate->playerDamageValues[1] = $myDamage;
    $gamestate->p2ClassState = $myClassState;
    $gamestate->p2Discard = $myDiscard;
    $gamestate->p2Material = $myMaterial;
    $gamestate->p2CardStats = $myCardStats;
    $gamestate->p2TurnStats = $myTurnStats;
    $gamestate->p1Deck= $theirDeck;
    $gamestate->p1Hand = $theirHand;
    $gamestate->p1Resources = $theirResources;
    $gamestate->p1CharEquip= $theirCharacter;
    $gamestate->playerDamageValues[0] = $theirDamage;
    $gamestate->p1ClassState = $theirClassState;
    $gamestate->p1Discard = $theirDiscard;
    $gamestate->p1Material = $theirMaterial;
    $gamestate->p1CardStats = $theirCardStats;
    $gamestate->p1TurnStats = $theirTurnStats;
  }
}

function UpdateMainPlayerGameStateInner()
{
  global $gamestateGamestateStillBuilt, $mpgBuiltFor;
  global $mainHand, $mainDeck, $mainResources, $mainCharacter, $mainArsenal, $mainPlayerDamage, $mainClassState;
  global $mainDiscard;
  global $defHand, $defDeck, $defResources, $defCharacter, $defPlayerDamage, $defClassState;
  global $defDiscard;
  global $mainMaterial, $defMaterial;
  global $mainCardStats, $defCardStats;
  global $mainTurnStats, $defTurnStats;

  $gamestate->p1Deck= $mpgBuiltFor == 1 ? $mainDeck : $defDeck;
  $gamestate->p1Hand = $mpgBuiltFor == 1 ? $mainHand : $defHand;
  $gamestate->p1Resources = $mpgBuiltFor == 1 ? $mainResources : $defResources;
  $gamestate->p1CharEquip= $mpgBuiltFor == 1 ? $mainCharacter : $defCharacter;
  $gamestate->playerDamageValues[0] = $mpgBuiltFor == 1 ? $mainPlayerDamage : $defPlayerDamage;
  $gamestate->p1ClassState = $mpgBuiltFor == 1 ? $mainClassState : $defClassState;
  $gamestate->p1Discard = $mpgBuiltFor == 1 ? $mainDiscard : $defDiscard;
  $gamestate->p1Material = $mpgBuiltFor == 1 ? $mainMaterial : $defMaterial;
  $gamestate->p1CardStats = $mpgBuiltFor == 1 ? $mainCardStats : $defCardStats;
  $gamestate->p1TurnStats = $mpgBuiltFor == 1 ? $mainTurnStats : $defTurnStats;
  $gamestate->p2Deck = $mpgBuiltFor == 2 ? $mainDeck : $defDeck;
  $gamestate->p2Hand = $mpgBuiltFor == 2 ? $mainHand : $defHand;
  $gamestate->p2Resources = $mpgBuiltFor == 2 ? $mainResources : $defResources;
  $gamestate->p2CharEquip = $mpgBuiltFor == 2 ? $mainCharacter : $defCharacter;
  $gamestate->playerDamageValues[1] = $mpgBuiltFor == 2 ? $mainPlayerDamage : $defPlayerDamage;
  $gamestate->p2ClassState = $mpgBuiltFor == 2 ? $mainClassState : $defClassState;
  $gamestate->p2Discard = $mpgBuiltFor == 2 ? $mainDiscard : $defDiscard;
  $gamestate->p2Material = $mpgBuiltFor == 2 ? $mainMaterial : $defMaterial;
  $gamestate->p2CardStats = $mpgBuiltFor == 2 ? $mainCardStats : $defCardStats;
  $gamestate->p2TurnStats = $mpgBuiltFor == 2 ? $mainTurnStats : $defTurnStats;
}

function MakeGamestateBackup($filename = "gamestateBackup.txt")
{
  global $filepath;
  if(!file_exists($filepath . "gamestate.txt")) WriteLog("Cannot copy gamestate file; it does not exist.");
  $result = copy($filepath . "gamestate.txt", $filepath . $filename);
  if(!$result) WriteLog("Copy of gamestate into " . $filename . " failed.");
}

function RevertGamestate($filename = "gamestateBackup.txt")
{
  global $gameName, $skipWriteGamestate, $useRedis, $filepath;
  if($useRedis)
  {
    $gamestate = file_get_contents($filepath . $filename);
    WriteCache($gameName . "GS", $gamestate);
  }
  copy($filepath . $filename, $filepath . "gamestate.txt");
  $skipWriteGamestate = true;
}

function MakeStartTurnBackup()
{
  global $gamestate, $filepath;
  $lastTurnFN = $filepath . "lastTurnGamestate.txt";
  $thisTurnFN = $filepath . "beginTurnGamestate.txt";
  if (file_exists($thisTurnFN)) copy($thisTurnFN, $lastTurnFN);
  copy($filepath . "gamestate.txt", $thisTurnFN);
  $startGameFN = $filepath . "startGamestate.txt";
  if ((IsPatron(1) || IsPatron(2)) && $gamestate->currentRound == 1 && !file_exists($startGameFN)) {
    copy($filepath . "gamestate.txt", $startGameFN);
  }
}
