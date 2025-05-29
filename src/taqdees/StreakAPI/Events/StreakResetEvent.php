<?php

namespace taqdees\StreakAPI\Events;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;
use taqdees\StreakAPI\Main;

class StreakResetEvent extends PluginEvent implements Cancellable {
    use CancellableTrait;
    
    private $player;
    private $instanceName;
    private $oldStreak;
    private $reason;
    
    public function __construct(Main $plugin, ?Player $player, string $instanceName, int $oldStreak, string $reason = "unknown") {
        parent::__construct($plugin);
        $this->player = $player;
        $this->instanceName = $instanceName;
        $this->oldStreak = $oldStreak;
        $this->reason = $reason;
    }
    
    public function getPlayer(): ?Player {
        return $this->player;
    }
    
    public function getInstanceName(): string {
        return $this->instanceName;
    }
    
    public function getOldStreak(): int {
        return $this->oldStreak;
    }
    
    public function getReason(): string {
        return $this->reason;
    }
    
    public function setReason(string $reason): void {
        $this->reason = $reason;
    }
}