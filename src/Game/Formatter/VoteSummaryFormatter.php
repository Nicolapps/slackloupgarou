<?php namespace Slackwolf\Game\Formatter;

use Slackwolf\Game\Game;

class VoteSummaryFormatter
{
    public static function format(Game $game)
    {
        $msg = ":memo: Votes des Villageois\r\n--------------------------------------------------------------\r\n";

        foreach ($game->getVotes() as $voteForId => $voters)
        {
            $voteForPlayer = $game->getPlayerById($voteForId);
            $numVoters = count($voters);

            if ($voteForId == 'noone'){
                $msg .= ":peace_symbol: Pas de lynchage\t\t | ({$numVoters}) | ";
            } else {
                $msg .= ":knife: Tuer @{$voteForPlayer->getUsername()}\t\t | ({$numVoters}) | ";
            }
            
            $voterNames = [];

            foreach ($voters as $voter)
            {
                $voter = $game->getPlayerById($voter);
                $voterNames[] = '@'.$voter->getUsername();
            }

            $msg .= implode(', ', $voterNames) . "\r\n";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n:hourglass: Personnes n'ayant pas encore voté: ";

        $playerNames = [];

        foreach ($game->getLivingPlayers() as $player)
        {
            if ( ! $game->hasPlayerVoted($player->getId())) {
                $playerNames[] = '@'.$player->getUsername();
            }
        }

        if (count($playerNames) > 0) {
            $msg .= implode(', ', $playerNames);
        } else {
            $msg .= "Aucune";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n";

        return $msg;
    }
}
