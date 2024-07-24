<?php

$GameStatus_Over = 2;
$GameStatus_Rematch = 3;

function DeckPieces()
{
  return 1;
}

function HandPieces()
{
  return 1;
}

function DiscardPieces()
{
  return 2;
}

//0 - Card ID
//1 - Status (2=ready, 1=unavailable, 0=destroyed)
//2 - Num counters
//3 - Num attack counters
//4 - Num defense counters
//5 - Num uses
//6 - On chain (1 = yes, 0 = no)
//7 - Flagged for destruction (1 = yes, 0 = no)
//8 - Frozen (1 = yes, 0 = no)
//9 - Is Active (2 = always active, 1 = yes, 0 = no)
//10 - Position (0 = normal, 1 = distant)
function CharacterPieces()
{
  return 11;
}

//0 - Card ID
//1 - Mods (INT == Intimidated)
//2 - Unique ID?
function BanishPieces()
{
  return 3;
}

//0 - Card ID
//1 - Status (2=ready, 1=unavailable, 0=destroyed)
//2 - Num counters
//3 - Num attack counters
//4 - Is Token (1 = yes, 0 = no)
//5 - Number of ability uses (triggered or activated)
//6 - Unique ID
//7 - My Hold priority for triggers (2 = always hold, 1 = hold, 0 = don't hold)
//8 - Opponent Hold priority for triggers (2 = always hold, 1 = hold, 0 = don't hold)
function AuraPieces()
{
  return 9;
}

//0 - Item ID
//1 - Counters/Steam Counters
//2 - Status (2=ready, 1=unavailable, 0=destroyed)
//3 - Num Uses
//4 - Unique ID
//5 - My Hold priority for triggers (2 = always hold, 1 = hold, 0 = don't hold)
//6 - Opponent Hold priority for triggers (2 = always hold, 1 = hold, 0 = don't hold)
function ItemPieces()
{
  return 7;
}

function PitchPieces()
{
  return 1;
}

//0 - Effect ID
//1 - Player ID
//2 - Applies to Unique ID
//3 - Number of uses remaining
function CurrentTurnPieces()
{
  return 4;
}

//0 - ?
//1 - Effect Card ID
function CharacterEffectPieces()
{
  return 2;
}

//0 - Card ID
//1 - Face up/down
//2 - ?
//3 - Counters
//4 - Exhausted: 0 = no, 1 = yes
//5 - Unique ID
function ArsenalPieces()
{
  return 6;
}
function MemoryPieces() { return ArsenalPieces(); }
function ResourcePieces() { return ArsenalPieces(); }

//0 - Card ID
//1 - Status: 2 = ready
//2 - Health
//3 - Frozen - 0 = no, 1 = yes
//4 - Subcards , delimited
//5 - Unique ID
//6 - Counters
//7 - Buff Counters
//8 - Ability/effect Uses
//9 - Round health modifier
//10 - Times Attacked
//11 - Owner
//12 - Turns in play
function AllyPieces()
{
  return 13;
}

//Card ID
function PermanentPieces()
{
  return 1;
}

//0 - Card ID/Layer type
//1 - Player
//2 - Parameter (For play card | Delimited, piece 0 = $from)
//3 - Target
//4 - Additional Costs
//5 - Unique ID (the unique ID of the object that created the layer)
//6 - Layer Unique ID (the unique ID of the layer)
function LayerPieces()
{
  return 7;
}

function LandmarkPieces()
{
  return 2;
}

function DecisionQueuePieces()
{
  return 5;
}

//0 - Card ID
function MaterialPieces()
{
  return 1;
}

//0 - Event type
//1 - Event Value
function EventPieces()
{
  return 2;
}

//0 - cardId
//1 - ownerId
function SubcardPieces(){
  return 2;
}

$SHMOP_CURRENTPLAYER = 9;
$SHMOP_ISREPLAY = 10;//0 = not replay, 1 = replay

