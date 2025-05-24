<?php

namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class CreateStreakCommand {
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
        
        if (!$this->validateArguments($args, 1, $sender, "/createstreak <instance_name> [display_name]")) {
            return true;
        }
        
        $instanceName = strtolower($args[0]);
        $displayName = $args[1] ?? $args[0];
        
        if ($this->plugin->createInstance($instanceName, $displayName)) {
            $this->sendSuccess($sender, "Created streak instance: {instance} ({display})", [
                "{instance}" => $instanceName,
                "{display}" => $displayName
            ]);
        } else {
            $this->sendError($sender, "Instance {instance} already exists!", ["{instance}" => $instanceName]);
        }
        
        return true;
    }
}