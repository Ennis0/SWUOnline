<?php

function AddBottomDeck($cardID, $player, $from)
{
  $deck = &GetDeck($player);
  $deck[] = $cardID;
}

function AddTopDeck($cardID, $player, $from)
{
  $deck = &GetDeck($player);
  array_unshift($deck, $cardID);
}

function AddPlayerHand($cardID, $player, $from)
{
  $hand = &GetHand($player);
  $hand[] = $cardID;
}

function RemoveHand($player, $index)
{
  $hand = &GetHand($player);
  if(count($hand) == 0) return "";
  $cardID = $hand[$index];
  for($j = $index + HandPieces() - 1; $j >= $index; --$j) unset($hand[$j]);
  $hand = array_values($hand);
  return $cardID;
}

function GainResources($player, $amount)
{
  $resources = &GetResources($player);
  $resources[0] += $amount;
}

function AddResourceCost($player, $amount)
{
  $resources = &GetResources($player);
  $resources[1] += $amount;
}

function RemovePitch($player, $index)
{
  $pitch = &GetPitch($player);
  $cardID = $pitch[$index];
  unset($pitch[$index]);
  $pitch = array_values($pitch);
  return $cardID;
}

function AddCharacter($cardID, $player, $counters=0, $status=2)
{
  $char = &GetPlayerCharacter($player);
  $char[] = $cardID;
  $char[] = $status;
  $char[] = $counters;
  $char[] = 0;
  $char[] = 0;
  $char[] = 1;
  $char[] = 0;
  $char[] = 0;
  $char[] = 0;
  $char[] = 2;
  $char[] = 0;
}

function AddMemory($cardID, $player, $from, $facing, $counters=0)
{
  $arsenal = &GetArsenal($player);
  $arsenal[] = $cardID;
  $arsenal[] = $facing;
  $arsenal[] = 1; //Num uses - currently always 1
  $arsenal[] = $counters; //Counters
  $arsenal[] = "0"; //Is Frozen (1 = Frozen)
  $arsenal[] = GetUniqueId(); //Unique ID
}

function AddResources($cardID, $player, $from, $facing, $counters=0, $isExhausted="0")
{
  $arsenal = &GetArsenal($player);
  $arsenal[] = $cardID;
  $arsenal[] = $facing;
  $arsenal[] = 1; //Num uses - currently always 1
  $arsenal[] = $counters; //Counters
  $arsenal[] = $isExhausted; //Is Frozen (1 = Frozen)
  $arsenal[] = GetUniqueId(); //Unique ID
}

function ArsenalEndTurn($player)
{
  $arsenal = &GetArsenal($player);
  for($i = 0; $i < count($arsenal); $i += ArsenalPieces()) {
    $arsenal[$i + 2] = 1;//Num uses - currently always 1
  }
}

function SetArsenalFacing($facing, $player)
{
  $arsenal = &GetArsenal($player);
  for ($i = 0; $i < count($arsenal); $i += ArsenalPieces()) {
    if ($facing == "UP" && $arsenal[$i + 1] == "DOWN") {
      $arsenal[$i + 1] = "UP";
      ArsenalTurnFaceUpAbility($arsenal[$i], $player);
      return $arsenal[$i];
    }
  }
  return "";
}

function ArsenalTurnFaceUpAbility($cardID, $player)
{
  switch($cardID)
  {
    default: break;
  }
}

function AddHand($player, $cardID)
{
  $hand = &GetHand($player);
  $hand[] = $cardID;
  return count($hand) - 1;
}

function RemoveResource($player, $index)
{
  $arsenal = &GetArsenal($player);
  if(count($arsenal) == 0) return "";
  $cardID = $arsenal[$index];
  for($i = $index + ArsenalPieces() - 1; $i >= $index; --$i) {
    unset($arsenal[$i]);
  }
  $arsenal = array_values($arsenal);
  return $cardID;
}

function RemoveArsenal($player, $index)
{
  $arsenal = &GetArsenal($player);
  if(count($arsenal) == 0) return "";
  $cardID = $arsenal[$index];
  for($i = $index + ArsenalPieces() - 1; $i >= $index; --$i) {
    unset($arsenal[$i]);
  }
  $arsenal = array_values($arsenal);
  return $cardID;
}

function DestroyArsenal($player, $index=-1)
{
  $arsenal = &GetArsenal($player);
  $cardIDs = "";
  for($i = 0; $i < count($arsenal); $i += ArsenalPieces()) {
    if($index > -1 && $index != $i) continue;
    if($cardIDs != "") $cardIDs .= ",";
    $cardIDs .= $arsenal[$i];
    WriteLog(CardLink($arsenal[$i], $arsenal[$i]) . " was destroyed from the arsenal");
    AddGraveyard($arsenal[$i], $player, "ARS");
    for($j=$i+ArsenalPieces()-1; $j>=$i; --$j) unset($arsenal[$j]);
  }
  $arsenal = array_values($arsenal);
  return $cardIDs;
}

function AddMaterial($cardID, $player, $from)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  $material = &GetMaterial($player);
  $material[] = $cardID;
}

function RemoveMaterial($player, $index)
{
  $material = &GetMaterial($player);
  $cardID = $material[$index];
  for($i=$index+MaterialPieces()-1; $i>=$index; --$i)
  {
    unset($material[$i]);
  }
  $material = array_values($material);
  return $cardID;
}

