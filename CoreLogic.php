<?php

  include "CardSetters.php";
  include "CardGetters.php";

function CharacterLevel($player)
{
  global $CS_CachedCharacterLevel;
  return GetClassState($player, $CS_CachedCharacterLevel);
}

function StartTurnAbilities()
{
  global $gamestate;
  ItemStartTurnAbilities();
}

function LeaderPlayCardAbilities($cardID, $from)
{
  global $gamestate, $CS_NumNonAttackCards, $CS_NumBoostPlayed;
  $character = &GetPlayerCharacter($gamestate->currentPlayer);
  for($i = 0; $i < count($character); $i += CharacterPieces()) {
    if($character[$i+1] != 2) continue;
    switch($character[$i]) {
      case "3045538805"://Hondo Ohnaka
        if($from == "RESOURCES") {
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give an experience token", 1);
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
          AddDecisionQueue("EXHAUSTCHARACTER", $gamestate->currentPlayer, $i, 1);
        }
        break;
      case "1384530409"://Cad Bane
        if($from != 'PLAY' && $from != 'EQUIP' && TraitContains($cardID, "Underworld", $gamestate->currentPlayer)) { 
          $otherPlayer = ($gamestate->currentPlayer == 1 ? 2 : 1);
          AddDecisionQueue("YESNO", $gamestate->currentPlayer, "if you want use Cad Bane's ability");
          AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-");
          AddDecisionQueue("EXHAUSTCHARACTER", $gamestate->currentPlayer, $i, 1);
          AddDecisionQueue("MULTIZONEINDICES", $otherPlayer, "MYALLY", 1);
          AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Choose a unit to deal 1 damage to", 1);
          AddDecisionQueue("CHOOSEMULTIZONE", $otherPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $otherPlayer, "DEALDAMAGE,1", 1);
        }
        break;
      case "9005139831"://The Mandalorian
        if(DefinedTypesContains($cardID, "Upgrade", $gamestate->currentPlayer)) {
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:maxHealth=4");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust", 1);
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
          AddDecisionQueue("EXHAUSTCHARACTER", $gamestate->currentPlayer, $i, 1);
        }
        break;
      case "9334480612"://Boba Fett Green Leader
        if($from != "PLAY" && DefinedTypesContains($cardID, "Unit", $gamestate->currentPlayer) && HasKeyword($cardID, "Any", $gamestate->currentPlayer)) {
          $character[$i+1] = 1;
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a card to give +1 power");
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->mainPlayer, "GETUNIQUEID", 1);
          AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->mainPlayer, "9334480612,HAND", 1);
        }
        break;
      default:
        break;
    }
  }
}

function DamageTrigger($player, $damage, $type, $source="NA", $canPass=false)
{
  AddDecisionQueue("DEALDAMAGE", $player, $damage . "-" . $source . "-" . $type, ($canPass ? 1 : "0"));
  return $damage;
}

function CanDamageBePrevented($player, $damage, $type, $source="-")
{
  global $gamestate;
  if($source == "aebjvwbciz" && IsClassBonusActive($gamestate->mainPlayer, "GUARDIAN") && CharacterLevel($gamestate->mainPlayer) >= 2) return false;
  return true;
}

function DealDamageAsync($player, $damage, $type="DAMAGE", $source="NA")
{
  $damage = max($damage, 0);
  $gamestate->dqVars[0] = $damage;
  if($type == "COMBAT") $gamestate->dqState[6] = $damage;
  PrependDecisionQueue("FINALIZEDAMAGE", $player, $damageThreatened . "," . $type . "," . $source);
  return $damage;
}

function FinalizeDamage($player, $damage, $damageThreatened, $type, $source)
{
  global $otherPlayer, $CS_DamageTaken, $CS_ArcaneDamageTaken, $defPlayer, $gamestate;
  $classState = &GetPlayerClassState($player);
  $otherPlayer = $player == 1 ? 2 : 1;
  if($damage > 0)
  {
    if($source != "NA")
    {
      $damage += CurrentEffectDamageModifiers($player, $source, $type);
    }

    $classState[$CS_DamageTaken] += $damage;
    CurrentEffectDamageEffects($player, $source, $type, $damage);
  }
  PlayerLoseHealth($player, $damage);
  LogDamageStats($player, $damageThreatened, $damage);
  return $damage;
}

function LoseHealth($amount, $player)
{
  PlayerLoseHealth($player, $amount);
}

function Restore($amount, $player)
{
  if(SearchCurrentTurnEffects("7533529264", $player)) {
    WriteLog("<span style='color:red;'>Wolffe prevents the healing</span>");
    return false;
  }
  $damage = &GetDamage($player);
  WriteLog("Player " . $player . " gained " . $amount . " health.");
  if($amount > $health) $amount = $damage;
  $damage -= $amount;
  AddEvent("RESTORE", "P" . $player . "BASE!" . $amount);
  return true;
}

function DealDamageToBase($player, $amount, &$damageDealt)
{
  $damage = &GetDamage($player);
  $char = &GetPlayerCharacter($player);
  if(count($char) == 0) return;
  $damage += $amount;
  $damageDealt = $amount;
  AddEvent("DAMAGE", "P" . $player . "BASE!" . $amount);
  if(PlayerRemainingHealth($player) <= 0)
  {
    PlayerWon(($player == 1 ? 2 : 1));
  }
}

function PlayerRemainingHealth($player) {
  $damage = &GetDamage($player);
  $char = &GetPlayerCharacter($player);
  if($char[0] == "DUMMY") return 1000 - $health;
  return CardHP($char[0]) - $health;
}

function IsGameOver()
{
  global $gamestate, $GameStatus_Over;
  return $gamestate->inGameStatus == $GameStatus_Over;
}

function PlayerWon($playerID)
{
  global $gamestate, $gameName, $p1id, $p2id, $p1uid, $p2uid, $conceded;
  global $p1DeckLink, $p2DeckLink, $GameStatus_Over, $p1deckbuilderID, $p2deckbuilderID;
  if($gamestate->turn[0] == "OVER") return;
  include_once "./MenuFiles/ParseGamefile.php";

  $gamestate->winner = $playerID;
  if ($playerID == 1 && $p1uid != "") WriteLog($p1uid . " wins!", $playerID);
  elseif ($playerID == 2 && $p2uid != "") WriteLog($p2uid . " wins!", $playerID);
  else WriteLog("Player " . $gamestate->winner . " wins!");

  $gamestate->inGameStatus = $GameStatus_Over;
  $gamestate->turn[0] = "OVER";
  try {
    logCompletedGameStats();
  } catch (Exception $e) {

  }

  if(!$conceded || $currentRound>= 3) {
    //If this happens, they left a game in progress -- add disconnect logging?
  }
}

function UnsetDiscardModifier($player, $modifier, $newMod="-")
{
  $discard = &GetDiscard($player);
  for($i=0; $i<count($discard); $i+=DiscardPieces())
  {
    $cardModifier = explode("-", $discard[$i+1])[0];
    if($cardModifier == $modifier) $discard[$i+1] = $newMod;
  }
}

function UnsetTurnModifiers()
{
  UnsetDiscardModifier(1, "TT");
  UnsetDiscardModifier(1, "TTFREE");
  UnsetDiscardModifier(2, "TT");
  UnsetDiscardModifier(2, "TTFREE");
}

function GetIndices($count, $add=0, $pieces=1)
{
  $indices = "";
  for($i=0; $i<$count; $i+=$pieces)
  {
    if($indices != "") $indices .= ",";
    $indices .= ($i + $add);
  }
  return $indices;
}

function GetMyHandIndices()
{
  global $gamestate;
  return GetIndices(count(GetHand($gamestate->currentPlayer)));
}

function GetDefHandIndices()
{
  global $defPlayer;
  return GetIndices(count(GetHand($defPlayer)));
}

function HasGamblersGloves($player)
{
  $gamblersGlovesIndex = FindCharacterIndex($player, "CRU179");
  return $gamblersGlovesIndex != -1 && IsCharacterAbilityActive($player, $gamblersGlovesIndex);
}

function GamblersGloves($player, $origPlayer, $fromDQ)
{
  $gamblersGlovesIndex = FindCharacterIndex($player, "CRU179");
  if(HasGamblersGloves($player))
  {
    if($fromDQ)
    {
      PrependDecisionQueue("ROLLDIE", $origPlayer, "1", 1);
      PrependDecisionQueue("DESTROYCHARACTER", $player, "-", 1);
      PrependDecisionQueue("PASSPARAMETER", $player, $gamblersGlovesIndex, 1);
      PrependDecisionQueue("NOPASS", $player, "-");
      PrependDecisionQueue("YESNO", $player, "if_you_want_to_destroy_Gambler's_Gloves_to_reroll_the_result");
    }
    else
    {
      AddDecisionQueue("YESNO", $player, "if_you_want_to_destroy_Gambler's_Gloves_to_reroll_the_result");
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("PASSPARAMETER", $player, $gamblersGlovesIndex, 1);
      AddDecisionQueue("DESTROYCHARACTER", $player, "-", 1);
      AddDecisionQueue("ROLLDIE", $origPlayer, "1", 1);
    }
  }
}

function IsCharacterAbilityActive($player, $index, $checkGem=false)
{
  $character = &GetPlayerCharacter($player);
  if($checkGem && $character[$index+9] == 0) return false;
  return $character[$index+1] == 2;
}

function GetDieRoll($player)
{
  global $CS_DieRoll;
  return GetClassState($player, $CS_DieRoll);
}

function HasLostClass($player)
{
  if(SearchCurrentTurnEffects("UPR187", $player)) return true;//Erase Face
  return false;
}