//Class State (one for each player)
$CS_NumVillainyPlayed = 0;
$CS_NumBoosted = 1;
$CS_AtksWWeapon = 2;
$CS_HitsWDawnblade = 3;
$CS_DamagePrevention = 4;
$CS_CardsBanished = 5;
$CS_DamageTaken = 6;
$CS_NumActionsPlayed = 7;
$CS_ArsenalFacing = 8;//Deprecated
$CS_CharacterIndex = 9;
$CS_PlayIndex = 10;
$CS_NumNonAttackCards = 11;
$CS_CachedCharacterLevel = 12;
$CS_PreparationCounters = 13;
$CS_NextNAACardGoAgain = 14;
$CS_NumAlliesDestroyed = 15;
$CS_Num6PowBan = 16;
$CS_ResolvingLayerUniqueID = 17;
$CS_NextWizardNAAInstant = 18;
$CS_ArcaneDamageTaken = 19;
$CS_NextNAAInstant = 20;
$CS_NextDamagePrevented = 21;
$CS_LastAttack = 22;
$CS_NumLeftPlay = 23;
$CS_NumMaterializations = 24;
$CS_NumFusedLightning = 25;
$CS_AfterPlayedBy = 26;
$CS_PlayCCIndex = 27;
$CS_NumAttackCards = 28; //Played or blocked
$CS_NumPlayedFromBanish = 29;
$CS_NumAttacks = 30;
$CS_DieRoll = 31;
$CS_NumMandalorianAttacks = 32;
$CS_NumWizardNonAttack = 33;
$CS_LayerTarget = 34;
$CS_NumSwordAttacks = 35;
$CS_HitsWithWeapon = 36;
$CS_ArcaneDamagePrevention = 37;
$CS_DynCostResolved = 38;
$CS_CardsEnteredGY = 39;
$CS_HighestRoll = 40;
$CS_NumMelodyPlayed = 41;
$CS_NumAuras = 42;
$CS_AbilityIndex = 43;
$CS_AdditionalCosts = 44;
$CS_NumRedPlayed = 45;
$CS_PlayUniqueID = 46;
$CS_NumPhantasmAADestroyed = 47;
$CS_NumEventsPlayed = 48;
$CS_AlluvionUsed = 49;
$CS_MaxQuellUsed = 50;
$CS_DamageDealt = 51; //Only includes damage dealt by the hero. CR 2.1 8.2.8f If an ally deals damage, the controlling player and their hero are not considered to have dealt damage.
$CS_ArcaneTargetsSelected = 52;
$CS_NumDragonAttacks = 53;
$CS_NumIllusionistAttacks = 54;
$CS_LastDynCost = 55;
$CS_NumIllusionistActionCardAttacks = 56;
$CS_ArcaneDamageDealt = 57;
$CS_LayerPlayIndex = 58;
$CS_NumCardsPlayed = 59; //Amulet of Ignition
$CS_NamesOfCardsPlayed = 60; //Amulet of Echoes
$CS_NumBoostPlayed = 61; //Hanabi Blaster
$CS_PlayedAsInstant = 62; //If the card was played as an instant -- some things like banish we lose memory of as soon as it is removed from the zone
$CS_AnotherWeaponGainedGoAgain = 63;
$CS_NumContractsCompleted = 64;
$CS_HitsWithSword = 65;
$CS_NumClonesPlayed = 66;
$CS_UnitsThatAttackedBase = 67;

function SetAfterPlayedBy($player, $cardID)
{
  global $CS_AfterPlayedBy;
  SetClassState($player, $CS_AfterPlayedBy, $cardID);
}


//Attack State(data pertaining to an ongoing attack)
$AS_AttackerIndex = 0;
$AS_IsAmbush = 1;
$AS_DamageDealt = 2;
$AS_AttackTarget = 3;
$AS_AfterAttackLayers = 4;
$AS_AttackerUniqueID = 5;
$AS_AttackTargetUID = 6;
$AS_CantAttackBase = 7;

function ResetAttackState()
{
  global $attackState, $AS_AttackerIndex, $AS_IsAmbush, $AS_DamageDealt, $AS_AttackTarget, $AS_AfterAttackLayers, $AS_AttackerUniqueID;
  global $AS_AttackTargetUID, $AS_CantAttackBase;
  global $mainPlayer, $defPlayer;

  $attackState[$AS_AttackerIndex] = -1;
  $attackState[$AS_IsAmbush] = 0;
  $attackState[$AS_DamageDealt] = 0;
  $attackState[$AS_AttackTarget] = "NA";
  $attackState[$AS_AfterAttackLayers] = "NA";
  $attackState[$AS_AttackerUniqueID] = -1;
  $attackState[$AS_AttackTargetUID] = "-";
  $attackState[$AS_CantAttackBase] = 0;
}

