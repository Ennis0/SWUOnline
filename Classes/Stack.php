<?php

class Stack { //A set of triggers, plus information on whether their order has already been set by the players.
    private $triggers = [];
    private $playerOrderDecided;
    private $player1TriggerOrderDecided;
    private $player2TriggerOrderDecided;
    private $parentStack; //A stack of nested triggers will store a reference to its parent, so that we can return to the parent and continue executing its triggers once the child is empty.

    function __construct(&$parent = NULL) {
        $parentStack = $parent;
    }

    function AddTrigger($trigger) {array_push($triggers, $trigger);}

    function IsEmpty() {return count($triggers) == 0;}

    function ResolveNextTrigger() {
        if(count($triggers) > 0) array_shift($triggers)->Resolve($this);
    }

    //Determine what, if any, decision currently needs to be made as to the order of triggers.
    function GetDecisionState() {
        if(count($triggers) < 2) return "";
        if(!$playerOrderDecided) {
            $hasP1Trigger = false;
            $hasP2Trigger = false;
            for($i = 0; $i < count($triggers); ++$i) {
                switch($triggers[$i]->Player()) {
                    case 1: $hasP1Trigger = true; break;
                    case 2: $hasP2Trigger = true; break;
                }
            }
            if($hasP1Trigger && $hasP2Trigger) return "PLAYERORDER";
            else {
                $playerOrderDecided = true; //If there are only triggers belonging to one player, player order is decided by default.
                $hasP1Trigger ? $player2TriggerOrderDecided = true : $player1TriggerOrderDecided = true; //The uninvolved player has no decision to make.
            } 
        }
        //Once player order has been determined, the player whose triggers come first should have all theirs grouped at the beginning.
        switch($triggers[0]->Player()){
            case 1:
                if($player1TriggerOrderDecided || $triggers[1]->Player() != 1) return "";
                else return "P1TRIGGERORDER";
                break;
            case 2:
                if($player2TriggerOrderDecided || $triggers[1]->Player() != 2) return "";
                else return "P2TRIGGERORDER";
                break;
        }
    }

    function BringPlayersTriggersForward($player) {
        $playersTriggers = [];
        for($i = count($triggers); $i >= 0; --$i) {
            if($triggers[$i]->Player() == $player) {
                array_unshift($playersTriggers, array_splice($triggers, $i, 1));
            }
        }
        $triggers = array_merge($playersTriggers, $triggers);
    }
}