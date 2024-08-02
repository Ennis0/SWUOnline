<?php


//Return 1 if the effect should be removed
function EffectHitEffect($cardID)
{
  global $gamestate, $defPlayer, $AS_AttackerIndex, $AS_DamageDealt;
  switch($cardID) {
    case "6954704048"://Heroic Sacrifice
      $ally = new Ally(AttackerMZID($gamestate->mainPlayer), $gamestate->mainPlayer);
      WriteLog("Heroic Sacrifice defeated " . CardLink($ally->CardID(), $ally->CardID()));
      $ally->Destroy();
      break;
    case "8988732248-1"://Rebel Assault
      AddCurrentTurnEffect("8988732248-2", $gamestate->mainPlayer);
      break;
    case "0802973415"://Outflank
      AddCurrentTurnEffect("0802973415-1", $gamestate->mainPlayer);
      break;
    case "5896817672-1"://Headhunting
    case "5896817672-2":
      AddCurrentTurnEffect("5896817672" . (substr($cardID, -2, 2) == "-1" ? "-2" : "-3"), $gamestate->mainPlayer);
      break;
    case "6514927936-1"://Leia Organa
      AddCurrentTurnEffectFromCombat("6514927936-2", $gamestate->mainPlayer);
      break;
    default:
      break;
  }
  return 0;
}