function ResetClassState($player)
{
  global $CS_NumVillainyPlayed, $CS_NumBoosted, $CS_AtksWWeapon, $CS_HitsWDawnblade, $CS_DamagePrevention, $CS_CardsBanished;
  global $CS_DamageTaken, $CS_NumActionsPlayed, $CS_CharacterIndex, $CS_PlayIndex, $CS_NumNonAttackCards;
  global $CS_PreparationCounters, $CS_NextNAACardGoAgain, $CS_NumAlliesDestroyed, $CS_Num6PowBan, $CS_ResolvingLayerUniqueID, $CS_NextWizardNAAInstant;
  global $CS_ArcaneDamageTaken, $CS_NextNAAInstant, $CS_NextDamagePrevented, $CS_LastAttack, $CS_PlayCCIndex;
  global $CS_NumLeftPlay, $CS_NumMaterializations, $CS_NumFusedLightning, $CS_AfterPlayedBy, $CS_NumAttackCards, $CS_NumPlayedFromBanish;
  global $CS_NumAttacks, $CS_DieRoll, $CS_NumMandalorianAttacks, $CS_NumWizardNonAttack, $CS_LayerTarget, $CS_NumSwordAttacks;
  global $CS_HitsWithWeapon, $CS_ArcaneDamagePrevention, $CS_DynCostResolved, $CS_CardsEnteredGY;
  global $CS_HighestRoll, $CS_NumAuras, $CS_AbilityIndex, $CS_AdditionalCosts, $CS_NumRedPlayed, $CS_PlayUniqueID, $CS_AlluvionUsed;
  global $CS_NumPhantasmAADestroyed, $CS_NumEventsPlayed, $CS_MaxQuellUsed, $CS_DamageDealt, $CS_ArcaneTargetsSelected, $CS_NumDragonAttacks, $CS_NumIllusionistAttacks;
  global $CS_LastDynCost, $CS_NumIllusionistActionCardAttacks, $CS_ArcaneDamageDealt, $CS_LayerPlayIndex, $CS_NumCardsPlayed, $CS_NamesOfCardsPlayed, $CS_NumBoostPlayed;
  global $CS_PlayedAsInstant, $CS_AnotherWeaponGainedGoAgain, $CS_NumContractsCompleted, $CS_HitsWithSword, $CS_NumMelodyPlayed, $CS_NumClonesPlayed, $CS_UnitsThatAttackedBase;

  $classState = &GetPlayerClassState($player);
  $classState[$CS_NumVillainyPlayed] = 0;
  $classState[$CS_NumBoosted] = 0;
  $classState[$CS_AtksWWeapon] = 0;
  $classState[$CS_HitsWDawnblade] = 0;
  $classState[$CS_DamagePrevention] = 0;
  $classState[$CS_CardsBanished] = 0;
  $classState[$CS_DamageTaken] = 0;
  $classState[$CS_NumActionsPlayed] = 0;
  $classState[$CS_CharacterIndex] = 0;
  $classState[$CS_PlayIndex] = -1;
  $classState[$CS_NumNonAttackCards] = 0;
  $classState[$CS_PreparationCounters] = 0;
  $classState[$CS_NextNAACardGoAgain] = 0;
  $classState[$CS_NumAlliesDestroyed] = 0;
  $classState[$CS_Num6PowBan] = 0;
  $classState[$CS_ResolvingLayerUniqueID] = -1;
  $classState[$CS_NextWizardNAAInstant] = 0;
  $classState[$CS_ArcaneDamageTaken] = 0;
  $classState[$CS_NextNAAInstant] = 0;
  $classState[$CS_NextDamagePrevented] = 0;
  $classState[$CS_LastAttack] = "NA";
  $classState[$CS_NumLeftPlay] = 0;
  $classState[$CS_NumMaterializations] = 0;
  $classState[$CS_NumFusedLightning] = 0;
  $classState[$CS_AfterPlayedBy] = "-";
  $classState[$CS_PlayCCIndex] = -1;
  $classState[$CS_NumAttackCards] = 0;
  $classState[$CS_NumPlayedFromBanish] = 0;
  $classState[$CS_NumAttacks] = 0;
  $classState[$CS_DieRoll] = 0;
  $classState[$CS_NumMandalorianAttacks] = 0;
  $classState[$CS_NumWizardNonAttack] = 0;
  $classState[$CS_LayerTarget] = "-";
  $classState[$CS_NumSwordAttacks] = 0;
  $classState[$CS_HitsWithWeapon] = 0;
  $classState[$CS_ArcaneDamagePrevention] = 0;
  $classState[$CS_DynCostResolved] = 0;
  $classState[$CS_CardsEnteredGY] = 0;
  $classState[$CS_HighestRoll] = 0;
  $classState[$CS_NumMelodyPlayed] = 0;
  $classState[$CS_NumAuras] = 0;
  $classState[$CS_AbilityIndex] = "-";
  $classState[$CS_AdditionalCosts] = "-";
  $classState[$CS_NumRedPlayed] = 0;
  $classState[$CS_PlayUniqueID] = -1;
  $classState[$CS_NumPhantasmAADestroyed] = 0;
  $classState[$CS_NumEventsPlayed] = 0;
  $classState[$CS_AlluvionUsed] = 0;
  $classState[$CS_MaxQuellUsed] = 0;
  $classState[$CS_DamageDealt] = 0;
  $classState[$CS_ArcaneTargetsSelected] = "-";
  $classState[$CS_NumDragonAttacks] = 0;
  $classState[$CS_NumIllusionistAttacks] = 0;
  $classState[$CS_LastDynCost] = 0;
  $classState[$CS_NumIllusionistActionCardAttacks] = 0;
  $classState[$CS_ArcaneDamageDealt] = 0;
  $classState[$CS_LayerPlayIndex] = -1;
  $classState[$CS_NumCardsPlayed] = 0;
  $classState[$CS_NamesOfCardsPlayed] = "-";
  $classState[$CS_NumBoostPlayed] = 0;
  $classState[$CS_PlayedAsInstant] = 0;
  $classState[$CS_AnotherWeaponGainedGoAgain] = "-";
  $classState[$CS_NumContractsCompleted] = 0;
  $classState[$CS_HitsWithSword] = 0;
  $classState[$CS_NumClonesPlayed] = 0;
  $classState[$CS_UnitsThatAttackedBase] = "-";
}

