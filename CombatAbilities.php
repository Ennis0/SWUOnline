<?php


 function ProcessHitEffect($cardID)
{
  global $mainPlayer, $attackState, $AS_DamageDealt, $defPlayer;
  switch($cardID)
  {
    case "0828695133"://Seventh Sister
      if(GetAttackTarget() == "THEIRCHAR-0") {
        AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose a card to deal 3 damage", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $mainPlayer, "DEALDAMAGE,3", 1);
      }
      break;
    case "3280523224"://Rukh
      if(IsAllyAttackTarget()) {
        $ally = new Ally(GetAttackTarget(), $defPlayer);
        if(!DefinedTypesContains($ally->CardID(), "Leader", $defPlayer)) {
          DestroyAlly($defPlayer, $ally->Index());
        }
      }
      break;
    case "87e8807695"://Leia Organa
      AddCurrentTurnEffect("87e8807695", $mainPlayer);
      break;
    default: break;
  }
  AllyHitEffects();
}

function CompletesAttackEffect($cardID) {
  global $mainPlayer, $defPlayer, $CS_NumLeftPlay;
  switch($cardID)
  {
    case "9560139036"://Ezra Bridger
      AddCurrentTurnEffect("9560139036", $mainPlayer);
      break;
    case "0e65f012f5"://Boba Fett
      if(GetClassState($defPlayer, $CS_NumLeftPlay) > 0) ReadyResource($mainPlayer, 2);
      break;
    case "9647945674"://Zeb Orrelios
      if(GetAttackTarget() == "NA") {//This means the target was defeated
        AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose a unit to deal 4 damage to");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $mainPlayer, "DEALDAMAGE,4", 1);
      }
      break;
    case "0518313150"://Embo
      if(GetAttackTarget() == "NA") {//This means the target was defeated
        AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose a unit to restore 2 damage");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $mainPlayer, "RESTORE,2", 1);
      }
      break;
    case "1086021299"://Arquitens Assault Cruiser
      if(GetAttackTarget() == "NA") {//This means the target was defeated
        $discard = &GetDiscard($defPlayer);
        $defeatedCard = RemoveDiscard($defPlayer, count($discard)-DiscardPieces());
        AddResources($defeatedCard, $mainPlayer, "PLAY", "DOWN");
      }
      break;
    default: break;
  }
}

function AttackModifier($cardID, $player, $index)
{
  global $mainPlayer, $defPlayer, $initiativePlayer, $attackState, $CS_NumLeftPlay;
  $modifier = 0;
  if($player == $mainPlayer) {
    //Raid is only for attackers
    $attacker = AttackerMZID($mainPlayer);
    $mzArr = explode("-", $attacker);
    if($mzArr[1] == $index) $modifier = RaidAmount($cardID, $mainPlayer, $mzArr[1]);
  }
  switch($cardID) {
    case "3988315236"://Seasoned Shoretrooper
      $modifier += NumResources($player) >= 6 ? 2 : 0;
      break;
    case "7922308768"://Valiant Assault Ship
      $modifier += $player == $mainPlayer && NumResources($mainPlayer) < NumResources($defPlayer) ? 2 : 0;
      break;
    case "6348804504"://Ardent Sympathizer
      $modifier += $initiativePlayer == $player ? 2 : 0;
      break;
    case "4619930426"://First Legion Snowtrooper
      if(!AttackIsOngoing() || $player == $defPlayer) break;
      $target = GetAttackTarget();
      if($target == "THEIRCHAR-0") break;
      $ally = new Ally($target, $defPlayer);
      $modifier += $ally->IsDamaged() ? 2 : 0;
      break;
    case "7648077180"://97th Legion
      $modifier += NumResources($player);
      break;
    case "8def61a58e"://Kylo Ren
      $hand = &GetHand($player);
      $modifier -= count($hand)/HandPieces();
      break;
    case "7486516061"://Concord Dawn Interceptors
      if($player == $defPlayer && GetAttackTarget() == "THEIRALLY-" . $index) $modifier += 2;
      break;
    case "6769342445"://Jango Fett
      if(IsAllyAttackTarget() && $player == $mainPlayer) {
        $ally = new Ally(GetAttackTarget(), $defPlayer);
        if($ally->HasBounty()) $modifier += 3;
      }
      break;
    case "4511413808"://Follower of the Way
      $ally = new Ally("MYALLY-" . $index, $player);
      if($ally->NumUpgrades() > 0) $modifier += 1;
      break;
    case "58f9f2d4a0"://Dr. Aphra
      $discard = &GetDiscard($player);
      $costs = [];
      for($i = 0; $i < count($discard); $i += DiscardPieces()) {
        $cost = CardCost($discard[$i]);
        $costs[$cost] = true;
      }
      if(count($costs) >= 5) $modifier += 3;
      break;
    case "8305828130"://Warbird Stowaway
        $modifier += $initiativePlayer == $player ? 2 : 0;
        break;
    default: break;
  }
  return $modifier;
}

?>