//Return true if there's a chained action
function FinalizeAttackEffects()
{
  global $gamestate;
  for($i=0; $i<count($gamestate->currentTurnEffects); $i+=CurrentTurnPieces()) {
    switch($gamestate->currentTurnEffects[$i]) {
      case "8988732248-2"://Rebel Assault
        PrependDecisionQueue("REMOVECURRENTEFFECT", $gamestate->mainPlayer, $gamestate->currentTurnEffects[$i]);
        PrependDecisionQueue("SWAPTURN", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("ELSE", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "ATTACK", 1);
        PrependDecisionQueue("CHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a unit to attack with");
        PrependDecisionQueue("MZFILTER", $gamestate->mainPlayer, "status=1");
        PrependDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY:trait=Rebel");
        return true;
      case "0802973415-1"://Outflank
        PrependDecisionQueue("REMOVECURRENTEFFECT", $gamestate->mainPlayer, $gamestate->currentTurnEffects[$i]);
        PrependDecisionQueue("SWAPTURN", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("ELSE", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "ATTACK", 1);
        PrependDecisionQueue("CHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a unit to attack with");
        PrependDecisionQueue("MZFILTER", $gamestate->mainPlayer, "status=1");
        PrependDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY");
        return true;
      case "5896817672-2"://Headhunting
      case "5896817672-3":
        global $AS_CantAttackBase;
        PrependDecisionQueue("REMOVECURRENTEFFECT", $gamestate->mainPlayer, $gamestate->currentTurnEffects[$i]);
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "ATTACK", 1);
        PrependDecisionQueue("PASSPARAMETER", $gamestate->mainPlayer, "{0}");
        PrependDecisionQueue("ADDLIMITEDCURRENTEFFECT", $gamestate->mainPlayer, "5896817672", 1);
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "GETUNIQUEID", 1);
        PrependDecisionQueue("MZALLCARDTRAITORPASS", $gamestate->mainPlayer, "Bounty Hunter", 1);
        PrependDecisionQueue("PASSPARAMETER", $gamestate->mainPlayer, "{0}", 1);
        PrependDecisionQueue("SETATTACKSTATE", $gamestate->mainPlayer, $AS_CantAttackBase, 1);
        PrependDecisionQueue("PASSPARAMETER", $gamestate->mainPlayer, 1, 1);
        PrependDecisionQueue("SETDQVAR", $gamestate->mainPlayer, "0");
        PrependDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a unit to attack with");
        PrependDecisionQueue("MZFILTER", $gamestate->mainPlayer, "status=1");
        PrependDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY");
        return true;
      case "6514927936-2"://Leia Organa
        PrependDecisionQueue("SWAPTURN", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("ELSE", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "ATTACK", 1);
        PrependDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a unit to attack with");
        PrependDecisionQueue("MZFILTER", $gamestate->mainPlayer, "status=1");
        PrependDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY:trait=Rebel");
        return true;
      case "87e8807695"://Leia Organa - Leader Unit
        SearchCurrentTurnEffects("87e8807695", $gamestate->mainPlayer, remove:true);
        PrependDecisionQueue("SWAPTURN", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("ELSE", $gamestate->mainPlayer, "-");
        PrependDecisionQueue("MZOP", $gamestate->mainPlayer, "ATTACK", 1);
        PrependDecisionQueue("MAYCHOOSEMULTIZONE", $gamestate->mainPlayer, "<-", 1);
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "Choose a unit to attack with");
        PrependDecisionQueue("MZFILTER", $gamestate->mainPlayer, "status=1");
        PrependDecisionQueue("MULTIZONEINDICES", $gamestate->mainPlayer, "MYALLY:trait=Rebel");
        return true;
      case "9560139036"://Ezra Bridger
        SearchCurrentTurnEffects("9560139036", $gamestate->mainPlayer, remove:true);
        PrependDecisionQueue("MODAL", $gamestate->mainPlayer, "EZRABRIDGER", 1);
        PrependDecisionQueue("SHOWMODES", $gamestate->mainPlayer, $gamestate->currentTurnEffects[$i], 1);
        PrependDecisionQueue("MULTICHOOSETEXT", $gamestate->mainPlayer, "1-Leave,Play,Discard-1");
        PrependDecisionQueue("SETDQCONTEXT", $gamestate->mainPlayer, "The top card is <0>; Choose a mode for Ezra Bridger");
        PrependDecisionQueue("SETDQVAR", $gamestate->mainPlayer, "0");
        PrependDecisionQueue("DECKCARDS", $gamestate->mainPlayer, "0");
        return true;
      default: break;
    }
  }
  return false;
}

function EffectAttackModifier($cardID, $playerID="")
{
  global $gamestate, $defPlayer;
  $params = explode("_", $cardID);
  if(count($params) == 1) {
    $params = explode("-", $cardID);
  }
  $cardID = $params[0];
  if(count($params) > 1) $subparam = $params[1];
  switch($cardID)
  {
    case "2587711125": return -4;//Disarm
    case "2569134232": return -4;//Jedha City
    case "1323728003": return -1;//Electrostaff
    case "2651321164": return 2;//Tactical Advantage
    case "1701265931": return 4;//Moment of Glory
    case "1900571801": return 2;//Overwhelming Barrage
    case "3809048641": return 3;//Surprise Strike
    case "3038238423": return 2;//Fleet Lieutenant
    case "9757839764": return 2;//Adelphi Patrol Wing
    case "3208391441": return -2;//Make an Opening
    case "9999079491": return -2;//Mystic Reflection
    case "6432884726": return 2;//Steadfast Battalion
    case "8244682354": return -1;//Jyn Erso
    case "8600121285": return 1;//IG-88
    case "6954704048": return 2;//Heroic Sacrifice
    case "20f21b4948": return -1;//Jyn Erso
    case "9097690846": return 2;//Snowtrooper Lieutenant
    case "9210902604"://Precision Fire
      $attacker = new Ally(AttackerMZID($gamestate->mainPlayer), $gamestate->mainPlayer);
      return TraitContains($attacker->CardID(), "Trooper", $gamestate->mainPlayer) ? 2 : 0;
    case "5896817672": if(!$subparam) return 2; else return 0;//Headhunting
    case "8297630396": return 1;//Shoot First
    case "5464125379": return -2;//Strafing Gunship
    case "8495694166": return -2;//Jedi Lightsaber
    case "3789633661": return 4;//Cunning
    case "1939951561": return $subparam;//Attack Pattern Delta
    case "8988732248": return 1;//Rebel Assault
    case "7109944284": return -1* $subparam;//Luke Skywalker
    case "1885628519": return 1;//Crosshair
    case "1480894253": return 2;//Kylo Ren
    case "2503039837": return IsAllyAttackTarget() ? 1 : 0;//Moff Gideon Leader
    case "4534554684": return 2;//Freetown Backup
    case "4721657243": return 3;//Kihraxz Heavy Fighter
    case "7171636330": return -4;//Chain Code Collector
    case "2526288781": return 1;//Bossk
    case "1312599620": return -3;//Smuggler's Starfighter
    case "8107876051": return -3;//Enfys Nest
    case "9334480612": return 1;//Boba Fett Green Leader
    case "6962053552": return 2;//Desperate Attack
    case "4085341914": return 4;//Heroic Resolve
    case "1938453783": return 2;//Armed to the Teeth
    case "6263178121": return 2;//Kylo Ren (Killing the Past)
    case "7578472075"://Let the Wookie Win
      $attacker = new Ally(AttackerMZID($gamestate->mainPlayer), $gamestate->mainPlayer);
      return TraitContains($attacker->CardID(), "Wookiee", $gamestate->mainPlayer) ? 2 : 0;
    case "4663781580"://Swoop Down
      $attackTarget = GetAttackTarget();
      if(!IsAllyAttackTarget()) return 0;
      $ally = new Ally($attackTarget, $defPlayer);
      $modifier = $playerID == $defPlayer ? -2 : 2;
      return CardArenas($ally->CardID()) == "Ground" ? $modifier : 0;
    case "3399023235": return isset($subparam) && $subparam == "2" ? -2 : 0;//Fenn Rau
    default: return 0;
  }
}

function EffectHasBlockModifier($cardID)
{
  switch($cardID)
  {
    default: return false;
  }
}

function CurrentEffectCostModifiers($cardID, $from, $reportMode=false)
{
  global $gamestate, $CS_PlayUniqueID;
  $costModifier = 0;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {
        case "TTFREE"://Free
          $costModifier -= 99;
          $remove = true;
          break;
        case "9644107128": //Bamboozle
          if($cardID == "9644107128") {
            $costModifier -= -99;
            $remove = true;
          }
          break;
        case "5707383130"://Bendu
          if(!AspectContains($cardID, "Heroism", $gamestate->currentPlayer) && !AspectContains($cardID, "Villainy", $gamestate->currentPlayer)) {
            $costModifier -= 2;
            $remove = true;
          }
          break;
        case "4919000710"://Home One
          $costModifier -= 3;
          $remove = true;
          break;
        case "5351496853"://Gideon's Light Cruiser
          $costModifier -= 99;
          $remove = true;
          break;
        case "2756312994"://Alliance Dispatcher
          $costModifier -= 1;
          $remove = true;
          break;
        case "3509161777"://You're My Only Hope
          $costModifier -= PlayerRemainingHealth($gamestate->currentPlayer) <= 5 ? 99 : 5;
          $remove = true;
          break;
        case "5494760041"://Galactic Ambition
          $costModifier -= 99;
          $remove = true;
          break;
        case "3426168686"://Sneak Attack
          if($from != "PLAY") {
            $costModifier -= 3;
            $remove = true;
          }
          break;
        case "5696041568"://Triple Dark Raid
          $costModifier -= 5;
          $remove = true;
          break;
        case "7870435409"://Bib Fortuna
          $costModifier -= 1;
          $remove = true;
          break;
        case "8506660490"://Darth Vader
          $costModifier -= 99;
          break;
        case "8968669390"://U-Wing Reinforcement
          $costModifier -= 99;
          break;
        case "5440730550"://Lando Calrissian Leader
        case "040a3e81f3"://Lando Calrissian Leader Unit
          $costModifier -= 2;
          $remove = true;
          break;
        case "4643489029"://Palpatine's Return
          $costModifier -= TraitContains($cardID, "Force", $gamestate->currentPlayer) ? 8 : 6;
          $remove = true;
          break;
        case "7270736993"://Unrefusable Offer
        case "4717189843"://A New Adventure
          $costModifier -= 99;
          $remove = true;
          break;
        case "9642863632"://Bounty Hunter's Quarry
          $costModifier -= 99;
          $remove = true;
          break;
        case "9226435975"://Han Solo Red
          $costModifier -= 1;
          $remove = true;
          break;
        case "0622803599-3"://Jabba the Hutt
          if(DefinedTypesContains($cardID, "Unit", $gamestate->currentPlayer)) {
            $costModifier -= 1;
            $remove = true;
          }
          break;
        case "f928681d36-3"://Jabba the Hutt Leader Unit
          if(DefinedTypesContains($cardID, "Unit", $gamestate->currentPlayer)) {
            $costModifier -= 2;
            $remove = true;
          }
          break;
        case "5576996578"://Endless Legions
          $costModifier -= 99;
          $remove = true;
          break;
        case "3399023235"://Fenn Rau
          $costModifier -= 2;
          $remove = true;
          break;
        case "7642980906"://Stolen Landspeeder
          $costModifier -= 99;
          $remove = false;
          break;
        default: break;
      }
      if($remove && !$reportMode) RemoveCurrentTurnEffect($i);
    }
  }
  return $costModifier;
}

