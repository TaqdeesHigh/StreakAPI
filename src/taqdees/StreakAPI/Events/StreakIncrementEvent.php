<?php

namespace taqdees\StreakAPI\Events;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;
use taqdees\StreakAPI\Main;

class StreakIncrementEvent extends PluginEvent implements Cancellable {
    use CancellableTrait;
    
    private $player;
    private $instanceName;
    private $oldStreak;
    private $newStreak;
    private $incrementAmount;
    
    public function __construct(Main $plugin, Player $player, string $instanceName, int $oldStreak, int $newStreak, int $incrementAmount) {
        parent::__construct($plugin);
        $this->player = $player;
        $this->instanceName = $instanceName;
        $this->oldStreak = $oldStreak;
        $this->newStreak = $newStreak;
        $this->incrementAmount = $incrementAmount;
    }
    
    public function getPlayer(): Player {
        return $this->player;
    }
    
    public function getInstanceName(): string {
        return $this->instanceName;
    }
    
    public function getOldStreak(): int {
        return $this->oldStreak;
    }
    
    public function getNewStreak(): int {
        return $this->newStreak;
    }
    
    public function getIncrementAmount(): int {
        return $this->incrementAmount;
    }
    
    public function setNewStreak(int $newStreak): void {
        $this->newStreak = $newStreak;
    }
    
    public function setIncrementAmount(int $amount): void {
        $this->incrementAmount = $amount;
        $this->newStreak = $this->oldStreak + $amount;
    }
}