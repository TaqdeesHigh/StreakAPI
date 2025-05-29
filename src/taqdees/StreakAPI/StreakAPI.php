<?php

namespace taqdees\StreakAPI;

class StreakAPI {
    
    private $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function createInstance(string $instanceName, string $displayName = "", array $config = []): bool {
        return $this->plugin->createInstance(strtolower($instanceName), $displayName, $config);
    }
    
    public function deleteInstance(string $instanceName): bool {
        return $this->plugin->deleteInstance(strtolower($instanceName));
    }
    
    public function instanceExists(string $instanceName): bool {
        return $this->plugin->instanceExists(strtolower($instanceName));
    }
    
    public function getInstances(): array {
        return $this->plugin->getInstances();
    }
    
    public function getInstanceConfig(string $instanceName): ?array {
        return $this->plugin->getInstanceConfig(strtolower($instanceName));
    }
    
    public function updateInstanceConfig(string $instanceName, array $config): bool {
        return $this->plugin->updateInstanceConfig(strtolower($instanceName), $config);
    }
    
    public function addStreak(string $instanceName, string $playerName, int $amount = 1): bool {
        return $this->plugin->addStreak(strtolower($instanceName), $playerName, $amount);
    }
    
    public function setStreak(string $instanceName, string $playerName, int $amount): bool {
        return $this->plugin->setStreak(strtolower($instanceName), $playerName, $amount);
    }
    
    public function resetStreak(string $instanceName, string $playerName, string $reason = "api"): bool {
        return $this->plugin->resetStreak(strtolower($instanceName), $playerName, $reason);
    }
    
    public function getStreak(string $instanceName, string $playerName): int {
        return $this->plugin->getStreak(strtolower($instanceName), $playerName);
    }
    
    public function getHighestStreak(string $instanceName, string $playerName): int {
        return $this->plugin->getHighestStreak(strtolower($instanceName), $playerName);
    }
    
    public function getTotalCount(string $instanceName, string $playerName): int {
        return $this->plugin->getTotalCount(strtolower($instanceName), $playerName);
    }
    
    public function getPlayerData(string $instanceName, string $playerName): ?array {
        return $this->plugin->getPlayerData(strtolower($instanceName), $playerName);
    }
    
    public function getAllStreaks(string $instanceName): array {
        return $this->plugin->getAllStreaks(strtolower($instanceName));
    }
    
    public function getAllInstancesData(): array {
        return $this->plugin->getAllInstancesData();
    }
}