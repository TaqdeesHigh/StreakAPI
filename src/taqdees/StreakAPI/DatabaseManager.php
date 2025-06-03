<?php

namespace taqdees\StreakAPI;

use pocketmine\utils\Config;
use taqdees\StreakAPI\Tasks\AsyncDatabaseTask;

class DatabaseManager {
    
    private $config;
    private $useDatabase;
    private $plugin;
    private $dbConfig;
    private $pendingOperations = [];
    private $batchBuffer = [];
    private $batchSize = 50;
    private $lastBatchTime = 0;
    private $batchInterval = 5;
    
    public function __construct(Main $plugin, Config $config) {
        $this->plugin = $plugin;
        $this->config = $config;
        $this->useDatabase = $config->get("use-database", false);
        $this->dbConfig = $config->get("database", []);
        
        if ($this->useDatabase) {
            $this->initializeDatabase();
        }
    }
    
    private function initializeDatabase(): void {
        if (!$this->validateDatabaseConfig()) {
            throw new \Exception("Invalid database configuration");
        }
        $task = new AsyncDatabaseTask($this->dbConfig, 'create_tables', [], $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    private function validateDatabaseConfig(): bool {
        $required = ['host', 'port', 'username', 'password', 'database'];
        foreach ($required as $key) {
            if (!isset($this->dbConfig[$key])) {
                $this->plugin->getLogger()->error("Missing database config: $key");
                return false;
            }
        }
        return true;
    }
    
    public function isUsingDatabase(): bool {
        return $this->useDatabase;
    }
    
    public function saveInstance(string $instanceName, array $config): void {
        if (!$this->useDatabase) return;
        
        $displayName = $config['display_name'] ?? $instanceName;
        $taskData = [
            'instance_name' => $instanceName,
            'display_name' => $displayName,
            'config' => $config
        ];
        
        $task = new AsyncDatabaseTask($this->dbConfig, 'save_instance', $taskData, $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function loadInstances(callable $callback = null): void {
        if (!$this->useDatabase) {
            if ($callback) $callback([]);
            return;
        }
        
        $this->pendingOperations['load_instances'] = $callback;
        $task = new AsyncDatabaseTask($this->dbConfig, 'load_instances', [], $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function deleteInstance(string $instanceName): void {
        if (!$this->useDatabase) return;
        
        $taskData = ['instance_name' => $instanceName];
        $task = new AsyncDatabaseTask($this->dbConfig, 'delete_instance', $taskData, $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function saveStreakData(string $instanceName, string $playerName, array $data): void {
        if (!$this->useDatabase) return;
        
        $streakData = [
            'instance_name' => $instanceName,
            'player_name' => $playerName,
            'current_streak' => $data['current_streak'],
            'highest_streak' => $data['highest_streak'],
            'total_count' => $data['total_count']
        ];
        
        $this->batchBuffer[] = $streakData;
        if (count($this->batchBuffer) >= $this->batchSize || 
            (time() - $this->lastBatchTime) >= $this->batchInterval) {
            $this->processBatch();
        }
    }
    
    private function processBatch(): void {
        if (empty($this->batchBuffer)) return;
        
        $taskData = ['streaks' => $this->batchBuffer];
        $task = new AsyncDatabaseTask($this->dbConfig, 'batch_save_streaks', $taskData, $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
        
        $this->batchBuffer = [];
        $this->lastBatchTime = time();
    }
    
    public function loadStreakData(callable $callback = null): void {
        if (!$this->useDatabase) {
            if ($callback) $callback([]);
            return;
        }
        
        $this->pendingOperations['load_streaks'] = $callback;
        $task = new AsyncDatabaseTask($this->dbConfig, 'load_streaks', [], $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function getPlayerData(string $instanceName, string $playerName, callable $callback = null): void {
        if (!$this->useDatabase) {
            if ($callback) $callback(null);
            return;
        }
        
        $taskData = [
            'instance_name' => $instanceName,
            'player_name' => $playerName
        ];
        
        $this->pendingOperations['get_player_data_' . $instanceName . '_' . $playerName] = $callback;
        $task = new AsyncDatabaseTask($this->dbConfig, 'get_player_data', $taskData, $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function getAllStreaks(string $instanceName, callable $callback = null): void {
        if (!$this->useDatabase) {
            if ($callback) $callback([]);
            return;
        }
        
        $taskData = ['instance_name' => $instanceName];
        $this->pendingOperations['get_all_streaks_' . $instanceName] = $callback;
        $task = new AsyncDatabaseTask($this->dbConfig, 'get_all_streaks', $taskData, $this->plugin->getName());
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }
    
    public function handleAsyncResult(array $result): void {
        if (!$result['success']) {
            $this->plugin->getLogger()->error("Database operation failed: " . ($result['error'] ?? 'Unknown error'));
            return;
        }
        
        $operation = $result['operation'] ?? '';
        $data = $result['data'] ?? null;
        
        switch ($operation) {
            case 'create_tables':
                $this->plugin->getLogger()->info("Database tables created successfully");
                break;
                
            case 'load_instances':
                if (isset($this->pendingOperations['load_instances'])) {
                    $callback = $this->pendingOperations['load_instances'];
                    if ($callback) $callback($data);
                    unset($this->pendingOperations['load_instances']);
                }
                break;
                
            case 'load_streaks':
                if (isset($this->pendingOperations['load_streaks'])) {
                    $callback = $this->pendingOperations['load_streaks'];
                    if ($callback) $callback($data);
                    unset($this->pendingOperations['load_streaks']);
                }
                break;
                
            case 'get_player_data':
                foreach ($this->pendingOperations as $key => $callback) {
                    if (strpos($key, 'get_player_data_') === 0) {
                        if ($callback) $callback($data);
                        unset($this->pendingOperations[$key]);
                        break;
                    }
                }
                break;
                
            case 'get_all_streaks':
                foreach ($this->pendingOperations as $key => $callback) {
                    if (strpos($key, 'get_all_streaks_') === 0) {
                        if ($callback) $callback($data);
                        unset($this->pendingOperations[$key]);
                        break;
                    }
                }
                break;
                
            case 'batch_save_streaks':
                break;
        }
    }
    
    public function forceBatchProcess(): void {
        $this->processBatch();
    }
    
    public function close(): void {
        $this->forceBatchProcess();
        $this->pendingOperations = [];
        $this->batchBuffer = [];
    }
    
    public function getPendingOperationsCount(): int {
        return count($this->pendingOperations);
    }
    
    public function getBatchBufferSize(): int {
        return count($this->batchBuffer);
    }
}