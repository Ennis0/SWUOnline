<?php
function DelimStringContains($str, $find)
{
  $arr = explode(",", $str);
  for($i=0; $i<count($arr); ++$i)
  {
    if($arr[$i] == $find) return true;
  }
  return false;
}

function RandomizeArray(&$arr)
{
  for($i=0; $i<count($arr); ++$i)
  {
    $rand = GetRandom(0, count($arr)-1);
    $temp = $arr[$i];
    $arr[$i] = $arr[$rand];
    $arr[$rand] = $temp;
  }
}

function GetRandom($low=-1, $high=-1)
{
  global $randomSeeded;
  if(!$randomSeeded) SeedRandom();
  if($low == -1) return mt_rand();
  return mt_rand($low, $high);
}

function SeedRandom()
{
  global $randomSeeded, $gamestate;
  $seedString = $currentRound. implode("", $gamestate->turn) . $gamestate->currentPlayer;
  if(count($gamestate->layers) > 0) for($i=0; $i<count($gamestate->layers); ++$i) $seedString .= $gamestate->layers[$i];

  $char = &GetPlayerCharacter(1);
  for($i=0; $i<count($char); ++$i) $seedString .= $char[$i];
  $char = &GetPlayerCharacter(2);
  for($i=0; $i<count($char); ++$i) $seedString .= $char[$i];

  $discard = &GetDiscard(1);
  for($i=0; $i<count($discard); ++$i) $seedString .= $discard[$i];
  $discard = &GetDiscard(2);
  for($i=0; $i<count($discard); ++$i) $seedString .= $discard[$i];

  $deck = &GetDeck(1);
  for($i=0; $i<count($deck); ++$i) $seedString .= $deck[$i];
  $deck = &GetDeck(2);
  for($i=0; $i<count($deck); ++$i) $seedString .= $deck[$i];

  $seedString = hash("sha256", $seedString);
  mt_srand(crc32($seedString));
  $randomSeeded = true;
}
?>
