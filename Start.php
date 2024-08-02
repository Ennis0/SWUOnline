<?php

ob_start();
include "HostFiles/Redirector.php";
include "Libraries/HTTPLibraries.php";
include "Libraries/SHMOPLibraries.php";
include "Libraries/NetworkingLibraries.php";
include "GameLogic.php";
include "GameTerms.php";
include "Libraries/StatFunctions.php";
include "Libraries/PlayerSettings.php";
include "Libraries/UILibraries2.php";
include_once "./includes/dbh.inc.php";
include_once "./includes/functions.inc.php";
include_once "./MenuFiles/StartHelper.php";
ob_end_clean();

$gameName = $_GET["gameName"];
if (!IsGameNameValid($gameName)) {
  echo ("Invalid game name.");
  exit;
}
$playerID = $_GET["playerID"];
if(!isset($authKey)) $authKey = TryGet("authKey", "");

if (!file_exists("./Games/" . $gameName . "/GameFile.txt")) exit;

ob_start();
include "MenuFiles/ParseGamefile.php";
include "MenuFiles/WriteGamefile.php";
ob_end_clean();
session_start();
if($playerID == 1 && isset($_SESSION["p1AuthKey"])) { $targetKey = $gamestate->p1Key; $authKey = $_SESSION["p1AuthKey"]; }
else if($playerID == 2 && isset($_SESSION["p2AuthKey"])) { $targetKey = $gamestate->p2Key; $authKey = $_SESSION["p2AuthKey"]; }
if ($authKey != $targetKey) { echo("Invalid auth key"); exit; }

//First initialize the initial state of the game
$filename = "./Games/" . $gameName . "/gamestate.txt";
$handler = fopen($filename, "w");

//Player 1
$p1DeckHandler = fopen("./Games/" . $gameName . "/p1Deck.txt", "r");
initializePlayerState($p1DeckHandler, 1);
fclose($p1DeckHandler);

//Player 2
$p2DeckHandler = fopen("./Games/" . $gameName . "/p2Deck.txt", "r");
initializePlayerState($p2DeckHandler, 2);
fclose($p2DeckHandler);

$gamestate->playerDamageValues = [0, 0];
$gamestate->winner = 0;
//$gamestate->firstPlayer = $firstPlayer;
$gamestate->currentPlayer = 1;
$gamestate->currentRound = 1;
$gamestate->turn = ["M", 1];
$gamestate->dqVars = ["0"];
$gamestate->dqState = ["0", "-", "-", "-"];
$gamestate->mainPlayer = 1;
$gamestate->permanentUniqueIDCounter = 0;
$gamestate->inGameStatus = 0;
$gamestate->p1TotalTime = 0;
$gamestate->p2TotalTime = 0;
$gamestate->lastUpdateTime = time();
$gamestate->EffectContext = "-";

fwrite($handler, serialize($gamestate));
fclose($handler);

//Set up log file
$filename = "./Games/" . $gameName . "/gamelog.txt";
$handler = fopen($filename, "w");
fclose($handler);

$currentTime = strval(round(microtime(true) * 1000));
$currentUpdate = GetCachePiece($gameName, 1);
$p1Hero = GetCachePiece($gameName, 7);
$p2Hero = GetCachePiece($gameName, 8);
$visibility = GetCachePiece($gameName, 9);
$format = GetCachePiece($gameName, 13);
$gamestate->currentPlayer = 0;
$isReplay = 0;
WriteCache($gameName, ($currentUpdate + 1) . "!" . $currentTime . "!" . $currentTime . "!-1!-1!" . $currentTime . "!"  . $p1Hero . "!" . $p2Hero . "!" . $visibility . "!" . $isReplay . "!0!0!" . $format . "!" . $MGS_GameStarted); //Initialize SHMOP cache for this game

ob_start();
include "ParseGamestate.php"; //Possibly don't need this here anymore because we kept the data saved in $gamestate?
include "StartEffects.php";
ob_end_clean();
//Update the game file to show that the game has started and other players can join to spectate
$gameStatus = $MGS_GameStarted;
WriteGameFile();

if(isset($gameUIPath)) header("Location: " . $gameUIPath . "?gameName=$gameName&playerID=$playerID");
else header("Location: " . $redirectPath . "/NextTurn4.php?gameName=$gameName&playerID=$playerID");

exit;


?>
