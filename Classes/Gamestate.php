<?php

class Gamestate {
    //Player 1
    public $p1Hand = [];
    public $p1Deck = [];
    public $p1Material = [];
    public $p1CharEquip = [];
    public $p1Resources = [];
    public $p1Discard = [];
    public $p1ClassState = [];
    public $p1CardStats = [];
    public $p1TurnStats = [];
    public $p1Allies = [];
    public $p1Settings = [];

    //Player 2
    public $p2Hand = [];
    public $p2Deck = [];
    public $p2Material = [];
    public $p2CharEquip = [];
    public $p2Resources = [];
    public $p2Discard = [];
    public $p2ClassState = [];
    public $p2CardStats = [];
    public $p2TurnStats = [];
    public $p2Allies = [];
    public $p2Settings = [];

    //Shared
    public $playerDamageValues = [];
    public $winner = "";
    public $firstPlayer = "";
    public $currentPlayer = "";
    public $mainPlayer = "";
    public $currentRound = "";
    public $turn = [];
    public $attackState = [];
    public $currentTurnEffects = [];
    public $currentTurnEffectsFromCombat = [];
    public $nextTurnEffects = [];
    public $decisionQueue = [];
    public $dqVars = [];
    public $dqState = [];
    public $currentlyResolvingStack = [];
    public $stackToAddNewTriggers = [];
    public $layers = [];
    public $lastPlayed = [];
    public $p1Key = "";
    public $p2Key = ""; //Player 1 and 2's authentication keys.
    public $permanentUniqueIDCounter = "";
    public $inGameStatus = ""; //Game status -- 0 = START, 1 = PLAY, 2 = OVER
    public $currentPlayerActivity = ""; //Current Player activity status -- 0 = active, 2 = inactive
    public $p1TotalTime = "";
    public $p2TotalTime = "";
    public $lastUpdateTime = "";
    public $events = [];
    public $EffectContext = "";
    public $initiativePlayer = "";
    public $initiativeTaken = "";
}