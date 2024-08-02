<?php
function ProcessInput($playerID, $mode, $buttonInput, $cardID, $chkCount, $chkInput, $isSimulation=false, $inputText="")
{
  global $gameName, $gamestate, $CS_CharacterIndex, $CS_PlayIndex, $CS_NextNAAInstant, $skipWriteGamestate;
  global $SET_PassDRStep, $redirectPath;
  switch ($mode) {
    case 3: //Play equipment ability
      MakeGamestateBackup();
      $index = $cardID;
      $found = -1;
      $character = &GetPlayerCharacter($playerID);
      $cardID = $character[$index];
      if ($index != -1 && IsPlayable($character[$index], $gamestate->turn[0], "CHAR", $index)) {
        SetClassState($playerID, $CS_CharacterIndex, $index);
        SetClassState($playerID, $CS_PlayIndex, $index);
        PlayCard($cardID, "EQUIP", -1, $index);
      }
      else
      {
        echo("Play equipment ability " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 4: //Add something to your arsenal
      $found = HasCard($cardID);
      if ($gamestate->turn[0] == "ARS" && $found >= 0) {
        $hand = &GetHand($playerID);
        unset($hand[$found]);
        $hand = array_values($hand);
        AddArsenal($cardID, $gamestate->currentPlayer, "HAND", "DOWN");
        PassTurn();
      }
      else
      {
        echo($cardID . " " . $gamestate->turn[0] . "<BR>");
        echo("Add to arsenal " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 5: //Card Played from resources
      $index = $cardID;
      $arsenal = &GetArsenal($playerID);
      if ($index < count($arsenal)) {
        $cardToPlay = $arsenal[$index];
        if (!IsPlayable($cardToPlay, $gamestate->turn[0], "RESOURCES", $index)) break;
        $isExhausted = $arsenal[$index + 4] == 1;
        $uniqueID = $arsenal[$index + 5];
        RemoveArsenal($playerID, $index);
        AddTopDeckAsResource($playerID, isExhausted:$isExhausted);
        PlayCard($cardToPlay, "RESOURCES", -1, -1, $uniqueID);
      }
      else
      {
        echo("Play from arsenal " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 6: //Deprecated
      break;
    case 7: //Number input
      if ($gamestate->turn[0] == "DYNPITCH") {
        ContinueDecisionQueue($buttonInput);
      }
      else
      {
        echo("Number input " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 8:
    case 9: //OPT, CHOOSETOP, CHOOSEBOTTOM
      if ($gamestate->turn[0] == "OPT" || $gamestate->turn[0] == "CHOOSETOP" || $gamestate->turn[0] == "MAYCHOOSETOP" || $gamestate->turn[0] == "CHOOSEBOTTOM") {
        $options = explode(",", $gamestate->turn[2]);
        $found = -1;
        for ($i = 0; $i < count($options); ++$i) {
          if ($options[$i] == $buttonInput) {
            $found = $i;
            break;
          }
        }
        if ($found == -1) break; //Invalid input
        $deck = &GetDeck($playerID);
        if ($mode == 8) {
          array_unshift($deck, $buttonInput);
          WriteLog("Player " . $playerID . " put a card on top of the deck.");
        } else if ($mode == 9) {
          $deck[] = $buttonInput;
          WriteLog("Player " . $playerID . " put a card on the bottom of the deck.");
        }
        unset($options[$found]);
        $options = array_values($options);
        $options = implode(",", $options);
        $gamestate->dqVars[0] = $options;
        if ($options != "") {
          PrependDecisionQueue($gamestate->turn[0], $gamestate->currentPlayer, $options);
        }
        ContinueDecisionQueue($buttonInput);
      }
      else
      {
        echo("Opt " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 10: //Item ability
      $index = $cardID; //Overridden to be index instead
      $items = &GetItems($playerID);
      if ($index >= count($items)) break; //Item doesn't exist
      $cardID = $items[$index];
      if (!IsPlayable($cardID, $gamestate->turn[0], "PLAY", $index)) break; //Item not playable
      --$items[$index + 3];
      SetClassState($playerID, $CS_PlayIndex, $index);
      PlayCard($cardID, "PLAY", -1, $index, $items[$index + 4]);
      break;
    case 11: //CHOOSEDECK
      if ($gamestate->turn[0] == "CHOOSEDECK" || $gamestate->turn[0] == "MAYCHOOSEDECK") {
        $deck = &GetDeck($playerID);
        $index = $cardID;
        $cardID = $deck[$index];
        unset($deck[$index]);
        $deck = array_values($deck);
        ContinueDecisionQueue($cardID);
      }
      break;
    case 12: //HANDTOP
      if ($gamestate->turn[0] == "HANDTOPBOTTOM") {
        $hand = &GetHand($playerID);
        $deck = &GetDeck($playerID);
        $cardID = $hand[$buttonInput];
        array_unshift($deck, $cardID);
        unset($hand[$buttonInput]);
        $hand = array_values($hand);
        ContinueDecisionQueue($cardID);
        WriteLog("Player " . $playerID . " put a card on the top of the deck.");
      }
      break;
    case 13: //HANDBOTTOM
      if ($gamestate->turn[0] == "HANDTOPBOTTOM") {
        $hand = &GetHand($playerID);
        $deck = &GetDeck($playerID);
        $cardID = $hand[$buttonInput];
        $deck[] = $cardID;
        unset($hand[$buttonInput]);
        $hand = array_values($hand);
        ContinueDecisionQueue($cardID);
        WriteLog("Player " . $playerID . " put a card on the bottom of the deck.");
      }
      break;
    case 16: case 18: //Decision Queue (15 and 18 deprecated)
      if(count($gamestate->decisionQueue) > 0)
      {
        $index = $cardID;
        $isValid = false;
        $validInputs = explode(",", $gamestate->turn[2]);
        for($i=0; $i<count($validInputs); ++$i)
        {
          if($validInputs[$i] == $index) $isValid = true;
        }
        if($isValid) ContinueDecisionQueue($index);
      }
      break;
    case 17: //BUTTONINPUT
      if (($gamestate->turn[0] == "BUTTONINPUT" || $gamestate->turn[0] == "CHOOSEARCANE" || $gamestate->turn[0] == "BUTTONINPUTNOPASS" || $gamestate->turn[0] == "CHOOSEFIRSTPLAYER")) {
        ContinueDecisionQueue($buttonInput);
      }
      break;
    case 19: //MULTICHOOSE X
      if (!str_starts_with($gamestate->turn[0], "MULTICHOOSE") && !str_starts_with($gamestate->turn[0], "MAYMULTICHOOSE")) break;
      $params = explode("-", $gamestate->turn[2]);
      $maxSelect = intval($params[0]);
      $options = explode(",", $params[1]);
      if(count($params) > 2) $minSelect = intval($params[2]);
      else $minSelect = -1;
      if (count($chkInput) > $maxSelect) {
        WriteLog("You selected " . count($chkInput) . " items, but a maximum of " . $maxSelect . " is allowed. Reverting gamestate prior to that effect.");
        RevertGamestate();
        $skipWriteGamestate = true;
        break;
      }
      if ($minSelect != -1 && count($chkInput) < $minSelect && count($chkInput) < count($options)) {
        WriteLog("You selected " . count($chkInput) . " items, but a minimum of " . $minSelect . " is requested. Reverting gamestate prior to that effect.");
        RevertGamestate();
        $skipWriteGamestate = true;
        break;
      }
      $input = [];
      for ($i = 0; $i < count($chkInput); ++$i) {
        if ($chkInput[$i] < 0 || $chkInput[$i] >= count($options)) {
          WriteLog("You selected option " . $chkInput[$i] . " but that was not one of the original options. Reverting gamestate prior to that effect.");
          RevertGamestate();
          $skipWriteGamestate = true;
          break;
        }
        else {
          $input[] = $options[$chkInput[$i]];
        }
      }
      if (!$skipWriteGamestate) {
        ContinueDecisionQueue($input);
      }
      break;
    case 20: //YESNO
      if ($gamestate->turn[0] == "YESNO" && ($buttonInput == "YES" || $buttonInput == "NO")) ContinueDecisionQueue($buttonInput);
      break;
    case 22: //Aura ability
      $index = $cardID; //Overridden to be index instead
      $auras = &GetAuras($playerID);
      if ($index >= count($auras)) break; //Item doesn't exist
      $cardID = $auras[$index];
      if (!IsPlayable($cardID, $gamestate->turn[0], "PLAY", $index)) break; //Aura ability not playable
      $auras[$index + 1] = 1; //Set status to used - for now
      SetClassState($playerID, $CS_PlayIndex, $index);
      PlayCard($cardID, "PLAY", -1, $index, $auras[$index+6]);
      break;
    case 23: //CHOOSECARD
      if ($gamestate->turn[0] == "CHOOSECARD" || $gamestate->turn[0] == "MAYCHOOSECARD") {
        $options = explode(",", $gamestate->turn[2]);
        $found = -1;
        for ($i = 0; $i < count($options); ++$i) {
          if ($options[$i] == $buttonInput) {
            $found = $i;
            break;
          }
        }
        if ($found == -1) break; //Invalid input
        unset($options[$found]);
        $options = array_values($options);
        ContinueDecisionQueue($buttonInput);
      }
      break;
    case 24: //Ally Ability
      MakeGamestateBackup();
      $allies = &GetAllies($gamestate->currentPlayer);
      $index = $cardID; //Overridden to be index instead
      if ($index >= count($allies)) break; //Ally doesn't exist
      $cardID = $allies[$index];
      if (!IsPlayable($cardID, $gamestate->turn[0], "PLAY", $index)) break; //Ally not playable
      $abilityNames = GetAbilityNames($allies[$index], $index);
      if($abilityNames == "" || SearchCount($abilityNames) == 1) $allies[$index + 1] = 1;
      SetClassState($playerID, $CS_PlayIndex, $index);
      PlayCard($cardID, "PLAY", -1, $index, $allies[$index+5]);
      break;
    case 25: //Landmark Ability
      $index = $cardID;
      if ($index >= count($landmarks)) break; //Landmark doesn't exist
      $cardID = $landmarks[$index];
      if (!IsPlayable($cardID, $gamestate->turn[0], "PLAY", $index)) break; //Landmark not playable
      SetClassState($playerID, $CS_PlayIndex, $index);
      PlayCard($cardID, "PLAY", -1);
      break;
    case 26: //Change setting
      $userID = "";
      if(!$isSimulation)
      {
        include "MenuFiles/ParseGamefile.php";
        include_once "./includes/dbh.inc.php";
        include_once "./includes/functions.inc.php";
        if($playerID == 1) $userID = $p1id;
        else $userID = $p2id;
      }
      $params = explode("-", $buttonInput);
      ChangeSetting($playerID, $params[0], $params[1], $userID);
      break;
    case 27: //Play card from hand by index
      MakeGamestateBackup();
      $found = $cardID;
      if ($found >= 0) {
        //Player actually has the card, now do the effect
        //First remove it from their hand
        $hand = &GetHand($playerID);
        if($found >= count($hand)) break;
        $cardID = $hand[$found];
        if(!IsPlayable($cardID, $gamestate->turn[0], "HAND", $found)) break;
        unset($hand[$found]);
        $hand = array_values($hand);
        PlayCard($cardID, "HAND");
      }
      break;
    case 29: //CHOOSETOPOPPONENT
      if($gamestate->turn[0] == "CHOOSETOPOPPONENT") {
        $otherPlayer = ($playerID == 1 ? 2 : 1);
        $options = explode(",", $gamestate->turn[2]);
        $found = -1;
        for ($i = 0; $i < count($options); ++$i) {
          if ($options[$i] == $buttonInput) {
            $found = $i;
            break;
          }
        }
        if($found == -1) break; //Invalid input
        $deck = &GetDeck($otherPlayer);
        array_unshift($deck, $buttonInput);
        unset($options[$found]);
        $options = array_values($options);
        if(count($options) > 0) {
          PrependDecisionQueue($gamestate->turn[0], $gamestate->currentPlayer, implode(",", $options));
        }
        ContinueDecisionQueue($buttonInput);
      } else {
        echo ("Choose top opponent " . $gamestate->turn[0] . " Invalid Input<BR>");
        return false;
      }
      break;
    case 30://String input
      $cardName = CardName(strtoupper($inputText));
      if($cardName != "") $inputText = $cardName;
      if($gamestate->turn[2] == "OUT052" && $inputText == "Head Leads the Tail")//Validate the name
      {
        WriteLog("Must name another card");
        break;
      }
      ContinueDecisionQueue(GamestateSanitize($inputText));
      break;
    case 31: //Move layer deeper
      $index = $buttonInput;
      if($index >= $gamestate->dqState[8]) break;
      $layer = [];
      for($i=$index; $i<$index+LayerPieces(); ++$i) $layer[] = $gamestate->layers[$i];
      $counter = 0;
      for($i=$index + LayerPieces(); $i<($index + LayerPieces()*2); ++$i)
      {
        $gamestate->layers[$i-LayerPieces()] = $gamestate->layers[$i];
        $gamestate->layers[$i] = $layer[$counter++];
      }
      break;
    case 32: //Move layer up
      $index = $buttonInput;
      if($index == 0) break;
      $layer = [];
      for($i=$index; $i<$index+LayerPieces(); ++$i) $layer[] = $gamestate->layers[$i];
      $counter = 0;
      for($i=$index - LayerPieces(); $i<$index; ++$i)
      {
        $gamestate->layers[$i+LayerPieces()] = $gamestate->layers[$i];
        $gamestate->layers[$i] = $layer[$counter++];
      }
      break;
    case 33: //Fully re-order layers
      break;
    case 34: //Claim Initiative
      global $isPass;
      WriteLog("Player " . $playerID . " claimed initiative.");
      $gamestate->initiativePlayer = $gamestate->currentPlayer;
      $otherPlayer = ($playerID == 1 ? 2 : 1);
      $roundPass = $gamestate->initiativeTaken == ($otherPlayer + 2);
      $gamestate->initiativeTaken = 1;
      $isPass = true;
      if($roundPass) BeginRoundPass();
      break;
    case 35://Play from discard
      MakeGamestateBackup();
      $found = $cardID;
      if ($found >= 0) {
        $discard = &GetDiscard($playerID);
        if($found >= count($discard)) break;
        $cardID = $discard[$found];
        $modifier = $discard[$found+1];
        if(!IsPlayable($cardID, $gamestate->turn[0], "GY", $found)) break;
        if($modifier == "TTFREE") AddCurrentTurnEffect("TTFREE", $playerID);
        RemoveDiscard($playerID, $found);
        PlayCard($cardID, "GY");
      }
      break;
    case 99: //Pass
      global $isPass, $gamestate;
      $isPass = true;
      $otherPlayer = ($playerID == 1 ? 2 : 1);
      $roundPass = $gamestate->initiativeTaken == ($otherPlayer + 2);
      $gamestate->dqState[8] = -1;
      if($gamestate->turn[0] == "M" && $gamestate->initiativeTaken != 1 && !$roundPass) $gamestate->initiativeTaken = $gamestate->currentPlayer + 2;
      if(CanPassPhase($gamestate->turn[0])) {
        PassInput(false);
      }
      break;
    case 100: //Cancel attack
      if($gamestate->currentPlayer == $gamestate->mainPlayer && !AttackIsOngoing()) {
        ResetAttackState();
        ProcessDecisionQueue();
      }
      break;
    case 101: //Pass block and Reactions
      ChangeSetting($playerID, $SET_PassDRStep, 1);
      if (CanPassPhase($gamestate->turn[0])) {
        PassInput(false);
      }
      break;
    case 102: //Toggle equipment Active
      $index = $buttonInput;
      $char = &GetPlayerCharacter($playerID);
      $char[$index + 9] = ($char[$index + 9] == "1" ? "0" : "1");
      break;
    case 103: //Toggle my permanent Active
      $input = explode("-", $buttonInput);
      $index = $input[1];
      switch($input[0])
      {
        case "AURAS": $zone = &GetAuras($playerID); $offset = 7; break;
        case "ITEMS": $zone = &GetItems($playerID); $offset = 5; break;
        default: $zone = &GetAuras($playerID); $offset = 7; break;
      }
      $zone[$index + $offset] = ($zone[$index + $offset] == "1" ? "0" : "1");
      break;
    case 104: //Toggle other player permanent Active
      $input = explode("-", $buttonInput);
      $index = $input[1];
      switch($input[0])
      {
        case "AURAS": $zone = &GetAuras($playerID == 1 ? 2 : 1); $offset = 8; break;
        case "ITEMS": $zone = &GetItems($playerID == 1 ? 2 : 1); $offset = 6; break;
        default: $zone = &GetAuras($playerID == 1 ? 2 : 1); $offset = 8; break;
      }
      $zone[$index + $offset] = ($zone[$index + $offset] == "1" ? "0" : "1");
      break;
    case 10000: //Undo
      RevertGamestate();
      $skipWriteGamestate = true;
      WriteLog("Player " . $playerID . " undid their last action.");
      break;
    case 10001:
      RevertGamestate("preBlockBackup.txt");
      $skipWriteGamestate = true;
      WriteLog("Player " . $playerID . " cancel their blocks.");
      break;
    case 10003: //Revert to prior turn
      RevertGamestate($buttonInput);
      WriteLog("Player " . $playerID . " reverted back to a prior turn.");
      break;
    case 10005:
      WriteLog("Player " . $playerID ." manually subtracted 1 damage from themselves.", highlight: true);
      Restore(1, $playerID);
      break;
    case 10006:
      WriteLog("Player " . $playerID ." manually added 1 damage point to themselves.", highlight: true);
      LoseHealth(1, $playerID);
      break;
    case 10007:
      WriteLog("Player " . $playerID ." manually subtracted 1 damage from their opponent.", highlight: true);
      Restore(1, ($playerID == 1 ? 2 : 1));
      break;
    case 10008:
      WriteLog("Player " . $playerID ." manually added 1 damage to their opponent.", highlight: true);
      LoseHealth(1, ($playerID == 1 ? 2 : 1));
      break;
    case 10009:
      WriteLog("Player " . $playerID ." manually drew a card for themselves.", highlight: true);
      Draw($playerID, false);
      break;
    case 10010:
      WriteLog("Player " . $playerID ." manually drew a card for their opponent.", highlight: true);
      Draw(($playerID == 1 ? 2 : 1), false);
      break;
    case 10011:
      WriteLog("Player " . $playerID ." manually added a card to their hand.", highlight: true);
      $hand = &GetHand($playerID);
      $hand[] = $cardID;
      break;
    case 10012://Add damage to friendly ally
      WriteLog("Player " . $playerID ." manually added damage to a friendly unit.", highlight: true);
      $index = $buttonInput;
      $ally = new Ally("MYALLY-" . $index, $playerID);
      $ally->AddDamage(1);
      break;
    case 10013://Remove damage from friendly ally
      WriteLog("Player " . $playerID ." manually removed damage from a friendly unit.", highlight: true);
      $index = $buttonInput;
      $ally = new Ally("MYALLY-" . $index, $playerID);
      $ally->RemoveDamage(1);
      break;
    case 100000: //Quick Rematch
      if($isSimulation) return;
      if($gamestate->turn[0] != "OVER") break;
      CloseDecisionQueue();
      $gamestate->decisionQueue = [];
      $otherPlayer = ($playerID == 1 ? 2 : 1);
      $char = &GetPlayerCharacter($otherPlayer);
      if ($char[0] != "DUMMY") {
        AddDecisionQueue("YESNO", $otherPlayer, "if you want a Quick Rematch?");
        AddDecisionQueue("NOPASS", $otherPlayer, "-", 1);
        AddDecisionQueue("QUICKREMATCH", $otherPlayer, "-", 1);
        AddDecisionQueue("OVER", $playerID, "-");
      } else {
        AddDecisionQueue("QUICKREMATCH", $otherPlayer, "-", 1);
      }
      ProcessDecisionQueue();
      break;
    case 100001: //Main Menu
      if($isSimulation) return;
      header("Location: " . $redirectPath . "/MainMenu.php");
      exit;
    case 100002: //Concede
      if($isSimulation) return;
      include_once "./includes/dbh.inc.php";
      include_once "./includes/functions.inc.php";
      $conceded = true;
      if(!IsGameOver()) PlayerWon(($playerID == 1 ? 2 : 1));
      break;
    case 100003: //Report Bug
      if($isSimulation) return;
      $bugCount = 0;
      $folderName = "./BugReports/" . $gameName . "-" . $bugCount;
      while ($bugCount < 10 && file_exists($folderName)) {
        ++$bugCount;
        $folderName = "./BugReports/" . $gameName . "-" . $bugCount;
      }
      if ($bugCount == 10) {
        WriteLog("Bug report file is temporarily full for this game. Please use the discord to report further bugs.");
      }
      mkdir($folderName, 0700, true);
      copy("./Games/$gameName/gamestate.txt", $folderName . "/gamestate.txt");
      copy("./Games/$gameName/gamestateBackup.txt", $folderName . "/gamestateBackup.txt");
      copy("./Games/$gameName/gamelog.txt", $folderName . "/gamelog.txt");
      copy("./Games/$gameName/beginTurnGamestate.txt", $folderName . "/beginTurnGamestate.txt");
      copy("./Games/$gameName/lastTurnGamestate.txt", $folderName . "/lastTurnGamestate.txt");
      WriteLog("Thank you for reporting a bug. To describe what happened, please report it on the discord server with the game number for reference (" . $gameName . "-" . $bugCount . ").");
      break;
    case 100004: //Full Rematch
      if($isSimulation) return;
      if($gamestate->turn[0] != "OVER") break;
      $otherPlayer = ($playerID == 1 ? 2 : 1);
      AddDecisionQueue("YESNO", $otherPlayer, "if you want a Rematch?");
      AddDecisionQueue("REMATCH", $otherPlayer, "-", 1);
      ProcessDecisionQueue();
      break;
    case 100005: //Reserved to trigger user return from activity
      break;
    case 100006: // User inactive
      $gamestate->currentPlayerActivity = 2;
      GamestateUpdated($gameName);
      break;
    case 100007: //Claim Victory when opponent is inactive
      if($isSimulation) return;
      include_once "./includes/dbh.inc.php";
      include_once "./includes/functions.inc.php";
      if(!IsGameOver()) PlayerWon(($playerID == 1 ? 1 : 2));
      break;
    case 100010: //Grant badge
      if($isSimulation) return;
      include "MenuFiles/ParseGamefile.php";
      include_once "./includes/dbh.inc.php";
      include_once "./includes/functions.inc.php";
      $myName = ($playerID == 1 ? $p1uid : $p2uid);
      $theirName = ($playerID == 1 ? $p2uid : $p1uid);
      if($playerID == 1) $userID = $p1id;
      else $userID = $p2id;
      if($userID != "")
      {
        AwardBadge($userID, 3);
        WriteLog($myName . " gave a badge to " . $theirName);
      }
      break;
    case 100012: //Create Replay
      if(!file_exists("./Games/" . $gameName . "/origGamestate.txt"))
      {
        WriteLog("Failed to create replay; original gamestate file failed to create.");
        return true;
      }
      include "MenuFiles/ParseGamefile.php";
      WriteLog("Player " . $playerID . " saved this game as a replay.");
      $pid = ($playerID == 1 ? $p1id : $p2id);
      $path = "./Replays/" . $pid . "/";
      if (!file_exists($path)) {
        mkdir($path, 0777, true);
      }
      if(!file_exists($path . "counter.txt")) $counter = 1;
      else {
        $counterFile = fopen($path . "counter.txt", "r");
        $counter = fgets($counterFile);
        fclose($counterFile);
      }
      mkdir($path . $counter . "/", 0777, true);
      copy("./Games/" . $gameName . "/origGamestate.txt", "./Replays/" . $pid . "/" . $counter . "/origGamestate.txt");
      copy("./Games/" . $gameName . "/commandfile.txt", "./Replays/" . $pid . "/" . $counter . "/replayCommands.txt");
      $counterFile = fopen($path . "counter.txt", "w");
      fwrite($counterFile, $counter+1);
      fclose($counterFile);
      break;
    case 100013: //Enable Spectate
      SetCachePiece($gameName, 9, "1");
      break;
    case 100014: //Report Player
      if($isSimulation) return;
      $reportCount = 0;
      $folderName = "./BugReports/" . $gameName . "-" . $reportCount;
      while ($reportCount < 5 && file_exists($folderName)) {
        ++$reportCount;
        $folderName = "./BugReports/" . $gameName . "-" . $reportCount;
      }
      if ($reportCount == 5) {
        WriteLog("Report file is full for this game. Please use discord for further reports.");
      }
      mkdir($folderName, 0700, true);
      copy("./Games/$gameName/gamestate.txt", $folderName . "/gamestate.txt");
      copy("./Games/$gameName/gamestateBackup.txt", $folderName . "/gamestateBackup.txt");
      copy("./Games/$gameName/gamelog.txt", $folderName . "/gamelog.txt");
      copy("./Games/$gameName/beginTurnGamestate.txt", $folderName . "/beginTurnGamestate.txt");
      copy("./Games/$gameName/lastTurnGamestate.txt", $folderName . "/lastTurnGamestate.txt");
      WriteLog("Thank you for reporting the player. The chat log has been saved to the server. Please report it to mods on the discord server with the game number for reference ($gameName).");
      break;
    case 100015:
      if($isSimulation) return;
      include_once "./includes/dbh.inc.php";
      include_once "./includes/functions.inc.php";
      $conceded = true;
      if(!IsGameOver()) PlayerWon(($playerID == 1 ? 2 : 1));
      header("Location: " . $redirectPath . "/MainMenu.php");
      break;
    default: break;
  }
  return true;
}

function IsModeAsync($mode)
{
  switch($mode) {
    case 26: return true;
    case 102: return true;
    case 103: return true;
    case 104: return true;
    case 10000: return true;
    case 10003: return true;
    case 100000: return true;
    case 100001: return true;
    case 100002: return true;
    case 100003: return true;
    case 100004: return true;
    case 100006: return true;
    case 100007: return true;
    case 100010: return true;
    case 100012: return true;
    case 100015: return true;
  }
  return false;
}

function IsModeAllowedForSpectators($mode)
{
  switch ($mode) {
    case 100001: return true;
    default: return false;
  }
}

function ExitProcessInput()
{
  global $playerID, $redirectPath, $gameName;
  exit;
}

function PitchHasCard($cardID)
{
  global $gamestate;
  return SearchPitchForCard($gamestate->currentPlayer, $cardID);
}

function HasCard($cardID)
{
  global $gamestate;
  $cardType = CardType($cardID);
  if($cardType == "C" || $cardType == "E" || $cardType == "W") {
    $character = &GetPlayerCharacter($gamestate->currentPlayer);
    for($i = 0; $i < count($character); $i += CharacterPieces()) {
      if($character[$i] == $cardID) return $i;
    }
  } else {
    $hand = &GetHand($gamestate->currentPlayer);
    for($i = 0; $i < count($hand); ++$i) {
      if($hand[$i] == $cardID) return $i;
    }
  }
  return -1;
}

function Passed($playerID)
{
  return $gamestate->turn[1 + $playerID];
}

function PassInput($autopass = false)
{
  global $gamestate;
  if($gamestate->turn[0] == "END" || $gamestate->turn[0] == "MAYMULTICHOOSETEXT" || $gamestate->turn[0] == "MAYCHOOSEMULTIZONE" || $gamestate->turn[0] == "MAYMULTICHOOSEAURAS" ||$gamestate->turn[0] == "MAYMULTICHOOSEHAND" || $gamestate->turn[0] == "MAYCHOOSEHAND" || $gamestate->turn[0] == "MAYCHOOSEDISCARD" || $gamestate->turn[0] == "MAYCHOOSEARSENAL" || $gamestate->turn[0] == "MAYCHOOSEPERMANENT" || $gamestate->turn[0] == "MAYCHOOSEDECK" || $gamestate->turn[0] == "MAYCHOOSEMYSOUL" || $gamestate->turn[0] == "MAYCHOOSETOP" || $gamestate->turn[0] == "MAYCHOOSECARD" || $gamestate->turn[0] == "CHOOSETRIGGERORDER" || $gamestate->turn[0] == "CHOOSEWHICHPLAYERSTRIGGERS" || $gamestate->turn[0] == "OK") {
    ContinueDecisionQueue("PASS");
  } else {
    if($autopass == true);
    else WriteLog("Player " . $gamestate->currentPlayer . " passed.");
    if(Pass($gamestate->turn, $gamestate->currentPlayer, $gamestate->currentPlayer)) {
      if($gamestate->turn[0] == "M")
      {
        $otherPlayer = ($gamestate->currentPlayer == 1 ? 2 : 1);
        if($gamestate->initiativeTaken == 1 && $gamestate->initiativePlayer != $gamestate->currentPlayer || $gamestate->initiativeTaken == ($otherPlayer + 2)) {
          BeginRoundPass();
        } else {
          BeginTurnPass();
        }
      }
      else PassTurn();
    }
  }
}

function Pass($playerID)
{
  global $gamestate, $defPlayer;
  if($gamestate->turn[0] == "M" || $gamestate->turn[0] == "ARS") {
    return 1;
  } else if($gamestate->turn[0] == "B") {
    AddLayer("DEFENDSTEP", $gamestate->mainPlayer, "-");
    ProcessDecisionQueue();
  } else if($gamestate->turn[0] == "A") {
    if(count($gamestate->turn) >= 3 && $gamestate->turn[2] == "D") {
      return BeginAttackResolution();
    } else {
      $gamestate->currentPlayer = $defPlayer;
      $gamestate->turn[0] = "D";
      $gamestate->turn[2] = "A";
    }
  } else if($gamestate->turn[0] == "D") {
    if(count($gamestate->turn) >= 3 && $gamestate->turn[2] == "A") {
      return BeginAttackResolution();
    } else {
      $gamestate->currentPlayer = $gamestate->mainPlayer;
      $gamestate->turn[0] = "A";
      $gamestate->turn[2] = "D";
    }
  }
  return 0;
}

function BeginAttackResolution()
{
  global $gamestate;
  $gamestate->turn[0] = "M";
  AddDecisionQueue("RESOLVEATTACK", $gamestate->mainPlayer, "-");
  ProcessDecisionQueue();
}

function ResolveCombatDamage($damageDone)
{
  global $gamestate;
  global $AS_DamageDealt, $CS_DamageDealt;

  PrependLayer("FINALIZEATTACK", $gamestate->mainPlayer, "0");

  WriteLog("Combat resulted in <span style='color:Crimson;'>$damageDone damage</span>");

  if($damageDone > 0)
  {
    $attacker = new Ally(AttackerMZID($gamestate->mainPlayer));
    if(!IsAllyAttackTarget()) $gamestate->attackState[$AS_DamageDealt] = $damageDone;
    $gamestate->EffectContext = $attacker->CardID();
    ProcessHitEffect($attacker->CardID());
    for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
      if(IsCombatEffectActive($gamestate->currentTurnEffects[$i])) {
        if($gamestate->currentTurnEffects[$i + 1] == $gamestate->mainPlayer) {
          $shouldRemove = EffectHitEffect($gamestate->currentTurnEffects[$i]);
          if($shouldRemove == 1) RemoveCurrentTurnEffect($i);
        }
      }
    }
    $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects); //In case any were removed
  }
  $gamestate->currentPlayer = $gamestate->mainPlayer;
  ProcessDecisionQueue(); //Any combat related decision queue logic should be main player gamestate
}

function FinalizeAttack($attackOver = false)
{
  global $gamestate, $AS_DamageDealt;
  global $mainClassState, $CS_LastAttack;
  $attackOver = true;
  BuildMainPlayerGameState();

  //Clean up combat effects that were used and are one-time
  CleanUpCombatEffects();
  CopyCurrentTurnEffectsFromCombat();
  $hasChainedAction = FinalizeAttackEffects();
  ProcessAfterCombatLayer();

  ResetAttackState();
  $gamestate->turn[0] = "M";
  if($gamestate->initiativeTaken == 1) FinalizeAction();
  else PassInput(true);
  
  if($hasChainedAction) ProcessDecisionQueue();
}

function CleanUpCombatEffects($weaponSwap=false)
{
  global $gamestate;
  for ($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $effectArr = explode(",", $gamestate->currentTurnEffects[$i]);
    if (IsCombatEffectActive($effectArr[0]) && (!IsCombatEffectLimited($i) || $gamestate->currentTurnEffects[$i+1] != $gamestate->mainPlayer) && !IsCombatEffectPersistent($effectArr[0])) {
      --$gamestate->currentTurnEffects[$i + 3];
      if ($gamestate->currentTurnEffects[$i + 3] == 0) RemoveCurrentTurnEffect($i);
    }
  }
}

function BeginRoundPass()
{
  global $gamestate;
  WriteLog("Both players have passed; ending the phase.");
  CurrentEffectStartRegroupAbilities();
  AddDecisionQueue("RESUMEROUNDPASS", $gamestate->mainPlayer, "-");
  ProcessDecisionQueue();
}

function ResumeRoundPass()
{
  global $gamestate;
  global $MakeStartTurnBackup;
  ResetClassState(1);
  ResetClassState(2);
  AllyBeginEndTurnEffects();
  AllyEndTurnAbilities(1);
  AllyEndTurnAbilities(2);
  LogEndTurnStats($gamestate->mainPlayer);
  CurrentEffectEndTurnAbilities();
  ResetCharacter(1);
  ResetCharacter(2);
  CharacterEndTurnAbilities(1);
  CharacterEndTurnAbilities(2);
  UnsetTurnModifiers();
  $gamestate->currentTurnEffects = $gamestate->nextTurnEffects;
  $gamestate->nextTurnEffects = [];
  $gamestate->mainPlayer = $gamestate->initiativePlayer == 1 ? 2 : 1;
  $gamestate->initiativeTaken = 0;
  EndTurnProcedure($gamestate->initiativePlayer);
  EndTurnProcedure($gamestate->initiativePlayer == 1 ? 2 : 1);
  $currentRound+= 1;
  WriteLog("<span style='color:#6E6DFF;'>A new round has begun</span>");
  CharacterStartTurnAbility(1);
  CharacterStartTurnAbility(2);
  AllyBeginRoundAbilities(1);
  AllyBeginRoundAbilities(2);
  CurrentEffectStartTurnAbilities();
  ProcessDecisionQueue();
  $MakeStartTurnBackup = true;
}

function BeginTurnPass()
{
  global $gamestate, $defPlayer;
  ResetAttackState();
  ProcessDecisionQueue();
}

function EndStep()
{
  global $gamestate;
  FinishTurnPass();
  BeginEndPhaseEffectTriggers();
  PlayerSuppress(1);
  PlayerSuppress(2);
}

//CR 2.0 4.4.2. - Beginning of the end phase
function FinishTurnPass()
{
  global $gamestate;
  ClearLog();
  ResetAttackState();
  ItemEndTurnAbilities();
  BeginEndPhaseEffects();
  PermanentBeginEndPhaseEffects();
  AddDecisionQueue("PASSTURN", $gamestate->mainPlayer, "-");
  ProcessDecisionQueue();
}

function PassTurn()
{
  global $playerID, $gamestate, $mainPlayerGamestateStillBuilt;
  if (!$mainPlayerGamestateStillBuilt) {
    BuildMainPlayerGameState();
  }

  FinalizeTurn();
}

function FinalizeTurn()
{
  //4.4.1. Players do not get priority during the End Phase.
  global $gamestate, $playerID, $defPlayer;
  global $mainHand, $defHand, $mainDeck, $defDeck, $mainCharacter, $defCharacter, $mainResources, $defResources;

  $gamestate->EffectContext = "-";

  //4.4.3d All players lose all action points and resources.
  $mainResources[0] = 0;
  $mainResources[1] = 0;
  $defResources[0] = 0;
  $defResources[1] = 0;
  $gamestate->lastPlayed = [];

  DoGamestateUpdate();

  //Update all the player neutral stuff
  if ($gamestate->mainPlayer == 2) {
    $currentRound+= 1;
  }
  $gamestate->turn[0] = "M";
  //$gamestate->turn[1] = $gamestate->mainPlayer == 2 ? $gamestate->turn[1] + 1 : $gamestate->turn[1];
  $gamestate->turn[2] = "";
  $gamestate->turn[3] = "";
  $actionPoints = 1;
  for ($i = 0; $i < count($gamestate->currentTurnEffects); $i += CurrentTurnEffectPieces()) {
    $effectCardID = explode("-", $gamestate->currentTurnEffects[$i]);
    WriteLog("Start of turn effect for " . CardLink($effectCardID[0], $effectCardID[0]) . " is now active.");
  }
  $defPlayer = $gamestate->mainPlayer;
  $gamestate->mainPlayer = ($gamestate->mainPlayer == 1 ? 2 : 1);
  $gamestate->currentPlayer = $gamestate->mainPlayer;

  BuildMainPlayerGameState();

  //Start of turn effects
  if ($gamestate->mainPlayer == 1) StatsStartTurn();
  ItemBeginTurnEffects($gamestate->mainPlayer);
  StartTurnAbilities();

  DoGamestateUpdate();
  ProcessDecisionQueue();
}

function SwapTurn() {
  global $gamestate, $defPlayer;
  $gamestate->turn[0] = "M";
  //$gamestate->turn[1] = $gamestate->mainPlayer == 2 ? $gamestate->turn[1] + 1 : $gamestate->turn[1];
  $gamestate->turn[2] = "";
  $gamestate->turn[3] = "";
  $actionPoints = 1;
  $defPlayer = $gamestate->mainPlayer;
  $gamestate->mainPlayer = ($gamestate->mainPlayer == 1 ? 2 : 1);
  $gamestate->currentPlayer = $gamestate->mainPlayer;
  BuildMainPlayerGameState();
}

function PlayCard($cardID, $from, $skipPrePaymentCheck = false)
{
  //This function covers the act of paying for a card, resolving it, and dealing with everything that logically follows(triggers etc.).
  //It does not cover removing the card from its original place, which should be done beforehand.
  global $playerID, $gamestate;
  global $CS_PlayIndex, $CS_PlayUniqueID, $CS_NumCardsPlayed;
  global $CS_NumVillainyPlayed, $CS_NumEventsPlayed, $CS_NumClonesPlayed;
  $resources = &GetResources($gamestate->currentPlayer);

  //If there are any decisions that must be made(alternate costs for example) before the card is even played:
  //add them here and run through them, then call this function again with the flag to skip this step.
  if(!$skipPrePaymentCheck) {
    AddPrePaymentDecisionQueue($cardID, $from, -1, true);
    AddDecisionQueue("OP", $gamestate->currentPlayer, "PLAYCARDSKIPPREPAYMENT," . $cardID . "," . $from);
    ProcessDecisionQueue();
    return;
  }

  //Announce the card being played
  WriteLog("Player " . $playerID . " " . PlayTerm($gamestate->turn[0], $from, $cardID) . " " . CardLink($cardID, $cardID), $gamestate->turn[0] != "P" ? $gamestate->currentPlayer : 0);

  LogPlayCardStats($gamestate->currentPlayer, $cardID, $from);
  ClearAdditionalCosts($gamestate->currentPlayer);
  $gamestate->lastPlayed = [];
  $gamestate->lastPlayed[0] = $cardID;
  $gamestate->lastPlayed[1] = $gamestate->currentPlayer;
  $gamestate->lastPlayed[2] = CardType($cardID);
  $gamestate->lastPlayed[3] = "-";

  //Determine resource cost.
  if($from == "RESOURCES") $baseCost = SmuggleCost($cardID, $gamestate->currentPlayer);
  else $baseCost = CardCost($cardID);
  $totalCost = $baseCost + SelfCostModifier($cardID, $from) + CurrentEffectCostModifiers($cardID, $from, reportMode:true);
  
  //Pay resources.
  $resources[1] = $totalCost;
  if($resources[1] < 0) $resources[1] = 0;
  LogResourcesUsedStats($gamestate->currentPlayer, $resources[1]);
  $resourceCards = &GetResourceCards($gamestate->currentPlayer);
  for($i = 0; $i < count($resourceCards); $i += ResourcePieces()) {
    if($resources[1] == 0) break;
    if($resourceCards[$i+4] == "0") {
      $resourceCards[$i+4] = "1";
      --$resources[1];
    }
  }
  if($resources[1] > 0) {
    WriteLog("Not enough resources to pay for that. Reverting gamestate.");
    RevertGamestate();
    return;
  }

  //Bookkeeping relevant to certain cards.
  if(AspectContains($cardID, "Villainy", $gamestate->currentPlayer)) IncrementClassState($gamestate->currentPlayer, $CS_NumVillainyPlayed);
  IncrementClassState($gamestate->currentPlayer, $CS_NumCardsPlayed);
  if(DefinedTypesContains($cardID, "Event", $gamestate->currentPlayer)) IncrementClassState($gamestate->currentPlayer, $CS_NumEventsPlayed);
  if(TraitContains($cardID, "Clone", $gamestate->currentPlayer)) IncrementClassState($gamestate->currentPlayer, $CS_NumClonesPlayed);

  //Put the card where it's supposed to go.
  switch(DefinedCardType($cardID)) {
    case "Unit":
      PlayAlly($cardID, $gamestate->currentPlayer, from: $from, owner: $gamestate->currentPlayer);
      break;
    case "Upgrade":
      $upgradeFilter = UpgradeFilter($cardID);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cardID);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      if($upgradeFilter != "") AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, $upgradeFilter);
      AddDecisionQueue("PASSREVERT", $gamestate->currentPlayer, "-");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attach <0>");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSUBCARD," . $cardID, 1);
      break;
    case "Event":
      //Comp Rules 6.2.5.C: an event is put in the owner's discard pile before its effects resolve.
      //Events that go to e.g. the resource zone when they resolve should do that in their own code.
      AddGraveyard($cardID, $gamestate->currentPlayer, $from);
      break;
  }
  PlayAbility($cardID, $from, NULL);
  AddAllyPlayCardAbilityLayers($cardID, $from);
  LeaderPlayCardAbilities($cardID, $from);
  
  ProcessDecisionQueue();
}

function DeclareAttack($attackerIndex, $player, $qualifiers) {
  //$qualifiers are special properties of the attack itself like Ambush, or being able to attack multiple targets.
  PrependDecisionQueue("DECLAREATTACK", $player, $attackerIndex . "|" . $qualifiers);
  GetTargetOfAttack($attackerIndex, $player, $qualifiers);
}

//Find the legal targets for an attack
function GetTargetOfAttack($attackerIndex, $player, $qualifiers)
{
  $defPlayer = $player == 1 ? 2 : 1;

  $targets = "";
  $sentinelTargets = "";
  $attacker = new Ally("MYALLY-" . $attackerIndex, $player);

  if(!in_array("CANTATTACKBASES", $qualifiers) &&
    !in_array("AMBUSH", $qualifiers) &&
    !in_array("3099663280", $attacker-GetUpgrades())){ //Entrenched
    $targets = "THEIRCHAR-0";
  }
  
  $allies = &GetAllies($defPlayer);
  for($i = 0; $i < count($allies); $i += AllyPieces()) {
    if($attacker->CardID() != "5464125379" && CardArenas($attacker->CardID()) != CardArenas($allies[$i]) && !SearchCurrentTurnEffects("4663781580", $player)) continue;//Strafing Gunship, Swoop Down
    if(!AllyCanBeAttackTarget($defPlayer, $i, $allies[$i])) continue;
    if($targets != "") $targets .= ",";
    $targets .= "THEIRALLY-" . $i;
    if(HasSentinel($allies[$i], $defPlayer, $i) && CardArenas($attacker->CardID()) == CardArenas($allies[$i])) {
      if($sentinelTargets != "") $sentinelTargets .= ",";
      $sentinelTargets .= "THEIRALLY-" . $i;
    }
  }
  if($sentinelTargets != "" && !HasSaboteur($attacker->CardID(), $player, $attacker->Index())) $targets = $sentinelTargets;
  PrependDecisionQueue("PROCESSATTACKTARGET", $player, "-");
  if(SearchCount($targets) > 1) {
    PrependDecisionQueue("CHOOSEMULTIZONE", $player, $targets);
    PrependDecisionQueue("SETDQCONTEXT", $player, "Choose a target for the attack");
  } else if($targets == "") {
    WriteLog("There are no valid targets for this attack. Reverting gamestate.");
    RevertGamestate();
  } else {
    PrependDecisionQueue("PASSPARAMETER", $player, $targets);
  }
}

function DealCombatDamageStep($attackerUniqueID, $targetUniqueID, $attackingPlayer, $attackQualifiers)
{
  global $gamestate;
  $attackedPlayer = $attackingPlayer == 1 ? 2 : 1;
  BuildMainPlayerGameState();

  $attackerIndex = SearchAlliesForUniqueID($attackerUniqueID, $attackingPlayer);
  $targetIndex = SearchAlliesForUniqueID($targetUniqueID, $attackedPlayer);
  if($targetUniqueID == "BASE") $targetIndex = NULL;
  if($attackerIndex == -1 || $targetIndex == -1) { //If either participant is gone, the attack is no longer valid.
    CancelAttack();
    ProcessDecisionQueue();
    return;
  }

  $attackerMZ = "MYALLY-" . $attackerIndex;
  $attackerArr = explode("-", $attackerMZ);
  $attacker = new Ally($attackerMZ, $attackingPlayer);
  $hasOverwhelm = HasOverwhelm($attacker->CardID(), $attackingPlayer, $attacker->Index());
  $attackerID = $attacker->CardID();
  $attackerSurvived = true;
  $totalAttack = $attacker->CurrentPower();
  LogCombatResolutionStats($totalAttack, 0);

  $targetArr = explode("-", $target);
  $damageDealt = 0;
  if($targetUniqueID == "BASE") {
    $damage = $totalAttack;
    DealDamageToBase($attackedPlayer, $damage, $damageDealt);
  }
  else {
    $defender = new Ally("THEIRALLY-" . $targetIndex, $attackedPlayer);
    $defenderPower = $defender->CurrentPower();
    $excess = $totalAttack - $defender->Health();
    $destroyed = $defender->DealDamage($totalAttack, bypassShield:HasSaboteur($attackerID, $attackingPlayer, $attacker->Index()), fromCombat:true, damageDealt:$damageDealt);
    if($attackerArr[0] == "MYALLY" && (!$destroyed || ($attackerID != "9500514827" && $attackerID != "4328408486" && !SearchCurrentTurnEffects("8297630396", $gamestate->mainPlayer)))) { //Han Solo shoots first; also Incinerator Trooper
      $attacker->DealDamage($defenderPower, fromCombat:true);
    }
    if($hasOverwhelm) DealDamageAsync($attackedPlayer, $excess, "OVERWHELM", $attackerID);
    else if($attackerID == "3830969722") { //Blizzard Assault AT-AT
      AddDecisionQueue("SETDQCONTEXT", $attackingPlayer, "Choose a unit to deal " . $excess . " damage to");
      AddDecisionQueue("MULTIZONEINDICES", $attackingPlayer, "THEIRALLY:arena=Ground");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $attackingPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $attackingPlayer, "DEALDAMAGE," . $excess, 1);
    }

  WriteLog("Combat resulted in <span style='color:Crimson;'>$damageDealt damage</span>");

  if($damageDealt > 0)
  {
    $attacker = new Ally(AttackerMZID($gamestate->mainPlayer));
    ProcessHitEffect($attacker->CardID());
    for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
      if(IsCombatEffectActive($gamestate->currentTurnEffects[$i])) {
        if($gamestate->currentTurnEffects[$i + 1] == $gamestate->mainPlayer) {
          $shouldRemove = EffectHitEffect($gamestate->currentTurnEffects[$i]);
          if($shouldRemove == 1) RemoveCurrentTurnEffect($i);
        }
      }
    }
    $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects); //In case any were removed
  }


    AddDecisionQueue("RESOLVECOMBATDAMAGE", $attackingPlayer, $totalAttack);
  } 
  //Add "Complete the attack" layer

  ProcessDecisionQueue();
}

function PlayCardSkipCosts($cardID, $from)
{
  global $gamestate;
  $cardType = CardType($cardID);
  if (($gamestate->turn[0] == "M") && $cardType == "AA") GetTargetOfAttack($cardID);
  if ($gamestate->turn[0] != "B" || (count($gamestate->layers) > 0 && $gamestate->layers[0] != "")) {
    if (HasBoost($cardID)) Boost();
    GetLayerTarget($cardID);
    LeaderPlayCardAbilities($cardID, $from);
  }
  PlayCardEffect($cardID, $from, 0);
}

function GetLayerTarget($cardID)
{
  global $gamestate;
  if(DefinedTypesContains($cardID, "Upgrade", $gamestate->currentPlayer)) 
  {
    $upgradeFilter = UpgradeFilter($cardID);
    AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cardID);
    AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
    AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
    if($upgradeFilter != "") AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, $upgradeFilter);
    AddDecisionQueue("PASSREVERT", $gamestate->currentPlayer, "-");
    AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attach <0>");
    AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
    AddDecisionQueue("SETLAYERTARGET", $gamestate->currentPlayer, $cardID, 1);
    AddDecisionQueue("SHOWSELECTEDTARGET", $gamestate->currentPlayer, "-", 1);
  } else {
    $targetType = PlayRequiresTarget($cardID);
    if($targetType != -1)
    {
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cardID);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a target for <0>");
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "TARGETSBYTYPE," . $targetType);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a target for <0>");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETLAYERTARGET", $gamestate->currentPlayer, $cardID, 1);
      AddDecisionQueue("SHOWSELECTEDTARGET", $gamestate->currentPlayer, "-", 1);
    }
  }
}

