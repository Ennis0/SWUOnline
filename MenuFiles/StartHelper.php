<?php

function initializePlayerState($deckHandler, $player)
{
  global $gamestate;
  global $p1IsPatron, $p2IsPatron, $p1id, $p2id;
  global $SET_AlwaysHoldPriority, $SET_TryUI2, $SET_DarkMode, $SET_ManualMode, $SET_SkipARs, $SET_SkipDRs, $SET_PassDRStep, $SET_AutotargetArcane;
  global $SET_ColorblindMode, $SET_EnableDynamicScaling, $SET_Mute, $SET_Cardback, $SET_IsPatron;
  global $SET_MuteChat, $SET_DisableStats, $SET_CasterMode, $SET_Language, $SET_DisableAnimations;

  $materialDeck = GetArray($deckHandler);
  $deckCards = GetArray($deckHandler);
  $deckSize = count($deckCards);

  $isPatron = ($player == 1 ? $p1IsPatron : $p2IsPatron);
  if($isPatron == "") $isPatron = "0";
  $mute = 0;
  $userId = ($player == 1 ? $p1id : $p2id);
  $savedSettings = LoadSavedSettings($userId);
  $settingArray = [];
  for($i=0; $i<=23; ++$i)
  {
    $value = "";
    switch($i)
    {
      case $SET_Mute: $value = $mute; break;
      case $SET_IsPatron: $value = $isPatron; break;
      default: $value = SettingDefaultValue($i, ""); break;
    }
    $settingArray[] = $value;
  }
  for($i=0; $i<count($savedSettings); $i+=2)
  {
    $settingArray[$savedSettings[intval($i)]] = $savedSettings[intval($i)+1];
  }
  
  if($player == 1){
    $gamestate->p1Hand = [];
    $gamestate->p1Deck = $deckCards;
    $gamestate->p1Material = $materialDeck;
    $gamestate->p1Resources = "0 0";
    $gamestate->p1ClassState = "0 0 0 0 0 0 0 0 DOWN 0 -1 0 0 0 0 0 0 -1 0 0 0 0 NA 0 0 0 - -1 0 0 0 0 0 0 - 0 0 0 0 0 0 0 0 - - 0 -1 0 0 0 0 0 - 0 0 0 0 0 -1 0 - 0 0 - 0 0";
    $gamestate->p1Settings = $settingArray;
  }
  else {
    $gamestate->p2Hand = [];
    $gamestate->p2Deck = $deckCards;
    $gamestate->p2Material = $materialDeck;
    $gamestate->p2Resources = "0 0";
    $gamestate->p2ClassState = "0 0 0 0 0 0 0 0 DOWN 0 -1 0 0 0 0 0 0 -1 0 0 0 0 NA 0 0 0 - -1 0 0 0 0 0 0 - 0 0 0 0 0 0 0 0 - - 0 -1 0 0 0 0 0 - 0 0 0 0 0 -1 0 - 0 0 - 0 0";
    $gamestate->p2Settings = $settingArray;
  }
}

function SettingDefaultValue($setting, $hero)
{
  global $SET_AlwaysHoldPriority, $SET_TryUI2, $SET_DarkMode, $SET_ManualMode, $SET_SkipARs, $SET_SkipDRs, $SET_PassDRStep, $SET_AutotargetArcane;
  global $SET_ColorblindMode, $SET_EnableDynamicScaling, $SET_Mute, $SET_Cardback, $SET_IsPatron;
  global $SET_MuteChat, $SET_DisableStats, $SET_CasterMode, $SET_Language, $SET_Playmat, $SET_DisableAnimations;
  switch($setting)
  {
    case $SET_TryUI2: return "1";
    case $SET_AutotargetArcane: return "1";
    case $SET_Playmat: return ($hero == "DUMMY" ? 8 : 0);
    default: return "0";
  }
}

function GetArray($handler)
{
  $line = trim(fgets($handler));
  if ($line == "") return [];
  return explode(" ", $line);
}

?>
