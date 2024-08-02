<?php

include "CardDictionary.php";
include "CoreLogic.php";

function PummelHit($player = -1, $passable = false, $fromDQ = false, $context="", $may=false)
{
  global $defPlayer;
  if($player == -1) $player = $defPlayer;
  if($context == "") $context = "Choose a card to discard";
  if($fromDQ)
  {
    PrependDecisionQueue("CARDDISCARDED", $player, "-", 1);
    PrependDecisionQueue("ADDDISCARD", $player, "HAND", 1);
    PrependDecisionQueue("MULTIREMOVEHAND", $player, "-", 1);
    if($may) PrependDecisionQueue("MAYCHOOSEHAND", $player, "<-", 1);
    else PrependDecisionQueue("CHOOSEHAND", $player, "<-", 1);
    PrependDecisionQueue("SETDQCONTEXT", $player, $context, 1);
    PrependDecisionQueue("FINDINDICES", $player, "HAND", ($passable ? 1 : 0));
  }
  else {
    AddDecisionQueue("FINDINDICES", $player, "HAND", ($passable ? 1 : 0));
    AddDecisionQueue("SETDQCONTEXT", $player, $context, 1);
    if($may) AddDecisionQueue("MAYCHOOSEHAND", $player, "<-", 1);
    else AddDecisionQueue("CHOOSEHAND", $player, "<-", 1);
    AddDecisionQueue("MULTIREMOVEHAND", $player, "-", 1);
    AddDecisionQueue("ADDDISCARD", $player, "HAND", 1);
    AddDecisionQueue("CARDDISCARDED", $player, "-", 1);
  }
}

function DefeatUpgrade($player, $may = false, $search="MYALLY&THEIRALLY", $upgradeFilter="", $to="DISCARD") {
  $verb = "";
  switch($to) {
    case "DISCARD": $verb = "defeat"; break;
    case "HAND": $verb = "bounce"; break;
  }
  AddDecisionQueue("MULTIZONEINDICES", $player, $search);
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a unit to " . $verb . " an upgrade from");
  if($may) AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
  else AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("SETDQVAR", $player, 0, 1);
  AddDecisionQueue("MZOP", $player, "GETUPGRADES", 1);
  if($upgradeFilter != "") AddDecisionQueue("MZFILTER", $player, $upgradeFilter, 1);
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose an upgrade to defeat");
  if($may) AddDecisionQueue("MAYCHOOSECARD", $player, "<-", 1);
  else AddDecisionQueue("CHOOSECARD", $player, "<-", 1);
  if($to == "DISCARD") AddDecisionQueue("OP", $player, "DEFEATUPGRADE", 1);
  else if($to == "HAND") AddDecisionQueue("OP", $player, "BOUNCEUPGRADE", 1);
}

function PlayCaptive($player, $target="")
{
  AddDecisionQueue("PASSPARAMETER", $player, $target);
  AddDecisionQueue("SETDQVAR", $player, 0);
  AddDecisionQueue("MZOP", $player, "GETCAPTIVES");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a captured unit to play");
  AddDecisionQueue("CHOOSECARD", $player, "<-", 1);
  AddDecisionQueue("OP", $player, "PLAYCAPTIVE", 1);
}

function RescueUnit($player, $target="")
{
  AddDecisionQueue("PASSPARAMETER", $player, $target);
  AddDecisionQueue("SETDQVAR", $player, 0);
  AddDecisionQueue("MZOP", $player, "GETCAPTIVES");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a unit to rescue");
  AddDecisionQueue("CHOOSECARD", $player, "<-", 1);
  AddDecisionQueue("OP", $player, "RESCUECAPTIVE", 1);
}

function HandToTopDeck($player)
{
  AddDecisionQueue("FINDINDICES", $player, "HAND");
  AddDecisionQueue("MAYCHOOSEHAND", $player, "<-", 1);
  AddDecisionQueue("MULTIREMOVEHAND", $player, "-", 1);
  AddDecisionQueue("MULTIADDTOPDECK", $player, "-", 1);
}