function AddPrePaymentDecisionQueue($cardID, $from, $index = -1, $skipAbilityType = false)
{
  global $gamestate, $CS_AdditionalCosts;
  if (!$skipAbilityType && IsStaticType(CardType($cardID), $from, $cardID)) {
    $names = GetAbilityNames($cardID, $index, validate:true);
    if ($names != "") {
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose which ability to activate");
      AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, $names);
      AddDecisionQueue("SETABILITYTYPE", $gamestate->currentPlayer, $cardID);
    }
  }
  switch ($cardID) {
    case "9644107128"://Bamboozle
      if(SearchCount(SearchHand($gamestate->currentPlayer, aspect:"Cunning")) > 0) {
        AddDecisionQueue("YESNO", $gamestate->currentPlayer, "if_you_want_to_discard_a_Cunning_card", 1);
        AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:aspect=Cunning", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("ADDDISCARD", $gamestate->currentPlayer, "HAND", 1);
        AddDecisionQueue("ADDCURRENTEFFECT", $gamestate->currentPlayer, "9644107128", 1);
        AddDecisionQueue("WRITELOG", $gamestate->currentPlayer, CardLink("9644107128", "9644107128") . "_alternative_cost_was_paid.", 1);
      }
      break;
    /*case "1705806419"://Force Throw
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose player to discard a card");
      AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, "Yourself,Opponent");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts);
      break;
    case "4772866341"://Pillage
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose player to discard 2 cards");
      AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, "Yourself,Opponent");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts);
      break;
    case "7262314209"://Mission Briefing
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose player to draw 2 cards");
      AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, "Yourself,Opponent");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts);
      break;*/
    default:
      break;
  }
}

