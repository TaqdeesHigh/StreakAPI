<?php

namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class DeleteStreakCommand {
    use StreakPluginTrait;
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    protected function getPlugin(): Main {
        return $this->plugin;
    }
    
    public function execute(CommandSender $sender, array $args): bool {
        if (!$this->validatePermission($sender, "streakapi.admin")) {
            return true;
        }
        
        if (!$this->validateArguments($args, 1, $sender, "/deletestreak <instance_name>")) {
            return true;
        }
        
        $instanceName = strtolower($args[0]);
        
        if ($this->plugin->deleteInstance($instanceName)) {
            $this->sendSuccessMessage($sender, "Deleted streak instance: $instanceName");
        } else {
            $this->sendErrorMessage($sender, "Instance $instanceName doesn't exist!");
        }
        
        return true;
    }
}