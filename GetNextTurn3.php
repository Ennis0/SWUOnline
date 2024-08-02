<?php

include 'Libraries/HTTPLibraries.php';
include "HostFiles/Redirector.php";
include "Libraries/SHMOPLibraries.php";
include "WriteLog.php";

// array holding allowed Origin domains
SetHeaders();

header('Content-Type: application/json; charset=utf-8');
$response = new stdClass();

//We should always have a player ID as a URL parameter
$gameName = $_GET["gameName"];
if (!IsGameNameValid($gameName)) {
  $response->errorMessage = ("Invalid game name.");
  echo (json_encode($response));
  exit;
}

$playerID = TryGet("playerID", 3);
if (!is_numeric($playerID)) {
  $response->errorMessage = ("Invalid player ID.");
  echo (json_encode($response));
  exit;
}

if ($playerID == 3 && GetCachePiece($gameName, 9) != "1") {
  header('HTTP/1.0 403 Forbidden');
  exit;
}

$authKey = TryGet("authKey", "");
$lastUpdate = intval(TryGet("lastUpdate", 0));
$windowWidth = intval(TryGet("windowWidth", 0));
$windowHeight = intval(TryGet("windowHeight", 0));

if (($playerID == 1 || $playerID == 2) && $authKey == "") {
  if (isset($_COOKIE["lastAuthKey"])) $authKey = $_COOKIE["lastAuthKey"];
}

$isGamePlayer = $playerID == 1 || $playerID == 2;
$opponentDisconnected = false;
$opponentInactive = false;

$currentTime = round(microtime(true) * 1000);
if ($isGamePlayer) {
  $playerStatus = intval(GetCachePiece($gameName, $playerID + 3));
  if ($playerStatus == "-1") WriteLog("Player $playerID has connected.");
  SetCachePiece($gameName, $playerID + 1, $currentTime);
  SetCachePiece($gameName, $playerID + 3, "0");
  if ($playerStatus > 0) {
    WriteLog("Player $playerID has reconnected.");
    SetCachePiece($gameName, $playerID + 3, "0");
  }
}
$count = 0;
$cacheVal = intval(GetCachePiece($gameName, 1));

while ($lastUpdate != 0 && $cacheVal <= $lastUpdate) {
  usleep(100000); //100 milliseconds
  if (!file_exists("./Games/" . $gameName . "/GameFile.txt")) break;
  $currentTime = round(microtime(true) * 1000);
  $cacheVal = GetCachePiece($gameName, 1);
  if ($isGamePlayer) {
    SetCachePiece($gameName, $playerID + 1, $currentTime);
    $otherP = ($playerID == 1 ? 2 : 1);
    $oppLastTime = intval(GetCachePiece($gameName, $otherP + 1));
    $oppStatus = GetCachePiece($gameName, $otherP + 3);
    if (($currentTime - $oppLastTime) > 3000 && (intval($oppStatus) == 0)) {
      WriteLog("Opponent has disconnected. Waiting 60 seconds to reconnect.");
      GamestateUpdated($gameName);
      SetCachePiece($gameName, $otherP + 3, "1");
    } else if (($currentTime - $oppLastTime) > 60000 && $oppStatus == "1") {
      WriteLog("Opponent has left the game.");
      GamestateUpdated($gameName);
      SetCachePiece($gameName, $otherP + 3, "2");
      $lastUpdate = 0;
      $opponentDisconnected = true;
    }
    //Handle server timeout
    $gamestate->lastUpdateTime = GetCachePiece($gameName, 6);
    if ($currentTime - $gamestate->lastUpdateTime > 90000 && GetCachePiece($gameName, 12) != "1") //90 seconds
    {
      SetCachePiece($gameName, 12, "1");
      $opponentInactive = true;
      $lastUpdate = 0;
    }
  }
  ++$count;
  if ($count == 100) break;
}

if($lastUpdate == 0)
{
  $currentTime = round(microtime(true) * 1000);
  $cacheVal = GetCachePiece($gameName, 1);
  if ($isGamePlayer) {
    SetCachePiece($gameName, $playerID + 1, $currentTime);
    $otherP = ($playerID == 1 ? 2 : 1);
    $oppLastTime = intval(GetCachePiece($gameName, $otherP + 1));
    $oppStatus = GetCachePiece($gameName, $otherP + 3);
    if (($currentTime - $oppLastTime) > 3000 && (intval($oppStatus) == 0)) {
      WriteLog("Opponent has disconnected. Waiting 60 seconds to reconnect.");
      GamestateUpdated($gameName);
      SetCachePiece($gameName, $otherP + 3, "1");
    } else if (($currentTime - $oppLastTime) > 60000 && $oppStatus == "1") {
      WriteLog("Opponent has left the game.");
      GamestateUpdated($gameName);
      SetCachePiece($gameName, $otherP + 3, "2");
      $lastUpdate = 0;
      $opponentDisconnected = true;
    }
    //Handle server timeout
    $gamestate->lastUpdateTime = GetCachePiece($gameName, 6);
    if ($currentTime - $gamestate->lastUpdateTime > 90000 && GetCachePiece($gameName, 12) != "1") //90 seconds
    {
      SetCachePiece($gameName, 12, "1");
      $opponentInactive = true;
      $lastUpdate = 0;
    }
  }
}