function BottomDeck($player="", $mayAbility=false, $shouldDraw=false)
{
  global $gamestate;
  if($player == "") $player = $gamestate->currentPlayer;
  AddDecisionQueue("FINDINDICES", $player, "HAND");
  AddDecisionQueue("SETDQCONTEXT", $player, "Put_a_card_from_your_hand_on_the_bottom_of_your_deck.");
  if($mayAbility) AddDecisionQueue("MAYCHOOSEHAND", $player, "<-", 1);
  else AddDecisionQueue("CHOOSEHAND", $player, "<-", 1);
  AddDecisionQueue("REMOVEMYHAND", $player, "-", 1);
  AddDecisionQueue("ADDBOTDECK", $player, "-", 1);
  AddDecisionQueue("WRITELOG", $player, "A card was put on the bottom of the deck", 1);
  if($shouldDraw) AddDecisionQueue("DRAW", $player, "-", 1);
}

function BottomDeckMultizone($player, $zone1, $zone2)
{
  AddDecisionQueue("MULTIZONEINDICES", $player, $zone1 . "&" . $zone2, 1);
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a card to sink (or Pass)", 1);
  AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("MZREMOVE", $player, "-", 1);
  AddDecisionQueue("ADDBOTDECK", $player, "-", 1);
}

function AddCurrentTurnEffectNextAttack($cardID, $player, $from = "", $uniqueID = -1)
{
  if(AttackIsOngoing()) AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID);
  else AddCurrentTurnEffect($cardID, $player, $from, $uniqueID);
}