function ClassOverride($cardID, $player="")
{
  global $gamestate;
  $cardClass = CardClass($cardID);
  if ($cardClass == "NONE") $cardClass = "";
  $otherPlayer = ($player == 1 ? 2 : 1);
  $otherCharacter = &GetPlayerCharacter($otherPlayer);

  if(SearchCurrentTurnEffects("UPR187", $player)) return "NONE";//Erase Face
  if(count($otherCharacter) > 0 && SearchCurrentTurnEffects($otherCharacter[0] . "-SHIYANA", $player)) {
    if($cardClass != "") $cardClass .= ",";
    $cardClass .= CardClass($otherCharacter[0]) . ",SHAPESHIFTER";
  }

  for($i=0; $i<count($gamestate->currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    if($gamestate->currentTurnEffects[$i+1] != $player) continue;
    $toAdd = "";
    switch($gamestate->currentTurnEffects[$i])
    {
      case "MON095": case "MON096": case "MON097":
      case "EVR150": case "EVR151": case "EVR152":
      case "UPR155": case "UPR156": case "UPR157": $toAdd = "ILLUSIONIST"; break;
      default: break;
    }
    if($toAdd != "")
    {
      if($cardClass != "") $cardClass .= ",";
      $cardClass .= $toAdd;
    }
  }
  if($cardClass == "") return "NONE";
  return $cardClass;
}

function NameOverride($cardID, $player="")
{
  $name = CardName($cardID);
  if(SearchCurrentTurnEffects("OUT183", $player)) $name = "";
  return $name;
}

function DefinedTypesContains($cardID, $type, $player="")
{
  if(!$cardID || $cardID == "" || strlen($cardID) < 3) return "";
  $cardTypes = DefinedCardType($cardID);
  $cardTypes2 = DefinedCardType2Wrapper($cardID);
  return DelimStringContains($cardTypes, $type) || DelimStringContains($cardTypes2, $type);
}

function CardTypeContains($cardID, $type, $player="")
{
  $cardTypes = CardTypes($cardID);
  return DelimStringContains($cardTypes, $type);
}

function ClassContains($cardID, $class, $player="")
{
  $cardClass = ClassOverride($cardID, $player);
  return DelimStringContains($cardClass, $class);
}

function AspectContains($cardID, $aspect, $player="")
{
  $cardAspect = CardAspects($cardID);
  return DelimStringContains($cardAspect, $aspect);
}

function TraitContains($cardID, $trait, $player="", $index=-1)
{
  $trait = str_replace("_", " ", $trait); //"MZALLCARDTRAITORPASS" and possibly other decision queue options call this function with $trait having been underscoreified, so I undo that here. 
  if($index != -1) {
    $ally = new Ally("MYALLY-" . $index, $player);
    $upgrades = $ally->GetUpgrades();
    for($i=0; $i<count($upgrades); ++$i) {
      switch($upgrades[$i]) {
        case "7687006104": if($trait == "Mandalorian") { return true; } break;
        default: break;}
      if(TraitContains($upgrades[$i], $trait, $player)) return true;
    }
  }
  $cardTrait = CardTraits($cardID);
  return DelimStringContains($cardTrait, $trait);
}

function HasKeyword($cardID, $keyword, $player="", $index=-1){
  switch($keyword){
    case "Smuggle": return SmuggleCost($cardID, $player, $index) > -1;
    case "Raid": return RaidAmount($cardID, $player, $index, true) > 0;
    case "Grit": return HasGrit($cardID, $player, $index);
    case "Restore": return RestoreAmount($cardID, $player, $index) > 0;
    case "Bounty": return CollectBounty($player, $index, $cardID, true) > 0;
    case "Overwhelm": return HasOverwhelm($cardID, $player, $index);
    case "Saboteur": return HasSaboteur($cardID, $player, $index);
    case "Shielded": return HasShielded($cardID, $player, $index);
    case "Sentinel": return HasSentinel($cardID, $player, $index);
    case "Ambush": return HasAmbush($cardID, $player, $index,"");
    case "Any":
      return SmuggleCost($cardID, $player, $index) > -1 ||
        RaidAmount($cardID, $player, $index, true) > 0 ||
        HasGrit($cardID, $player, $index) ||
        RestoreAmount($cardID, $player, $index) > 0 ||
        CollectBounty($player, $index, $cardID, true) > 0 ||
        HasOverwhelm($cardID, $player, $index) ||
        HasSaboteur($cardID, $player, $index) ||
        HasShielded($cardID, $player, $index) ||
        HasSentinel($cardID, $player, $index) ||
        HasAmbush($cardID, $player, $index, "");
    default: return false;
  }
}

function ArenaContains($cardID, $arena, $player="")
{
  $cardArena = CardArenas($cardID);
  return DelimStringContains($cardArena, $arena);
}

function SubtypeContains($cardID, $subtype, $player="")
{
  $cardSubtype = CardSubtype($cardID);
  return DelimStringContains($cardSubtype, $subtype);
}

function ElementContains($cardID, $element, $player="")
{
  $cardElement = CardElement($cardID);
  return DelimStringContains($cardElement, $element);
}

function CardNameContains($cardID, $name, $player="")
{
  $cardName = NameOverride($cardID, $player);
  return DelimStringContains($cardName, $name);
}

function TalentOverride($cardID, $player="")
{
  global $gamestate;
  $cardTalent = CardTalent($cardID);
  //CR 2.2.1 - 6.3.6. Continuous effects that remove a property, or part of a property, from an object do not remove properties, or parts of properties, that were added by another effect.
  if(SearchCurrentTurnEffects("UPR187", $player)) $cardTalent = "NONE";
  for($i=0; $i<count($gamestate->currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    $toAdd = "";
    if($gamestate->currentTurnEffects[$i+1] != $player) continue;
    switch($gamestate->currentTurnEffects[$i])
    {
      case "UPR060": case "UPR061": case "UPR062": $toAdd = "DRACONIC";
      default: break;
    }
    if($toAdd != "")
    {
      if($cardTalent == "NONE") $cardTalent = "";
      if($cardTalent != "") $cardTalent .= ",";
      $cardTalent .= $toAdd;
    }
  }
  return $cardTalent;
}

function TalentContains($cardID, $talent, $player="")
{
  $cardTalent = TalentOverride($cardID, $player);
  return DelimStringContains($cardTalent, $talent);
}

//parameters: (comma delimited list of card ids, , )
function RevealCards($cards, $player="", $from="HAND")
{
  global $gamestate;
  if($player == "") $player = $gamestate->currentPlayer;
  if(!CanRevealCards($player)) return false;
  $cardArray = explode(",", $cards);
  $string = "";
  for($i=count($cardArray)-1; $i>=0; --$i)
  {
    if($string != "") $string .= ", ";
    $string .= CardLink($cardArray[$i], $cardArray[$i]);
    //AddEvent("REVEAL", $cardArray[$i]);
    OnRevealEffect($player, $cardArray[$i], $from, $i);
  }
  $string .= (count($cardArray) == 1 ? " is" : " are");
  $string .= " revealed.";
  WriteLog($string);
  return true;
}

function OnRevealEffect($player, $cardID, $from, $index)
{
  switch($cardID)
  {
    default: break;
  }
}

function IsEquipUsable($player, $index)
{
  $character = &GetPlayerCharacter($player);
  if($index >= count($character) || $index < 0) return false;
  return $character[$index + 1] == 2;
}

function CancelAttack()
{
  global $gamestate, $AS_AttackTarget;
  $gamestate->layers = [];
  PrependLayer("FINALIZEATTACK", $gamestate->mainPlayer, true);
  $gamestate->turn[0] = "M";
  $gamestate->currentPlayer = $gamestate->mainPlayer;
  $gamestate->attackState[$AS_AttackTarget] = "NA";
}

function RemoveCharacter($player, $index)
{
  $char = &GetPlayerCharacter($player);
  $cardID = $char[$index];
  for($i=$index+CharacterPieces()-1; $i>=$index; --$i)
  {
    unset($char[$i]);
  }
  $char = array_values($char);
  return $cardID;
}

function AddDurabilityCounters($player, $amount=1)
{
  AddDecisionQueue("PASSPARAMETER", $player, $amount);
  AddDecisionQueue("SETDQVAR", $player, "0");
  AddDecisionQueue("MULTIZONEINDICES", $player, "MYCHAR:type=WEAPON");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a weapon to add durability counter" . ($amount > 1 ? "s" : ""), 1);
  AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("MZOP", $player, "ADDDURABILITY", 1);
}

function LookAtHand($player)
{
  $hand = &GetHand($player);
  $otherPlayer = ($player == 1 ? 2 : 1);
  $caption = "Their hand is: ";
  for($i=0; $i<count($hand); $i+=HandPieces())
  {
    if($i > 0) $caption .= ", ";
    $caption .= CardLink($hand[$i], $hand[$i]);
  }
  AddDecisionQueue("SETDQCONTEXT", $otherPlayer, $caption);
  AddDecisionQueue("OK", $otherPlayer, "-");
}

function AddCharacterUses($player, $index, $numToAdd)
{
  $character = &GetPlayerCharacter($player);
  if($character[$index+1] == 0) return;
  $character[$index+1] = 2;
  $character[$index+5] += $numToAdd;
}

  function CanPassPhase($phase)
  {
    global $gamestate;
    switch($phase)
    {
      case "P": return 0;
      case "CHOOSEDECK": return 0;
      case "HANDTOPBOTTOM": return 0;
      case "CHOOSECHARACTER": return 0;
      case "CHOOSEHAND": return 0;
      case "CHOOSEHANDCANCEL": return 0;
      case "MULTICHOOSEDISCARD": return 0;
      case "CHOOSEDISCARDCANCEL": return 0;
      case "CHOOSEARCANE": return 0;
      case "CHOOSEARSENAL": return 0;
      case "CHOOSEDISCARD": return 0;
      case "MULTICHOOSEHAND": return 0;
      case "MULTICHOOSEUNIT": return 0;
      case "MULTICHOOSETHEIRUNIT": return 0;
      case "CHOOSEMULTIZONE": return 0;
      case "BUTTONINPUTNOPASS": return 0;
      case "CHOOSEFIRSTPLAYER": return 0;
      case "MULTICHOOSEDECK": return 0;
      case "CHOOSEPERMANENT": return 0;
      case "MULTICHOOSETEXT": return 0;
      case "CHOOSEMYSOUL": return 0;
      case "OVER": return 0;
      default: return 1;
    }
  }

  function PitchDeck($player, $index)
  {
    $deck = &GetDeck($player);
    $cardID = RemovePitch($player, $index);
    $deck[] = $cardID;
  }

  function GetUniqueId()
  {
    global $gamestate;
    ++$gamestate->permanentUniqueIDCounter;
    return $gamestate->permanentUniqueIDCounter;
  }

  function IsHeroAttackTarget()
  {
    $target = explode("-", GetAttackTarget());
    return $target[0] == "THEIRCHAR";
  }

  function IsAllyAttackTarget()
  {
    $target = GetAttackTarget();
    if($target == "NA") return false;
    $targetArr = explode("-", $target);
    return $targetArr[0] == "THEIRALLY";
  }

  function AttackerIndex()
  {
    global $gamestate, $AS_AttackerIndex;
    if(isset($gamestate->attackState[$AS_AttackerIndex])) return $gamestate->attackState[$AS_AttackerIndex];
    return -1;
  }
  
  function AttackIsOngoing() {
    return AttackerIndex() > -1;
  }

  function IsAttackTargetRested()
  {
    global $defPlayer;
    $target = GetAttackTarget();
    $mzArr = explode("-", $target);
    if($mzArr[0] == "ALLY" || $mzArr[0] == "MYALLY" || $mzArr[0] == "THEIRALLY")
    {
      $allies = &GetAllies($defPlayer);
      return $allies[$mzArr[1]+1] == 1;
    }
    else
    {
      $char = &GetPlayerCharacter($defPlayer);
      return $char[1] == 1;
    }
  }

  function IsSpecificAllyAttackTarget($player, $index)
  {
    $mzTarget = GetAttackTarget();
    $mzArr = explode("-", $mzTarget);
    if($mzArr[0] == "ALLY" || $mzArr[0] == "MYALLY" || $mzArr[0] == "THEIRALLY")
    {
      return $index == intval($mzArr[1]);
    }
    return false;
  }

  function IsSpecificAllyAttacking($player, $index)
  {
    global $gamestate;
    if($gamestate->mainPlayer != $player) return false;
    return explode("-", AttackerMZID($player))[1] == $index;
  }

  function AttackerMZID($player)
  {
    global $gamestate, $AS_AttackerIndex;
    if($player == $gamestate->mainPlayer) return "MYALLY-" . $gamestate->attackState[$AS_AttackerIndex];
    else return "THEIRALLY-" . $gamestate->attackState[$AS_AttackerIndex];
  }

  function AttackerCardID() {
    global $gamestate;
    $attacker = new Ally(AttackerMZID($gamestate->mainPlayer));
    return $attacker->CardID();
  }

  function ClearAttacker() {
    global $gamestate, $AS_AttackerIndex;
    $gamestate->attackState[$AS_AttackerIndex] = -1;
  }

function RevealMemory($player)
{
  $memory = &GetMemory($player);
  $toReveal = "";
  for($i=0; $i<count($memory); $i += MemoryPieces())
  {
    if($toReveal != "") $toReveal .= ",";
    $toReveal .= $memory[$i];
  }
  return RevealCards($toReveal, $player, "MEMORY");
}

  function CanRevealCards($player)
  {
    return true;
  }

function GetDamagePreventionIndices($player)
{
  $rv = "";

  $char = &GetPlayerCharacter($player);
  $indices = "";
  for($i=0; $i<count($char); $i+=CharacterPieces())
  {
    if($char[$i+1] != 0 && WardAmount($char[$i]) > 0)
    {
      if($indices != "") $indices .= ",";
      $indices .= $i;
    }
  }
  $indices = SearchMultiZoneFormat($indices, "MYCHAR");
  $mzIndices = CombineSearches($mzIndices, $indices);

  $ally = &GetAllies($player);
  $indices = "";
  for($i=0; $i<count($ally); $i+=AllyPieces())
  {
    if($ally[$i+1] != 0 && WardAmount($ally[$i]) > 0)
    {
      if($indices != "") $indices .= ",";
      $indices .= $i;
    }
  }
  $indices = SearchMultiZoneFormat($indices, "MYALLY");
  $mzIndices = CombineSearches($mzIndices, $indices);
  $rv = $mzIndices;
  return $rv;
}

function SameWeaponEquippedTwice()
{
  global $gamestate;
  $char = &GetPlayerCharacter($gamestate->mainPlayer);
  $weaponIndex = explode(",", SearchCharacter($gamestate->mainPlayer, "W"));
  if (count($weaponIndex) > 1 && $char[$weaponIndex[0]] == $char[$weaponIndex[1]]) return true;
  return false;
}

function SelfCostModifier($cardID, $from)
{
  global $gamestate, $CS_LastAttack, $CS_LayerTarget, $CS_NumClonesPlayed;
  $modifier = 0;
  //Aspect Penalty
  $heraSyndullaAspectPenaltyIgnore = TraitContains($cardID, "Spectre", $gamestate->currentPlayer) && (HeroCard($gamestate->currentPlayer) == "7440067052" || SearchAlliesForCard($gamestate->currentPlayer, "80df3928eb") != ""); //Hera Syndulla (Spectre Two)
  $omegaAspectPenaltyIgnore = TraitContains($cardID, "Clone", $gamestate->currentPlayer) && SearchAlliesForCard($gamestate->currentPlayer, "1386874723") != "" && GetClassState($gamestate->currentPlayer, $CS_NumClonesPlayed) < 1; //Omega (Part of the Squad)
  $playerAspects = PlayerAspects($gamestate->currentPlayer);
  if(!$heraSyndullaAspectPenaltyIgnore && !$omegaAspectPenaltyIgnore) {
    $penalty = 0;
    $cardAspects = CardAspects($cardID);
    //Manually changing the aspects of cards played with smuggle that have different aspect requirements for smuggle.
    //Not a great solution; ideally we could define a whole smuggle ability in one place.
    if ($from == "RESOURCES") {
      $tech = SearchAlliesForCard($gamestate->currentPlayer, "3881257511");
      if($tech != "") {
        $ally = new Ally("MYALLY-" . $tech, $gamestate->currentPlayer);
        $techOnBoard = !$ally->LostAbilities();
      } else {
        $techOnBoard = false;
      }
      switch($cardID) {
        case "5169472456"://Chewbacca (Pykesbane)
          if(!$techOnBoard || $playerAspects["Aggression"] != 0) {
            //if tech is here and player is not aggression, tech will always be cheaper than aggression cost
            $cardAspects = "Heroism,Aggression";
          }
          break;
        case "9871430123"://Sugi
          //vigilance is always cheaper than vigilance,vigilance, do not use tech passive
          $cardAspects = "Vigilance";
          break;
        case "5874342508"://Hotshot DL-44 Blaster
          if(!$techOnBoard || ($playerAspects["Cunning"] != 0 && $playerAspects["Aggression"] == 0)) {
            //if tech is here, cunning smuggle is better only if player is cunning and not aggression
            $cardAspects = "Cunning";
          }
          break;
        case "4002861992"://DJ (Blatant Thief)
          if(!$techOnBoard) {
            //cunning will always be cheaper than cunning+cunning, do not add a cunning if tech is here
            $cardAspects = "Cunning,Cunning";
          }
          break;
        case "3010720738"://Tobias Beckett
          if(!$techOnBoard || $playerAspects["Vigilance"] != 0) {
            //if tech is here and player is not vigilance, tech will always be cheaper than vigilance cost
            $cardAspects = "Vigilance";
          }
          break;
        default: break;
      }
    }
    if($cardAspects != "") {
      $aspectArr = explode(",", $cardAspects);
      for($i=0; $i<count($aspectArr); ++$i)
      {
        --$playerAspects[$aspectArr[$i]];
        if($playerAspects[$aspectArr[$i]] < 0) {
          //We have determined that the player is liable for an aspect penalty
          //Now we need to determine if they are exempt
          switch($cardID) {
            case "6263178121"://Kylo Ren (Killing the Past)
              if(!ControlsNamedCard($gamestate->currentPlayer, "Rey")) ++$penalty;
              break;
            case "0196346374"://Rey (Keeping the Past)
              if(!ControlsNamedCard($gamestate->currentPlayer, "Kylo Ren")) ++$penalty;
              break;
            default:
              ++$penalty;
              break;
          }
        }
      }
      $modifier += $penalty * 2;
    }
  }
  //Self Cost Modifier
  switch($cardID) {
    case "1446471743"://Force Choke
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Force")) > 0) $modifier -= 1;
      break;
    case "4111616117"://Volunteer Soldier
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Trooper")) > 0) $modifier -= 1;
      break;
    case "6905327595"://Reputable Hunter
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $theirAllies = &GetAllies($otherPlayer);
      $hasBounty = false;
      for($i=0; $i<count($theirAllies) && !$hasBounty; $i+=AllyPieces())
      {
        $theirAlly = new Ally("MYALLY-" . $i, $otherPlayer);
        if($theirAlly->HasBounty()) { $hasBounty = true; $modifier -= 1; }
      }
      break;
    case "7212445649"://Bravado
      global $CS_NumAlliesDestroyed;
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      if(GetClassState($otherPlayer, $CS_NumAlliesDestroyed) > 0) $modifier -= 2;
      break;
    case "8380936981"://Jabba's Rancor
      if(ControlsNamedCard($gamestate->currentPlayer, "Jabba the Hutt")) $modifier -= 1;
      break;
    default: break;
  }
  //Target cost modifier
  if(count($gamestate->layers) > 0) {
    $targetID = GetMZCard($gamestate->currentPlayer, GetClassState($gamestate->currentPlayer, $CS_LayerTarget));
  } else {
    if(SearchAlliesForCard($gamestate->currentPlayer, "4166047484") != "") $targetID = "4166047484";
    else if($cardID == "3141660491") $targetID = "4088c46c4d";
    else $targetID = "";
  }
  if(DefinedTypesContains($cardID, "Upgrade", $gamestate->currentPlayer)) {
    if($targetID == "4166047484") $modifier -= 1;//Guardian of the Whills
    if($cardID == "3141660491" && $targetID != "" && TraitContains($targetID, "Mandalorian", $gamestate->currentPlayer)) $modifier -= $penalty * 2;//The Darksaber
  }
  //My ally cost modifier
  $allies = &GetAllies($gamestate->currentPlayer);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    if($allies[$i+1] == 0) continue;
    switch($allies[$i]) {
      case "5035052619"://Jabba the Hutt
        if(DefinedTypesContains($cardID, "Event", $gamestate->currentPlayer) && TraitContains($cardID, "Trick", $gamestate->currentPlayer)) $modifier -= 1;
        break;
      default: break;
    }
  }
  //Opponent ally cost modifier
  $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
  $allies = &GetAllies($otherPlayer);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    if($allies[$i+1] == 0) continue;
    switch($allies[$i]) {
      case "9412277544"://Del Meeko
        if(DefinedTypesContains($cardID, "Event", $gamestate->currentPlayer)) $modifier += 1;
        break;
      default: break;
    }
  }
  return $modifier;
}

