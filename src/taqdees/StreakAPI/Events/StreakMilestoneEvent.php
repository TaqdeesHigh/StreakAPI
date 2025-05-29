<?php

namespace taqdees\StreakAPI\Events;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\Player;
use taqdees\StreakAPI\Main;

class StreakMilestoneEvent extends PluginEvent {
    
    private $player;
    private $instanceName;
    private $streak;
    private $milestone;
    private $isNewRecord;
    
    public function __construct(Main $plugin, Player $player, string $instanceName, int $streak, int $milestone, bool $isNewRecord = false) {
        parent::__construct($plugin);
        $this->player = $player;
        $this->instanceName = $instanceName;
        $this->streak = $streak;
        $this->milestone = $milestone;
        $this->isNewRecord = $isNewRecord;
    }
    
    public function getPlayer(): Player {
        return $this->player;
    }
    
    public function getInstanceName(): string {
        return $this->instanceName;
    }
    
    public function getStreak(): int {
        return $this->streak;
    }
    
    public function getMilestone(): int {
        return $this->milestone;
    }
    
    public function isNewRecord(): bool {
        return $this->isNewRecord;
    }
}