function CurrentEffectPreventDamagePrevention($player, $type, $damage, $source)
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnEffectPieces(); $i >= 0; $i -= CurrentTurnEffectPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $player) {
      switch ($gamestate->currentTurnEffects[$i]) {

        default: break;
      }
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
  return $damage;
}

function CurrentEffectDamagePrevention($player, $type, $damage, $source, $preventable, $uniqueID=-1)
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnEffectPieces(); $i >= 0 && $damage > 0; $i -= CurrentTurnEffectPieces()) {
    if($uniqueID != -1 && $gamestate->currentTurnEffects[$i + 2] != $uniqueID) continue;
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $player || $uniqueID != -1) {
      $effects = explode("-", $gamestate->currentTurnEffects[$i]);
      switch($effects[0]) {
        case "pv4n1n3gyg"://Cleric's Robe
          if($preventable) $damage -= 1;
          $remove = true;
          break;
        default: break;
      }
      if($remove) RemoveCurrentTurnEffect($i);
    }
  }
  return $damage;
}

function CurrentEffectPlayAbility($cardID, $from)
{
  global $gamestate, $CS_LastDynCost;

  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {

        default:
          break;
      }
      if($remove) RemoveCurrentTurnEffect($i);
    }
  }
  return false;
}

function CurrentEffectPlayOrActivateAbility($cardID, $from)
{
  global $gamestate;

  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {

        default:
          break;
      }
      if($remove) RemoveCurrentTurnEffect($i);
    }
  }
  $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects); //In case any were removed
  return false;
}