function PlayerAspects($player)
{
  $char = &GetPlayerCharacter($player);
  $aspects = [];
  $aspects["Vigilance"] = 0;
  $aspects["Command"] = 0;
  $aspects["Aggression"] = 0;
  $aspects["Cunning"] = 0;
  $aspects["Heroism"] = 0;
  $aspects["Villainy"] = 0;
  for($i=0; $i<count($char); $i+=CharacterPieces())
  {
    $cardAspects = explode(",", CardAspects($char[$i]));
    for($j=0; $j<count($cardAspects); ++$j) {
      ++$aspects[$cardAspects[$j]];
    }
  }
  $leaderIndex = SearchAllies($player, definedType:"Leader");
  if($leaderIndex != "") {
    $allies = &GetAllies($player);
    $cardAspects = explode(",", CardAspects($allies[$leaderIndex]));
    for($j=0; $j<count($cardAspects); ++$j) {
      ++$aspects[$cardAspects[$j]];
    }
  }
  return $aspects;
}

function IsAlternativeCostPaid($cardID, $from)
{
  global $gamestate;
  $isAlternativeCostPaid = false;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {
        case "9644107128"://Bamboozle
          $isAlternativeCostPaid = true;
          $remove = true;
          break;
        default:
          break;
      }
      if($remove) RemoveCurrentTurnEffect($i);
    }
  }
  return $isAlternativeCostPaid;
}

