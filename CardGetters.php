<?php

//Player == currentplayer
function &GetMZZone($player, $zone)
{
  global $gamestate;
  $rv = "";
  if ($zone == "MYCHAR" || $zone == "THEIRCHAR") $rv = &GetPlayerCharacter($player);
  else if ($zone == "ALLY" || $zone == "MYALLY" || $zone == "THEIRALLY") $rv = &GetAllies($player);
  else if ($zone == "MYHAND" || $zone == "THEIRHAND") $rv = &GetHand($player);
  else if ($zone == "MYDISCARD" || $zone == "THEIRDISCARD") $rv = &GetDiscard($player);
  else if ($zone == "DECK" || $zone == "MYDECK" || $zone == "THEIRDECK") $rv = &GetDeck($player);
  else if ($zone == "LAYER") return $gamestate->layers;
  return $rv;
}

/*
function GetMZPieces($zone)
{
  if($zone == "MYCHAR" || $zone == "THEIRCHAR") return CharacterPieces();
  else if($zone == "MYAURAS" || $zone == "THEIRAURAS") return AuraPieces();
}
*/

function &GetPlayerCharacter($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $mainCharacter, $defCharacter, $myCharacter, $theirCharacter;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainCharacter;
    else return $defCharacter;
  } else {
    if ($player == $myStateBuiltFor) return $myCharacter;
    else return $theirCharacter;
  }
}

function &GetPlayerClassState($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myClassState, $theirClassState, $mainClassState, $defClassState;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainClassState;
    else return $defClassState;
  } else {
    if ($player == $myStateBuiltFor) return $myClassState;
    else return $theirClassState;
  }
}

function GetClassState($player, $piece)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myClassState, $theirClassState, $mainClassState, $defClassState;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainClassState[$piece];
    else return $defClassState[$piece];
  } else {
    if ($player == $myStateBuiltFor) return $myClassState[$piece];
    else return $theirClassState[$piece];
  }
}

function &GetDeck($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myDeck, $theirDeck, $mainDeck, $defDeck;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainDeck;
    else return $defDeck;
  } else {
    if ($player == $myStateBuiltFor) return $myDeck;
    else return $theirDeck;
  }
}

function &GetHand($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myHand, $theirHand, $mainHand, $defHand;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainHand;
    else return $defHand;
  } else {
    if ($player == $myStateBuiltFor) return $myHand;
    else return $theirHand;
  }
}

function &GetDamage($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myDamage, $theirDamage, $mainDamage, $defDamage;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainDamage;
    else return $defDamage;
  } else {
    if ($player == $myStateBuiltFor) return $myDamage;
    else return $theirDamage;
  }
}

function &GetResources($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myResources, $theirResources, $mainResources, $defResources;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainResources;
    else return $defResources;
  } else {
    if ($player == $myStateBuiltFor) return $myResources;
    else return $theirResources;
  }
}

function &GetMaterial($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myMaterial, $theirMaterial, $mainMaterial, $defMaterial;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainMaterial;
    else return $defMaterial;
  } else {
    if ($player == $myStateBuiltFor) return $myMaterial;
    else return $theirMaterial;
  }
}

function &GetDiscard($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myDiscard, $theirDiscard, $mainDiscard, $defDiscard;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainDiscard;
    else return $defDiscard;
  } else {
    if ($player == $myStateBuiltFor) return $myDiscard;
    else return $theirDiscard;
  }
}

function &GetResourceCards($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myArsenal, $theirArsenal, $mainArsenal, $defArsenal;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainArsenal;
    else return $defArsenal;
  } else {
    if ($player == $myStateBuiltFor) return $myArsenal;
    else return $theirArsenal;
  }
}

function &GetCardStats($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myCardStats, $theirCardStats, $mainCardStats, $defCardStats;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainCardStats;
    else return $defCardStats;
  } else {
    if ($player == $myStateBuiltFor) return $myCardStats;
    else return $theirCardStats;
  }
}

function &GetTurnStats($player)
{
  global $gamestate, $mainPlayerGamestateStillBuilt;
  global $myTurnStats, $theirTurnStats, $mainTurnStats, $defTurnStats;
  global $myStateBuiltFor;
  if ($mainPlayerGamestateStillBuilt) {
    if ($player == $gamestate->mainPlayer) return $mainTurnStats;
    else return $defTurnStats;
  } else {
    if ($player == $myStateBuiltFor) return $myTurnStats;
    else return $theirTurnStats;
  }
}

function &GetAllies($player)
{
  global $gamestate;
  if ($player == 1) return $gamestate->p1Allies;
  else return $gamestate->p2Allies;
}

function &GetSettings($player)
{
  global $gamestate;
  if ($player == 1) return $gamestate->p1Settings;
  else return $gamestate->p2Settings;
}

function HeroCard($player) {
  $character = &GetPlayerCharacter($player);
  return count($character) > CharacterPieces() ? $character[CharacterPieces()] : "";
}

function HasTakenDamage($player)
{
  global $CS_DamageTaken;
  return GetClassState($player, $CS_DamageTaken) > 0;
}

function GetPlayerBase($player)
{
  $character = &GetPlayerCharacter($player);
  return $character[0];
}