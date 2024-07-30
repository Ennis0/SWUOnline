<?php

function PutCharacterIntoPlayForPlayer($cardID, $player)
{
  $char = &GetPlayerCharacter($player);
  $index = count($char);
  $char[] = $cardID;
  $char[] = 2;
  $char[] = CharacterCounters($cardID);
  $char[] = 0;
  $char[] = 0;
  $char[] = 1;
  $char[] = 0;
  $char[] = 0;
  $char[] = 0;
  $char[] = 2;
  $char[] = 0;
  return $index;
}

function CharacterCounters ($cardID)
{
  switch($cardID) {
    case "DYN492a": return 8;
    default: return 0;
  }
}

function CharacterStartTurnAbility($player)
{
  $character = &GetPlayerCharacter($player);
  for($i = 0; $i < count($character); $i += CharacterPieces()) {
    if($character[$i + 1] == 0 || $character[$i + 1] == 1) continue; //Do not process ability if it is destroyed
    switch($character[$i]) {
      case "1951911851"://Grand Admiral Thrawn
        $myDeck = &GetDeck($player);
        $theirDeck = &GetDeck($player == 1 ? 2 : 1);
        AddDecisionQueue("SETDQCONTEXT", $player, "The top of your deck is " . CardLink($myDeck[0], $myDeck[0]) . " and the top of their deck is " . CardLink($theirDeck[0], $theirDeck[0]));
        AddDecisionQueue("OK", $player, "-");
        break;
      default:
        break;
    }
  }
}

function DefCharacterStartTurnAbilities()
{
  global $defPlayer, $mainPlayer;
  $character = &GetPlayerCharacter($defPlayer);
  for($i = 0; $i < count($character); $i += CharacterPieces()) {
    if($character[$i + 1] == 0 || $character[$i + 1] == 1) continue; //Do not process ability if it is destroyed
    switch($character[$i]) {

      default:
        break;
    }
  }
}

function CharacterStaticHealthModifiers($cardID, $index, $player)
{
  $modifier = 0;
  $char = &GetPlayerCharacter($player);
  for($i=0; $i<count($char); $i+=CharacterPieces()) {
    switch($char[$i])
    {
      case "5784497124"://Emperor Palpatine
        if($cardID == "1780978508") $modifier += 1;//Royal Guard
        break;
      default: break;
    }
  }
  return $modifier;
}

function CharacterDestroyEffect($cardID, $player)
{
  switch($cardID) {

    default:
      break;
  }
}

function ResetCharacter($player) {
  $char = &GetPlayerCharacter($player);
  for ($i = 1; $i < count($char); $i += CharacterPieces()) {
    if ($char[$i + 6] == 1) $char[$i] = 0; //Destroy if it was flagged for destruction
    if ($char[$i] != 0) {
      $char[$i] = 2;
      $char[$i + 4] = CharacterNumUsesPerTurn($char[$i - 1]);
    }
  }
}

function CharacterCostModifier($cardID, $from)
{
  global $currentPlayer, $CS_NumSwordAttacks;
  $modifier = 0;
  if(CardSubtype($cardID) == "Sword" && GetClassState($currentPlayer, $CS_NumSwordAttacks) == 1 && SearchCharacterActive($currentPlayer, "CRU077")) {
    --$modifier;
  }
  return $modifier;
}

function EquipCard($player, $card)
{
  $char = &GetPlayerCharacter($player);
  $lastWeapon = 0;
  $replaced = 0;
  $numHands = 0;
  //Replace the first destroyed weapon; if none you can't re-equip
  for($i=CharacterPieces(); $i<count($char) && !$replaced; $i+=CharacterPieces())
  {
    if(CardType($char[$i]) == "W")
    {
      $lastWeapon = $i;
      if($char[$i+1] == 0)
      {
        $char[$i] = $card;
        $char[$i+1] = 2;
        $char[$i+2] = 0;
        $char[$i+3] = 0;
        $char[$i+4] = 0;
        $char[$i+5] = 1;
        $char[$i+6] = 0;
        $char[$i+7] = 0;
        $char[$i+8] = 0;
        $char[$i+9] = 2;
        $char[$i+10] = 0;
        $replaced = 1;
      }
      else if(Is1H($char[$i])) ++$numHands;
      else $numHands += 2;
    }
  }
  if($numHands < 2 && !$replaced)
  {
    $insertIndex = $lastWeapon + CharacterPieces();
    array_splice($char, $insertIndex, 0, $card);
    array_splice($char, $insertIndex+1, 0, 2);
    array_splice($char, $insertIndex+2, 0, 0);
    array_splice($char, $insertIndex+3, 0, 0);
    array_splice($char, $insertIndex+4, 0, 0);
    array_splice($char, $insertIndex+5, 0, 1);
    array_splice($char, $insertIndex+6, 0, 0);
    array_splice($char, $insertIndex+7, 0, 0);
    array_splice($char, $insertIndex+8, 0, 0);
    array_splice($char, $insertIndex+9, 0, 2);
    array_splice($char, $insertIndex+10, 0, 0);
  }
}

function EquipPayAdditionalCosts($cardIndex, $from)
{
  global $currentPlayer;
  if($cardIndex == -1) return;//TODO: Add error handling
  $character = &GetPlayerCharacter($currentPlayer);
  $cardID = $character[$cardIndex];
  switch($cardID) {
    case "1393827469"://Tarkintown
    case "2569134232"://Jedha City
    case "2429341052"://Security Complex
    case "8327910265"://Energy Conversion Lab (ECL)
      $character[$cardIndex+1] = 0;
      break;
    default:
      --$character[$cardIndex+5];
      if($character[$cardIndex+5] == 0) $character[$cardIndex+1] = 1; //By default, if it's used, set it to used
      break;
  }
}

function CharacterTriggerInGraveyard($cardID)
{
  switch($cardID) {
    default: return false;
  }
}

function CharacterDamageTakenAbilities($player, $damage)
{
  $char = &GetPlayerCharacter($player);
  $otherPlayer = $player == 1 ? 1 : 2;
  for ($i = count($char) - CharacterPieces(); $i >= 0; $i -= CharacterPieces())
  {
    if($char[$i + 1] != 2) continue;
    switch ($char[$i]) {

      default:
        break;
    }
  }
}

function CharacterDealDamageAbilities($player, $damage)
{
  $char = &GetPlayerCharacter($player);
  $otherPlayer = $player == 1 ? 1 : 2;
  for ($i = count($char) - CharacterPieces(); $i >= 0; $i -= CharacterPieces())
  {
    if($char[$i + 1] != 2) continue;
    switch ($char[$i]) {

      default:
        break;
    }
  }
}
?>