function IsCardNamed($player, $cardID, $name)
{
  global $gamestate;
  if(CardName($cardID) == $name) return true;
  for($i=0; $i<count($gamestate->currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    $effectArr = explode("-", $gamestate->currentTurnEffects[$i]);
    $name = CurrentEffectNameModifier($effectArr[0], (count($effectArr) > 1 ? GamestateUnsanitize($effectArr[1]) : "N/A"));
    //You have to do this at the end, or you might have a recursive loop -- e.g. with OUT052
    if($name != "" && $gamestate->currentTurnEffects[$i+1] == $player) return true;
  }
  return false;
}

function ClearGameFiles($gameName)
{
  if(file_exists("./Games/" . $gameName . "/gamestateBackup.txt")) unlink("./Games/" . $gameName . "/gamestateBackup.txt");
  if(file_exists("./Games/" . $gameName . "/beginTurnGamestate.txt")) unlink("./Games/" . $gameName . "/beginTurnGamestate.txt");
  if(file_exists("./Games/" . $gameName . "/lastTurnGamestate.txt")) unlink("./Games/" . $gameName . "/lastTurnGamestate.txt");
}

function IsClassBonusActive($player, $class)
{
  $char = &GetPlayerCharacter($player);
  if(count($char) == 0) return false;
  if(ClassContains($char[0], $class, $player)) return true;
  return false;
}

function PlayAbility($cardID, $from, $resourcesPaid, $target = "-", $additionalCosts = "-")
{
  global $gamestate, $CS_PlayIndex, $AS_CantAttackBase;
  $index = GetClassState($gamestate->currentPlayer, $CS_PlayIndex);
  if($from == "PLAY" && IsAlly($cardID, $gamestate->currentPlayer)) {
    $playAlly = new Ally("MYALLY-" . $index);
    $abilityName = GetResolvedAbilityName($cardID, $from);
    if($abilityName == "Heroic Resolve") {
      $ally = new Ally("MYALLY-" . $index, $gamestate->currentPlayer);
      $ownerId = $ally->DefeatUpgrade("4085341914");
      AddGraveyard("4085341914", $ownerId, "PLAY");
      AddCurrentTurnEffect("4085341914", $gamestate->currentPlayer, "PLAY", $ally->UniqueID());
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "MYALLY-" . $index);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK");
      return "";
    }
  }
  if($target != "-")
  {
    $targetArr = explode("-", $target);
    if($targetArr[0] == "LAYERUID") { $targetArr[0] = "LAYER"; $targetArr[1] = SearchLayersForUniqueID($targetArr[1]); }
    $target = count($targetArr) > 1 ? $targetArr[0] . "-" . $targetArr[1] : "-";
  }
  if($from != "PLAY" && $from != "EQUIP" && $from != "CHAR") {
    AddAllyPlayCardAbilityLayers($cardID, $from);
  }
  if($from != "PLAY" && IsAlly($cardID, $gamestate->currentPlayer)) {
    $playAlly = new Ally("MYALLY-" . LastAllyIndex($gamestate->currentPlayer));
  }
  if($from == "EQUIP" && DefinedTypesContains($cardID, "Leader", $gamestate->currentPlayer)) {
    $abilityName = GetResolvedAbilityName($cardID, $from);
    if($abilityName == "Deploy" || $abilityName == "") {
      if(NumResources($gamestate->currentPlayer) < CardCost($cardID)) {
        WriteLog("You don't control enough resources to deploy that leader; reverting the game state.");
        RevertGamestate();
        return "";
      }
      $playIndex = PlayAlly(LeaderUnit($cardID), $gamestate->currentPlayer);
      if(HasShielded(LeaderUnit($cardID), $gamestate->currentPlayer, $playIndex)) {
        $allies = &GetAllies($gamestate->currentPlayer);
        AddLayer("TRIGGER", $gamestate->currentPlayer, "SHIELDED", "-", "-", $allies[$playIndex + 5]);
      }
      PlayAbility(LeaderUnit($cardID), "CHAR", 0, "-", "-");
      //On Deploy ability
      switch($cardID) {
        case "5784497124"://Emperor Palpatine
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:damagedOnly=true");
          AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a damaged unit to take control of", 1);
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "TAKECONTROL", 1);
          break;
        case "2432897157"://Qi'Ra
          $myAllies = &GetAllies($gamestate->currentPlayer);
          for($i=0; $i<count($myAllies); $i+=AllyPieces())
          {
            $ally = new Ally("MYALLY-" . $i, $gamestate->currentPlayer);
            $ally->Heal(9999);
            $ally->DealDamage(floor($ally->MaxHealth()/2));
          }
          $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
          $theirAllies = &GetAllies($otherPlayer);
          for($i=0; $i<count($theirAllies); $i+=AllyPieces())
          {
            $ally = new Ally("MYALLY-" . $i, $otherPlayer);
            $ally->Heal(9999);
            $ally->DealDamage(floor($ally->MaxHealth()/2));
          }
          break;
        case "0254929700"://Doctor Aphra
          AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "GY");
          AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "3-", 1);
          AddDecisionQueue("APPENDLASTRESULT", $gamestate->currentPlayer, "-3", 1);
          AddDecisionQueue("MULTICHOOSEDISCARD", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "DOCTORAPHRA", 1);
          break;
        case "0622803599"://Jabba the Hutt
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
          AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture another unit");
          AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
          AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY", 1);
          AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader", 1);
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture", 1);
          AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE,{0}", 1);
          break;
        default: break;
      }
      RemoveCharacter($gamestate->currentPlayer, CharacterPieces());
      return CardLink($cardID, $cardID) . " was deployed.";
    }
  }
  switch($cardID)
  {
    case "4721628683"://Patrolling V-Wing
      if($from != "PLAY") Draw($gamestate->currentPlayer);
      break;
    case "2050990622"://Spark of Rebellion
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRHAND");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose which card you want your opponent to discard", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZDISCARD", $gamestate->currentPlayer, "HAND," . $gamestate->currentPlayer, 1);
      AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
      break;
    case "3377409249"://Rogue Squadron Skirmisher
      if($from != "PLAY") MZMoveCard($gamestate->currentPlayer, "MYDISCARD:maxCost=2;definedType=Unit", "MYHAND", may:true);
      break;
    case "5335160564"://Guerilla Attack Pod
      if($from != "PLAY" && (GetDamage(1) >= 15 || GetDamage(2) >= 15)) {
        $playAlly->Ready();
      }
      break;
    case "7262314209"://Mission Briefing
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $player = $additionalCosts == "Yourself" ? $gamestate->currentPlayer : $otherPlayer;
      Draw($player);
      Draw($player);
      break;
    case "6253392993"://Bright Hope
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Ground");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to bounce");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
        AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      }
      break;
    case "6702266551"://Smoke and Cinders
      $hand = &GetHand(1);
      for($i=0; $i<(count($hand)/HandPieces())-2; ++$i) PummelHit(1);
      $hand = &GetHand(2);
      for($i=0; $i<(count($hand)/HandPieces())-2; ++$i) PummelHit(2);
      break;
    case "8148673131"://Open Fire
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 4 damage to");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,4", 1);
      break;
    case "8429598559"://Black One
      if($from != "PLAY") BlackOne($gamestate->currentPlayer);
      break;
    case "8986035098"://Viper Probe Droid
      if($from != "PLAY") LookAtHand($gamestate->currentPlayer == 1 ? 2 : 1);
      break;
    case "9266336818"://Grand Moff Tarkin
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Imperial", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Imperial", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD");
        AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      }
      break;
    case "9459170449"://Cargo Juggernaut
      if($from != "PLAY" && SearchCount(SearchAllies($gamestate->currentPlayer, aspect:"Vigilance")) > 1) {
        Restore(4, $gamestate->currentPlayer);
      }
      break;
    case "7257556541"://Bodhi Rook
      if($from != "PLAY") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        AddDecisionQueue("FINDINDICES", $otherPlayer, "HAND");
        AddDecisionQueue("REVEALHANDCARDS", $otherPlayer, "-");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRHAND");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Unit");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to discard");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZDESTROY", $gamestate->currentPlayer, "-", 1);
      }
      break;
    case "6028207223"://Pirated Starfighter
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      }
      break;
    case "8981523525"://Moment of Peace
      if($target != "-") {
        $ally = new Ally($target);
        $ally->Attach("8752877738", $gamestate->currentPlayer);//Shield
      }
      break;
    case "8679831560"://Repair
      $mzArr = explode("-", $target);
      if($mzArr[0] == "MYCHAR") Restore(3, $gamestate->currentPlayer);
      else if($mzArr[0] == "MYALLY") {
        $ally = new Ally($target);
        $ally->Heal(3);
      }
      break;
    case "7533529264"://Wolffe
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      AddCurrentTurnEffect($cardID, $otherPlayer);
      break;
    case "7596515127"://Academy Walker
      if($from != "PLAY") {
        $allies = &GetAllies($gamestate->currentPlayer);
        for($i=0; $i<count($allies); $i+=AllyPieces()) {
          $ally = new Ally("MYALLY-" . $i);
          if($ally->IsDamaged()) $ally->Attach("2007868442");//Experience token
        }
      }
      break;
    case "7202133736"://Waylay
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to return to hand");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      break;
    case "5283722046"://Spare the Target
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to return to hand");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "COLLECTBOUNTIES", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{1}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      break;
    case "7485151088"://Search your feelings
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYDECK");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("MULTIADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("SHUFFLEDECK", $gamestate->currentPlayer, "-");
      break;
    case "0176921487"://Power of the Dark Side
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      MZChooseAndDestroy($otherPlayer, "MYALLY");
      break;
    case "0827076106"://Admiral Ackbar
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to damage");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "ADMIRALACKBAR", 1);
      }
      break;
    case "0867878280"://It Binds All Things
      $ally = new Ally($target);
      $amountHealed = $ally->Heal(3);
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Force")) > 0) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal " . $amountHealed . " damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE," . $amountHealed, 1);
      }
      break;
    case "1021495802"://Cantina Bouncer
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY&MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      }
      break;
    case "1353201082"://Superlaser Blast
      DestroyAllAllies();
      break;
    case "1705806419"://Force Throw
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      if($additionalCosts == "Yourself") PummelHit($gamestate->currentPlayer);
      else PummelHit($otherPlayer);
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Force")) > 0) {
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "FORCETHROW", 1);
      }
      break;
    case "1746195484"://Jedha Agitator
      if($from == "PLAY" && HasLeader($gamestate->currentPlayer)){
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRCHAR:definedType=Base&MYALLY:arena=Ground&THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose something to deal 2 damage", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      }
      break;
    case "2587711125"://Disarm
      $ally = new Ally($target);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $ally->UniqueID());
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $ally->PlayerID(), "2587711125,HAND");
      break;
    case "5707383130"://Bendu
      if($from == "PLAY") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      }
      break;
    case "6472095064"://Vanquish
      MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY&THEIRALLY", filter:"definedType=Leader");
      break;
    case "6663619377"://AT-AT Suppressor
      if($from != "PLAY"){
        ExhaustAllAllies("Ground", 1);
        ExhaustAllAllies("Ground", 2);
      }
      break;
    case "6931439330"://The Ghost
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Spectre");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give a shield");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      break;
    case "8691800148"://Reinforcement Walker
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "TOPDECK");
      AddDecisionQueue("DECKCARDS", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose if you want to draw <0>", 1);
      AddDecisionQueue("YESNO", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "REINFORCEMENTWALKER", 1);
      break;
    case "9002021213"://Imperial Interceptor
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a Space unit to deal 3 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Space&THEIRALLY:arena=Space");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,3", 1);
      }
      break;
    case "9133080458"://Inferno Four
      if($from != "PLAY") PlayerOpt($gamestate->currentPlayer, 2);
      break;
    case "9568000754"://R2-D2
      PlayerOpt($gamestate->currentPlayer, 1);
      break;
    case "9624333142"://Count Dooku
      if($from != "PLAY") {
        MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY:maxHealth=4&THEIRALLY:maxHealth=4", may:true, filter:"index=MYALLY-" . $playAlly->Index());
      }
      break;
    case "9097316363"://Emperor Palpatine
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "ALLTHEIRUNITSMULTI");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose units to damage", 1);
        AddDecisionQueue("MULTICHOOSETHEIRUNIT", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MULTIDISTRIBUTEDAMAGE", $gamestate->currentPlayer, 6, 1);
      }
      break;
    case "1208707254"://Rallying Cry
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      break;
    case "1446471743"://Force Choke
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "trait=Vehicle");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 5 damage to");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,5", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "FORCECHOKE", 1);
      break;
    case "1047592361"://Ruthless Raider
      if($from != "PLAY") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        DealDamageAsync($otherPlayer, 2, "DAMAGE", "1047592361");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      }
      break;
    case "1862616109"://Snowspeeder
      if($from == "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground;trait=Vehicle");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      }
      break;
    case "2554951775"://Bail Organa
      if($from == "PLAY" && GetResolvedAbilityType($cardID) == "A") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $index);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to add an experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "3058784025"://Keep Fighting
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxAttack=3&THEIRALLY:maxAttack=3");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to ready");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      break;
    case "3613174521"://Outer Rim Headhunter
      if($from == "PLAY" && HasLeader($gamestate->currentPlayer)) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      }
      break;
    case "3684950815"://Bounty Hunter Crew
      if($from != "PLAY") MZMoveCard($gamestate->currentPlayer, "MYDISCARD:definedType=Event", "MYHAND", may:true, context:"Choose an event to return with " . CardLink("3684950815", "3684950815"));
      break;
    case "4092697474"://TIE Advanced
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "4536594859"://Medal Ceremony
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Rebel");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "numAttacks=0");
      AddDecisionQueue("OP", $gamestate->currentPlayer, "MZTONORMALINDICES");
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "3-", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose up to 3 units that have attacked to give experience", 1);
      AddDecisionQueue("MULTICHOOSEUNIT", $gamestate->currentPlayer, "<-", 1, 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "MEDALCEREMONY");
      break;
    case "6515891401"://Karabast
      $ally = new Ally($target);
      $damage = $ally->MaxHealth() - $ally->Health() + 1;
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal " . $damage . " damage to");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE," . $damage, 1);
      break;
    case "7929181061"://General Tagge
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Trooper");
        AddDecisionQueue("OP", $gamestate->currentPlayer, "MZTONORMALINDICES");
        AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "3-", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose up to 3 troopers to give experience");
        AddDecisionQueue("MULTICHOOSEUNIT", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "MULTIGIVEEXPERIENCE", 1);
      }
      break;
    case "8240629990"://Avenger
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      MZChooseAndDestroy($otherPlayer, "MYALLY", filter:"definedType=Leader", context:"Choose a unit to destroy");
      break;
    case "8294130780"://Gladiator Star Destroyer
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give Sentinel");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "WRITECHOICE", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "8294130780,HAND", 1);
      }
      break;
    case "4919000710"://Home One
      if($from != "PLAY") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYDISCARD:definedType=Unit&Aspect=Heroism");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "4849184191"://Takedown
      MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY:maxHealth=5&THEIRALLY:maxHealth=5");
      break;
    case "4631297392"://Devastator
      if($from != "PLAY") {
        $resourceCards = &GetResourceCards($gamestate->currentPlayer);
        $numResources = count($resourceCards)/ResourcePieces();
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal " . $numResources . " damage");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE," . $numResources, 1);
      }
      break;
    case "4599464590"://Rugged Survivors
      if($from == "PLAY" && HasLeader($gamestate->currentPlayer)) {
        Draw($gamestate->currentPlayer);
      }
      break;
    case "4299027717"://Mining Guild Tie Fighter
      if($from == "PLAY" && NumResourcesAvailable($gamestate->currentPlayer) >= 2) {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Do you want to pay 2 to draw a card?");
        AddDecisionQueue("YESNO", $gamestate->currentPlayer, "-");
        AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "", 1);
        AddDecisionQueue("PAYRESOURCES", $gamestate->currentPlayer, "2,1", 1);
        AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      }
      break;
    case "3802299538"://Cartel Spacer
      if($from != "PLAY" && SearchCount(SearchAllies($gamestate->currentPlayer, aspect:"Cunning")) > 1) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:maxCost=4");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      }
      break;
    case "3443737404"://Wing Leader
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Rebel");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to add experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "2756312994"://Alliance Dispatcher
      if($from == "PLAY" && GetResolvedAbilityType($cardID) == "A") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "2569134232"://Jedha City
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "leader=1");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "2569134232,HAND");
      break;
    case "1349057156"://Strike True
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal damage equal to it's power");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "POWER", 1);
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "DEALDAMAGE,", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to damage");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "{0}", 1);
      break;
    case "1393827469"://Tarkintown
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 3 damage to");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:damagedOnly=true&THEIRALLY:damagedOnly=true");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,3", 1);
      break;
    case "1880931426"://Lothal Insurgent
      global $CS_NumCardsPlayed;
      if($from != "PLAY" && GetClassState($gamestate->currentPlayer, $CS_NumCardsPlayed) > 1) {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        Draw($otherPlayer);
        DiscardRandom($otherPlayer, $cardID);
      }
      break;
    case "2429341052"://Security Complex
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "leader=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give a shield");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      break;
    case "3018017739"://Vanguard Ace
      global $CS_NumCardsPlayed;
      if($from != "PLAY") {
        $ally = new Ally("MYALLY-" . LastAllyIndex($gamestate->currentPlayer));
        for($i=0; $i<(GetClassState($gamestate->currentPlayer, $CS_NumCardsPlayed)-1); ++$i) {
          $ally->Attach("2007868442");//Experience token
        }
      }
      break;
    case "3401690666"://Relentless
      if($from != "PLAY") {
        global $CS_NumEventsPlayed;
        $otherPlayer = ($gamestate->currentPlayer == 1 ? 2 : 1);
        if(GetClassState($otherPlayer, $CS_NumEventsPlayed) == 0) {
          AddCurrentTurnEffect("3401690666", $otherPlayer, from:"PLAY");
        }
      }
      break;
    case "3407775126"://Recruit
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-definedType-Unit", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD");
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      break;
    case "3498814896"://Mon Mothma
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Rebel", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a rebel to draw", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD");
        AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      }
      break;
    case "3509161777"://You're My Only Hope
      $deck = new Deck($gamestate->currentPlayer);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $deck->Top());
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Do you want to play <0>?");
      AddDecisionQueue("YESNO", $gamestate->currentPlayer, "-");
      AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-");
      AddDecisionQueue("ADDCURRENTEFFECT", $gamestate->currentPlayer, "3509161777", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "MYDECK-0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "3572356139"://Chewbacca, Walking Carpet
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Taunt") {
        global $CS_AfterPlayedBy;
        SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit;maxCost=3");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "2579145458"://Luke Skywalker
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Give Shield") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:aspect=Heroism");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "turns=>0");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      }
      break;
    case "2912358777"://Grand Moff Tarkin
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Give Experience") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "3187874229"://Cassian Andor
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Draw Card") {
        global $CS_DamageTaken;
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        if(GetClassState($otherPlayer, $CS_DamageTaken) >= 3) Draw($gamestate->currentPlayer);
      }
      break;
    case "4841169874"://Sabine Wren
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        DealDamageAsync(1, 1, "DAMAGE", $cardID);
        DealDamageAsync(2, 1, "DAMAGE", $cardID);
      }
      break;
    case "5871074103"://Forced Surrender
      Draw($gamestate->currentPlayer);
      Draw($gamestate->currentPlayer);
      global $CS_DamageTaken;
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      if(GetClassState($otherPlayer, $CS_DamageTaken) > 0) {
        PummelHit($otherPlayer);
        PummelHit($otherPlayer);
      }
      break;
    case "9250443409"://Lando Calrissian
      if($from != "PLAY") {
        for($i=0; $i<2; ++$i) {
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose up to two resource cards to return to your hand");
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYRESOURCES");
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
        }
      }
      break;
    case "9070397522"://SpecForce Soldier
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to lose sentinel");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "9070397522,HAND", 1);
      }
      break;
    case "6458912354"://Death Trooper
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      }
      break;
    case "7109944284"://Luke Skywalker
      global $CS_NumAlliesDestroyed;
      if($from != "PLAY") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        $amount = GetClassState($gamestate->currentPlayer, $CS_NumAlliesDestroyed) > 0 ? 6 : 3;
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to debuff");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "7109944284-" . $amount . ",HAND", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REDUCEHEALTH," . $amount, 1);
      }
      break;
    case "7366340487"://Outmaneuver
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a mode for Outmaneuver");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "1-Ground,Space-1");
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "OUTMANEUVER", 1);
      break;
    case "6901817734"://Asteroid Sanctuary
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give a shield token");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=3");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      break;
    case "0705773109"://Vader's Lightsaber
      if(CardTitle(GetMZCard($gamestate->currentPlayer, $target)) == "Darth Vader") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 4 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,4", 1);
      }
      break;
    case "2048866729"://Iden Versio
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Heal") {
        global $CS_NumAlliesDestroyed;
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        if(GetClassState($otherPlayer, $CS_NumAlliesDestroyed) > 0) {
          Restore(1, $gamestate->currentPlayer);
        }
      }
      break;
    case "9680213078"://Leia Organa
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a mode for Leia Organa");
        AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "1-Ready Resource,Exhaust Unit-1");
        AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
        AddDecisionQueue("MODAL", $gamestate->currentPlayer, "LEIAORGANA", 1);
      }
      break;
    case "7916724925"://Bombing Run
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a mode for Bombing Run");
      AddDecisionQueue("MULTICHOOSETEXT", $gamestate->currentPlayer, "1-Ground,Space-1");
      AddDecisionQueue("SHOWMODES", $gamestate->currentPlayer, $cardID, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "BOMBINGRUN", 1);
      break;
    case "6088773439"://Darth Vader
      global $CS_NumVillainyPlayed;
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage" && GetClassState($gamestate->currentPlayer, $CS_NumVillainyPlayed) > 0) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage to", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        DealDamageAsync($otherPlayer, 1, "DAMAGE", "6088773439");
      }
      break;
    case "3503494534"://Regional Governor
      if($from != "PLAY") {
        WriteLog("This is a partially manual card. Name the card in chat and enforce the restrictions.");
      }
      break;
    case "0523973552"://I Am Your Father
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 7 damage to", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETCARDID", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Do you want your opponent to deal 7 damage to <1>?");
      AddDecisionQueue("YESNO", $otherPlayer, "-");
      AddDecisionQueue("NOPASS", $otherPlayer, "-", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,7", 1);
      AddDecisionQueue("ELSE", $otherPlayer, "-");
      AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      break;
    case "6903722220"://Luke's Lightsaber
      if(CardTitle(GetMZCard($gamestate->currentPlayer, $target)) == "Luke Skywalker") {
        $ally = new Ally($target, $gamestate->currentPlayer);
        $ally->Heal($ally->MaxHealth()-$ally->Health());
        $ally->Attach("8752877738");//Shield Token
      }
      break;
    case "5494760041"://Galactic Ambition
      global $CS_AfterPlayedBy;
      SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $gamestate->currentPlayer, "5494760041", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETCARDID", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 1, 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "2651321164"://Tactical Advantage
      $ally = new Ally($target);
      $ally->AddRoundHealthModifier(2);
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY", $ally->UniqueID());
      break;
    case "1701265931"://Moment of Glory
      $ally = new Ally($target);
      $ally->AddRoundHealthModifier(4);
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY", $ally->UniqueID());
      break;
    case "1900571801"://Overwhelming Barrage
      $ally = new Ally($target);
      $ally->AddRoundHealthModifier(2);
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY", $ally->UniqueID());
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "ALLTHEIRUNITSMULTI");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose units to damage", 1);
      AddDecisionQueue("MULTICHOOSETHEIRUNIT", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MULTIDISTRIBUTEDAMAGE", $gamestate->currentPlayer, $ally->CurrentPower(), 1);
      break;
    case "3974134277"://Prepare for Takeoff
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 8);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Vehicle", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Vehicle", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      break;
    case "3896582249"://Redemption
      if($from != "PLAY") {
        $ally = new Ally("MYALLY-" . LastAllyIndex($gamestate->currentPlayer));
        for($i=0; $i<8; ++$i) {
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY", $i == 0 ? 0 : 1);
          AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "MYCHAR-0,", $i == 0 ? 0 : 1);
          AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to restore 1 (Remaining: " . (8-$i) . ")", $i == 0 ? 0 : 1);
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "RESTORE,1", 1);
          AddDecisionQueue("UNIQUETOMZ", $gamestate->currentPlayer, $ally->UniqueID(), 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
        }
      }
      break;
    case "7861932582"://The Force is With Me
      $ally = new Ally($target, $gamestate->currentPlayer);
      $ally->Attach("2007868442");//Experience token
      $ally->Attach("2007868442");//Experience token
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Force")) > 0) {
        $ally->Attach("8752877738");//Shield Token
      }
      if(!$ally->IsExhausted()) {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Do you want to attack with the unit?");
        AddDecisionQueue("YESNO", $gamestate->currentPlayer, "-");
        AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-");
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "9985638644"://Snapshot Reflexes
      $mzArr = explode("-", $target);
      if($mzArr[0] == "MYALLY") {
        $ally = new Ally($target);
        if(!$ally->IsExhausted()) {
          AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK");
        }
      }
      break;
    case "7728042035"://Chimaera
      if($from == "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Name the card in chat");
        AddDecisionQueue("OK", $gamestate->currentPlayer, "-");
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "1");
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        AddDecisionQueue("FINDINDICES", $otherPlayer, "HAND");
        AddDecisionQueue("REVEALHANDCARDS", $otherPlayer, "-");
        AddDecisionQueue("FINDINDICES", $otherPlayer, "HAND");
        AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "If you have the named card, you must discard it", 1);
        AddDecisionQueue("MAYCHOOSEHAND", $otherPlayer, "<-", 1);
        AddDecisionQueue("MULTIREMOVEHAND", $otherPlayer, "-", 1);
        AddDecisionQueue("ADDDISCARD", $otherPlayer, "HAND", 1);
      }
      break;
    case "3809048641"://Surprise Strike
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack and give +3");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "3809048641,HAND", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "3038238423"://Fleet Lieutenant
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack and give +2");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("MZALLCARDTRAITORPASS", $gamestate->currentPlayer, "Rebel", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "3038238423,HAND", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}");
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "3208391441"://Make an Opening
      Restore(2, $gamestate->currentPlayer);
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give -2/-2", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "3208391441,HAND", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REDUCEHEALTH,2", 1);
      break;
    case "2758597010"://Maximum Firepower
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      for($i=0; $i<2; ++$i) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "dqVar=0", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to deal damage", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("APPENDDQVAR", $gamestate->currentPlayer, 0, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "POWER", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 1, 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,{1}", 1);
      }
      break;
    case "4263394087"://Chirrut Imwe
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Buff HP") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give +2 hp");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDHEALTH,2", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "4263394087,HAND", 1);
      }
      break;
    case "5154172446"://ISB Agent
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to reveal");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Event");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETCARDID", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage", 1);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "4300219753"://Fett's Firespray
      if($from != "PLAY") {
        $ready = false;
        $char = &GetPlayerCharacter($gamestate->currentPlayer);
        if(count($char) > CharacterPieces() && (CardTitle($char[CharacterPieces()]) == "Boba Fett" || CardTitle($char[CharacterPieces()]) == "Jango Fett")) $ready = true;
        if(SearchCount(SearchAlliesForTitle($gamestate->currentPlayer, "Boba Fett")) > 0 || SearchCount(SearchAlliesForTitle($gamestate->currentPlayer, "Jango Fett")) > 0) $ready = true;
        if($ready) {
          $playAlly->Ready();
        }
      } else {
        $abilityName = GetResolvedAbilityName($cardID, $from);
        if($abilityName == "Exhaust") {
          AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
          AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "unique=1");
          AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust");
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
        }
      }
      break;
    case "8009713136"://C-3PO
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a number");
      AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20");
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "C3PO", 1);
      break;
    case "7911083239"://Grand Inquisitor
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage and ready");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxAttack=3");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      }
      break;
    case "5954056864"://Han Solo
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Resource") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to resource");
        MZMoveCard($gamestate->currentPlayer, "MYHAND", "MYRESOURCES", may:false, silent:true);
        AddNextTurnEffect($cardID, $gamestate->currentPlayer);
      }
      break;
    case "6514927936"://Leia Organa Leader
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        AddCurrentTurnEffect($cardID . "-1", $gamestate->currentPlayer);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Rebel");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "8055390529"://Traitorous
      $mzArr = explode("-", $target);
      if($mzArr[0] == "THEIRALLY") {
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "TAKECONTROL");
      }
      break;
    case "8244682354"://Jyn Erso
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        AddCurrentTurnEffect($cardID, $otherPlayer);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "8327910265"://Energy Conversion Lab (ECL)
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit;maxCost=6");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "8600121285"://IG-88
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        if(HasMoreUnits($gamestate->currentPlayer)) AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "6954704048"://Heroic Sacrifice
      Draw($gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $gamestate->currentPlayer, "6954704048", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "3426168686"://Sneak Attack
      global $CS_AfterPlayedBy;
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cardID);
      AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AfterPlayedBy);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "8800836530"://No Good To Me Dead
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to exhaust");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDNEXTTURNEFFECT", $otherPlayer, "8800836530", 1);
      break;
    case "9097690846"://Snowtrooper Lieutenant
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack with");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
        AddDecisionQueue("MZALLCARDTRAITORPASS", $gamestate->currentPlayer, "Imperial", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "9097690846", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}");
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "9210902604"://Precision Fire
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack with");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "9210902604", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "7870435409"://Bib Fortuna
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Event") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an event to play");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Event");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "8297630396"://Shoot First
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack with");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "8297630396", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "5767546527"://For a Cause I Believe In
      $deck = new Deck($gamestate->currentPlayer);
      $deck->Reveal(4);
      $cards = $deck->Top(remove:true, amount:4);
      $cardArr = explode(",", $cards);
      $damage = 0;
      for($i=0; $i<count($cardArr); ++$i) {
        if(AspectContains($cardArr[$i], "Heroism", $gamestate->currentPlayer)) {
          ++$damage;
        }
      }
      WriteLog(CardLink($cardID, $cardID) . " is dealing " . $damage . " damage. Pass to discard the rest of the cards.");
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      DealDamageAsync($otherPlayer, $damage, "DAMAGE", "5767546527");
      if($cards != "") {
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cards);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Push pass (or Space) to discard the rest of the cards");
        AddDecisionQueue("MAYCHOOSETOP", $gamestate->currentPlayer, $cards);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "FORACAUSEIBELIEVEIN");
      }
      break;
    case "5784497124"://Emperor Palpatine
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an ally to destroy");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DESTROY", 1);
        AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an ally to deal 1 damage");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "8117080217"://Admiral Ozzel
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Imperial Unit") {
        global $CS_AfterPlayedBy;
        SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit;trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "1626462639"://Change of Heart
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "leader=1", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to take control of", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "TAKECONTROL", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "1626462639", 1);
      break;
    case "2855740390"://Lieutenant Childsen
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "HANDASPECT,Vigilance");
        AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "4-", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose up to 4 cards to reveal", 1);
        AddDecisionQueue("MULTICHOOSEHAND", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "LTCHILDSEN", 1);
      }
      break;
    case "8506660490"://Darth Vader
      if($from != "PLAY") {
        global $CS_AfterPlayedBy;
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
        $hand = &GetHand($gamestate->currentPlayer);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $cardID);
        AddDecisionQueue("SETCLASSSTATE", $gamestate->currentPlayer, $CS_AfterPlayedBy);
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXINDICES,10");
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "Deck-include-aspect-Villainy", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "Deck-include-maxCost-3", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "Deck-include-definedType-Unit", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "10-", 1);
        AddDecisionQueue("MULTICHOOSEDECK", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MULTIREMOVEDECK", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "DARTHVADER");
      }
      break;
    case "8615772965"://Vigilance
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $additionalCosts, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "VIGILANCE", 1);
      break;
    case "0073206444"://Command
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $additionalCosts, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "COMMAND", 1);
      break;
    case "3736081333"://Aggression
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $additionalCosts, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "AGGRESSION", 1);
      break;
    case "3789633661"://Cunning
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $additionalCosts, 1);
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "CUNNING", 1);
      break;
    case "2471223947"://Frontline Shuttle
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Shuttle") {
        $ally = new Ally("MYALLY-" . $index);
        $ally->Destroy();
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an ally to attack with");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, 1, 1);
        AddDecisionQueue("SETATTACKSTATE", $gamestate->currentPlayer, $AS_CantAttackBase, 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "8968669390"://U-Wing Reinforcement
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXINDICES,10");
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "Deck-include-definedType-Unit", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "Deck-include-maxCost-7", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "3-");
      AddDecisionQueue("MULTICHOOSEDECK", $gamestate->currentPlayer, "<-", 1, 1);
      AddDecisionQueue("MULTIREMOVEDECK", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "UWINGREINFORCEMENT");
      break;
    case "5950125325"://Confiscate
      DefeatUpgrade($gamestate->currentPlayer);
      break;
    case "2668056720"://Disabling Fang Fighter
      if($from != "PLAY") DefeatUpgrade($gamestate->currentPlayer, true);
      break;
    case "4323691274"://Power Failure
      DefeatUpgrade($gamestate->currentPlayer);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "POWERFAILURE", 1);
      break;
    case "6087834273"://Restock
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "GY");
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "4-");
      AddDecisionQueue("MULTICHOOSEDISCARD", $gamestate->currentPlayer, "<-");
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "RESTOCK", 1);
      break;
    case "5035052619"://Jabba the Hutt
      if($from != "PLAY") {
        $deck = &GetDeck($gamestate->currentPlayer);
        $numTricks = 0;
        for($i=0; $i<8 && $i<count($deck); ++$i) {
          if(TraitContains($deck[$i], "Trick", $gamestate->currentPlayer)) ++$numTricks;
        }
        if($numTricks == 0) {
          WriteLog("There are no tricks.");
        } else {
          AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 8);
          AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
          AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Trick", 1);
          AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
          AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
          AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
          AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
          AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
        }
      }
      break;
    case "9644107128"://Bamboozle
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "BAMBOOZLE", 1);
      break;
    case "2639435822"://Force Lightning
      $damage = 2 * (intval($resourcesPaid) - 1);
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to lose abilities and deal " . $damage . " damage");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "2639435822,PLAY", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE," . $damage, 1);
      break;
    case "1951911851"://Grand Admiral Thrawn
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Exhaust") {
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose player to reveal top of deck");
        AddDecisionQueue("BUTTONINPUT", $gamestate->currentPlayer, "Yourself,Opponent");
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "GRANDADMIRALTHRAWN", 1);
      }
      break;
    case "9785616387"://The Emperor's Legion
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "THEEMPERORSLEGION");
      break;
    case "1939951561"://Attack Pattern Delta
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      for($i=3; $i>0; --$i) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "dqVar=0");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give +" . $i . "/+" . $i, 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("APPENDDQVAR", $gamestate->currentPlayer, 0, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDHEALTH," . $i, 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "1939951561_" . $i . ",PLAY", 1);
      }
      break;
    case "2202839291"://Don't Get Cocky
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, 0);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "DONTGETCOCKY");
      break;
    case "2715652707"://I Had No Choice
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "leader=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("OP", $otherPlayer, "SWAPDQPERSPECTIVE", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY", 1);
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "leader=1", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("OP", $otherPlayer, "SWAPDQPERSPECTIVE", 1);
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "{0},", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Choose a unit to return to hand");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $otherPlayer, "<-", 1);
      AddDecisionQueue("SPECIFICCARD", $otherPlayer, "IHADNOCHOICE", 1);
      break;
    case "8988732248"://Rebel Assault
      AddCurrentTurnEffect($cardID . "-1", $gamestate->currentPlayer);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Rebel");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "0802973415"://Outflank
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "5896817672"://Headhunting
      AddCurrentTurnEffect($cardID . "-1", $gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0");
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, 1, 1);
      AddDecisionQueue("SETATTACKSTATE", $gamestate->currentPlayer, $AS_CantAttackBase, 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZALLCARDTRAITORPASS", $gamestate->currentPlayer, "Bounty Hunter", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "5896817672", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}");
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      AddDecisionQueue("REMOVECURRENTEFFECT", $gamestate->currentPlayer, $cardID . "-1");
      break;
    case "8142386948"://Razor Crest
      MZMoveCard($gamestate->currentPlayer, "MYDISCARD:definedType=Upgrade", "MYHAND", may:true);
      break;
    case "3228620062"://Cripple Authority
      Draw($gamestate->currentPlayer);
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      if(NumResources($otherPlayer) > NumResources($gamestate->currentPlayer)) {
        PummelHit($otherPlayer);
      }
      break;
    case "6722700037"://Doctor Pershing
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Draw") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
        AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      }
      break;
    case "6536128825"://Grogu
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Exhaust") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      }
      break;
    case "6585115122"://The Mandalorian
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=2&THEIRALLY:maxCost=2");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to heal and shield");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "RESTORE,999", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      }
      break;
    case "3329959260"://Fell the Dragon
      MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY:minAttack=5&THEIRALLY:minAttack=5", filter:"leader=1");
      break;
    case "0282219568"://Clan Wren Rescuer
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to add experience");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "1081897816"://Mandalorian Warrior
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Mandalorian&THEIRALLY:trait=Mandalorian");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to add experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "0866321455"://Smuggler's Aid
      Restore(3, $gamestate->currentPlayer);
      break;
    case "1090660242"://The Client
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Bounty") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give the bounty");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "1090660242-2,PLAY", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "WRITECHOICEFROMUNIQUE", 1);
      }
      break;
    case "1565760222"://Remnant Reserves
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
      for($i=0; $i<3; ++$i) {
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-definedType-Unit", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      }
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      break;
    case "2288926269"://Privateer Crew
      if($from == "RESOURCES") {
        for($i=0; $i<3; ++$i) $playAlly->Attach("2007868442");//Experience token
      }
      break;
    case "2470093702"://Wrecker
      MZChooseAndDestroy($gamestate->currentPlayer, "MYRESOURCES", may:true, context:"Choose a resource to destroy");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Ground&THEIRALLY:arena=Ground", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a ground unit to deal 5 damage to", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,5", 1);
      break;
    case "1885628519"://Crosshair
      if($from != "PLAY") break;
      $ally = new Ally("MYALLY-" . $index);
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Buff") {
        AddCurrentTurnEffect("1885628519", $gamestate->currentPlayer, $from, $ally->UniqueID());
      } else if($abilityName == "Snipe") {
        $currentPower = $ally->CurrentPower();
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a ground unit to deal " . $currentPower . " damage to", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE," . $currentPower, 1);
      }
      break;
    case "3514010297"://Mandalorian Armor
      $ally = new Ally($target, $gamestate->currentPlayer);
      if(TraitContains(GetMZCard($gamestate->currentPlayer, $target), "Mandalorian", $gamestate->currentPlayer, $ally->Index())) {
        $ally->Attach("8752877738");//Shield Token
      }
      break;
    case "1480894253"://Kylo Ren
      PummelHit($gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give +2 power", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "1480894253,PLAY", 1);
      break;
    case "0931441928"://Ma Klounkee
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Underworld");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to bounce");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 3 damage to", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,3", 1);
      break;
    case "0302968596"://Calculated Lethality
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=3&THEIRALLY:maxCost=3");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to defeat");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "CALCULATEDLETHALITY", 1);
      break;
    case "2503039837"://Moff Gideon Leader
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=3");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "9690731982"://Reckless Gunslinger
      if($from != "PLAY") {
        DealDamageAsync(1, 1, "DAMAGE", $cardID);
        DealDamageAsync(2, 1, "DAMAGE", $cardID);
      }
      break;
    case "8712779685"://Outland TIE Vanguard
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=3&THEIRALLY:maxCost=3");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to give experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "5874342508"://Hotshot DL-44 Blaster
      if($from == "RESOURCES") {
        $ally = new Ally($target);
        if(!$ally->IsExhausted() && $ally->PlayerID() == $gamestate->currentPlayer) {
          AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target);
          AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
        }
      }
      break;
    case "6884078296"://Greef Karga
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-definedType-Upgrade", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an upgrade to draw", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD");
        AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      }
      break;
    case "1304452249"://Covetous Rivals
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:hasBountyOnly=true&THEIRALLY:hasBountyOnly=true");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit with bounty to deal 2 damage to");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      break;
    case "2526288781"://Bossk
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage/Buff") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:hasBountyOnly=true&THEIRALLY:hasBountyOnly=true");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit with bounty to deal 1 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("YESNO", $gamestate->currentPlayer, "if you want to give the unit +1 power", 1);
        AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "2526288781", 1);
      }
      break;
    case "7424360283"://Bo-Katan Kryze
      global $CS_NumMandalorianAttacks;
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage" && GetClassState($gamestate->currentPlayer, $CS_NumMandalorianAttacks)) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "0505904136"://Scanning Officer
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $resources = &GetResourceCards($otherPlayer);
      if(count($resources) == 0) break;
      $numDestroyed = 0;
      $cards = "";
      for($i = 0; $i < 3 && count($resources) > 0; $i++) {
        $index = (GetRandom() % (count($resources)/ResourcePieces())) * ResourcePieces();
        if($cards != "") $cards .= ",";
        $cards .= $resources[$index];
        if(SmuggleCost($resources[$index], $otherPlayer, $index) >= 0) {
          for($j=$i; $j<$i+ResourcePieces(); ++$j) unset($resources[$j]);
          $resources = array_values($resources);
          ++$numDestroyed;
        }
      }
      for($i=0; $i<$numDestroyed; ++$i) {
        AddTopDeckAsResource($otherPlayer);
      }
      RevealCards($cards);
      break;
    case "2560835268"://The Armorer
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:trait=Mandalorian");
        AddDecisionQueue("OP", $gamestate->currentPlayer, "MZTONORMALINDICES");
        AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "3-", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose up to 3 mandalorians to give a shield");
        AddDecisionQueue("MULTICHOOSEUNIT", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "MULTIGIVESHIELD", 1);
      }
      break;
    case "3622749641"://Krrsantan
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $numBounty = SearchCount(SearchAllies($otherPlayer, hasBountyOnly:true));
      if($numBounty > 0) {
        $playAlly->Ready();
      }
      break;
    case "9765804063"://Discerning Veteran
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $playAlly->UniqueID(), 1);
      break;
    case "3765912000"://Take Captive
      $targetAlly = new Ally($target, $gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=" . CardArenas($targetAlly->CardID()));
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $targetAlly->UniqueID(), 1);
      break;
    case "8877249477"://Legal Authority
      $targetAlly = new Ally($target, $gamestate->currentPlayer);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:maxAttack=" . ($targetAlly->CurrentPower()-1));
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $targetAlly->UniqueID(), 1);
      break;
    case "5303936245"://Rival's Fall
      MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY&THEIRALLY");
      break;
    case "8818201543"://Midnight Repairs
      for($i=0; $i<8; ++$i) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY", $i == 0 ? 0 : 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to restore 1 (Remaining: " . (8-$i) . ")", $i == 0 ? 0 : 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "RESTORE,1", 1);
      }
      break;
    case "3012322434"://Give In To Your Hate
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage to");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
      AddDecisionQueue("WRITELOG", $gamestate->currentPlayer, "This is a partially manual card. Make sure you attack a unit with this unit for your next action.", 1);
      break;
    case "2090698177"://Street Gang Recruiter
      MZMoveCard($gamestate->currentPlayer, "MYDISCARD:trait=Underworld", "MYHAND", may:true, context:"Choose an uncerworld card to return with " . CardLink("2090698177", "2090698177"));
      break;
    case "7964782056"://Qi'Ra
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      LookAtHand($otherPlayer);
      WriteLog("This is a partially manual card. Name the card in chat and make sure you don't play that card if you don't have enough resources.");
      break;
    case "5830140660"://Bazine Netal
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRHAND");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to discard");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZDESTROY", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $otherPlayer, "-", 1);
      break;
    case "8645125292"://Covert Strength
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to restore 2 and give a experience token to");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "RESTORE,2", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      break;
    case "4783554451"://First Light
      if($from == "RESOURCES") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 4 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,4", 1);
      }
      break;
    case "5351496853"://Gideon's Light Cruiser
      if(ControlsNamedCard($gamestate->currentPlayer, "Moff Gideon")) {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYDISCARD:definedType=Unit;aspect=Villainy;maxCost=3&MYHAND:definedType=Unit;aspect=Villainy;maxCost=3");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "5440730550"://Lando Calrissian
      global $CS_AfterPlayedBy;
      SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYRESOURCES:keyword=Smuggle");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
      break;
    case "040a3e81f3"://Lando Calrissian Leader Unit
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Smuggle") {
        global $CS_AfterPlayedBy;
        SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYRESOURCES:keyword=Smuggle");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
        AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
      }
      break;
    case "0754286363"://The Mandalorian's Rifle
      $ally = new Ally($target, $gamestate->currentPlayer);
      if(CardTitle($ally->CardID()) == "The Mandalorian") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=0");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $ally->UniqueID(), 1);
      }
      break;
    case "4643489029"://Palpatine's Return
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYDISCARD:definedType=Unit");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to play");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "4717189843"://A New Adventure
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxCost=6&THEIRALLY:maxCost=6");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to return to hand");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "9757839764"://Adelphi Patrol Wing
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack and give +2");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      if($gamestate->initiativePlayer == $gamestate->currentPlayer) {
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "9757839764,HAND", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      }
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "7212445649"://Bravado
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to ready");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      break;
    case "2432897157"://Qi'Ra
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Shield") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage and give a shield");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      }
      break;
    case "4352150438"://Rey
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Experience") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxAttack=2");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give an experience");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "5778949819"://Relentless Pursuit
      $ally = new Ally($target, $gamestate->currentPlayer);
      if(TraitContains($ally->CardID(), "Bounty Hunter", $gamestate->currentPlayer)) $ally->Attach("8752877738");//Shield Token
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:maxCost=" . (CardCost($ally->CardID())));
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to capture");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $ally->UniqueID(), 1);
      break;
    case "6847268098"://Timely Intervention
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY");
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "1973545191"://Unexpected Escape
      $owner = MZPlayerID($gamestate->currentPlayer, $target);
      $ally = new Ally($target, $owner);
      $ally->Exhaust();
      RescueUnit($gamestate->currentPlayer, $target);
      break;
    case "9552605383"://L3-37
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to rescue from (or pass for shield)");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "L337");
      break;
    case "5818136044"://Xanadu Blood
      XanaduBlood($gamestate->currentPlayer, $playAlly->Index());
      break;
    case "1312599620"://Smuggler's Starfighter
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Underworld")) > 0) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give -3 power");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "1312599620,PLAY", 1);
      }
      break;
    case "6853970496"://Slaver's Freighter
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $theirAllies = &GetAllies($otherPlayer);
      $numUpgrades = 0;
      for($i=0; $i<count($theirAllies); $i+=AllyPieces()) {
        $ally = new Ally("MYALLY-" . $i, $otherPlayer);
        $numUpgrades += $ally->NumUpgrades();
      }
      if($numUpgrades > 0) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:maxAttack=" . $numUpgrades . "&THEIRALLY:maxAttack=" . $numUpgrades);
        if($index > -1) AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "index=MYALLY-" . $playAlly->Index());
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to ready");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      }
      break;
    case "2143627819"://The Marauder
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYDISCARD");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card in your discard to resource");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "THEMARAUDER", 1);
      break;
    case "7642980906"://Stolen Landspeeder
      if($from == "HAND") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        AddDecisionQueue("PASSPARAMETER", $otherPlayer, "THEIRALLY-" . $playAlly->Index(), 1);
        AddDecisionQueue("MZOP", $otherPlayer, "TAKECONTROL", 1);
      }
      break;
    case "2346145249"://Choose Sides
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a friendly unit to swap");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $otherPlayer, "TAKECONTROL", 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY", 1);
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an enemy unit to swap", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "TAKECONTROL", 1);
      break;
    case "0598830553"://Dryden Vos
      PlayCaptive($gamestate->currentPlayer, $target);
      break;
    case "1477806735"://Wookiee Warrior
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Wookiee")) > 1) {
        Draw($gamestate->currentPlayer);
      }
      break;
    case "5696041568"://Triple Dark Raid
      global $CS_AfterPlayedBy;
      SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 8);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Vehicle", 1);
      AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{1}", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "PLAYCARD,DECK", 1);
      break;
    case "0911874487"://Fennec Shand
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Ambush") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit;maxCost=4");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "2b13cefced"://Fennec Shand
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Ambush") {
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer, "PLAY");
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit;maxCost=4");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "9828896088":
      WriteLog("Spark of Hope is a partially manual card. Enforce the turn restriction manually.");
      MZMoveCard($gamestate->currentPlayer, "MYDISCARD:definedType=Unit", "MYRESOURCES", may:true);
      AddDecisionQueue("PAYRESOURCES", $gamestate->currentPlayer, "1,1", 1);
      break;
    case "9845101935"://This is the Way
      WriteLog("This is a partially manual card. Enforce the type restriction manually.");
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 8);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      break;
    case "8261033110"://Evacuate
      $gamestate->p1Allies = &GetAllies(1);
      for($i=count($gamestate->p1Allies)-AllyPieces(); $i>=0; $i-=AllyPieces()) {
        if(!IsLeader($gamestate->p1Allies[$i], 1))
          MZBounce(1, "MYALLY-" . $i);
      }
      $gamestate->p2Allies = &GetAllies(2);
      for($i=count($gamestate->p2Allies)-AllyPieces(); $i>=0; $i-=AllyPieces()) {
        if(!IsLeader($gamestate->p2Allies[$i], 1))
          MZBounce(2, "MYALLY-" . $i);
      }
      break;
    case "1910812527"://Final Showdown
      AddCurrentTurnEffect("1910812527", $gamestate->currentPlayer);
      $myAllies = &GetAllies($gamestate->currentPlayer);
      for($i=0; $i<count($myAllies); $i+=AllyPieces())
      {
        $ally = new Ally("MYALLY-" . $i, $gamestate->currentPlayer);
        $ally->Ready();
      }
      break;
    case "a742dea1f1"://Han Solo Red Unit
    case "9226435975"://Han Solo Red
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play") {
        global $CS_AfterPlayedBy;
        SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddCurrentTurnEffect("9226435975", $gamestate->currentPlayer);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Unit");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to play");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "7354795397"://No Bargain
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      PummelHit($otherPlayer);
      Draw($gamestate->currentPlayer);
      break;
    case "9270539174"://Wild Rancor
      DamageAllAllies(2, "9270539174", arena:"Ground", except:"MYALLY-".LastAllyIndex($gamestate->currentPlayer));
      break;
    case "2744523125"://Salacious Crumb
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Bounce") {
        $salaciousCrumbIndex = SearchAlliesForCard($gamestate->currentPlayer, $cardID);
        MZBounce($gamestate->currentPlayer, "MYALLY-" . $salaciousCrumbIndex);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Ground&THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 1 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,1", 1);
      } else if($from != "PLAY") {
        Restore(1, $gamestate->currentPlayer);
      }
      break;
    case "0622803599"://Jabba the Hutt Leader
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Bounty") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give bounty");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "0622803599-2,PLAY", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "WRITECHOICEFROMUNIQUE", 1);
      }
      break;
    case "f928681d36"://Jabba the Hutt Leader Unit
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Bounty") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give bounty");
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "f928681d36-2,PLAY", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "WRITECHOICEFROMUNIQUE", 1);
      }
      break;
    case "8090818642"://The Chaos of War
      $gamestate->p1Hand = &GetHand(1);
      DamageTrigger(1, count($gamestate->p1Hand)/HandPieces(), "DAMAGE", "8090818642");
      $gamestate->p2Hand = &GetHand(2);
      DamageTrigger(2, count($gamestate->p2Hand)/HandPieces(), "DAMAGE", "8090818642");
      break;
    case "7826408293"://Daring Raid
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("PREPENDLASTRESULT", $gamestate->currentPlayer, "MYCHAR-0,THEIRCHAR-0,");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose something to deal 2 damage to");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      break;
    case "4772866341"://Pillage
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      $player = $additionalCosts == "Yourself" ? $gamestate->currentPlayer : $otherPlayer;
      PummelHit($player);
      PummelHit($player);
      break;
    case "5984647454"://Enforced Loyalty
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose something to sacrifice");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DESTROY", 1);
      AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $gamestate->currentPlayer, "-", 1);
      break;
    case "6234506067"://Cassian Andor
      if($from == "RESOURCES") $playAlly->Ready();
      break;
    case "5169472456"://Chewbacca Pykesbane
      if($from != "PLAY") {
        MZChooseAndDestroy($gamestate->currentPlayer, "MYALLY:maxHealth=5&THEIRALLY:maxHealth=5", may:true, filter:"index=MYALLY-" . $playAlly->Index());
      }
      break;
    case "6962053552"://Desperate Attack
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "status=1");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "damaged=0");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to attack and give +2");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "6962053552,HAND", 1);
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}");
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      }
      break;
    case "3803148745"://Ruthless Assassin
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage to");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      break;
    case "4057912610"://Bounty Guild Initiate
      if($from != "PLAY" && SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Bounty Hunter")) > 1) {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      }
      break;
    case "6475868209"://Criminal Muscle
      if($from != "PLAY") {
        DefeatUpgrade($gamestate->currentPlayer, may:true, upgradeFilter: "unique=1", to:"HAND");
      }
      break;
    case "1743599390"://Trandoshan Hunters
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      if(SearchCount(SearchAllies($otherPlayer, hasBountyOnly:true)) > 0) $playAlly->Attach("2007868442");//Experience token
      break;
    case "1141018768"://Commission
      WriteLog("This is a partially manual card. Enforce the type restriction manually.");
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 10);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD", 1);
      AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      break;
    case "9596662994"://Finn
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Shield") {
        DefeatUpgrade($gamestate->currentPlayer, search:"MYALLY");
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDSHIELD", 1);
      }
      break;
    case "7578472075"://Let the Wookie Win
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("BUTTONINPUT", $otherPlayer, "Ready Resources,Ready Unit");
      AddDecisionQueue("MODAL", $gamestate->currentPlayer, "LETTHEWOOKIEWIN");
      break;
    case "8380936981"://Jabba's Rancor
      JabbasRancor($gamestate->currentPlayer, $playAlly->Index());
      break;
    case "2750823386"://Look the Other Way
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to exhaust");
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETCARDID", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("YESNO", $otherPlayer, "if you want to pay 2 to prevent <1> from being exhausted", 1);//Should have a CardLink, but doing SETDQVAR and adding <1> to the string for YESNO breaks the UI. Something to do with YESNO being processed outside normal DecisionQueue stuff I suspect.
      AddDecisionQueue("NOPASS", $otherPlayer, "-", 1);
      AddDecisionQueue("PAYRESOURCES", $otherPlayer, "2", 1);
      AddDecisionQueue("ELSE", $gamestate->currentPlayer, "-");
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      break;
    case "4002861992"://DJ (Blatant Thief)
      if($from == "RESOURCES") {
        $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
        $theirResources = &GetResourceCards($otherPlayer);
        $resourceCard = RemoveResource($otherPlayer, count($theirResources) - ResourcePieces());
        AddResources($resourceCard, $gamestate->currentPlayer, "PLAY", "DOWN");
        AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);
      }
      break;
    case "7718080954"://Frozen in Carbonite
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, $target);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REST", 1);
      break;
    case "6117103324"://Jetpack
      $ally = new Ally($target, $gamestate->currentPlayer);
      $ally->AddEffect("6117103324");
      $ally->Attach("8752877738");//Shield Token
      break;
    case "1386874723"://Omega (Part of the Squad)
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-trait-Clone", 1);
        AddDecisionQueue("MAYCHOOSECARD", $gamestate->currentPlayer, "<-", 1); //The search window only shows takeable cards, same as Grand Moff Tarkin unit's trigger(which I copied the code from). Ideally the player would get to see all the cards in the search(to see what's getting sent to the bottom).
        AddDecisionQueue("ADDHAND", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("REVEALCARDS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "REMOVECARD");
        AddDecisionQueue("ALLRANDOMBOTTOM", $gamestate->currentPlayer, "DECK");
      }
      break;
    case "6151970296"://Bounty Posting
      MZMoveCard($gamestate->currentPlayer, "MYDECK:trait=Bounty", "MYHAND", isReveal:true, may:true, context:"Choose a bounty to add to your hand");
      AddDecisionQueue("YESNO", $gamestate->currentPlayer, "if you want to play the upgrade", 1);
      AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-", 1);
      AddDecisionQueue("FINDINDICES", $gamestate->currentPlayer, "MZLASTHAND", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "8576088385"://Detention Block Rescue
      $owner = MZPlayerID($gamestate->currentPlayer, $target);
      $ally = new Ally($target, $owner);
      $damage = count($ally->GetCaptives()) > 0 ? 6 : 3;
      $ally->DealDamage($damage);
      break;
    case "9999079491"://Mystic Reflection
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a card to debuff", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, 0, 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "9999079491,HAND", 1);
      if(SearchCount(SearchAllies($gamestate->currentPlayer, trait:"Force")) > 0) {
        AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "REDUCEHEALTH,2", 1);
      }
      break;
    case "5576996578"://Endless Legions
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "ENDLESSLEGIONS");
      break;
    case "8095362491"://Frontier Trader
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYRESOURCES");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a resource to return to hand", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "BOUNCE", 1);
        AddDecisionQueue("YESNO", $gamestate->currentPlayer, "if you want to add a resource from the top of your deck", 1);
        AddDecisionQueue("NOPASS", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("OP", $gamestate->currentPlayer, "ADDTOPDECKASRESOURCE", 1);
      }
        break;
    case "8709191884"://Hunter (Outcast Sergeant)
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Replace Resource") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYRESOURCES");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a resource to reveal", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "HUNTEROUTCASTSERGEANT", 1);
      }
      break;
    case "4663781580"://Swoop Down
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:arena=Space");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to attack with", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "4663781580,HAND", 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $otherPlayer, "4663781580", 1);
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ATTACK", 1);
      break;
    case "9752523457"://Finalizer
      $allies = &GetAllies($gamestate->currentPlayer);
      for($i=0; $i<count($allies); $i+=AllyPieces()) {
        $ally = new Ally("MYALLY-" . $i, $gamestate->currentPlayer);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "THEIRALLY");
        AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "definedType=Leader", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit for " . CardLink($ally->CardID(), $ally->CardID()) . " to capture (must be in same arena)", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "CAPTURE," . $ally->UniqueID(), 1);
      }
      break;
    case "6425029011"://Altering the Deal
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "hasCaptives=0", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a friendly unit to discard a captive from.", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETCAPTIVES", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a captive to discard", 1);
      AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("OP", $gamestate->currentPlayer, "DISCARDCAPTIVE", 1);
      break;
    case "6452159858"://Evidence of the Crime
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:hasUpgradeOnly=true&THEIRALLY:hasUpgradeOnly=true");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to take a 3-cost or less upgrade from.", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUPGRADES", 1);
      AddDecisionQueue("FILTER", $gamestate->currentPlayer, "LastResult-include-maxCost-3", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an upgrade to take.", 1);
      AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY&THEIRALLY");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "canAttach={0}", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to move <0> to.", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "MOVEUPGRADE", 1);
      break;
    case "3399023235"://Fenn Rau
      AddCurrentTurnEffect($cardID, $gamestate->currentPlayer);//Cost discount
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND:definedType=Upgrade");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an upgrade to play");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "PLAYCARD", 1);
      break;
    case "1503633301"://Survivors' Gauntlet
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:hasUpgradeOnly=true&THEIRALLY:hasUpgradeOnly=true");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to move an upgrade from.", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUPGRADES", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an upgrade to move.", 1);
      AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "SURVIVORS'GAUNTLET", 1);
      break;
    case "3086868510"://Pre Vizsla
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:hasUpgradeOnly=true&THEIRALLY:hasUpgradeOnly=true");
      AddDecisionQueue("MZFILTER", $gamestate->currentPlayer, "trait=Vehicle", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to steal an upgrade from.", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "1", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUPGRADES", 1);
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose an upgrade to steal.", 1);
      AddDecisionQueue("CHOOSECARD", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "PREVIZSLA", 1);
      break;
    case "3671559022"://Echo
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYHAND");
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "You may discard a card to Echo's ability", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZDISCARD", $gamestate->currentPlayer, "HAND," . $gamestate->currentPlayer, 1);
        AddDecisionQueue("MZREMOVE", $gamestate->currentPlayer, "-", 1);
        AddDecisionQueue("SETDQVAR", $gamestate->currentPlayer, "0", 1);
        AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY:sameTitle={0}&THEIRALLY:sameTitle={0}", 1);
        AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a unit to give 2 experience tokens to.", 1);
        AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
        AddDecisionQueue("MZOP", $gamestate->currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "8080818347"://Rule with Respect
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a friendly unit to capture all enemy units that attacked your base this phase", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "RULEWITHRESPECT", 1);
    break;
    case "3468546373"://General Rieekan
      AddDecisionQueue("MULTIZONEINDICES", $gamestate->currentPlayer, "MYALLY");
      AddDecisionQueue("SETDQCONTEXT", $gamestate->currentPlayer, "Choose a target for " . CardLink($cardID, $cardID) . "'s ability", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $gamestate->currentPlayer, "<-", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "GENERALRIEEKAN", 1);
    default: break;
  }
}