function PayAbilityAdditionalCosts($cardID)
{
  global $gamestate;
  switch ($cardID) {
    case "MON000":
      for($i = 0; $i < 2; ++$i) {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "HANDPITCH,2");
        AddDecisionQueue("CHOOSEHANDCANCEL", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MULTIREMOVEHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("DISCARDCARD", $gamestate->currentPlayer, "HAND", 1);
      }
      break;
    default:
      break;
  }
}

function PayAdditionalCosts($cardID, $from)
{
  global $gamestate, $CS_AdditionalCosts, $CS_CharacterIndex, $CS_PlayIndex, $CS_PreparationCounters;
  if(RequiresDiscard($cardID)) {
    $discarded = DiscardRandom($gamestate->currentPlayer, $cardID);
    if($discarded == "") {
      WriteLog("You do not have a card to discard. Reverting gamestate.");
      RevertGamestate();
      return;
    }
    SetClassState($gamestate->currentPlayer, $CS_AdditionalCosts, $discarded);
  }
  switch($cardID) {
    case "8615772965"://Vigilance
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose 2 modes");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "2-Mill,Heal,Defeat,Shield-2");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts, 1);
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      break;
    case "0073206444"://Command
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose 2 modes");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "2-Experience,Deal Damage,Resource,Return Unit-2");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts, 1);
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      break;
    case "3736081333"://Aggression
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose 2 modes");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "2-Draw,Defeat Upgrades,Ready Unit,Deal Damage-2");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts, 1);
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      break;
    case "3789633661"://Cunning
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose 2 modes");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "2-Return Unit,Buff Unit,Exhaust Units,Discard Random-2");
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AdditionalCosts, 1);
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      break;
    default:
      break;
  }
}