if ($lastUpdate != 0 && $cacheVal <= $lastUpdate) {
  echo "0";
  exit;
} else {
  //First we need to parse the game state from the file
  include "ParseGamestate.php";
  include 'GameLogic.php';
  include "GameTerms.php";
  include "Libraries/UILibraries2.php";
  include "Libraries/StatFunctions.php";
  include "Libraries/PlayerSettings.php";

  $isReactFE = true;

  if ($opponentDisconnected && !IsGameOver()) {
    include_once "./includes/dbh.inc.php";
    include_once "./includes/functions.inc.php";
    PlayerLoseHealth($otherP, GetHealth($otherP));
    include "WriteGamestate.php";
  } else if ($opponentInactive && !IsGameOver()) {
    $gamestate->currentPlayerActivity = 2;
    WriteLog("The current player is inactive.");
    include "WriteGamestate.php";
    GamestateUpdated($gameName);
  }

  if ($gamestate->turn[0] == "REMATCH") {
    include "MenuFiles/ParseGamefile.php";
    include "MenuFiles/WriteGamefile.php";
    if ($gameStatus == $MGS_GameStarted) {
      $origDeck = "./Games/" . $gameName . "/p1DeckOrig.txt";
      if (file_exists($origDeck)) copy($origDeck, "./Games/" . $gameName . "/p1Deck.txt");
      $origDeck = "./Games/" . $gameName . "/p2DeckOrig.txt";
      if (file_exists($origDeck)) copy($origDeck, "./Games/" . $gameName . "/p2Deck.txt");
      $gameStatus = (IsPlayerAI(2) ? $MGS_ReadyToStart : $MGS_ChooseFirstPlayer);
      SetCachePiece($gameName, 14, $gameStatus);
      $gamestate->firstPlayer = 1;
      $firstPlayerChooser = ($gamestate->winner == 1 ? 2 : 1);
      unlink("./Games/" . $gameName . "/gamestate.txt");

      $errorFileName = "./BugReports/CreateGameFailsafe.txt";
      $errorHandler = fopen($errorFileName, "a");
      date_default_timezone_set('America/Chicago');
      $errorDate = date('m/d/Y h:i:s a');
      $errorOutput = "Rematch failsafe hit for game $gameName at $errorDate";
      fwrite($errorHandler, $errorOutput . "\r\n");
      fclose($errorHandler);

      WriteLog("Player $firstPlayerChooser lost and will choose first player for the rematch.");
    }
    WriteGameFile();
    $currentTime = round(microtime(true) * 1000);
    SetCachePiece($gameName, 2, $currentTime);
    SetCachePiece($gameName, 3, $currentTime);
    $response->errorMessage = "1234REMATCH";
    echo (json_encode($response));
    exit;
  }

  $response->lastUpdate = $cacheVal;

  $targetAuth = ($playerID == 1 ? $gamestate->p1Key : $gamestate->p2Key);
  if ($playerID != 3 && $authKey != $targetAuth) {
    $response->errorMessage = "Invalid Authkey";
    echo (json_encode($response));
    exit;
  }

  if (count($gamestate->turn) == 0) {
    RevertGamestate();
    GamestateUpdated($gameName);
    exit();
  }

  // send initial on-load information if our first time connecting.
  if ($lastUpdate == 0) {
    include "MenuFiles/ParseGamefile.php";
    $initialLoad = new stdClass();
    $initialLoad->playerName = $playerID == 1 ? $p1uid : $p2uid;
    $initialLoad->opponentName = $playerID == 1 ? $p2uid : $p1uid;
    $contributors = array("sugitime", "OotTheMonk", "Launch", "LaustinSpayce", "Star_Seraph", "Tower", "Etasus", "scary987", "Celenar");
    $initialLoad->playerIsPatron = ($playerID == 1 ? $p1IsPatron : $p2IsPatron);
    $initialLoad->playerIsContributor = in_array($initialLoad->playerName, $contributors);
    $initialLoad->opponentIsPatron = ($playerID == 1 ? $p2IsPatron : $p1IsPatron);
    $initialLoad->opponentIsContributor = in_array($initialLoad->opponentName, $contributors);
    $initialLoad->roguelikeGameID = $roguelikeGameID;
    $response->initialLoad = $initialLoad;
  }

  $blankZone = 'blankZone';
  $otherPlayer = ($playerID == 1 ? 2 : 1);

  //Choose Cardback
  $MyCardBack = GetCardBack($playerID);
  $TheirCardBack = GetCardBack($otherPlayer);

  $response->MyPlaymat = (IsColorblindMode($playerID) ? 0 : GetPlaymat($playerID));
  $response->TheirPlaymat = (IsColorblindMode($playerID) ? 0 : GetPlaymat($otherPlayer));

  //Display ongoing attack
  $ongoingAttack = new stdClass();
  if(AttackIsOngoing()){
    $borderColor = $action == 21 ? 1 : null;
    
    $ongoingAttack->attackingCard = JSONRenderedCard(
      cardNumber: AttackerCardID(),
      controller: $gamestate->mainPlayer,
      actionDataOverride: '0',
      borderColor: $borderColor,
    );
  }
  $ongoingAttack->attackTarget = CardName(GetMZCard($gamestate->mainPlayer, GetAttackTarget()));
  $ongoingAttack->damagePrevention = GetDamagePrevention($defPlayer);

  //Display layer
  $layerObject = new stdClass;
  $layerContents = array();
  for ($i = count($gamestate->layers) - LayerPieces(); $i >= 0; $i -= LayerPieces()) {
    $layerName = ($gamestate->layers[$i] == "LAYER" || IsAbilityLayer($gamestate->layers[$i]) ? $gamestate->layers[$i + 2] : $gamestate->layers[$i]);
    $layerContents[] = JSONRenderedCard(cardNumber: $layerName, controller: $gamestate->layers[$i+1]);
  }
  $reorderableLayers = array();
  $numReorderable = 0;
  for ($i = count($gamestate->layers) - LayerPieces(); $i >= 0; $i -= LayerPieces()) {
    $layer = new stdClass();
    $layerName = ($gamestate->layers[$i] == "LAYER" || IsAbilityLayer($gamestate->layers[$i]) ? $gamestate->layers[$i + 2] : $gamestate->layers[$i]);
    $layer->card = JSONRenderedCard(cardNumber: $layerName, controller: $gamestate->layers[$i + 1]);
    $layer->layerID = $i;
    $layer->isReorderable = $playerID == $gamestate->mainPlayer && $i <= $gamestate->dqState[8] && ($i > 0 || $numReorderable > 0);
    if($layer->isReorderable) ++$numReorderable;
    $reorderableLayers[] = $layer;
  }
  $target = GetAttackTarget();
  $layerObject->target = $target;
  $layerObject->layerContents = $layerContents;
  $layerObject->reorderableLayers = $reorderableLayers;
  $response->layerDisplay = $layerObject;

  //Opponent Hand
  $handContents = array();
  for ($i = 0; $i < count($theirHand); ++$i) {
    $handContents[] = JSONRenderedCard(cardNumber: $TheirCardBack, controller: ($playerID == 1 ? 2 : 1));
  }
  $response->opponentHand = $handContents;

  //Their Health
  $response->opponentHealth = $theirDamage;
  //Their soul count
  $response->opponentSoulCount = count($theirSoul);

  //Display their discard and deck
  $opponentDiscardArray = array();
  for ($i = 0; $i < count($theirDiscard); $i += DiscardPieces()) {
    $opponentDiscardArray[] = JSONRenderedCard($theirDiscard[$i]);
  }
  $response->opponentDiscard = $opponentDiscardArray;

  $response->opponentDeckCount = count($theirDeck);
  $response->opponentDeckCard = JSONRenderedCard(count($theirDeck) > 0 ? $TheirCardBack : $blankZone);

  //Now display their character and equipment
  $numWeapons = 0;
  $characterContents = array();
  for ($i = 0; $i < count($theirCharacter); $i += CharacterPieces()) {
    if ($i > 0 && $gamestate->inGameStatus == "0") continue;
    $atkCounters = 0;
    $counters = 0;
    $type = CardType($theirCharacter[$i]); //NOTE: This is not reliable type
    $sType = CardSubType($theirCharacter[$i]);
    if ($type == "W") {
      ++$numWeapons;
      if ($numWeapons > 1) {
        $type = "E";
        $sType = "Off-Hand";
      }
    }
    if (CardType($theirCharacter[$i]) == "W") $atkCounters = $theirCharacter[$i + 3];
    if ($theirCharacter[$i + 2] > 0) $counters = $theirCharacter[$i + 2];
    $counters = $theirCharacter[$i + 1] != 0 ? $counters : 0;
    $characterContents[] = JSONRenderedCard(cardNumber: $theirCharacter[$i], overlay: ($theirCharacter[$i+1] != 2 ? 1 : 0), counters: $counters, defCounters: $theirCharacter[$i+4], atkCounters: $atkCounters, controller: $otherPlayer, type: $type, sType: $sType, isFrozen: ($theirCharacter[$i+8] == 1), isAttack: ($theirCharacter[$i+6] == 1), isBroken: ($theirCharacter[$i+1] == 0));
  }
  $response->opponentEquipment = $characterContents;

  // my hand contents
  $restriction = "";
  $actionType = $gamestate->turn[0] == "ARS" ? 4 : 27;
  if (str_contains($gamestate->turn[0], "CHOOSEHAND") && ($gamestate->turn[0] != "MULTICHOOSEHAND" || $gamestate->turn[0] != "MAYMULTICHOOSEHAND")) $actionType = 16;
  $myHandContents = array();
  for ($i = 0; $i < count($myHand); ++$i) {
    if ($playerID == 3) {
      $myHandContents[] = JSONRenderedCard(cardNumber: $MyCardBack, controller: 2);
    } else {
      if ($playerID == $gamestate->currentPlayer) $playable = $gamestate->turn[0] == "ARS" || IsPlayable($myHand[$i], $gamestate->turn[0], "HAND", -1, $restriction) || ($actionType == 16 && str_contains("," . $gamestate->turn[2] . ",", "," . $i . ","));
      else $playable = false;
      $border = CardBorderColor($myHand[$i], "HAND", $playable);
      $actionTypeOut = (($gamestate->currentPlayer == $playerID) && $playable == 1 ? $actionType : 0);
      if ($restriction != "") $restriction = implode("_", explode(" ", $restriction));
      $actionDataOverride = (($actionType == 16 || $actionType == 27) ? strval($i) : $myHand[$i]);
      $myHandContents[] = JSONRenderedCard(cardNumber: $myHand[$i], action: $actionTypeOut, borderColor: $border, actionDataOverride: $actionDataOverride, controller: $playerID, restriction: $restriction);
    }
  }
  $response->playerHand = $myHandContents;

  //My Health
  $response->playerHealth = $myDamage;
  //My soul count
  $response->playerSoulCount = count($mySoul);

  $playerDiscardArr = array();
  for ($i = 0; $i < count($myDiscard); $i += DiscardPieces()) {
    $playerDiscardArr[] = JSONRenderedCard($myDiscard[$i]);
  }
  $response->playerDiscard = $playerDiscardArr;

  $response->playerDeckCount = count($myDeck);
  $response->playerDeckCard = JSONRenderedCard(count($myDeck) > 0 ? $MyCardBack : $blankZone);

  //Now display my character and equipment
  $numWeapons = 0;
  $myCharData = array();
  for ($i = 0; $i < count($myCharacter); $i += CharacterPieces()) {
    $restriction = "";
    $counters = 0;
    $atkCounters = 0;
    if (CardType($myCharacter[$i]) == "W") $atkCounters = $myCharacter[$i + 3];
    if ($myCharacter[$i + 2] > 0) $counters = $myCharacter[$i + 2];
    $playable = $playerID == $gamestate->currentPlayer && $myCharacter[$i + 1] == 2 && IsPlayable($myCharacter[$i], $gamestate->turn[0], "CHAR", $i, $restriction);
    $border = CardBorderColor($myCharacter[$i], "CHAR", $playable);
    $type = CardType($myCharacter[$i]);
    $sType = CardSubType($myCharacter[$i]);
    if ($type == "W") {
      ++$numWeapons;
      if ($numWeapons > 1) {
        $type = "E";
        $sType = "Off-Hand";
      }
    }
    $gem = 0;
    if ($myCharacter[$i + 9] != 2 && $myCharacter[$i + 1] != 0 && $playerID != 3) {
      $gem = ($myCharacter[$i + 9] == 1 ? 1 : 2);
    }
    $restriction = implode("_", explode(" ", $restriction));
    $myCharData[] = JSONRenderedCard($myCharacter[$i], $gamestate->currentPlayer == $playerID && $playable ? 3 : 0, $myCharacter[$i+1] != 2 ? 1 : 0, $border, $myCharacter[$i+1] != 0 ? $counters : 0, strval($i), 0, $myCharacter[$i+4], $atkCounters, $playerID, $type, $sType, $restriction, $myCharacter[$i+1] == 0, $myCharacter[$i+6] == 1, $myCharacter[$i+8] == 1, $gem, numUses: $myCharacter[$i+5]);
  }
  $response->playerEquipment = $myCharData;

  // what's up their arse
  $theirArse = array();
  if ($theirArsenal != "") {
    for ($i = 0; $i < count($theirArsenal); $i += ArsenalPieces()) {
      if ($theirArsenal[$i + 1] == "UP") {
        $theirArse[] = JSONRenderedCard(
          cardNumber: $theirArsenal[$i],
          controller: ($playerID == 1 ? 2 : 1),
          facing: $theirArsenal[$i+1],
          countersMap: (object)["counters" => $theirArsenal[$i+3]]
        );
      } else $theirArse[] = (JSONRenderedCard(
        cardNumber: $TheirCardBack,
        controller: ($playerID == 1 ? 2 : 1),
        facing: $theirArsenal[$i+1],
        countersMap: (object)["counters" => $theirArsenal[$i+3]]
      ));
    }
  }
  $response->opponentArse = $theirArse;

  // what's up my arse
  $myArse = array();
  if ($myArsenal != "") {
    for ($i = 0; $i < count($myArsenal); $i += ArsenalPieces()) {
      if ($playerID == 3 && $myArsenal[$i + 1] != "UP") {
        $myArse[] = JSONRenderedCard(
          cardNumber: $MyCardBack,
          controller: 2,
          facing: $myArsenal[$i+1],
          countersMap: (object)["counters" => $myArsenal[$i+3]]
        );
      } else {
        if ($playerID == $gamestate->currentPlayer) $playable = $gamestate->turn[0] == "ARS" || IsPlayable($myArsenal[$i], $gamestate->turn[0], "ARS", $i, $restriction) || ($actionType == 16 && str_contains("," . $gamestate->turn[2] . ",", "," . $i . ","));
        else $playable = false;
        $border =
          CardBorderColor($myArsenal[$i], "ARS", $playable);
        $actionTypeOut = (($gamestate->currentPlayer == $playerID) && $playable == 1 ? 5 : 0);
        if ($restriction != "") $restriction = implode("_", explode(" ", $restriction));
        $actionDataOverride = (($actionType == 16 || $actionType == 27) ? strval($i) : "");
        $myArse[] = JSONRenderedCard(
          cardNumber: $myArsenal[$i],
          action: $actionTypeOut,
          borderColor: $border,
          actionDataOverride: $actionDataOverride,
          controller: $playerID,
          restriction: $restriction,
          facing: $myArsenal[$i+1],
          countersMap: (object)["counters" => $myArsenal[$i+3]]
        );
      }
    }
  }
  $response->playerArse = $myArse;

  // their allies
  $theirAlliesOutput = array();
  $theirAllies = GetAllies($playerID == 1 ? 2 : 1);
  for ($i = 0; $i + AllyPieces() - 1 < count($theirAllies); $i += AllyPieces()) {
    $type = CardType($theirAllies[$i]);
    $sType = CardSubType($theirAllies[$i]);
    $theirAlliesOutput[] = JSONRenderedCard(cardNumber: $theirAllies[$i], overlay: ($theirAllies[$i+1] != 2 ? 1 : 0), counters: $theirAllies[$i+6], lifeCounters: $theirAllies[$i+2], controller: $otherPlayer, type: $type, sType: $sType, isFrozen: ($theirAllies[$i+3] == 1));
  }
  $response->opponentAllies = $theirAlliesOutput;

  //their auras
  $theirAurasOutput = array();
  for ($i = 0; $i + AuraPieces() - 1 < count($theirAuras); $i += AuraPieces()) {
    $type = CardType($theirAuras[$i]);
    $sType = CardSubType($theirAuras[$i]);
    $theirAurasOutput[] = JSONRenderedCard(cardNumber: $theirAuras[$i], actionDataOverride: strval($i), overlay: ($theirAuras[$i+1] != 2 ? 1 : 0), counters: $theirAuras[$i+2], atkCounters: $theirAuras[$i+3], controller: $otherPlayer, type: $type, sType: $sType, gem: $theirAuras[$i+8]);
  }
  $response->opponentAuras = $theirAurasOutput;

  //their items
  $theirItemsOutput = array();
  for ($i = 0; $i + ItemPieces() - 1 < count($theirItems); $i += ItemPieces()) {
    $type = CardType($theirItems[$i]);
    $sType = CardSubType($theirItems[$i]);
    $theirItemsOutput[] = JSONRenderedCard(cardNumber: $theirItems[$i], actionDataOverride: strval($i), overlay: ($theirItems[$i+2] != 2 ? 1 : 0), counters: $theirItems[$i+1], controller: $otherPlayer, type: $type, sType: $sType, gem: $theirItems[$i+6]);
  }
  $response->opponentItems = $theirItemsOutput;

  //their permanents
  $theirPermanentsOutput = array();
  $theirPermanents = GetPermanents($playerID == 1 ? 2 : 1);
  for ($i = 0; $i + PermanentPieces() - 1 < count($theirPermanents); $i += PermanentPieces()) {
    $type = CardType($theirPermanents[$i]);
    $sType = CardSubType($theirPermanents[$i]);
    $theirPermanentsOutput[] = JSONRenderedCard(cardNumber: $theirPermanents[$i], controller: $otherPlayer, type: $type, sType: $sType);
  }
  $response->opponentPermanents = $theirPermanentsOutput;

  //my allies
  $myAlliesOutput = array();
  $myAllies = GetAllies($playerID == 1 ? 1 : 2);
  for ($i = 0; $i + AllyPieces() - 1 < count($myAllies); $i += AllyPieces()) {
    $type = CardType($myAllies[$i]);
    $sType = CardSubType($myAllies[$i]);
    $playable = IsPlayable($myAllies[$i], $gamestate->turn[0], "PLAY", $i, $restriction) && $myAllies[$i + 1] == 2;
    $actionType = ($gamestate->currentPlayer == $playerID && $gamestate->turn[0] != "P" && $playable) ? 24 : 0;
    $border = CardBorderColor($myAllies[$i], "PLAY", $playable);
    $actionDataOverride = ($actionType == 24 ? strval($i) : "");
    $myAlliesOutput[] = JSONRenderedCard(
      cardNumber: $myAllies[$i],
      action: $actionType,
      overlay: ($myAllies[$i+1] != 2 ? 1 : 0),
      counters: $myAllies[$i+6],
      borderColor: $border,
      actionDataOverride: $actionDataOverride,
      lifeCounters: $myAllies[$i+2],
      controller: $playerID,
      type: $type,
      sType: $sType,
      isFrozen: ($myAllies[$i+3] == 1)
    );
  }
  $response->playerAllies = $myAlliesOutput;

  //my auras
  $auraTileMap = [];
  $myAurasOutput = array();
  for ($i = 0; $i + AuraPieces() - 1 < count($myAuras); $i += AuraPieces()) {
    $playable = ($gamestate->currentPlayer == $playerID && $myAuras[$i+1] == 2 && IsPlayable($myAuras[$i], $gamestate->turn[0], "PLAY", $i, $restriction));
    $border = CardBorderColor($myAuras[$i], "PLAY", $playable);
    $counters = $myAuras[$i + 2];
    $atkCounters = $myAuras[$i + 3];
    $action = $gamestate->currentPlayer == $playerID && $gamestate->turn[0] != "P" && $playable ? 22 : 0;
    $type = CardType($myAuras[$i]);
    $sType = CardSubType($myAuras[$i]);
    $gem = $myAuras[$i + 7];
    if (isset($auraTileMap[$myAuras[$i]])) $gem = $auraTileMap[$myAuras[$i]];
    else $auraTileMap[$myAuras[$i]] = $gem;
    $myAurasOutput[] = JSONRenderedCard(
      cardNumber: $myAuras[$i],
      overlay: ($myAuras[$i+1] != 2 ? 1 : 0),
      counters: $counters,
      atkCounters: $atkCounters,
      action: $action,
      controller: $otherPlayer,
      borderColor: $border,
      type: $type,
      actionDataOverride: strval($i),
      sType: $sType,
      gem: $gem
    );
  }
  $response->playerAuras = $myAurasOutput;

  //my items
  $itemTileMap = [];
  $myItemsOutput = array();
  for ($i = 0; $i + ItemPieces() - 1 < count($myItems); $i += ItemPieces()) {
    $type = CardType($myItems[$i]);
    $sType = CardSubType($myItems[$i]);
    $playable = ($gamestate->currentPlayer == $playerID && IsPlayable($myItems[$i], $gamestate->turn[0], "PLAY", $i, $restriction));
    $border = CardBorderColor($myItems[$i], "PLAY", $playable);
    $actionTypeOut = (($gamestate->currentPlayer == $playerID) && $playable == 1 ? 10 : 0);
    if ($restriction != "") $restriction = implode("_", explode(" ", $restriction));
    $actionDataOverride = strval($i);
    $gem = $myItems[$i + 5];
    if (isset($itemTileMap[$myItems[$i]])) $gem = $itemTileMap[$myItems[$i]];
    else $itemTileMap[$myItems[$i]] = $gem;
    $myItemsOutput[] = JSONRenderedCard(cardNumber: $myItems[$i], action: $actionTypeOut, borderColor: $border, actionDataOverride: $actionDataOverride, overlay: ItemOverlay($myItems[$i], $myItems[$i+2], $myItems[$i+3]), counters: $myItems[$i+1], controller: $otherPlayer, type: $type, sType: $sType, gem: $gem, restriction: $restriction);
  }
  $response->playerItems = $myItemsOutput;

  //my permanents
  $myPermanentsOutput = array();
  $myPermanents = GetPermanents($playerID == 1 ? 1 : 2);
  for ($i = 0; $i + PermanentPieces() - 1 < count($myPermanents); $i += PermanentPieces()) {
    $type = CardType($myPermanents[$i]);
    $sType = CardSubType($myPermanents[$i]);
    $myPermanentsOutput[] = JSONRenderedCard(cardNumber: $myPermanents[$i], controller: $otherPlayer, type: $type, sType: $sType);
  }
  $response->playerPermanents = $myPermanentsOutput;

  //Landmarks
  $landmarksOutput = array();
  for ($i = 0; $i + LandmarkPieces() - 1 < count($landmarks); $i += LandmarkPieces()) {
    $type = CardType($landmarks[$i]);
    $sType = CardSubType($landmarks[$i]);
    $landmarksOutput[] = JSONRenderedCard(cardNumber: $landmarks[$i], type: $type, sType: $sType);
  }
  $response->landmarks = $landmarksOutput;

  // Chat Log
  $response->chatLog = JSONLog($gameName, $playerID);

  // Deduplicate current turn effects
  $playerEffects = array();
  $opponentEffects = array();
  for ($i = 0; $i + CurrentTurnPieces() - 1 < count($gamestate->currentTurnEffects); $i += CurrentTurnPieces()) {
    $cardID = explode("-", $gamestate->currentTurnEffects[$i])[0];
    $cardID = explode(",", $cardID)[0];
    $cardID = explode("_", $cardID)[0];
    if ($playerID == $gamestate->currentTurnEffects[$i + 1] || $playerID == 3 && $otherPlayer != $gamestate->currentTurnEffects[$i + 1]) $playerEffects[] = JSONRenderedCard($cardID);
    else $opponentEffects[] = JSONRenderedCard($cardID);
  }
  $response->opponentEffects = $opponentEffects;
  $response->playerEffects = $playerEffects;

  //Events
  $newEvents = new stdClass();
  $newEvents->eventArray = array();
  for ($i = 0; $i < count($gamestate->events); $i += EventPieces()) {
    $thisEvent = new stdClass();
    $thisEvent->eventType = $gamestate->events[$i];
    $thisEvent->eventValue = $gamestate->events[$i + 1];
    $newEvents->eventArray[] = $thisEvent;
  }
  $response->newEvents = $newEvents;

  // Phase of the turn (for the tracker widget)
  $turnPhase = new stdClass();
  $turnPhase->turnPhase = $gamestate->turn[0];
  if (count($gamestate->layers) > 0) {
    $turnPhase->layer = $gamestate->layers[0];
  }
  $isItMeOrThem = $gamestate->currentPlayer == $playerID ? "Choose " : "Your opponent choosing ";
  $turnPhase->caption = $isItMeOrThem . TypeToPlay($gamestate->turn[0]);
  $response->turnPhase = $turnPhase;

  // Do we have priority?
  $response->havePriority = $gamestate->currentPlayer == $playerID;

  // opponent and player Action Points
  if ($gamestate->mainPlayer == $playerID || ($playerID == 3 && $gamestate->mainPlayer != $otherPlayer)) {
    $response->opponentAP = 0;
    $response->playerAP = $actionPoints;
  } else {
    $response->opponentAP = $actionPoints;
    $response->playerAP = 0;
  }

  // Last played Card
  $response->lastPlayedCard = (count($gamestate->lastPlayed) == 0) ?
    JSONRenderedCard($MyCardBack) :
    JSONRenderedCard($gamestate->lastPlayed[0], controller: $gamestate->lastPlayed[1]);

  // is the player the active player (is it their turn?)
  $response->amIActivePlayer = $gamestate->turn[1] == $playerID;

  //Turn number
  $response->turnNo = $currentRound;

  $playerPrompt = new StdClass();
  $promptButtons = array();
  $helpText = "";
  // Reminder text box highlight thing
  if ($gamestate->turn[0] != "OVER") {
    $helpText .= ($gamestate->currentPlayer != $playerID ? "Waiting for other player to choose " . TypeToPlay($gamestate->turn[0]) : GetPhaseHelptext());

    if ($gamestate->currentPlayer == $playerID) {
      if ($gamestate->turn[0] == "P" || $gamestate->turn[0] == "CHOOSEHANDCANCEL" || $gamestate->turn[0] == "CHOOSEDISCARDCANCEL") {
        $helpText .=  (" ( " . ($gamestate->turn[0] == "P" ? $myResources[0] . " of " . $myResources[1] . " " : "") . ")");
        $promptButtons[] = CreateButtonAPI($playerID, "Cancel", 10000, 0, "16px");
      }
      if (CanPassPhase($gamestate->turn[0])) {
        if ($gamestate->turn[0] == "B") {
          $promptButtons[] = CreateButtonAPI($playerID, "Undo Block", 10001, 0, "16px");
          $promptButtons[] = CreateButtonAPI($playerID, "Pass", 99, 0, "16px");
          $promptButtons[] = CreateButtonAPI($playerID, "Pass Block and Reactions", 101, 0, "16px", "", "Reactions will not be skipped if the opponent reacts");
        }
      }
    } else {
      if (
        $gamestate->currentPlayerActivity == 2 && $playerID != 3
      ) {
        $helpText .= "— Opponent is inactive ";
        $promptButtons[] = CreateButtonAPI($playerID, "Claim Victory", 100007, 0, "16px");
      }
    }
  }
  $playerPrompt->helpText = $helpText;
  $playerPrompt->buttons = $promptButtons;
  $response->playerPrompt = $playerPrompt;

  $response->fullRematchAccepted = $gamestate->turn[0] == "REMATCH";

  // ******************************
  // * PLAYER MUST CHOOSE A THING *
  // ******************************

  $playerInputPopup = new stdClass();
  $playerInputButtons = array();
  $playerInputPopup->active = false;

  // Do arcane separately
  if (($gamestate->turn[0] == "BUTTONINPUT" || $gamestate->turn[0] == "BUTTONINPUTNOPASS" || $gamestate->turn[0] == "CHOOSEARCANE") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $options = explode(",", $gamestate->turn[2]);
    $caption = "";
    if ($gamestate->turn[0] == "CHOOSEARCANE") {
      $vars = explode("-", $gamestate->dqVars[0]);
      $caption .= "Source: " . CardLink($vars[1], $vars[1]) . " Total Damage: " . $vars[0];
    }
    for ($i = 0; $i < count($options); ++$i) {
      $playerInputButtons[] = CreateButtonAPI($playerID, str_replace("_", " ", $options[$i]), 17, strval($options[$i]), "24px");
    }
    $playerInputPopup->popup = CreatePopupAPI("BUTTONINPUT", [], 0, 1, $caption . GetPhaseHelptext(), 1, "");
  }

  if ($gamestate->turn[0] == "YESNO" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputButtons[] = CreateButtonAPI($playerID, "Yes", 20, "YES", "20px");
    $playerInputButtons[] = CreateButtonAPI($playerID, "No", 20, "NO", "20px");
    if (GetDQHelpText() != "-") $caption = implode(" ", explode("_", GetDQHelpText()));
    else $caption = "Choose " . TypeToPlay($gamestate->turn[0]);
    $playerInputPopup->popup = CreatePopupAPI("YESNO", [], 0, 1, $caption, 1, "");
  }

  if ($gamestate->turn[0] == "DYNPITCH" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $options = explode(",", $gamestate->turn[2]);
    for ($i = 0; $i < count($options); ++$i) {
      $playerInputButtons[] = CreateButtonAPI($playerID, $options[$i], 7, $options[$i], "24px");
    }
    $playerInputPopup->popup = CreatePopupAPI("DYNPITCH", [], 0, 1, "Choose " . TypeToPlay($gamestate->turn[0]), 1, "");
  }

  if ($gamestate->turn[0] == "OK" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputButtons[] = CreateButtonAPI($playerID, "Ok", 99, "OK", "20px");
    if (GetDQHelpText() != "-") $caption = implode(" ", explode("_", GetDQHelpText()));
    else $caption = "Choose " . TypeToPlay($gamestate->turn[0]);
    $playerInputPopup->popup = CreatePopupAPI("OK", [], 0, 1, $caption, 1, "");
  }

  if (($gamestate->turn[0] == "OPT" || $gamestate->turn[0] == "CHOOSETOP" || $gamestate->turn[0] == "MAYCHOOSETOP" || $gamestate->turn[0] == "CHOOSEBOTTOM" || $gamestate->turn[0] == "CHOOSECARD" || $gamestate->turn[0] == "MAYCHOOSECARD") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $options = explode(",", $gamestate->turn[2]);
    $optCards = array();
    for ($i = 0; $i < count($options); ++$i) {
      $optCards[] = JSONRenderedCard($options[$i], action: 0);
      if (
        $gamestate->turn[0] == "CHOOSETOP" || $gamestate->turn[0] == "MAYCHOOSETOP" || $gamestate->turn[0] == "OPT"
      ) $playerInputButtons[] = CreateButtonAPI($playerID, "Top", 8, $options[$i], "20px");
      if ($gamestate->turn[0] == "CHOOSEBOTTOM" || $gamestate->turn[0] == "OPT") $playerInputButtons[] = CreateButtonAPI($playerID, "Bottom", 9, $options[$i], "20px");
      if ($gamestate->turn[0] == "CHOOSECARD" || $gamestate->turn[0] == "MAYCHOOSECARD") $playerInputButtons[] = CreateButtonAPI($playerID, "Choose", 23, $options[$i], "20px");
    }
    $playerInputPopup->popup = CreatePopupAPI("OPT", [], 0, 1, "Choose " . TypeToPlay($gamestate->turn[0]), 1, "", cardsArray: $optCards);
  }

  if (($gamestate->turn[0] == "CHOOSETOPOPPONENT") && $gamestate->turn[1] == $playerID
  ) { //Use when you have to reorder the top of your opponent library e.g. Righteous Cleansing
    $playerInputPopup->active = true;
    $otherPlayer = ($playerID == 1 ? 2 : 1);
    $options = explode(",", $gamestate->turn[2]);
    $optCards = array();
    for ($i = 0; $i < count($options); ++$i) {
      $optCards[] = JSONRenderedCard($options[$i], action: 0);
      if (
        $gamestate->turn[0] == "CHOOSETOPOPPONENT"
      ) $playerInputButtons[] = CreateButtonAPI($otherPlayer, "Top", 29, $options[$i], "20px");
    }
    $playerInputPopup->popup = CreatePopupAPI("CHOOSETOPOPPONENT", [], 0, 1, "Choose " . TypeToPlay($gamestate->turn[0]), 1, "", cardsArray: $optCards);
  }

  if ($gamestate->turn[0] == "INPUTCARDNAME" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = CreatePopupAPI("INPUTCARDNAME", [], 0, 1, "Name a card");
  }

  if ($gamestate->turn[0] == "HANDTOPBOTTOM" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $cardsArray = array();
    for ($i = 0; $i < count($myHand); ++$i) {
      $cardsArray[] = JSONRenderedCard($myHand[$i], action: 0);
    }
    for ($i = 0; $i < count($myHand); ++$i) {
      $playerInputButtons[] = CreateButtonAPI($playerID, "Top", 12, $i, "20px");
      $playerInputButtons[] = CreateButtonAPI($playerID, "Bottom", 13, $i, "20px");
    }
    $playerInputPopup->popup = CreatePopupAPI("HANDTOPBOTTOM", [], 0, 1, "Choose " . TypeToPlay($gamestate->turn[0]), 1, "", cardsArray: $cardsArray);
  }

  if (($gamestate->turn[0] == "MAYCHOOSEMULTIZONE" || $gamestate->turn[0] == "CHOOSEMULTIZONE") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $options = explode(",", $gamestate->turn[2]);
    $otherPlayer = $playerID == 2 ? 1 : 2;
    $theirAllies = &GetAllies($otherPlayer);
    $myAllies = &GetAllies($playerID);
    $cardsMultiZone = array();
    for ($i = 0; $i < count($options); ++$i) {
      $option = explode("-", $options[$i]);
      if ($option[0] == "MYAURAS") $source = $myAuras;
      else if ($option[0] == "THEIRAURAS") $source = $theirAuras;
      else if ($option[0] == "MYCHAR") $source = $myCharacter;
      else if ($option[0] == "THEIRCHAR") $source = $theirCharacter;
      else if ($option[0] == "MYITEMS") $source = $myItems;
      else if ($option[0] == "THEIRITEMS") $source = $theirItems;
      else if ($option[0] == "LAYER") $source = $gamestate->layers;
      else if ($option[0] == "MYHAND") $source = $myHand;
      else if ($option[0] == "THEIRHAND") $source = $theirHand;
      else if ($option[0] == "MYDISCARD") $source = $myDiscard;
      else if ($option[0] == "THEIRDISCARD") $source = $theirDiscard;
      else if ($option[0] == "MYALLY") $source = $myAllies;
      else if ($option[0] == "THEIRALLY") $source = $theirAllies;
      else if ($option[0] == "MYARS") $source = $myArsenal;
      else if ($option[0] == "THEIRARS") $source = $theirArsenal;
      else if ($option[0] == "MYDECK") $source = $myDeck;
      else if ($option[0] == "THEIRDECK") $source = $theirDeck;
      else if ($option[0] == "MYMATERIAL") $source = $myMaterial;
      else if ($option[0] == "THEIRMATERIAL") $source = $theirMaterial;
      else if ($option[0] == "MYRESOURCES") $source = &GetMemory($playerID);
      else if ($option[0] == "THEIRRESOURCES") $source = &GetMemory($playerID == 1 ? 2 : 1);
      else if ($option[0] == "LANDMARK") $source = $landmarks;

      $counters = 0;
      $lifeCounters = 0;
      $enduranceCounters = 0;
      $atkCounters = 0;
      $label = "";

      if (($option[0] == "MYALLY" || $option[0] == "THEIRALLY" || $option[0] == "THEIRAURAS") && $option[1] == $gamestate->attackState[$AS_AttackerIndex]) {
        $label = "Attacker";
      }

      if (count($gamestate->layers) > 0) {
        if ($option[0] == "THEIRALLY" && $gamestate->layers[0] != "" && $gamestate->mainPlayer != $gamestate->currentPlayer) {
          $index = SearchLayer($otherPlayer, subtype: "Ally");
          if ($index != "") {
            $params = explode("|", $gamestate->layers[$index + 2]);
            if ($option[1] == $params[2]) $label = "Attacker";
          }
        }
        if ($option[0] == "THEIRAURAS" && $gamestate->layers[0] != "" && $gamestate->mainPlayer != $gamestate->currentPlayer) {
          $index = SearchLayer($otherPlayer, subtype: "Aura");
          if ($index != "") {
            $params = explode("|", $gamestate->layers[$index + 2]);
            if ($option[1] == $params[2]) $label = "Attacker";
          }
        }
      }

      //Add indication for Crown of Providence if you have the same card in hand and in the arsenal.
      if ($option[0] == "MYARS") $label = "Arsenal";

      $index = intval($option[1]);
      $card = $source[$index];
      if ($option[0] == "LAYER" && (IsAbilityLayer($card))) $card = $source[$index + 2];
      $playerBorderColor = 0;

      if (str_starts_with($option[0], "MY")) $playerBorderColor = 1;
      else if (str_starts_with($option[0], "THEIR")) $playerBorderColor = 2;
      else if ($option[0] == "LAYER") {
        $playerBorderColor = ($gamestate->layers[$index + 1] == $playerID ? 1 : 2);
      }

      if ($option[0] == "THEIRARS" && $theirArsenal[$index + 1] == "DOWN") $card = $TheirCardBack;

      //Show Life and Def counters on allies in the popups
      if ($option[0] == "THEIRALLY") {
        $lifeCounters = $theirAllies[$index + 2];
        $enduranceCounters = $theirAllies[$index + 6];
        if (SearchCurrentTurnEffectsForUniqueID($theirAllies[$index + 5]) != -1) $attackCounters = EffectAttackModifier(SearchUniqueIDForCurrentTurnEffects($theirAllies[$index + 5])) + AttackValue($theirAllies[$index]);
        else $attackCounters = 0;
      } elseif ($option[0] == "MYALLY") {
        $lifeCounters = $myAllies[$index + 2];
        $enduranceCounters = $myAllies[$index + 6];
        if (SearchCurrentTurnEffectsForUniqueID($myAllies[$index + 5]) != -1) $attackCounters = EffectAttackModifier(SearchUniqueIDForCurrentTurnEffects($myAllies[$index + 5])) + AttackValue($myAllies[$index]);
        else $attackCounters = 0;
      }

      //Show Atk counters on Auras in the popups
      if ($option[0] == "THEIRAURAS") {
        $atkCounters = $theirAuras[$index + 3];
      } elseif ($option[0] == "MYAURAS") {
        $atkCounters = $myAuras[$index + 3];
      }
      $cardsMultiZone[] = JSONRenderedCard($card, action: 16, overlay: 0, borderColor: $playerBorderColor, counters: $counters, actionDataOverride: $options[$i], lifeCounters: $lifeCounters, defCounters: $enduranceCounters, atkCounters: $atkCounters, controller: $playerBorderColor, label: $label);
    }
    $playerInputPopup->popup = CreatePopupAPI("CHOOSEMULTIZONE", [], 0, 1, GetPhaseHelptext(), 1, cardsArray: $cardsMultiZone);
  }

  if (($gamestate->turn[0] == "MAYCHOOSEDECK" || $gamestate->turn[0] == "CHOOSEDECK") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($myDeck, $gamestate->turn[2], 11, "Choose a card from your deck");
  }

  if (($gamestate->turn[0] == "CHOOSETHEIRHAND") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($theirHand, $gamestate->turn[2], 16, "Choose a card from your opponent's hand");
  }

  if (($gamestate->turn[0] == "CHOOSEDISCARD" || $gamestate->turn[0] == "MAYCHOOSEDISCARD" || $gamestate->turn[0] == "CHOOSEDISCARDCANCEL") && $gamestate->turn[1] == $playerID) {
    $caption = "Choose a card from your discard";
    if (GetDQHelpText() != "-") $caption = implode(" ", explode("_", GetDQHelpText()));
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($myDiscard, $gamestate->turn[2], 16, $caption);
  }

  if (($gamestate->turn[0] == "MAYCHOOSETHEIRDISCARD") && $gamestate->turn[1] == $playerID
  ) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($theirDiscard, $gamestate->turn[2], 16, "Choose a card from your opponent's graveyard");
  }

  if ($gamestate->turn[0] == "CHOOSECHARACTER" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($myCharacter, $gamestate->turn[2], 16, "Choose a card from your character/equipment", CharacterPieces());
  }

  if ($gamestate->turn[0] == "CHOOSETHEIRCHARACTER" && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($theirCharacter, $gamestate->turn[2], 16, "Choose a card from your opponent character/equipment", CharacterPieces());
  }

  if (($gamestate->turn[0] == "MULTICHOOSETHEIRDISCARD" || $gamestate->turn[0] == "MULTICHOOSEDISCARD" || $gamestate->turn[0] == "MULTICHOOSEHAND" || $gamestate->turn[0] == "MAYMULTICHOOSEHAND" || $gamestate->turn[0] == "MULTICHOOSEUNIT" || $gamestate->turn[0] == "MULTICHOOSETHEIRUNIT" || $gamestate->turn[0] == "MULTICHOOSEDECK" || $gamestate->turn[0] == "MULTICHOOSETEXT" || $gamestate->turn[0] == "MAYMULTICHOOSETEXT" || $gamestate->turn[0] == "MULTICHOOSETHEIRDECK" || $gamestate->turn[0] == "MAYMULTICHOOSEAURAS") && $gamestate->currentPlayer == $playerID) {
    $playerInputPopup->active = true;
    $formOptions = new stdClass();
    $cardsArray = array();

    $content = "";
    $params = explode("-", $gamestate->turn[2]);
    $options = explode(",", $params[1]);

    $title = "Choose up to " . $params[0] . " card" . ($params[0] > 1 ? "s." : ".");
    if (GetDQHelpText() != "-") $caption = implode(" ", explode("_", GetDQHelpText()));

    $formOptions->playerID = $playerID;
    $formOptions->caption = "Submit";
    $formOptions->mode = 19;
    $formOptions->maxNo = count($options);
    $playerInputPopup->formOptions = $formOptions;

    $choiceOptions = "checkbox";
    $playerInputPopup->choiceOptions = $choiceOptions;

    if ($gamestate->turn[0] == "MULTICHOOSETEXT" || $gamestate->turn[0] == "MAYMULTICHOOSETEXT") {
      $multiChooseText = array();

      for ($i = 0; $i < count($options); ++$i) {
        $multiChooseText[] = CreateCheckboxAPI($i, $i, -1, false, implode(" ", explode("_", strval($options[$i]))));
      }
      $caption = "Choose up to $params[0] card" . ($params[0] > 1 ? "s." : ".");
      $playerInputPopup->popup =  CreatePopupAPI("MULTICHOOSE", [], 0, 1, $caption, 1, $content);
      $playerInputPopup->multiChooseText = $multiChooseText;
    } else {
      for ($i = 0; $i < count($options); ++$i) {
        if ($gamestate->turn[0] == "MULTICHOOSEDISCARD") $cardsArray[] = JSONRenderedCard($myDiscard[$options[$i]], actionDataOverride: $i);
        else if ($gamestate->turn[0] == "MULTICHOOSETHEIRDISCARD") $cardsArray[] = JSONRenderedCard($theirDiscard[$options[$i]], actionDataOverride: $i);
        else if ($gamestate->turn[0] == "MULTICHOOSEHAND" || $gamestate->turn[0] == "MAYMULTICHOOSEHAND") $cardsArray[] = JSONRenderedCard($myHand[$options[$i]], actionDataOverride: $i);
        else if ($gamestate->turn[0] == "MULTICHOOSEDECK") $cardsArray[] = JSONRenderedCard($myDeck[$options[$i]], actionDataOverride: $i);
        else if ($gamestate->turn[0] == "MULTICHOOSETHEIRDECK") $cardsArray[] = JSONRenderedCard($theirDeck[$options[$i]], actionDataOverride: $i);
        else if ($gamestate->turn[0] == "MAYMULTICHOOSEAURAS") $cardsArray[] = JSONRenderedCard($myAuras[$options[$i]], actionDataOverride: $i);
      }
      $caption = "Choose up to $params[0] card" . ($params[0] > 1 ? "s." : ".");
      $playerInputPopup->popup = CreatePopupAPI("MULTICHOOSE", [], 0, 1, $caption, 1, cardsArray: $cardsArray);
    }
  }

  if (($gamestate->turn[0] == "CHOOSEMYSOUL" || $gamestate->turn[0] == "MAYCHOOSEMYSOUL") && $gamestate->turn[1] == $playerID) {
    $playerInputPopup->active = true;
    $playerInputPopup->popup = ChoosePopup($mySoul, $gamestate->turn[2], 16, "Choose one of your soul", SoulPieces());
  }

  $playerInputPopup->buttons = $playerInputButtons;
  $response->playerInputPopUp = $playerInputPopup;
  $response->canPassPhase = (CanPassPhase($gamestate->turn[0]) && $gamestate->currentPlayer == $playerID) || (IsReplay() && $playerID == 3);
  // encode and send it out
  echo json_encode($response);
  exit;
}