function CurrentEffectGrantsNonAttackActionGoAgain($cardID)
{
  global $gamestate;
  $hasGoAgain = false;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {

        default:
          break;
      }
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
  return $hasGoAgain;
}

function CurrentEffectEndTurnAbilities()
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    $params = explode("_", $gamestate->currentTurnEffects[$i]);
    $cardID = $params[0];
    if(count($params) > 1) $subparam = $params[1];
    if(SearchCurrentTurnEffects($cardID . "-UNDER", $gamestate->currentTurnEffects[$i + 1])) {
      AddNextTurnEffect($gamestate->currentTurnEffects[$i], $gamestate->currentTurnEffects[$i + 1]);
    }
    switch($cardID) {
      case "3426168686-2"://Sneak Attack
      case "7270736993-2"://Unrefusable Offer
        $ally = new Ally("MYALLY-" . SearchAlliesForUniqueID($gamestate->currentTurnEffects[$i+2], $gamestate->currentTurnEffects[$i+1]), $gamestate->currentTurnEffects[$i+1]);
        $ally->Destroy();
        break;
      case "1626462639"://Change of Heart
        $index = SearchAlliesForUniqueID($gamestate->currentTurnEffects[$i+2], $gamestate->currentTurnEffects[$i+1]);
        if($index > -1) {
          $ally = new Ally("MYALLY-" . $index, $gamestate->currentTurnEffects[$i+1]);
          $owner = $ally->Owner();
          WriteLog("Change of Heart unit reverted control of " . CardLink($ally->CardID(), $ally->CardID()) . "back to player $owner");
          AddDecisionQueue("PASSPARAMETER", $owner, "THEIRALLY-" . $index, 1);
          AddDecisionQueue("MZOP", $owner, "TAKECONTROL", 1);
        }
        break;
      case "5696041568-2"://Triple Dark Raid
        $allyId = SearchAlliesForUniqueID($gamestate->currentTurnEffects[$i+2], $gamestate->currentTurnEffects[$i+1]);
        if($allyId > -1) {
          $ally = new Ally("MYALLY-" . $allyId, $gamestate->currentTurnEffects[$i+1]);
          MZBounce($gamestate->currentTurnEffects[$i+1], "MYALLY-" . $ally->Index());
        }
        break;
      case "1910812527":
        DealDamageAsync($gamestate->currentTurnEffects[$i+1], 999999);
        break;
      case "6117103324"://Jetpack
        $ally = new Ally("MYALLY-" . SearchAlliesForUniqueID($gamestate->currentTurnEffects[$i+2], $gamestate->currentTurnEffects[$i+1]), $gamestate->currentTurnEffects[$i+1]);
        $ally->DefeatUpgrade("8752877738");
        break;
      case "4002861992"://DJ (Blatant Thief)
        AddNextTurnEffect($gamestate->currentTurnEffects[$i], $gamestate->currentTurnEffects[$i + 1]);
        break;
      default: break;
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
}


