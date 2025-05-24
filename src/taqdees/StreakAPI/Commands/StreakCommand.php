<?php

namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class StreakCommand {
    use StreakPluginTrait;
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    protected function getPlugin(): Main {
        return $this->plugin;
    }
    
    public function execute(CommandSender $sender, array $args): bool {
        if (!$this->hasInstances($sender)) {
            return true;
        }
        
        [$instanceName, $targetName] = $this->parseInstanceAndPlayer($args, $sender);
        
        if ($instanceName === null) {
            $this->sendError($sender, "No streak instances available!");
            return true;
        }
        
        if ($targetName === null) {
            $this->sendError($sender, "Usage: /streak [instance] [player]");
            return true;
        }
        
        $this->sendStreakInfo($sender, $instanceName, $targetName);
        return true;
    }
}