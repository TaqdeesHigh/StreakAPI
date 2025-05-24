<?php

namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class TopStreaksCommand {
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
        
        $instanceName = isset($args[0]) ? strtolower($args[0]) : $this->getDefaultInstance();
        
        if (!$this->validateInstance($instanceName, $sender)) {
            return true;
        }
        
        $this->sendTopStreaks($sender, $instanceName);
        return true;
    }
}