function CurrentEffectStartRegroupAbilities()
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    $params = explode("_", $gamestate->currentTurnEffects[$i]);
    $cardID = $params[0];
    if(count($params) > 1) $subparam = $params[1];
    if(SearchCurrentTurnEffects($cardID . "-UNDER", $gamestate->currentTurnEffects[$i + 1])) {
      AddNextTurnEffect($gamestate->currentTurnEffects[$i], $gamestate->currentTurnEffects[$i + 1]);
    }
    switch($cardID) {
      case "2522489681"://Zorii Bliss
        PummelHit($gamestate->currentTurnEffects[$i+1]);
        break;
      default: break;
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
}

function CurrentEffectStartTurnAbilities()
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    $cardID = substr($gamestate->currentTurnEffects[$i], 0, 6);
    if(SearchCurrentTurnEffects($cardID . "-UNDER", $gamestate->currentTurnEffects[$i + 1])) {
      AddNextTurnEffect($gamestate->currentTurnEffects[$i], $gamestate->currentTurnEffects[$i + 1]);
    }
    switch($gamestate->currentTurnEffects[$i]) {
      case "5954056864": case "5e90bd91b0"://Han Solo
        MZChooseAndDestroy($gamestate->currentTurnEffects[$i+1], "MYRESOURCES", context:"Choose a resource to destroy");
        break;
      case "8800836530"://No Good To Me Dead
        $ally = new Ally("MYALLY-" . SearchAlliesForUniqueID($gamestate->currentTurnEffects[$i+2], $gamestate->currentTurnEffects[$i+1]), $gamestate->currentTurnEffects[$i+1]);
        $ally->Exhaust();
        $remove = true;
        break;
      default: break;
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
}

