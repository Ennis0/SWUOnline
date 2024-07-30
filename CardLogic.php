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
  global $currentPlayer;
  if($player == "") $player = $currentPlayer;
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
  global $currentTurnEffects;
  $card = explode("-", $cardID)[0];
  if(CardType($card) == "A" && AttackIsOngoing() && IsCombatEffectActive($cardID) && !IsCombatEffectPersistent($cardID) && $from != "PLAY") {
    AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID);
    return;
  }
  array_push($currentTurnEffects, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
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
  global $currentTurnEffects, $afterResolveEffects;
  for($i = 0; $i < count($afterResolveEffects); $i += CurrentTurnEffectPieces()) {
    array_push($currentTurnEffects, $afterResolveEffects[$i], $afterResolveEffects[$i+1], $afterResolveEffects[$i+2], $afterResolveEffects[$i+3]);
  }
  $afterResolveEffects = [];
}

//This is needed because if you add a current turn effect from combat, it could get deleted as part of the combat resolution
function AddCurrentTurnEffectFromCombat($cardID, $player, $uniqueID = -1)
{
  global $currentTurnEffectsFromCombat;
  array_push($currentTurnEffectsFromCombat, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function CopyCurrentTurnEffectsFromCombat()
{
  global $currentTurnEffects, $currentTurnEffectsFromCombat;
  for($i = 0; $i < count($currentTurnEffectsFromCombat); $i += CurrentTurnEffectPieces()) {
    array_push($currentTurnEffects, $currentTurnEffectsFromCombat[$i], $currentTurnEffectsFromCombat[$i+1], $currentTurnEffectsFromCombat[$i+2], $currentTurnEffectsFromCombat[$i+3]);
  }
  $currentTurnEffectsFromCombat = [];
}

function RemoveCurrentTurnEffect($index)
{
  global $currentTurnEffects;
  unset($currentTurnEffects[$index+3]);
  unset($currentTurnEffects[$index+2]);
  unset($currentTurnEffects[$index+1]);
  unset($currentTurnEffects[$index]);
  $currentTurnEffects = array_values($currentTurnEffects);
}

function CurrentTurnEffectPieces()
{
  return 4;
}

function CurrentTurnEffectUses($cardID)
{
  switch ($cardID) {
    case "EVR033": return 6;
    case "EVR034": return 5;
    case "EVR035": return 4;
    case "UPR000": return 3;
    case "UPR088": return 4;
    case "UPR221": return 4;
    case "UPR222": return 3;
    case "UPR223": return 2;
    default: return 1;
  }
}

function AddNextTurnEffect($cardID, $player, $uniqueID = -1)
{
  global $nextTurnEffects;
  array_push($nextTurnEffects, $cardID, $player, $uniqueID, CurrentTurnEffectUses($cardID));
}

function IsCombatEffectLimited($index)
{
  global $currentTurnEffects, $mainPlayer, $attackState, $AS_AttackerIndex, $AS_AttackerUniqueID;
  if(!AttackIsOngoing() || $currentTurnEffects[$index + 2] == -1) return false;
  $allies = &GetAllies($mainPlayer);
  if(count($allies) < $attackState[$AS_AttackerIndex] + 5) return false;
  if($allies[$attackState[$AS_AttackerIndex] + 5] != $currentTurnEffects[$index + 2]) return true;
  return false;
}

function IsAbilityLayer($cardID)
{
  return $cardID == "TRIGGER" || $cardID == "PLAYABILITY" || $cardID == "ATTACKABILITY" || $cardID == "ACTIVATEDABILITY" || $cardID == "AFTERPLAYABILITY";
}

function AddDecisionQueue($phase, $player, $parameter, $subsequent = 0, $makeCheckpoint = 0)
{
  global $decisionQueue;
  if(count($decisionQueue) == 0) $insertIndex = 0;
  else {
    $insertIndex = count($decisionQueue) - DecisionQueuePieces();
    if(!IsGamePhase($decisionQueue[$insertIndex])) //Stack must be clear before you can continue with the step
    {
      $insertIndex = count($decisionQueue);
    }
  }

  $parameter = str_replace(" ", "_", $parameter);
  array_splice($decisionQueue, $insertIndex, 0, $phase);
  array_splice($decisionQueue, $insertIndex + 1, 0, $player);
  array_splice($decisionQueue, $insertIndex + 2, 0, $parameter);
  array_splice($decisionQueue, $insertIndex + 3, 0, $subsequent);
  array_splice($decisionQueue, $insertIndex + 4, 0, $makeCheckpoint);
}

function PrependDecisionQueue($phase, $player, $parameter, $subsequent = 0, $makeCheckpoint = 0)
{
  global $decisionQueue;
  if($parameter == null || $parameter == "") return;
  $parameter = str_replace(" ", "_", $parameter);
  array_unshift($decisionQueue, $makeCheckpoint);
  array_unshift($decisionQueue, $subsequent);
  array_unshift($decisionQueue, $parameter);
  array_unshift($decisionQueue, $player);
  array_unshift($decisionQueue, $phase);
}

function IsDecisionQueueActive()
{
  global $dqState;
  return $dqState[0] == "1";
}

function ProcessDecisionQueue()
{
  global $turn, $decisionQueue, $dqState;
  if($dqState[0] != "1") {
    if(count($turn) < 3) $turn[2] = "-";
    $dqState[0] = "1"; //If the decision queue is currently active/processing
    $dqState[1] = $turn[0];
    $dqState[2] = $turn[1];
    $dqState[3] = $turn[2];
    $dqState[4] = "-"; //DQ helptext initial value
    $dqState[5] = "-"; //Decision queue multizone indices
    $dqState[6] = "0"; //Damage dealt
    $dqState[7] = "0"; //Target
    ContinueDecisionQueue("");
  }
}

function CloseDecisionQueue()
{
  global $turn, $decisionQueue, $dqState, $currentPlayer, $mainPlayer;
  $dqState[0] = "0";
  $turn[0] = $dqState[1];
  $turn[1] = $dqState[2];
  $turn[2] = $dqState[3];
  $dqState[4] = "-"; //Clear the context, just in case
  $dqState[5] = "-"; //Clear Decision queue multizone indices
  $dqState[6] = "0"; //Damage dealt
  $dqState[7] = "0"; //Target
  $decisionQueue = [];
  if(($turn[0] == "D" || $turn[0] == "A") && !AttackIsOngoing()) {
    $currentPlayer = $mainPlayer;
    $turn[0] = "M";
  }
}

//Returns true when the given player should be given the choice to arrange triggers or to choose which player to resolve triggers first.
function ShouldHoldPriorityNow($player) 
{
  global $mainPlayer;
  $innermostTriggerStack = InnermostTriggerStack();
  $p1TriggerCount = count(array_filter($innermostTriggerStack, function($a){return $a->Player() == 1;}));
  $p2TriggerCount = count(array_filter($innermostTriggerStack, function($a){return $a->Player() == 2;}));
  if($p1TriggerCount > 0 && $p2TriggerCount > 0) return $player == $mainPlayer; //If both players have triggers to resolve, the active player chooses which player gets to resolve theirs first.
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
  global $decisionQueue, $turn, $currentPlayer, $mainPlayerGamestateStillBuilt, $makeCheckpoint, $otherPlayer;
  global $dqVars, $dqState, $CS_PlayIndex, $CS_AdditionalCosts, $mainPlayer, $CS_LayerPlayIndex;
  if(count($decisionQueue) == 0) {
    //Resolve triggers, or next other layer if there are none.
    if(!$currentlyResolvingStack->IsEmpty()) {
      switch($currentlyResolvingStack->GetDecisionState()) {
        case "PLAYERORDER":
          AddDecisionQueue("CHOOSEWHICHPLAYERSTRIGGERS", $mainPlayer, "-");
          break;
        case "P1TRIGGERORDER":
          AddDecisionQueue("CHOOSETRIGGERORDER", 1, "-");
          break;
        case "P2TRIGGERORDER":
          AddDecisionQueue("CHOOSETRIGGERORDER", 2, "-");
          break;
        default: //No ordering decisions need to be made, resolve the next trigger.
          $currentlyResolvingStack->ResolveNextTrigger();
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
        else if($layerType == "RESUMETURN") $turn[0] = "M";
        else if($layerType == "LAYER") ProcessLayer($player, $parameter);
        else if($layerType == "FINALIZEATTACK") FinalizeAttack($parameter);
        else if($layerType == "DEFENDSTEP") { $turn[0] = "A"; $currentPlayer = $mainPlayer; }
      }
      return;
    }
    else if(IsGamePhase($decisionQueue[0])) {
      switch($decisionQueue[0]) {
        case "RESUMEPLAY":
          if($currentPlayer != $decisionQueue[1]) {
            $currentPlayer = $decisionQueue[1];
            $otherPlayer = $currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($currentPlayer);
          }
          $params = explode("|", $decisionQueue[2]);
          CloseDecisionQueue();
          if($turn[0] == "B" && count($layers) == 0) //If a layer is not created
          {
            PlayCardEffect($params[0], $params[1], $params[2], "-", $params[3], $params[4]);
          } else {
            //params 3 = ability index
            //params 4 = Unique ID
            $additionalCosts = GetClassState($currentPlayer, $CS_AdditionalCosts);
            if($additionalCosts == "") $additionalCosts = "-";
            $layerIndex = count($layers) - GetClassState($currentPlayer, $CS_LayerPlayIndex);
            $layers[$layerIndex + 2] = $params[1] . "|" . $params[2] . "|" . $params[3] . "|" . $params[4];
            $layers[$layerIndex + 4] = $additionalCosts;
            ProcessDecisionQueue();
            return;
          }
          break;
        case "RESUMEPAYING":
          $player = $decisionQueue[1];
          $params = explode("-", $decisionQueue[2]); //Parameter
          if($lastResult == "") $lastResult = 0;
          CloseDecisionQueue();
          if($currentPlayer != $player) {
            $currentPlayer = $player;
            $otherPlayer = $currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($currentPlayer);
          }
          PlayCard($params[0], $params[1], $lastResult, $params[2]);
          break;
        case "RESOLVEATTACK":
          CloseDecisionQueue();
          ResolveAttack();
          break;
        case "RESOLVECOMBATDAMAGE":
          $parameter = $decisionQueue[2];
          if($parameter != "-") $damageDone = $parameter;
          else $damageDone = $dqState[6];
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
      /*global $stackToAddNewTriggers, $currentlyResolvingStack;
      if (!$stackToAddNewTriggers->IsEmpty()) {
        $currentlyResolvingStack =& $stackToAddNewTriggers;
        $stackToAddNewTriggers = new Stack();
      }*/
  //Carry out the next DecisionQueue step.
  $phase = array_shift($decisionQueue);
  $player = array_shift($decisionQueue);
  $parameter = array_shift($decisionQueue);
  // WriteLog("->" . $phase . " " . $player . " Param:" . $parameter . " LR:" . $lastResult);//Uncomment this to visualize decision queue execution
  $parameter = str_replace("{I}", $dqState[5], $parameter);
  if(count($dqVars) > 0) {
    if(str_contains($parameter, "{0}")) $parameter = str_replace("{0}", $dqVars[0], $parameter);
    if(str_contains($parameter, "<0>")) $parameter = str_replace("<0>", CardLink($dqVars[0], $dqVars[0]), $parameter);
    if(str_contains($parameter, "{1}")) $parameter = str_replace("{1}", $dqVars[1], $parameter);
    if(str_contains($parameter, "<1>")) $parameter = str_replace("<1>", CardLink($dqVars[1], $dqVars[1]), $parameter);
    if(str_contains($parameter, "{2}")) $parameter = str_replace("{2}", $dqVars[2], $parameter);
    $parameter = str_replace(" ", "_", $parameter);//CardLink()s contain spaces, which can break things if this $parameter makes it to WriteGamestate.php(such as if $phase is YESNO). But CardLink() is also used in some cases where the underscores would show up directly, so I fix this here.
  }
  $subsequent = array_shift($decisionQueue);
  $makeCheckpoint = array_shift($decisionQueue);
  $turn[0] = $phase;
  $turn[1] = $player;
  $currentPlayer = $player;
  $turn[2] = ($parameter == "<-" ? $lastResult : $parameter);
  $return = "PASS";
  if($subsequent != 1 || is_array($lastResult) || strval($lastResult) != "PASS") $return = DecisionQueueStaticEffect($phase, $player, ($parameter == "<-" ? $lastResult : $parameter), $lastResult);
  if($parameter == "<-" && !is_array($lastResult) && $lastResult == "-1") $return = "PASS"; //Collapse the rest of the queue if this decision point has invalid parameters
  if(is_array($return) || strval($return) != "NOTSTATIC") {
    if($phase != "SETDQCONTEXT") $dqState[4] = "-"; //Clear out context for static states -- context only persists for one choice
    ContinueDecisionQueue($return);
  }
}

function AddAfterCombatLayer($cardID, $player, $parameter, $target = "-", $additionalCosts = "-", $uniqueID = "-") {
  global $attackState, $AS_AfterAttackLayers;
  if($attackState[$AS_AfterAttackLayers] == "NA") $attackState[$AS_AfterAttackLayers] = $cardID . "~" . $player . "~" . $parameter . "~" . $target . "~" . $additionalCosts . "~" . $uniqueID;
  else $attackState[$AS_AfterAttackLayers] .= "|" . $cardID . "~" . $player . "~" . $parameter . "~" . $target . "~" . $additionalCosts . "~" . $uniqueID;
}

function ProcessAfterCombatLayer() {
  global $attackState, $AS_AfterAttackLayers;
  if($attackState[$AS_AfterAttackLayers] == "NA") return;
  $layers = explode("|", $attackState[$AS_AfterAttackLayers]);
  $attackState[$AS_AfterAttackLayers] = "NA";
  for($i = 0; $i < count($layers); $i++) {
    $layer = explode("~", $layers[$i]);
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
  global $EffectContext, $AS_IsAmbush;
  $items = &GetItems($player);
  $character = &GetPlayerCharacter($player);
  $auras = &GetAuras($player);
  $EffectContext = $parameter;
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
  global $dqState;
  if(count($dqState) < 5) return "-";
  return $dqState[4];
}

function FinalizeAction()
{
  global $currentPlayer, $mainPlayer, $actionPoints, $turn, $defPlayer, $makeBlockBackup, $mainPlayerGamestateStillBuilt;
  global $isPass, $inputMode;
  BuildMainPlayerGamestate();
  if($turn[0] == "M") {
    if(AttackIsOngoing())
    {
      $turn[0] = "B";
      $currentPlayer = $defPlayer;
      $turn[2] = "";
      $makeBlockBackup = 1;
    } else {
      $turn[0] = "M";
      $currentPlayer = $mainPlayer;
      $turn[2] = "";
      if(!$isPass || $inputMode == 99) SwapTurn();
      $isPass = false;
    }
  } else if($turn[0] == "A") {
    $currentPlayer = $mainPlayer;
    $turn[2] = "";
  } else if($turn[0] == "D") {
    $turn[0] = "A";
    $currentPlayer = $mainPlayer;
    $turn[2] = "";
  }
  return 0;
}

function IsReactionPhase()
{
  global $turn, $dqState;
  if($turn[0] == "A" || $turn[0] == "D") return true;
  if(count($dqState) >= 2 && ($dqState[1] == "A" || $dqState[1] == "D")) return true;
  return false;
}

//Return whether priority should be held for the player by default/settings
function ShouldHoldPriority($player, $layerCard = "")
{
  global $mainPlayer;
  $prioritySetting = HoldPrioritySetting($player);
  if($prioritySetting == 0 || $prioritySetting == 1) return 1;
  if(($prioritySetting == 2 || $prioritySetting == 3) && $player != $mainPlayer) return 1;
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
  global $currentPlayer;
  PlayerOpt($currentPlayer, $amount);
}

function PlayerOpt($player, $amount, $optKeyword = true)
{
  AddDecisionQueue("FINDINDICES", $player, "DECKTOPXREMOVE," . $amount);
  AddDecisionQueue("OPT", $player, "<-", 1);
}

function DiscardRandom($player = "", $source = "")
{
  global $currentPlayer;
  if($player == "") $player = $currentPlayer;
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
  global $mainPlayer;
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