function AddCurrentTurnEffect($cardID, $player, $from = "", $uniqueID = -1)
{
  global $gamestate;
  $card = explode("-", $cardID)[0];
  if(CardType($card) == "A" && AttackIsOngoing() && IsCombatEffectActive($cardID) && !IsCombatEffectPersistent($cardID) && $from != "PLAY") {
    AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID);
    return;
  }
  array_push($gamestate->currentTurnEffects, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function AddAfterResolveEffect($cardID, $player, $from = "", $uniqueID = -1)
{
  global $afterResolveEffects;
  $card = explode("-", $cardID)[0];
  if(CardType($card) == "A" && AttackIsOngoing() && !IsCombatEffectPersistent($cardID) && $from != "PLAY") {
    AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID);
    return;
  }
  array_push($afterResolveEffects, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function HasLeader($player) {
  return SearchCount(SearchAllies($player, definedType:"Leader")) > 0;
}

function HasMoreUnits($player) {
  $allies = &GetAllies($player);
  $theirAllies = &GetAllies($player == 1 ? 2 : 1);
  return count($allies) > count($theirAllies);
}

function CopyCurrentTurnEffectsFromAfterResolveEffects()
{
  global $gamestate, $afterResolveEffects;
  for($i = 0; $i < count($afterResolveEffects); $i += CurrentTurnEffectPieces()) {
    array_push($gamestate->currentTurnEffects, $afterResolveEffects[$i], $afterResolveEffects[$i+1], $afterResolveEffects[$i+2], $afterResolveEffects[$i+3]);
  }
  $afterResolveEffects = [];
}

//This is needed because if you add a current turn effect from combat, it could get deleted as part of the combat resolution
function AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID = -1)
{
  global $gamestate;
  array_push($gamestate->currentTurnEffectsFromCombat, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function CopyCurrentTurnEffectsFromCombat()
{
  global $gamestate;
  for($i = 0; $i < count($gamestate->currentTurnEffectsFromCombat); $i += CurrentTurnEffectPieces()) {
    array_push($gamestate->currentTurnEffects, $gamestate->currentTurnEffectsFromCombat[$i], $gamestate->currentTurnEffectsFromCombat[$i+1], $gamestate->currentTurnEffectsFromCombat[$i+2], $gamestate->currentTurnEffectsFromCombat[$i+3]);
  }
  $gamestate->currentTurnEffectsFromCombat = [];
}

function RemoveCurrentTurnEffect($index)
{
  global $gamestate;
  unset($gamestate->currentTurnEffects[$index+3]);
  unset($gamestate->currentTurnEffects[$index+2]);
  unset($gamestate->currentTurnEffects[$index+1]);
  unset($gamestate->currentTurnEffects[$index]);
  $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects);
}

function CurrentTurnEffectPieces()
{
  return 4;
}

function CurrentTurnEffectUses($cardID)
{
  switch ($cardID) {
    default: return 1;
  }
}

function AddNextTurnEffect($cardID, $player, $uniqueID = -1)
{
  global $gamestate;
  array_push($gamestate->ZnextTurnEffects, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function IsCombatEffectLimited($index)
{
  global $gamestate, $AS_AttackerIndex, $AS_AttackerUniqueID;
  if(!AttackIsOngoing() || $gamestate->currentTurnEffects[$index + 2] == -1) return false;
  $allies = &GetAllies($gamestate->mainPlayer);
  if(count($allies) < $gamestate->attackState[$AS_AttackerIndex] + 5) return false;
  if($allies[$gamestate->attackState[$AS_AttackerIndex] + 5] != $gamestate->currentTurnEffects[$index + 2]) return true;
  return false;
}

function IsAbilityLayer($cardID)
{
  return $cardID == "TRIGGER" || $cardID == "PLAYABILITY" || $cardID == "ATTACKABILITY" || $cardID == "ACTIVATEDABILITY" || $cardID == "AFTERPLAYABILITY";
}

function AddDecisionQueue($phase, $player, $parameter, $subsequent = 0, $makeCheckpoint = 0)
{
  global $gamestate;
  if(count($gamestate->decisionQueue) == 0) $insertIndex = 0;
  else {
    $insertIndex = count($gamestate->decisionQueue) - DecisionQueuePieces();
    if(!IsGamePhase($gamestate->decisionQueue[$insertIndex])) //Stack must be clear before you can continue with the step
    {
      $insertIndex = count($gamestate->decisionQueue);
    }
  }

  $parameter = str_replace(" ", "_", $parameter);
  array_splice($gamestate->decisionQueue, $insertIndex, 0, $phase);
  array_splice($gamestate->decisionQueue, $insertIndex + 1, 0, $player);
  array_splice($gamestate->decisionQueue, $insertIndex + 2, 0, $parameter);
  array_splice($gamestate->decisionQueue, $insertIndex + 3, 0, $subsequent);
  array_splice($gamestate->decisionQueue, $insertIndex + 4, 0, $makeCheckpoint);
}

function PrependDecisionQueue($phase, $player, $parameter, $subsequent = 0, $makeCheckpoint = 0)
{
  global $gamestate;
  if($parameter == null || $parameter == "") return;
  $parameter = str_replace(" ", "_", $parameter);
  array_unshift($gamestate->decisionQueue, $makeCheckpoint);
  array_unshift($gamestate->decisionQueue, $subsequent);
  array_unshift($gamestate->decisionQueue, $parameter);
  array_unshift($gamestate->decisionQueue, $player);
  array_unshift($gamestate->decisionQueue, $phase);
}

function IsDecisionQueueActive()
{
  global $gamestate;
  return $gamestate->dqState[0] == "1";
}

function ProcessDecisionQueue()
{
  global $gamestate;
  if($gamestate->dqState[0] != "1") {
    if(count($gamestate->turn) < 3) $gamestate->turn[2] = "-";
    $gamestate->dqState[0] = "1"; //If the decision queue is currently active/processing
    $gamestate->dqState[1] = $gamestate->turn[0];
    $gamestate->dqState[2] = $gamestate->turn[1];
    $gamestate->dqState[3] = $gamestate->turn[2];
    $gamestate->dqState[4] = "-"; //DQ helptext initial value
    $gamestate->dqState[5] = "-"; //Decision queue multizone indices
    $gamestate->dqState[6] = "0"; //Damage dealt
    $gamestate->dqState[7] = "0"; //Target
    ContinueDecisionQueue("");
  }
}

function CloseDecisionQueue()
{
  global $gamestate;
  $gamestate->dqState[0] = "0";
  $gamestate->turn[0] = $gamestate->dqState[1];
  $gamestate->turn[1] = $gamestate->dqState[2];
  $gamestate->turn[2] = $gamestate->dqState[3];
  $gamestate->dqState[4] = "-"; //Clear the context, just in case
  $gamestate->dqState[5] = "-"; //Clear Decision queue multizone indices
  $gamestate->dqState[6] = "0"; //Damage dealt
  $gamestate->dqState[7] = "0"; //Target
  $gamestate->decisionQueue = [];
  if(($gamestate->turn[0] == "D" || $gamestate->turn[0] == "A") && !AttackIsOngoing()) {
    $gamestate->currentPlayer = $gamestate->mainPlayer;
    $gamestate->turn[0] = "M";
  }
}

//Returns true when the given player should be given the choice to arrange triggers or to choose which player to resolve triggers first.
function ShouldHoldPriorityNow($player) 
{
  global $gamestate;
  $innermostTriggerStack = InnermostTriggerStack();
  $p1TriggerCount = count(array_filter($innermostTriggerStack, function($a){return $a->Player() == 1;}));
  $p2TriggerCount = count(array_filter($innermostTriggerStack, function($a){return $a->Player() == 2;}));
  if($p1TriggerCount > 0 && $p2TriggerCount > 0) return $player == $gamestate->mainPlayer; //If both players have triggers to resolve, the active player chooses which player gets to resolve theirs first.
  switch($player){
    case 1: return $p1TriggerCount > 1;
    case 2: return $p2TriggerCount > 1;
  }
}

function IsGamePhase($phase)
{
  switch ($phase) {
    case "RESUMEPAYING":
    case "RESUMEPLAY":
    case "RESOLVEATTACK":
    case "RESOLVECOMBATDAMAGE":
    case "PASSTURN":
      return true;
    default: return false;
  }
}

//Must be called with the my/their context
function ContinueDecisionQueue($lastResult = "")
{
  global $gamestate, $mainPlayerGamestateStillBuilt, $makeCheckpoint, $otherPlayer;
  global $CS_PlayIndex, $CS_AdditionalCosts, $CS_LayerPlayIndex;
  if(count($gamestate->decisionQueue) == 0) {
    //Resolve triggers, or next other layer if there are none.
    if(!$gamestate->currentlyResolvingStack->IsEmpty()) {
      switch($gamestate->currentlyResolvingStack->GetDecisionState()) {
        case "PLAYERORDER":
          AddDecisionQueue("CHOOSEWHICHPLAYERSTRIGGERS", $gamestate->mainPlayer, "-");
          break;
        case "P1TRIGGERORDER":
          AddDecisionQueue("CHOOSETRIGGERORDER", 1, "-");
          break;
        case "P2TRIGGERORDER":
          AddDecisionQueue("CHOOSETRIGGERORDER", 2, "-");
          break;
        default: //No ordering decisions need to be made, resolve the next trigger.
          $gamestate->currentlyResolvingStack->ResolveNextTrigger();
          break;
        }
        ProcessDecisionQueue();
        return;
      }
      else { //Resolve pending non-trigger layer.
        global $pendingLayer;
        $layerType = $pendingLayer->Type();
        if($layerType == "ENDTURN") EndStep();
        else if($layerType == "ENDSTEP") FinishTurnPass();
        else if($layerType == "RESUMETURN") $gamestate->turn[0] = "M";
        else if($layerType == "LAYER") ProcessLayer($player, $parameter);
        else if($layerType == "FINALIZEATTACK") FinalizeAttack($parameter);
        else if($layerType == "DEFENDSTEP") { $gamestate->turn[0] = "A"; $gamestate->currentPlayer = $gamestate->mainPlayer; }
      }
      return;
    }
    else if(IsGamePhase($gamestate->decisionQueue[0])) {
      switch($gamestate->decisionQueue[0]) {
        case "RESUMEPLAY":
          if($gamestate->currentPlayer != $gamestate->decisionQueue[1]) {
            $gamestate->currentPlayer = $gamestate->decisionQueue[1];
            $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($gamestate->currentPlayer);
          }
          $params = explode("|", $gamestate->decisionQueue[2]);
          CloseDecisionQueue();
          if($gamestate->turn[0] == "B" && count($gamestate->layers) == 0) //If a layer is not created
          {
            PlayCardEffect($params[0], $params[1], $params[2], "-", $params[3], $params[4]);
          } else {
            //params 3 = ability index
            //params 4 = Unique ID
            $additionalCosts = GetClassState($gamestate->currentPlayer, $CS_AdditionalCosts);
            if($additionalCosts == "") $additionalCosts = "-";
            $layerIndex = count($gamestate->layers) - GetClassState($gamestate->currentPlayer, $CS_LayerPlayIndex);
            $gamestate->layers[$layerIndex + 2] = $params[1] . "|" . $params[2] . "|" . $params[3] . "|" . $params[4];
            $gamestate->layers[$layerIndex + 4] = $additionalCosts;
            ProcessDecisionQueue();
            return;
          }
          break;
        case "RESUMEPAYING":
          $player = $gamestate->decisionQueue[1];
          $params = explode("-", $gamestate->decisionQueue[2]); //Parameter
          if($lastResult == "") $lastResult = 0;
          CloseDecisionQueue();
          if($gamestate->currentPlayer != $player) {
            $gamestate->currentPlayer = $player;
            $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($gamestate->currentPlayer);
          }
          PlayCard($params[0], $params[1], $lastResult, $params[2]);
          break;
        case "RESOLVEATTACK":
          CloseDecisionQueue();
          ResolveAttack();
          break;
        case "RESOLVECOMBATDAMAGE":
          $parameter = $gamestate->decisionQueue[2];
          if($parameter != "-") $damageDone = $parameter;
          else $damageDone = $gamestate->dqState[6];
          CloseDecisionQueue();
          ResolveCombatDamage($damageDone);
          break;
        case "PASSTURN":
          CloseDecisionQueue();
          PassTurn();
          break;
        default:
          CloseDecisionQueue();
          FinalizeAction();
          break;
      }
      ProcessDecisionQueue();
      return;
    }
      /*global $gamestate->stackToAddNewTriggers, $gamestate->currentlyResolvingStack;
      if (!$gamestate->stackToAddNewTriggers->IsEmpty()) {
        $gamestate->currentlyResolvingStack =& $gamestate->stackToAddNewTriggers;
        $gamestate->stackToAddNewTriggers = new Stack();
      }*/
  //Carry out the next DecisionQueue step.
  $phase = array_shift($gamestate->decisionQueue);
  $player = array_shift($gamestate->decisionQueue);
  $parameter = array_shift($gamestate->decisionQueue);
  // WriteLog("->" . $phase . " " . $player . " Param:" . $parameter . " LR:" . $lastResult);//Uncomment this to visualize decision queue execution
  $parameter = str_replace("{I}", $gamestate->dqState[5], $parameter);
  if(count($gamestate->dqVars) > 0) {
    if(str_contains($parameter, "{0}")) $parameter = str_replace("{0}", $gamestate->dqVars[0], $parameter);
    if(str_contains($parameter, "<0>")) $parameter = str_replace("<0>", CardLink($gamestate->dqVars[0], $gamestate->dqVars[0]), $parameter);
    if(str_contains($parameter, "{1}")) $parameter = str_replace("{1}", $gamestate->dqVars[1], $parameter);
    if(str_contains($parameter, "<1>")) $parameter = str_replace("<1>", CardLink($gamestate->dqVars[1], $gamestate->dqVars[1]), $parameter);
    if(str_contains($parameter, "{2}")) $parameter = str_replace("{2}", $gamestate->dqVars[2], $parameter);
    $parameter = str_replace(" ", "_", $parameter);//CardLink()s contain spaces, which can break things if this $parameter makes it to WriteGamestate.php(such as if $phase is YESNO). But CardLink() is also used in some cases where the underscores would show up directly, so I fix this here.
  }
  $subsequent = array_shift($gamestate->decisionQueue);
  $makeCheckpoint = array_shift($gamestate->decisionQueue);
  $gamestate->turn[0] = $phase;
  $gamestate->turn[1] = $player;
  $gamestate->currentPlayer = $player;
  $gamestate->turn[2] = ($parameter == "<-" ? $lastResult : $parameter);
  $return = "PASS";
  if($subsequent != 1 || is_array($lastResult) || strval($lastResult) != "PASS") $return = DecisionQueueStaticEffect($phase, $player, ($parameter == "<-" ? $lastResult : $parameter), $lastResult);
  if($parameter == "<-" && !is_array($lastResult) && $lastResult == "-1") $return = "PASS"; //Collapse the rest of the queue if this decision point has invalid parameters
  if(is_array($return) || strval($return) != "NOTSTATIC") {
    if($phase != "SETDQCONTEXT") $gamestate->dqState[4] = "-"; //Clear out context for static states -- context only persists for one choice
    ContinueDecisionQueue($return);
  }
}

function AddAfterCombatLayer($cardID, $player, $parameter, $target = "-", $additionalCosts = "-", $uniqueID = "-") {
  global $gamestate, $AS_AfterAttackLayers;
  if($gamestate->attackState[$AS_AfterAttackLayers] == "NA") $gamestate->attackState[$AS_AfterAttackLayers] = $cardID . "~" . $player . "~" . $parameter . "~" . $target . "~" . $additionalCosts . "~" . $uniqueID;
  else $gamestate->attackState[$AS_AfterAttackLayers] .= "|" . $cardID . "~" . $player . "~" . $parameter . "~" . $target . "~" . $additionalCosts . "~" . $uniqueID;
}

function ProcessAfterCombatLayer() {
  global $gamestate, $AS_AfterAttackLayers;
  if($gamestate->attackState[$AS_AfterAttackLayers] == "NA") return;
  $gamestate->layers = explode("|", $gamestate->attackState[$AS_AfterAttackLayers]);
  $gamestate->attackState[$AS_AfterAttackLayers] = "NA";
  for($i = 0; $i < count($gamestate->layers); $i++) {
    $layer = explode("~", $gamestate->layers[$i]);
    AddLayer($layer[0], $layer[1], $layer[2], $layer[3], $layer[4], $layer[5], append:true);
  }
}

function ProcessLayer($player, $parameter)
{
  switch ($parameter) {
    case "PHANTASM":
      PhantasmLayer();
      break;
    default: break;
  }
}

function ProcessTrigger($player, $parameter, $uniqueID, $additionalCosts, $target="-", $triggerCode="-")
{
  if($triggerCode != "-"){
    return $triggerCode();
  }
  global $gamestate, $AS_IsAmbush;
  $items = &GetItems($player);
  $character = &GetPlayerCharacter($player);
  $auras = &GetAuras($player);
  $gamestate->EffectContext = $parameter;
  switch ($parameter) {
    case "AMBUSH":
      $index = SearchAlliesForUniqueID($uniqueID, $player);
      AddDecisionQueue("YESNO", $player, "if_you_want_to_resolve_the_ambush_attack");
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("PASSPARAMETER", $player, 1, 1);
      AddDecisionQueue("SETATTACKSTATE", $player, $AS_IsAmbush, 1);
      AddDecisionQueue("PASSPARAMETER", $player, "MYALLY-" . $index, 1);
      AddDecisionQueue("MZOP", $player, "ATTACK", 1);
      break;
    case "SHIELDED":
      $index = SearchAlliesForUniqueID($uniqueID, $player);
      $ally = new Ally("MYALLY-" . $index, $player);
      $ally->Attach("8752877738");//Shield Token
      break;
    case "PLAYALLY":
      PlayAlly($target, $player, from:"CAPTIVE");
      break;
    case "AFTERPLAYABILITY":
      $arr = explode(",", $uniqueID);
      $abilityID = $arr[0];
      $uniqueID = $arr[1];
      AllyPlayCardAbility($target, $player, from: $additionalCosts, abilityID:$abilityID, uniqueID:$uniqueID);
      break;
    case "9642863632"://Bounty Hunter's Quarry
      AddCurrentTurnEffect($parameter, $player);
      AddDecisionQueue("MZMYDECKTOPX", $player, $target);
      AddDecisionQueue("SETDQCONTEXT", $player, "Choose a card to play");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
      AddDecisionQueue("MZOP", $player, "PLAYCARD", 1);
      break;
    case "7642980906"://Stolen Landspeeder
      AddDecisionQueue("MULTIZONEINDICES", $player, "MYDISCARD:cardID=" . "7642980906");
      AddDecisionQueue("SETDQCONTEXT", $player, "Click the Stolen Landspeeder to play it for free.", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $player, "7642980906", 1);//Cost discount and experience adding.
      AddDecisionQueue("MZOP", $player, "PLAYCARD", 1);
      AddDecisionQueue("REMOVECURRENTEFFECT", $player, "7642980906");
      break;
    case "7270736993"://Unrefusable Offer
      //There's in theory a minor bug with this implementation: if there's a second copy of the bountied unit in the discard
      //it can be played even if the original unit is somehow removed from the discard before this trigger resolves.
      //I can't think of a way to prevent this without adding functionality to track a specific card between zones.
      global $CS_AfterPlayedBy;
      AddDecisionQueue("YESNO", $player, "if you want to play " . CardLink($target, $target) . " for free off of " . CardLink("7270736993", "7270736993"));
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("MULTIZONEINDICES", $player, "THEIRDISCARD:cardID=" . $target . ";maxCount=1", 1);
      AddDecisionQueue("SETDQVAR", $player, "0", 1);
      AddDecisionQueue("PASSPARAMETER", $player, "7270736993", 1);
      AddDecisionQueue("SETCLASSSTATE", $player, $CS_AfterPlayedBy, 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $player, "7270736993", 1);
      AddDecisionQueue("PASSPARAMETER", $player, "{0}", 1);
      AddDecisionQueue("MZOP", $player, "PLAYCARD", 1);
      break;
    default: break;
  }
}

function GetDQHelpText()
{
  global $gamestate;
  if(count($gamestate->dqState) < 5) return "-";
  return $gamestate->dqState[4];
}

function FinalizeAction()
{
  global $gamestate, $defPlayer, $makeBlockBackup, $mainPlayerGamestateStillBuilt;
  global $isPass, $inputMode;
  BuildMainPlayerGamestate();
  if($gamestate->turn[0] == "M") {
    if(AttackIsOngoing())
    {
      $gamestate->turn[0] = "B";
      $gamestate->currentPlayer = $defPlayer;
      $gamestate->turn[2] = "";
      $makeBlockBackup = 1;
    } else {
      $gamestate->turn[0] = "M";
      $gamestate->currentPlayer = $gamestate->mainPlayer;
      $gamestate->turn[2] = "";
      if(!$isPass || $inputMode == 99) SwapTurn();
      $isPass = false;
    }
  } else if($gamestate->turn[0] == "A") {
    $gamestate->currentPlayer = $gamestate->mainPlayer;
    $gamestate->turn[2] = "";
  } else if($gamestate->turn[0] == "D") {
    $gamestate->turn[0] = "A";
    $gamestate->currentPlayer = $gamestate->mainPlayer;
    $gamestate->turn[2] = "";
  }
  return 0;
}

function IsReactionPhase()
{
  global $gamestate;
  if($gamestate->turn[0] == "A" || $gamestate->turn[0] == "D") return true;
  if(count($gamestate->dqState) >= 2 && ($gamestate->dqState[1] == "A" || $gamestate->dqState[1] == "D")) return true;
  return false;
}

//Return whether priority should be held for the player by default/settings
function ShouldHoldPriority($player, $layerCard = "")
{
  global $gamestate;
  $prioritySetting = HoldPrioritySetting($player);
  if($prioritySetting == 0 || $prioritySetting == 1) return 1;
  if(($prioritySetting == 2 || $prioritySetting == 3) && $player != $gamestate->mainPlayer) return 1;
  return 0;
}

function EndTurnProcedure($player) {
  $allies = &GetAllies($player);
  for($i = 0; $i < count($allies); $i += AllyPieces()) {
    $ally = new Ally("MYALLY-" . $i, $player);
    $ally->Ready();
  }
  $resources = &GetResourceCards($player);
  for($i=0; $i<count($resources); $i+=ResourcePieces()) {
    $resources[$i+4] = "0";
  }
  Draw($player);
  Draw($player);
  MZMoveCard($player, "MYHAND", "MYRESOURCES", may:true, context:"Choose a card to resource", silent:true);
  AddDecisionQueue("AFTERRESOURCE", $player, "HAND", 1);
}

function DiscardHand($player)
{
  $hand = &GetHand($player);
  for($i = count($hand)-HandPieces(); $i>=0; $i-=HandPieces()) {
    DiscardCard($player, $i);
  }
}

function Opt($cardID, $amount)
{
  global $gamestate;
  PlayerOpt($gamestate->currentPlayer, $amount);
}

function PlayerOpt($player, $amount, $optKeyword = true)
{
  AddDecisionQueue("FINDINDICES", $player, "DECKTOPXREMOVE," . $amount);
  AddDecisionQueue("OPT", $player, "<-", 1);
}

function DiscardRandom($player = "", $source = "")
{
  global $gamestate;
  if($player == "") $player = $gamestate->currentPlayer;
  $hand = &GetHand($player);
  if(count($hand) == 0) return "";
  $index = GetRandom() % count($hand);
  $discarded = $hand[$index];
  unset($hand[$index]);
  $hand = array_values($hand);
  AddGraveyard($discarded, $player, "HAND");
  WriteLog(CardLink($discarded, $discarded) . " was randomly discarded");
  CardDiscarded($player, $discarded, $source);
  DiscardedAtRandomEffects($player, $discarded, $source);
  return $discarded;
}

function DiscardedAtRandomEffects($player, $discarded, $source) {
  switch($discarded) {
    default: break;
  }
}

function DiscardCard($player, $index)
{
  $hand = &GetHand($player);
  $discarded = RemoveHand($player, $index);
  AddGraveyard($discarded, $player, "HAND");
  CardDiscarded($player, $discarded);
  return $discarded;
}

function CardDiscarded($player, $discarded, $source = "")
{
  global $gamestate;
  AllyCardDiscarded($player, $discarded);
  AddEvent("DISCARD", $discarded);
}

function DestroyFrozenArsenal($player)
{
  $arsenal = &GetArsenal($player);
  for($i = 0; $i < count($arsenal); $i += ArsenalPieces()) {
    if($arsenal[$i + 4] == "1") {
      DestroyArsenal($player);
    }
  }
}

function SharesAspect($card1, $card2)
{
  $c1Aspects = explode(",", CardAspects($card1));
  $c2Aspects = explode(",", CardAspects($card2));
  for($i=0; $i<count($c1Aspects); $i++) {
    for($j=0; $j<count($c2Aspects); $j++) {
      if($c1Aspects[$i] == $c2Aspects[$j]) return true;
    }
  }
  return false;
}

function BlackOne($player) {
  AddDecisionQueue("SETDQCONTEXT", $player, "Do you want to discard your hand to draw 3?");
  AddDecisionQueue("YESNO", $player, "-");
  AddDecisionQueue("NOPASS", $player, "-");
  AddDecisionQueue("OP", $player, "DISCARDHAND", 1);
  AddDecisionQueue("DRAW", $player, "-", 1);
  AddDecisionQueue("DRAW", $player, "-", 1);
  AddDecisionQueue("DRAW", $player, "-", 1);
  
}