function ResetCharacterEffects()
{
  global $mainCharacterEffects, $defCharacterEffects;
  $mainCharacterEffects = [];
  $defCharacterEffects = [];
}

function SetAttackTarget($mzTarget)
{
  global $attackState, $AS_AttackTarget, $AS_AttackTargetUID, $defPlayer;
  if($mzTarget == "") return;
  $mzArr = explode("-", $mzTarget);
  $attackState[$AS_AttackTarget] = $mzTarget;
  $attackState[$AS_AttackTargetUID] = MZGetUniqueID($mzTarget, $defPlayer);
}

function UpdateAttacker() {
  global $attackState, $AS_AttackerIndex, $AS_AttackerUniqueID, $mainPlayer;
  $index = SearchAlliesForUniqueID($attackState[$AS_AttackerUniqueID], $mainPlayer);
  $attackState[$AS_AttackerIndex] = $index == -1 ? $attackState[$AS_AttackerIndex] : $index;
}

function UpdateAttackTarget() {
  global $attackState, $AS_AttackTarget, $AS_AttackTargetUID, $defPlayer;
  $mzArr = explode("-", $attackState[$AS_AttackTarget]);
  if($mzArr[0] = "THEIRCHAR") return;
  $index = SearchAlliesForUniqueID($attackState[$AS_AttackTargetUID], $defPlayer);
  $attackState[$AS_AttackTarget] = $index == -1 ? "NA" : $mzArr[0] . "-" . $index;
}

function GetAttackTarget()
{
  global $attackState, $AS_AttackTarget, $AS_AttackTargetUID, $defPlayer;
  $uid = $attackState[$AS_AttackTargetUID];
  if($uid == "-") return $attackState[$AS_AttackTarget];
  $mzArr = explode("-", $attackState[$AS_AttackTarget]);
  $index = SearchZoneForUniqueID($uid, $defPlayer, $mzArr[0]);
  return $mzArr[0] . "-" . $index;
}

function ClearAttackTarget() {
  global $attackState, $AS_AttackTarget, $AS_AttackTargetUID;
  $attackState[$AS_AttackTarget] = "NA";
  $attackState[$AS_AttackTargetUID] = "-";
}

function GetDamagePrevention($player)
{
  global $CS_DamagePrevention;
  return GetClassState($player, $CS_DamagePrevention);
}

function AttackPlayedFrom()
{
  global $AS_AttackPlayedFrom, $attackState;
  return $attackState[$AS_AttackPlayedFrom];
}

function CCOffset($piece)
{
  switch($piece)
  {
    case "player": return 1;
    default: return 0;
  }
}