function PlayableCardBorderColor($cardID)
{
  if (HasReprise($cardID) && RepriseActive()) return 3;
  return 0;
}

function ChoosePopup($zone, $options, $mode, $caption = "", $zoneSize = 1)
{
  $options = explode(",", $options);
  $cardList = array();

  for ($i = 0; $i < count($options); ++$i) {
    $cardList[] = JSONRenderedCard($zone[$options[$i]], action: $mode, actionDataOverride: strval($options[$i]));
  }

  return CreatePopupAPI("CHOOSEZONE", [], 0, 1, $caption, 1, "", cardsArray: $cardList);
}

function ItemOverlay($item, $isReady, $numUses)
{
  if ($item == "EVR070" && $numUses < 3) return 1;
  return ($isReady != 2 ? 1 : 0);
}

function GetCharacterLeft($cardType, $cardSubType)
{
  global $cardWidth;
  switch ($cardType) {
    case "C":
      return "calc(50% - " . ($cardWidth / 2 + 5) . "px)";
      //case "W": return "calc(50% " . ($cardSubType == "" ? "- " : "+ ") . ($cardWidth/2 + $cardWidth + 10) . "px)";
    case "W":
      return "calc(50% - " . ($cardWidth / 2 + $cardWidth + 25) . "px)";
    default:
      break;
  }
  switch ($cardSubType) {
    case "Head":
      return "95px";
    case "Chest":
      return "95px";
    case "Arms":
      return ($cardWidth + 115) . "px";
    case "Legs":
      return "95px";
    case "Off-Hand":
      return "calc(50% + " . ($cardWidth / 2 + 15) . "px)";
  }
}