function MaterializeCardEffect($cardID)
{
  global $gamestate;
  switch($cardID)
  {

    default:
      break;
  }
}

function PlayCardEffect($cardID, $from, $resourcesPaid, $target = "-", $additionalCosts = "-", $uniqueID = "-1", $layerIndex = -1)
{
  global $gamestate, $defPlayer, $CS_PlayIndex;
  global $CS_CharacterIndex, $CS_NumAttacks;
  global $AS_AttackerIndex, $AS_AttackerUniqueID, $CS_NumEventsPlayed, $CS_AfterPlayedBy;
  global $SET_PassDRStep, $CS_AbilityIndex, $CS_NumMandalorianAttacks;

  if($layerIndex > -1) SetClassState($gamestate->currentPlayer, $CS_PlayIndex, $layerIndex);
  if(intval($uniqueID) != -1) $index = SearchForUniqueID($uniqueID, $gamestate->currentPlayer);
  if(!isset($index)) $index = GetClassState($gamestate->currentPlayer, $CS_PlayIndex);
  if($index > -1) SetClassState($gamestate->currentPlayer, $CS_PlayIndex, $index);

  $definedCardType = CardType($cardID);
  //Figure out where it goes
  if (GetResolvedAbilityType($cardID, $from) == "AA") { //If this is an attack
    if($from == "PLAY" && $uniqueID != "-1" && $index == -1) { WriteLog(CardLink($cardID, $cardID) . " does not resolve because it is no longer in play."); return; }
    $gamestate->attackState[$AS_AttackerIndex] = GetClassState($gamestate->currentPlayer, $CS_PlayIndex);
    $gamestate->attackState[$AS_AttackerUniqueID] = $uniqueID;
    ChangeSetting($defPlayer, $SET_PassDRStep, 0);
    ProcessAttackTarget();
    if(AttackIsOngoing()) {
      $ally = new Ally("MYALLY-" . GetClassState($gamestate->currentPlayer, $CS_PlayIndex), $gamestate->currentPlayer);
      $attackValue = $ally->CurrentPower();
      $ally->IncrementTimesAttacked();
      if(GetAttackTarget() == "THEIRCHAR-0") {
        //Add attacker to defender's list of units that attacked their base this phase.
        global $CS_UnitsThatAttackedBase;
        AppendClassState($defPlayer, $CS_UnitsThatAttackedBase, $ally->UniqueID(), false);
      }
    }
    else $attackValue = ($baseAttackSet != -1 ? $baseAttackSet : AttackValue($cardID));
    $gamestate->attackState[$AS_AttackerUniqueID] = $uniqueID;
    if($definedCardType == "AA" || $definedCardType == "W")
    {
      $char = &GetPlayerCharacter($gamestate->currentPlayer);
      $char[1] = 1;
    }
    IncrementClassState($gamestate->currentPlayer, $CS_NumAttacks);
    if(TraitContains($cardID, "Mandalorian", $gamestate->currentPlayer, $gamestate->attackState[$AS_AttackerIndex])) IncrementClassState($gamestate->currentPlayer, $CS_NumMandalorianAttacks);
    if ($from == "PLAY" && IsAlly($cardID))
    {
      AllyAttackAbilities($cardID);
      SpecificAllyAttackAbilities($cardID);
    }
  } else if ($from != "PLAY") {
    $cardSubtype = CardSubType($cardID);
    if ($definedCardType != "C" && $definedCardType != "E" && $definedCardType != "W") {
      $goesWhere = GoesWhereAfterResolving($cardID, $from, $gamestate->currentPlayer, resourcesPaid:$resourcesPaid, additionalCosts:$additionalCosts);
      switch ($goesWhere) {
        case "BOTDECK":
          AddBottomDeck($cardID, $gamestate->currentPlayer, $from);
          break;
        case "HAND":
          AddPlayerHand($cardID, $gamestate->currentPlayer, $from);
          break;
        case "GY":
          AddGraveyard($cardID, $gamestate->currentPlayer, $from);
          break;
        case "ALLY":
          PlayAlly($cardID, $gamestate->currentPlayer);
          break;
        case "RESOURCE":
          AddResources($cardID, $gamestate->currentPlayer, $from, "DOWN", isExhausted:"1");
          break;
        case "ATTACHTARGET":
          MZAttach($gamestate->currentPlayer, $target, $cardID);
          //When you play an upgrade on this unit (e.g. Fenn Rau)
          $mzArr = explode("-", $target);
          if($mzArr[0] == "MYALLY" || $mzArr[0] == "THEIRALLY") {
            $owner = MZPlayerID($gamestate->currentPlayer, $target);
            $targetAlly = new Ally($target, $owner);
            switch($targetAlly->CardID()) {
              case "3399023235"://Fenn Rau
                if($gamestate->currentPlayer == $owner) {
                  $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
                  AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
                  AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give -2/-2", 1);
                  AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
                  AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
                  AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
                  AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "3399023235-2,HAND", 1);
                  AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
                  AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REDUCEHEALTH,2", 1);
                }
                break;
              default: break;
            }
          }
          break;
        default:
          break;
      }
    }
  }
  //Resolve Effects
  CurrentEffectPlayOrActivateAbility($cardID, $from);
  if($from != "PLAY") {
    CurrentEffectPlayAbility($cardID, $from);
  }
  $gamestate->EffectContext = $cardID;
  if(GetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy) != "-") AfterPlayedByAbility(GetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy));
  if(DefinedTypesContains($cardID, "Event", $gamestate->currentPlayer) && SearchCurrentTurnEffects("3401690666", $gamestate->currentPlayer, remove:true)) {
    //Relentless
    WriteLog("<span style='color:red;'>The event does nothing because of Relentless.</span>");
  }
  else {
    $abilityIndex = GetClassState($gamestate->currentPlayer, $CS_AbilityIndex);
    $playIndex = GetClassState($gamestate->currentPlayer, $CS_PlayIndex);
    $layerName = "PLAYABILITY";
    if($from == "PLAY" || $from == "EQUIP") {
      if(GetResolvedAbilityType($cardID) == "A") $layerName = "ACTIVATEDABILITY";
      else $layerName = "ATTACKABILITY";
    }
    if($layerName == "ATTACKABILITY") { if(HasAttackAbility($cardID)) PlayAbility($cardID, "PLAY", "0"); }
    //TODO: Fix this Relentless and first light and The Mandalorian hack
    else if($from == "PLAY" || $from == "EQUIP" || HasWhenPlayed($cardID) || $cardID == "3401690666" || $cardID == "4783554451" || $cardID == "4088c46c4d" || DefinedTypesContains($cardID, "Event", $gamestate->currentPlayer) || DefinedTypesContains($cardID, "Upgrade", $gamestate->currentPlayer)) AddLayer($layerName, $gamestate->currentPlayer, $cardID, $from . "!" . $resourcesPaid . "!" . $target . "!" . $additionalCosts . "!" . $abilityIndex . "!" . $playIndex, "-", $uniqueID, append:true);
    else if($from != "PLAY" && $from != "EQUIP") {
      AddAllyPlayCardAbilityLayers($cardID, $from);
    }
  }
  if($from != "PLAY") {
    $index = LastAllyIndex($gamestate->currentPlayer);
    if(HasShielded($cardID, $gamestate->currentPlayer, $index)) {
      $allies = &GetAllies($gamestate->currentPlayer);
      AddLayer("TRIGGER", $gamestate->currentPlayer, "SHIELDED", "-", "-", $allies[$index + 5], append:true);
    }
    if(HasAmbush($cardID, $gamestate->currentPlayer, $index, $from)) {
      $allies = &GetAllies($gamestate->currentPlayer);
      AddLayer("TRIGGER", $gamestate->currentPlayer, "AMBUSH", "-", "-", $allies[$index + 5], append:true);
    }
  }
  CopyCurrentTurnEffectsFromAfterResolveEffects();

  //Now determine what needs to happen next
  SetClassState($gamestate->currentPlayer, $CS_PlayIndex, -1);
  SetClassState($gamestate->currentPlayer, $CS_CharacterIndex, -1);
  ProcessDecisionQueue();
}

function ProcessAttackTarget()
{
  global $defPlayer;
  $target = explode("-", GetAttackTarget());
  if($target[0] == "THEIRALLY") {
    $ally = new Ally($target[0] . "-" . $target[1], $defPlayer);
    AllyAttackedAbility($ally->CardID(), $target[1]);
  }
  return false;
}

function WriteGamestate()
{
  global $gameName, $gamestate;
  $filename = "./Games/" . $gameName . "/gamestate.txt";
  $handler = fopen($filename, "w");

  $lockTries = 0;
  while (!flock($handler, LOCK_EX) && $lockTries < 10) {
    usleep(100000); //50ms
    ++$lockTries;
  }

  if ($lockTries == 10) { fclose($handler); exit; }

  fwrite($handler, serialize($gamestate));
  
  fclose($handler);
}

function AddEvent($type, $value)
{
  global $gamestate;
  $gamestate->events[] = $type;
  $gamestate->events[] = $value;
}

?>
