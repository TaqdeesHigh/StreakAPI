<?php
namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class ResetStreakCommand implements PluginOwned {
    use StreakPluginTrait;
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
    
    protected function getPlugin(): Main {
        return $this->plugin;
    }
    
    public function execute(CommandSender $sender, array $args): bool {
        if (!$this->validatePermission($sender, "streakapi.reset")) {
            return true;
        }
        
        if (!$this->validateArguments($args, 2, $sender, "/resetstreak <instance> <player>")) {
            return true;
        }
        
        $instanceName = strtolower($args[0]);
        $targetName = $args[1];
        
        if (!$this->validateInstance($instanceName, $sender)) {
            return true;
        }
        if ($this->plugin->resetStreak($instanceName, $targetName, "command")) {
            $this->sendSuccessMessage($sender, "Reset $instanceName streak for $targetName");
        } else {
            $this->sendErrorMessage($sender, "Failed to reset streak (player not found)");
        }
        
        return true;
    }
}