function ReadyResource($player, $amount=1) {
  $resourceCards = &GetResourceCards($player);
  $numReadied = 0;
  for($i=0; $i<count($resourceCards) && $numReadied < $amount; $i+=ResourcePieces()) {
    if($resourceCards[$i + 4] == 1) {
      ++$numReadied;
      $resourceCards[$i + 4] = 0;
    }
  }
}

function ExhaustResource($player, $amount=1) {
  $resourceCards = &GetResourceCards($player);
  $numExhausted = 0;
  for($i=0; $i<count($resourceCards) && $numExhausted < $amount; $i+=ResourcePieces()) {
    if($resourceCards[$i + 4] == 0) {
      ++$numExhausted;
      $resourceCards[$i + 4] = 1;
    }
  }
}

function AfterPlayedByAbility($cardID) {
  global $gamestate, $CS_AfterPlayedBy;
  SetClassState($gamestate->currentPlayer, $CS_AfterPlayedBy, "-");
  $index = LastAllyIndex($gamestate->currentPlayer);
  $ally = new Ally("MYALLY-" . $index, $gamestate->currentPlayer);
  switch($cardID) {
    case "040a3e81f3"://Lando Calrissian Leader Unit
    case "5440730550"://Lando Calrissian
      AddDecisionQueue("OP", $gamestate->currentPlayer, "ADDTOPDECKASRESOURCE");
      MZChooseAndDestroy($gamestate->currentPlayer, "MYRESOURCES", context:"Choose a resource to destroy");
      break;
    case "a742dea1f1"://Han Solo Red Unit
    case "9226435975"://Han Solo Red
      AddDecisionQueue("OP", $gamestate->currentPlayer, "GETLASTALLYMZ");
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "DEALDAMAGE,2", 1);
      break;
    case "3572356139"://Chewbacca, Walking Carpet
      AddDecisionQueue("OP", $gamestate->currentPlayer, "GETLASTALLYMZ");
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "3572356139,PLAY", 1);
      break;
    case "5494760041"://Galactic Ambition
      AddDecisionQueue("PASSPARAMETER", $gamestate->currentPlayer, "{1}", 1);
      AddDecisionQueue("SPECIFICCARD", $gamestate->currentPlayer, "GALACTICAMBITION", 1);
      break;
    case "7270736993"://Unrefusable Offer
    case "3426168686"://Sneak Attack
      AddDecisionQueue("OP", $gamestate->currentPlayer, "GETLASTALLYMZ");
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, $cardID . "-2,PLAY", 1);
      break;
    case "8117080217"://Admiral Ozzel
      $ally->Ready();
      $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Choose a unit to ready");
      AddDecisionQueue("MULTIZONEINDICES", $otherPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $otherPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $otherPlayer, "READY", 1);
      break;
    case "8506660490"://Darth Vader
      global $gamestate;
      $index = count($gamestate->currentTurnEffects) - CurrentTurnEffectPieces();
      RemoveCurrentTurnEffect($index);
      break;
    case "8968669390"://U-Wing Reinforcement
      SearchCurrentTurnEffects("8968669390", $gamestate->currentPlayer, remove:true);
      break;
    case "5696041568"://Triple Dark Raid
      AddDecisionQueue("OP", $gamestate->currentPlayer, "GETLASTALLYMZ", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "READY", 1);
      AddDecisionQueue("MZOP", $gamestate->currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->currentPlayer, "5696041568-2,HAND", 1);
      break;
    default: break;
  }
}