function IsCombatEffectActive($cardID)
{
  global $gamestate;
  if(!AttackIsOngoing()) return;
  $effectArr = explode("-", $cardID);
  $cardID = $effectArr[0];
  switch($cardID)
  {
    case "2587711125": return true;//Disarm
    case "2569134232": return true;//Jedha City
    case "1323728003": return true;//Electrostaff
    case "3809048641": return true;//Surprise Strike
    case "9757839764": return true;//Adelphi Patrol Wing
    case "3038238423": return true;//Fleet Lieutenant
    case "8244682354": return true;//Jyn Erso
    case "8600121285": return true;//IG-88
    case "6954704048": return true;//Heroic Sacrifice
    case "20f21b4948": return true;//Jyn Erso
    case "9097690846": return true;//Snowtrooper Lieutenant
    case "9210902604": return true;//Precision Fire
    case "8297630396": return true;//Shoot First
    case "5464125379": return true;//Strafing Gunship
    case "8495694166": return true;//Jedi Lightsaber
    case "3789633661": return true;//Cunning
    case "8988732248": return true;//Rebel Assault
    case "6514927936": return true;//Leia Organa
    case "0802973415": return true;//Outflank
    case "1480894253": return true;//Kylo Ren
    case "2503039837": return true;//Moff Gideon Leader
    case "4721657243": return true;//Kihraxz Heavy Fighter
    case "7171636330": return true;//Chain Code Collector
    case "8107876051": return true;//Enfys Nest
    case "7578472075": return true;//Let the Wookie Win
    case "4663781580": return true;//Swoop Down
    case "4085341914": return true;//Heroic Resolve
    case "5896817672": return true;//Headhunting
    case "6962053552": return true;//Desperate attack
    default: return false;
  }
}

function IsCombatEffectPersistent($cardID)
{
  global $gamestate;
  $effectArr = explode(",", $cardID);
  switch($cardID) {
    case "2587711125": return true;//Disarm
    case "2569134232": return true;//Jedha City
    case "3789633661": return true;//Cunning
    case "1480894253": return true;//Kylo Ren
    default:
      return false;
  }
}

function IsEffectPersistent($cardID)
{
  global $gamestate;
  $effectArr = explode(",", $cardID);
  switch($cardID) {
    case "7dedg616r0": return true;//Freydis, Master Tactician
    default:
      return false;
  }
}

function BeginEndPhaseEffects()
{
  global $gamestate;
  for($i = 0; $i < count($gamestate->currentTurnEffects); $i += CurrentTurnPieces()) {
    $gamestate->EffectContext = $gamestate->currentTurnEffects[$i];
    if(IsEffectPersistent($gamestate->EffectContext)) AddNextTurnEffect($gamestate->EffectContext, $gamestate->currentTurnEffects[$i+1]);
    switch($gamestate->currentTurnEffects[$i]) {
      default:
        break;
    }
  }
}

function BeginEndPhaseEffectTriggers()
{
  global $gamestate;
  for($i = 0; $i < count($gamestate->currentTurnEffects); $i += CurrentTurnPieces()) {
    switch($gamestate->currentTurnEffects[$i]) {
      default: break;
    }
  }
}

function ActivateAbilityEffects()
{
  global $gamestate;
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $gamestate->currentPlayer) {
      switch($gamestate->currentTurnEffects[$i]) {

        default:
          break;
      }
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
  $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects);
}

function CurrentEffectNameModifier($effectID, $effectParameter)
{
  $name = "";
  switch($effectID)
  {

    default: break;
  }
  return $name;
}

function CurrentEffectAllyEntersPlay($player, $index)
{
  global $gamestate;
  $allies = &GetAllies($player);
  for($i = count($gamestate->currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($gamestate->currentTurnEffects[$i + 1] == $player) {
      switch($gamestate->currentTurnEffects[$i]) {
        case "7642980906"://Stolen Landspeeder
          $remove = true;
          $ally = new Ally("MYALLY-" . $index, $player);
          $ally->Attach("2007868442");//Experience token
          break;
        default:
          break;
      }
    }
    if($remove) RemoveCurrentTurnEffect($i);
  }
  $gamestate->currentTurnEffects = array_values($gamestate->currentTurnEffects);
}

?>
