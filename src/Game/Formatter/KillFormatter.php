<?php namespace Slackwolf\Game\Formatter;

use Slackwolf\Game\Game;
use Slackwolf\Game\Role;

class KillFormatter
{
    public static function format(Game $game)
    {
        $msg = ":memo: Votes des Loups-Garous\r\n-----------------------------------------\r\n";

        foreach ($game->getVotes() as $voteForId => $voters)
        {
            $voteForPlayer = $game->getPlayerById($voteForId);

            $numVoters = count($voters);

            $msg .= ":knife: Tuer @{$voteForPlayer->getUsername()}\t\t | ({$numVoters}) | ";

            $voterNames = [];

            foreach ($voters as $voter)
            {
                $voter = $game->getPlayerById($voter);
                $voterNames[] = '@'.$voter->getUsername();
            }

            $msg .= implode(', ', $voterNames) . "\r\n";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n:hourglass: Votes restants: ";

        $playerNames = [];

        foreach ($game->getPlayersOfRole(Role::WEREWOLF) as $player)
        {
            if ( ! $game->hasPlayerVoted($player->getId())) {
                $playerNames[] = '@'.$player->getUsername();
            }
        }

        if (count($playerNames) > 0) {
            $msg .= implode(', ', $playerNames);
        } else {
            $msg .= "Aucun";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n";

        return $msg;
    }
}
