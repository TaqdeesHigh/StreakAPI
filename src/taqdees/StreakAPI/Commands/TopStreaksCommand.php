<?php
namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class TopStreaksCommand implements PluginOwned {
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