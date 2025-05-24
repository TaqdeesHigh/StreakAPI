<?php

namespace taqdees\StreakAPI\Commands;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Traits\StreakPluginTrait;

class ListStreaksCommand {
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
            $this->sendInfoMessage($sender, "Other plugins can create instances using the StreakAPI.");
            return true;
        }
        
        $storageType = $this->getStorageTypeString();
        $this->sendWarningMessage($sender, "=== Streak Instances ($storageType) ===");
        
        foreach ($this->plugin->getInstances() as $instance) {
            $displayName = $this->getInstanceDisplayName($instance);
            $playerCount = count($this->plugin->getAllStreaks($instance));
            $this->sendInfoMessage($sender, "- $instance " . TF::WHITE . "($displayName) " . TF::GRAY . "[$playerCount players]");
        }
        
        return true;
    }
}