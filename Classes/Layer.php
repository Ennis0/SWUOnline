<?php

include_once "CoreLogic.php";

class Layer { //A game event that can be queued up to resolve later. Triggers, attack resolutions, game phase transitions, and so forth.
    private $type; //"TRIGGER", "DEALCOMBATDAMAGE", "AFTERCOMBAT"
    private $lambda; //The code that runs when the layer resolves.
    private $player;
    private $associatedCardID; //Usually the CardID of the card that creates the layer. Used to show a graphical representation when ordering triggers.
    private $uniqueID; //The layer's own UniqueID.
    private $parameters; //Any extra data required for the layer. The attacking unit of an attack, for example.

    function __construct($type, $lambda, $player = NULL, $associatedCardID = NULL, $uniqueID = NULL, $parameters = NULL) {
        $this->type = $type;
        $this->player = $player;
        $this->associatedCardID = $associatedCardID;
        $this->uniqueID = $uniqueID === NULL ? GetUniqueId() : $uniqueID;
        $this->lambda = $lambda;
        $this->parameters = $parameters;
    }

    function Type() {return $type;}
    function Player() {return $player;}
    function AssociatedCardID() {return $associatedCardID;}
    function UniqueID() {return $uniqueID;}
    function Parameters() {return $parameters;}

    function Resolve(&$stack = NULL) {
        /* Copypasted from ContinueDecisionQueue layer section. Might be necessary?
        global $gamestate;
        if($gamestate->currentPlayer != $player) {
            $gamestate->currentPlayer = $player;
            $otherPlayer = $gamestate->currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($gamestate->currentPlayer);
            }*/
        if($this->type == "TRIGGER") {
            global $gamestate;
            $gamestate->stackToAddNewTriggers = new Stack($stack);
            $lambda($parameters);
            AddDecisionQueue("SWITCHTONESTEDSTACK", $gamestate->mainPlayer, "-");
        }
        ($this->lambda)($this->parameters);
    }
}