function GetCharacterBottom($cardType, $cardSubType)
{
  global $cardSize;
  switch ($cardType) {
    case "C":
      return ($cardSize * 2 - 25) . "px";
    case "W":
      return ($cardSize * 2 - 25) . "px";
    default:
      break;
  }
  switch ($cardSubType) {
    case "Head":
      return ($cardSize * 2 - 25) . "px";
    case "Chest":
      return ($cardSize - 10) . "px";
    case "Arms":
      return ($cardSize - 10) . "px";
    case "Legs":
      return "5px";
    case "Off-Hand":
      return ($cardSize * 2 - 25) . "px";
  }
}

function GetCharacterTop($cardType, $cardSubType)
{
  global $cardSize;
  switch ($cardType) {
    case "C":
      return "52px";
    case "W":
      return "52px";
      //case "C": return ($cardSize + 20) . "px";
      //case "W": return ($cardSize + 20) . "px";
    default:
      break;
  }
  switch ($cardSubType) {
    case "Head":
      return "5px";
    case "Chest":
      return ($cardSize - 10) . "px";
    case "Arms":
      return ($cardSize - 10) . "px";
    case "Legs":
      return ($cardSize * 2 - 25) . "px";
    case "Off-Hand":
      return "52px";
  }
}

function GetZoneRight($zone)
{
  global $cardWidth, $rightSideWidth;
  switch ($zone) {
    case "DISCARD":
      return intval($rightSideWidth * 1.08) . "px";
    case "DECK":
      return intval($rightSideWidth * 1.08) . "px";
  }
}

function GetZoneBottom($zone)
{
  global $cardSize;
  switch ($zone) {
    case "MYDISCARD":
      return ($cardSize * 2 - 25) . "px";
    case "MYDECK":
      return ($cardSize - 10) . "px";
  }
}

function GetZoneTop($zone)
{
  global $cardSize;
  switch ($zone) {
    case "THEIRDISCARD":
      return ($cardSize * 2 - 25) . "px";
    case "THEIRDECK":
      return ($cardSize - 10) . "px";
  }
}

function GetPhaseHelptext()
{
  global $gamestate;
  $defaultText = "Choose " . TypeToPlay($gamestate->turn[0]);
  return (GetDQHelpText() != "-" ? implode(" ", explode("_", GetDQHelpText())) : $defaultText);
}