function EffectArcaneBonus($cardID)
{
  $idArr = explode("-", $cardID);
  $cardID = $idArr[0];
  $modifier = (count($idArr) > 1 ? $idArr[1] : 0);
  switch($cardID)
  {
    case "ARC115": return 1;
    case "ARC122": return 1;
    case "ARC123": case "ARC124": case "ARC125": return 2;
    case "ARC129": return 3;
    case "ARC130": return 2;
    case "ARC131": return 1;
    case "ARC132": case "ARC133": case "ARC134": return intval($modifier);
    case "CRU161": return 1;
    case "CRU165": case "CRU166": case "CRU167": return 1;
    case "CRU171": case "CRU172": case "CRU173": return 1;
    case "DYN200": return 3;
    case "DYN201": return 2;
    case "DYN202": return 1;
    case "DYN209": case "DYN210": case "DYN211": return 1;
    default: return 0;
  }
}

function ConsumeDamagePrevention($player)
{
  global $CS_NextDamagePrevented;
  $prevention = GetClassState($player, $CS_NextDamagePrevented);
  SetClassState($player, $CS_NextDamagePrevented, 0);
  return $prevention;
}

function IncrementClassState($player, $piece, $amount = 1)
{
  SetClassState($player, $piece, (GetClassState($player, $piece) + $amount));
}

function DecrementClassState($player, $piece, $amount = 1)
{
  SetClassState($player, $piece, (GetClassState($player, $piece) - $amount));
}

function AppendClassState($player, $piece, $value, $allowRepeats = true)
{
  $currentState = GetClassState($player, $piece);
  if ($currentState == "-") $currentState = "";
  if (!$allowRepeats) {
    $currentStateArray = explode(",", $currentState);
    for($i = 0; $i < count($currentStateArray); ++$i) {
      if($currentStateArray[$i] == $value) return;
    }
  }
  if ($currentState != "") $currentState .= ",";
  $currentState .= $value;
  SetClassState($player, $piece, $currentState);
}

function SetClassState($player, $piece, $value)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myClassState, $theirClassState, $mainClassState, $defClassState;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) $mainClassState[$piece] = $value;
    else $defClassState[$piece] = $value;
  } else {
    if ($player == $myStateBuiltFor) $myClassState[$piece] = $value;
    else $theirClassState[$piece] = $value;
  }
}

function AddGraveyard($cardID, $player, $from, $modifier="-")
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myDiscard, $theirDiscard, $mainDiscard, $defDiscard;
  global $myStateBuiltFor, $CS_CardsEnteredGY;
  IncrementClassState($player, $CS_CardsEnteredGY);
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) AddSpecificGraveyard($cardID, $mainDiscard, $from, $player, $modifier);
    else AddSpecificGraveyard($cardID, $defDiscard, $from, $player, $modifier);
  } else {
    if ($player == $myStateBuiltFor) AddSpecificGraveyard($cardID, $myDiscard, $from, $player, $modifier);
    else AddSpecificGraveyard($cardID, $theirDiscard, $from, $player, $modifier);
  }
}

function RemoveDiscard($player, $index)
{
  return RemoveGraveyard($player, $index);
}

function RemoveGraveyard($player, $index)
{
  if($index == "") return "-";
  $discard = &GetDiscard($player);
  $cardID = $discard[$index];
  for($i=$index; $i<$index+DiscardPieces(); ++$i) { unset($discard[$i]); }
  $discard = array_values($discard);
  return $cardID;
}

function SearchCharacterAddUses($player, $uses, $type = "", $subtype = "")
{
  $character = &GetPlayerCharacter($player);
  for ($i = 0; $i < count($character); $i += CharacterPieces()) {
    if ($character[$i + 1] != 0 && ($type == "" || CardType($character[$i]) == $type) && ($subtype == "" || $subtype == CardSubtype($character[$i]))) {
      $character[$i + 1] = 2;
      $character[$i + 5] += $uses;
    }
  }
}

function AddSpecificGraveyard($cardID, &$graveyard, $from, $player, $modifier="-")
{
  if($cardID == "3991112153" && ($from == "HAND" || $from == "DECK")) $modifier = "TT";
  array_push($graveyard, $cardID, $modifier);
}

function NegateLayer($MZIndex, $goesWhere = "GY")
{
  global $gamestate;
  $params = explode("-", $MZIndex);
  $index = $params[1];
  $cardID = $gamestate->layers[$index];
  $player = $gamestate->layers[$index + 1];
  for ($i = $index + LayerPieces()-1; $i >= $index; --$i) {
    unset($gamestate->layers[$i]);
  }
  $gamestate->layers = array_values($gamestate->layers);
  switch ($goesWhere) {
    case "GY":
      AddGraveyard($cardID, $player, "LAYER");
      break;
    case "HAND":
      AddPlayerHand($cardID, $player, "LAYER");
      break;
    default:
      break;
  }
}

function AddAdditionalCost($player, $value)
{
  global $CS_AdditionalCosts;
  AppendClassState($player, $CS_AdditionalCosts, $value);
}

function ClearAdditionalCosts($player)
{
  global $CS_AdditionalCosts;
  SetClassState($player, $CS_AdditionalCosts, "-");
}

function FaceDownArsenalBotDeck($player)
{
  if(ArsenalHasFaceDownCard($player)) {
    AddDecisionQueue("FINDINDICES", $player, "ARSENALDOWN");
    AddDecisionQueue("CHOOSEARSENAL", $player, "<-", 1);
    AddDecisionQueue("REMOVEARSENAL", $player, "-", 1);
    AddDecisionQueue("ADDBOTDECK", $player, "-", 1);
  }
}
