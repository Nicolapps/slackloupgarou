<?php namespace Slackwolf\Game\Command;

use Slack\DirectMessageChannel;
use Slackwolf\Game;
use Slackwolf\Game\Formatter\OptionFormatter;

class SetOptionCommand extends Command
{
    public function init()
    {
        if (count($this->args) > 1)
        {
            //Attempt to change an option detected
            $this->gameManager->optionsManager->setOptionValue($this->args, true);
        }
    }
    
    public function fire()
    {
        $client = $this->client;

        $help_msg =  "Options\r\n------------------------\r\n";
        $help_msg .= "Pour activer une option, tapez !setOption <nom> <valeur>. Voici les noms et valeurs disponibles sont spécifiées plus bas (la valeur entre parenthèses est la valeur actuelle) :.\r\n";
        $help_msg .= "Options disponibles\r\n------------------------\r\n";
        foreach($this->gameManager->optionsManager->options as $curOption)
        {
            /** @var Slackwolf\Game\Option $curOption */
            $help_msg .= OptionFormatter::format($curOption);
        }
        
        $this->client->getDMByUserId($this->userId)->then(function(DirectMessageChannel $dm) use ($client, $help_msg) {
            $client->send($help_msg, $dm);
        });
    }
}
