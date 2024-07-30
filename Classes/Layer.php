<?php

class Layer { //A game event that can be queued up to resolve later. Triggers, attack resolutions, game phase transitions, and so forth.
    private $type; //"TRIGGER", "ATTACK"
    private $player;
    private $associatedCardID; //Usually the CardID of the card that creates the layer. Used to show a graphical representation when ordering triggers.
    private $uniqueID; //The layer's own UniqueID.
    private $lambda; //The code that runs when the layer resolves.
    private $parameters; //Any extra data required for the layer. The attacking unit of an attack, for example.

    function __construct($type, $lambda, $player = NULL, $associatedCardID = NULL, $parameters = NULL) {
        $this->type = $type;
        $this->player = $player;
        $this->associatedCardID = $associatedCardID;
        $this->uniqueID = GetUniqueId();
        $this->lambda = $lambda;
        $this->parameters = $parameters;
    }

    function Type() {return $type;}
    function Player() {return $player;}
    function AssociatedCardID() {return $associatedCardID;}
    function UniqueID() {return $uniqueID;}
    function Parameters() {return $parameters;}

    function Resolve(&$stack) {
        /* Copypasted from ContinueDecisionQueue layer section. Might be necessary?
        global $currentPlayer;
        if($currentPlayer != $player) {
            $currentPlayer = $player;
            $otherPlayer = $currentPlayer == 1 ? 2 : 1;
            BuildMyGamestate($currentPlayer);
            }*/
        if($type == "TRIGGER") {
            global $mainPlayer, $stackToAddNewTriggers;
            $stackToAddNewTriggers = new Stack($stack);
            $lambda($parameters);
            AddDecisionQueue("SWITCHTONESTEDSTACK", $mainPlayer, "-");
        }
        $lambda($parameters);
    }
}