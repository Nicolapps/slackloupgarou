<?php namespace Slackwolf\Game;

use Exception;

class OptionName
{
    const changevote = 'changevote';
    const no_lynch = 'pas_de_lynchage';
    const mods = 'mods';
    const role_seer = 'role_voyante';
    const role_tanner = 'role_tanneur';
    const role_lycan = 'role_chien';
    const role_beholder = 'role_spectateur';
    const role_bodyguard = 'role_salvateur';
}

class OptionType
{
    const Bool = 0;
    const Int = 1;
    const String = 2;
    const StringArray = 3;
    const UserArray = 4;
}

class Option
{
    public $name = "";
    public $optionType;
    public $value;
    public $helpText = "";

    /*
     * @param string $name
     * @param OptionType $optionType
     * @param string $helpText
     */
    public function __construct($name, $optionType, $value, $helpText)
    {
        $this->name = $name;
        $this->optionType = $optionType;
        $this->value = $value;
        $this->helpText = $helpText;
    }
}

class OptionsManager
{
    const optionsFileName = "options.json";
    /** @var Option[] $options */
    public $options = [];
    
    public function __construct()
    {
        $this->options[] = new Option(OptionName::changevote, OptionType::Bool, "on", "Quand activé, les votes peuvent être changés tant que le dernier vote n'a pas été fait.");
        $this->options[] = new Option(OptionName::no_lynch, OptionType::Bool, "on", "Quand activé, les villageois peuvent voter pour ne tuer personne.");
        $this->options[] = new Option(OptionName::role_seer, OptionType::Bool, "on", "Utiliser la Voyante dans les parties aléatoires.");
        $this->options[] = new Option(OptionName::role_tanner, OptionType::Bool, "on", "Utiliser le Tanneur dans les parties aléatoires.");
        $this->options[] = new Option(OptionName::role_lycan, OptionType::Bool, "on", "Utiliser le Chien dans les parties aléatoires.");
        $this->options[] = new Option(OptionName::role_beholder, OptionType::Bool, "on", "Utiliser le Spctateur dans les parties aléatoires.");
        $this->options[] = new Option(OptionName::role_bodyguard, OptionType::Bool, "on", "Utiliser le Salvateur dans les parties aléatoires.");

        $this->loadOptions();
    }
    
    public function loadOptions()
    {        
        /*
         * Load existing options
         */
        if (file_exists(OptionsManager::optionsFileName)) {
            try {
                $optionsLoaded = json_decode(file_get_contents(OptionsManager::optionsFileName));
                foreach($optionsLoaded as $loadedOption)
                {
                    /** @var Option $loadedOption */
                    if ($loadedOption->optionType == OptionType::Bool)
                    {
                        $this->setOptionValue([$loadedOption->name, $loadedOption->value ? "on" : "off"], false);
                    }
                    else
                    {
                        $this->setOptionValue([$loadedOption->name, $loadedOption->value], false);
                    }
                }
            } catch(Exception $e) { }
        }
    }

    public function saveOptions()
    {
        try {
            file_put_contents(OptionsManager::optionsFileName,json_encode($this->options));
        } catch (Exception $e) {}
    }

    public function setOptionValue(array $args, $doSave)
    {
        if (count($args) < 2) { return; } //minimum name/value required
        /** @var Option $option */
        $option = null;
        
        foreach ($this->options as $searchOption)
        {
            /** @var Option $searchOption */
            if ($searchOption->name == $args[0]){
                $option = $searchOption;
                break;
            }
        }
        
        if ($option==null) { return; }
        $newValue = $option->value;
        $setValue = $args[1];
        switch($option->optionType)
        {
          case OptionType::Bool:
              $newValue = strcasecmp($setValue, "on") == 0 ? true : false;
              break;
          case OptionType::Int:
              $newValue = ctype_digit($setValue) ? intval($setValue) : $option->value;
              break;
          case OptionType::String:
              $newValue = $setValue;
              break;
          case OptionType::StringArray:
          case OptionType::UserArray:
              if (count($args) < 3) { return; } //name add|remove value, all required
              if ($option->optionType == OptionType::UserArray) 
              {
                  $this->client->getChannelGroupOrDMByID($this->channel)
                    ->then(function (Channel $channel) {
                        return $channel->getMembers();
                    })
                    ->then(function (array $users) use ($gameManager, $message, $client) {
                        /** @var \Slack\User[] $users */
                        $setValue = UserIdFormatter::format($setValue, $users);
                      });
              }
              switch(strtolower($args[1]))
              {
                  //TODO: Work!
                  case "add":
                        $newValue[] = $args[2];
                        break;
                  case "remove":
                        //TODO: if option name ='mods' user is same username as .env admin, do not allow removal
                        unset($newValue[$args[2]]);
                        break;
                  default:
                        return;
              }
              break;
        }
        $option->value = $newValue;
        
        if ($doSave) {                   
            $this->saveOptions();
        }
    }
    
    public function getOptionValue($optionName)
    {
        /** @var Option $option */
        $option = null;
        
        foreach ($this->options as $searchOption)
        {
            /** @var Option $searchOption */
            if ($searchOption->name == $optionName){
                $option = $searchOption;
                break;
            }
        }
        
        return ($option==null ? null : $option->value);
    }
}
