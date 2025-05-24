<?php

namespace taqdees\StreakAPI\Traits;

use pocketmine\command\CommandSender;
use taqdees\StreakAPI\Main;
use taqdees\StreakAPI\Utils\MessageFormatter;

trait StreakPluginTrait {
    
    /**
     * Get the main plugin instance
     */
    abstract protected function getPlugin(): Main;
    
    /**
     * Validate if sender has required permission
     */
    protected function validatePermission(CommandSender $sender, string $permission): bool {
        if (!$sender->hasPermission($permission)) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cYou don't have permission to use this command!"));
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum argument count
     */
    protected function validateArguments(array $args, int $minCount, CommandSender $sender, string $usage): bool {
        if (count($args) < $minCount) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cUsage: {usage}", ["{usage}" => $usage]));
            return false;
        }
        return true;
    }
    
    /**
     * Validate if instance exists
     */
    protected function validateInstance(string $instanceName, CommandSender $sender): bool {
        if (!$this->getPlugin()->instanceExists($instanceName)) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cInstance '{instance}' doesn't exist!", ["{instance}" => $instanceName]));
            return false;
        }
        return true;
    }
    
    /**
     * Get formatted instance display name
     */
    protected function getInstanceDisplayName(string $instanceName): string {
        $config = $this->getPlugin()->getInstanceConfig($instanceName);
        return $config['display_name'] ?? $instanceName;
    }
    
    /**
     * Check if there are any instances available
     */
    protected function hasInstances(CommandSender $sender): bool {
        if (empty($this->getPlugin()->getInstances())) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cNo streak instances available! Other plugins need to create instances first."));
            return false;
        }
        return true;
    }
    
    /**
     * Get storage type string for display
     */
    protected function getStorageTypeString(): string {
        return $this->getPlugin()->isUsingDatabase() ? "Database" : "JSON";
    }
    
    /**
     * Send formatted streak info using MessageFormatter
     */
    protected function sendStreakInfo(CommandSender $sender, string $instanceName, string $playerName): void {
        if (!$this->validateInstance($instanceName, $sender)) {
            return;
        }
        
        $data = $this->getPlugin()->getPlayerData($instanceName, $playerName);
        if ($data === null) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cNo streak data found for {player} in {instance}", [
                "{player}" => $playerName,
                "{instance}" => $instanceName
            ]));
            return;
        }
        
        $displayName = $this->getInstanceDisplayName($instanceName);
        $messages = MessageFormatter::formatStreakInfo($playerName, $displayName, $data);
        
        foreach ($messages as $message) {
            $sender->sendMessage($message);
        }
    }
    
    /**
     * Send formatted top streaks using MessageFormatter
     */
    protected function sendTopStreaks(CommandSender $sender, string $instanceName): void {
        $streaks = $this->getPlugin()->getAllStreaks($instanceName);
        if (empty($streaks)) {
            $sender->sendMessage(MessageFormatter::formatMessage("&cNo streak data found for {instance}!", ["{instance}" => $instanceName]));
            return;
        }
        
        uasort($streaks, function($a, $b) {
            return $b['current_streak'] <=> $a['current_streak'];
        });
        
        $displayName = $this->getInstanceDisplayName($instanceName);
        $messages = MessageFormatter::formatTopStreaks($displayName, $streaks);
        
        foreach ($messages as $message) {
            $sender->sendMessage($message);
        }
    }
    
    /**
     * Get default instance if none specified
     */
    protected function getDefaultInstance(): ?string {
        $instances = $this->getPlugin()->getInstances();
        return $instances[0] ?? null;
    }
    
    /**
     * Parse instance and player from arguments
     */
    protected function parseInstanceAndPlayer(array $args, CommandSender $sender): array {
        $instanceName = null;
        $targetName = $sender instanceof \pocketmine\player\Player ? $sender->getName() : null;
        
        if (isset($args[0])) {
            if ($this->getPlugin()->instanceExists(strtolower($args[0]))) {
                $instanceName = strtolower($args[0]);
                $targetName = $args[1] ?? ($sender instanceof \pocketmine\player\Player ? $sender->getName() : null);
            } else {
                $targetName = $args[0];
                $instanceName = $this->getDefaultInstance();
            }
        } else {
            $instanceName = $this->getDefaultInstance();
        }
        
        return [$instanceName, $targetName];
    }
    
    /**
     * Send success message using MessageFormatter
     */
    protected function sendSuccess(CommandSender $sender, string $message, array $replacements = []): void {
        $sender->sendMessage(MessageFormatter::formatMessage("&a" . $message, $replacements));
    }
    
    /**
     * Send error message using MessageFormatter
     */
    protected function sendError(CommandSender $sender, string $message, array $replacements = []): void {
        $sender->sendMessage(MessageFormatter::formatMessage("&c" . $message, $replacements));
    }
    
    /**
     * Send info message using MessageFormatter
     */
    protected function sendInfo(CommandSender $sender, string $message, array $replacements = []): void {
        $sender->sendMessage(MessageFormatter::formatMessage("&e" . $message, $replacements));
    }
}