function MemoryCount($player) {
  $memory = &GetMemory($player);
  return count($memory)/MemoryPieces();
}

function MemoryRevealRandom($player, $returnIndex=false)
{
  $memory = &GetMemory($player);
  $rand = GetRandom()%(count($memory)/MemoryPieces());
  $index = $rand*MemoryPieces();
  $toReveal = $memory[$index];
  $wasRevealed = RevealCards($toReveal);
  return $wasRevealed ? ($returnIndex ? $toReveal : $index) : ($returnIndex ? -1 : "");
}

function ExhaustAllAllies($arena, $player)
{
  $allies = &GetAllies($player);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    if(CardArenas($allies[$i]) == $arena) {
      $ally = new Ally("MYALLY-" . $i, $player);
      $ally->Exhaust();
    }
  }
}

function DestroyAllAllies()
{
  global $gamestate;
  $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
  $theirAllies = &GetAllies($otherPlayer);
  for($i=count($theirAllies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if (!isset($theirAllies[$i])) continue;
    $ally = new Ally("MYALLY-" . $i, $otherPlayer);
    $ally->Destroy();
  }
  $allies = &GetAllies($gamestate->currentPlayer);
  for($i=count($allies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if (!isset($allies[$i])) continue;
    $ally = new Ally("MYALLY-" . $i, $gamestate->currentPlayer);
    $ally->Destroy();
  }
}

function DamagePlayerAllies($player, $damage, $source, $type, $arena="")
{
  $allies = &GetAllies($player);
  for($i=count($allies)-AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if($arena != "" && !ArenaContains($allies[$i], $arena, $player)) continue;
    $ally = new Ally("MYALLY-" . $i, $player);
    $ally->DealDamage($damage);
  }
}

function DamageAllAllies($amount, $source, $alsoRest=false, $alsoFreeze=false, $arena="", $except="")
{
  global $gamestate;
  $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
  $theirAllies = &GetAllies($otherPlayer);
  for($i=count($theirAllies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if(!ArenaContains($theirAllies[$i], $arena, $otherPlayer)) continue;
    if($alsoRest) $theirAllies[$i+1] = 1;
    if($alsoFreeze) $theirAllies[$i+3] = 1;
    $ally = new Ally("THEIRALLY-$i");
    $ally->DealDamage($amount);
  }
  $allies = &GetAllies($gamestate->currentPlayer);
  for($i=count($allies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if(!ArenaContains($allies[$i], $arena, $gamestate->currentPlayer)) continue;
    if($except != "" && $except == ("MYALLY-" . $i)) continue;
    if($alsoRest) $allies[$i+1] = 1;
    if($alsoFreeze) $allies[$i+3] = 1;
    $ally = new Ally("MYALLY-$i");
    $ally->DealDamage($amount);
  }
}



function IsHarmonizeActive($player)
{
  global $CS_NumMelodyPlayed;
  return GetClassState($player, $CS_NumMelodyPlayed) > 0;
}

function AddPreparationCounters($player, $amount=1)
{
  global $CS_PreparationCounters;
  IncrementClassState($player, $CS_PreparationCounters, $amount);
}

function DrawIntoMemory($player)
{
  $deck = &GetDeck($player);
  if(count($deck) > 0) AddMemory(array_shift($deck), $player, "DECK", "DOWN");
}

function Mill($player, $amount)
{
  $cards = "";
  $deck = &GetDeck($player);
  if($amount > count($deck)) $amount = count($deck);
  for($i=0; $i<$amount; ++$i)
  {
    $card = array_shift($deck);
    if($cards != "") $cards .= ",";
    $cards .= $card;
    AddGraveyard($card, $player, "DECK");
  }
  return $cards;
}

function AddTopDeckAsResource($player, $isExhausted=true)
{
  $deck = &GetDeck($player);
  if(count($deck) > 0) {
    $card = array_shift($deck);
    AddResources($card, $player, "DECK", "DOWN", isExhausted:($isExhausted ? 1 : 0));
  }
}

//target type return values
//-1: no target
// 0: My Hero + Their Hero
// 1: Their Hero only
// 2: Any Target
// 3: Their Hero + Their Allies
// 4: My Hero only (For afflictions)
// 6: Any unit
// 7: Friendly unit
function PlayRequiresTarget($cardID)
{
  global $gamestate;
  switch($cardID)
  {
    case "8679831560": return 2;//Repair
    case "8981523525": return 6;//Moment of Peace
    case "0867878280": return 6;//It Binds All Things
    case "2587711125": return 6;//Disarm
    case "6515891401": return 6;//Karabast
    case "2651321164": return 6;//Tactical Advantage
    case "1900571801": return 7;//Overwhelming Barrage
    case "7861932582": return 6;//The Force is With Me
    case "2758597010": return 6;//Maximum Firepower
    case "2202839291": return 6;//Don't Get Cocky
    case "1701265931": return 6;//Moment of Glory
    case "3765912000": return 7;//Take Captive
    case "5778949819": return 7;//Relentless Pursuit
    case "1973545191": return 6;//Unexpected Escape
    case "0598830553": return 6;//Dryden Vos
    case "8576088385": return 6;//Detention Block Rescue
    default: return -1;
  }
}

  //target type return values
  //-1: no target
  // 0: My Hero + Their Hero
  // 1: Their Hero only
  // 2: Any Target
  // 3: Their Units
  // 4: My Hero only (For afflictions)
  // 6: Any unit
  // 7: Friendly unit
  function GetIndicesForTargetType($player, $targetType)
  {
    global $CS_TargetsSelected;
    $otherPlayer = ($player == 1 ? 2 : 1);
    if ($targetType == 4) return "MYCHAR-0";
    if($targetType != 3 && $targetType != 6 && $targetType != 7) $rv = "THEIRCHAR-0";
    else $rv = "";
    if(($targetType == 0 && !ShouldAutotargetOpponent($player)) || $targetType == 2)
    {
      $rv .= ",MYCHAR-0";
    }
    if($targetType == 2 || $targetType == 6)
    {
      $theirAllies = &GetAllies($otherPlayer);
      for($i=0; $i<count($theirAllies); $i+=AllyPieces())
      {
        if($rv != "") $rv .= ",";
        $rv .= "THEIRALLY-" . $i;
      }
      $myAllies = &GetAllies($player);
      for($i=0; $i<count($myAllies); $i+=AllyPieces())
      {
        if($rv != "") $rv .= ",";
        $rv .= "MYALLY-" . $i;
      }
    }
    elseif($targetType == 3 || $targetType == 5)
    {
      $theirAllies = &GetAllies($otherPlayer);
      for($i=0; $i<count($theirAllies); $i+=AllyPieces())
      {
        if($rv != "") $rv .= ",";
        $rv .= "THEIRALLY-" . $i;
      }
    } else if($targetType == 7) {
      $myAllies = &GetAllies($player);
      for($i=0; $i<count($myAllies); $i+=AllyPieces())
      {
        if($rv != "") $rv .= ",";
        $rv .= "MYALLY-" . $i;
      }
    }
    $targets = explode(",", $rv);
    $targetsSelected = GetClassState($player, $CS_TargetsSelected);
    for($i=count($targets)-1; $i>=0; --$i)
    {
      if(DelimStringContains($targetsSelected, $targets[$i])) unset($targets[$i]);
    }
    return implode(",", $targets);
  }

function CountPitch(&$pitch, $min = 0, $max = 9999)
{
  $pitchCount = 0;
  for($i = 0; $i < count($pitch); ++$i) {
    $cost = CardCost($pitch[$i]);
    if($cost >= $min && $cost <= $max) ++$pitchCount;
  }
  return $pitchCount;
}

function HandIntoMemory($player)
{
  AddDecisionQueue("MULTIZONEINDICES", $player, "MYHAND");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a card to put into memory", 1);
  AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("MZADDZONE", $player, "MYMEMORY,HAND,DOWN", 1);
  AddDecisionQueue("MZREMOVE", $player, "-", 1);
}

function Draw($player, $mainPhase = true, $fromCardEffect = true)
{
  global $gamestate;
  $otherPlayer = ($player == 1 ? 2 : 1);
  $deck = &GetDeck($player);
  $hand = &GetHand($player);
  if(count($deck) == 0) {
    $char = &GetPlayerCharacter($player);
    if(count($char) > CharacterPieces() && $char[CharacterPieces()] != "DUMMY") WriteLog("Player " . $player . " took 3 damage for having no cards left in their deck.");
    DealDamageAsync($player, 3, "DAMAGE", "DRAW");
    return -1;
  }
  $hand[] = array_shift($deck);
  $hand = array_values($hand);
  return $hand[count($hand) - 1];
}

function WakeUpChampion($player)
{
  $char = &GetPlayerCharacter($player);
  $char[1] = 2;
}

