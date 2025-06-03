<?php

namespace taqdees\StreakAPI\Tasks;

use pocketmine\scheduler\AsyncTask;
use taqdees\StreakAPI\Main;

class AsyncDatabaseTask extends AsyncTask {
    
    private $host;
    private $username;
    private $password;
    private $database;
    private $port;
    private $operation;
    private $data;
    private $pluginName;
    
    public function __construct(array $dbConfig, string $operation, array $data = [], string $pluginName = "") {
        $this->host = $dbConfig['host'];
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];
        $this->database = $dbConfig['database'];
        $this->port = $dbConfig['port'];
        $this->operation = $operation;
        $this->data = $data;
        $this->pluginName = $pluginName;
    }
    
    public function onRun(): void {
        try {
            $connection = new \mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
            
            if ($connection->connect_error) {
                $this->setResult(['success' => false, 'error' => $connection->connect_error]);
                return;
            }
            
            $result = null;
            
            switch ($this->operation) {
                case 'create_tables':
                    $result = $this->createTables($connection);
                    break;
                    
                case 'save_instance':
                    $result = $this->saveInstance($connection);
                    break;
                    
                case 'load_instances':
                    $result = $this->loadInstances($connection);
                    break;
                    
                case 'delete_instance':
                    $result = $this->deleteInstance($connection);
                    break;
                    
                case 'save_streak':
                    $result = $this->saveStreakData($connection);
                    break;
                    
                case 'load_streaks':
                    $result = $this->loadStreakData($connection);
                    break;
                    
                case 'get_player_data':
                    $result = $this->getPlayerData($connection);
                    break;
                    
                case 'get_all_streaks':
                    $result = $this->getAllStreaks($connection);
                    break;
                    
                case 'batch_save_streaks':
                    $result = $this->batchSaveStreaks($connection);
                    break;
                    
                default:
                    throw new \Exception("Unknown operation: " . $this->operation);
            }
            
            $connection->close();
            $this->setResult(['success' => true, 'data' => $result, 'operation' => $this->operation]);
            
        } catch (\Exception $e) {
            $this->setResult(['success' => false, 'error' => $e->getMessage(), 'operation' => $this->operation]);
        }
    }
    
    private function createTables(\mysqli $connection): bool {
        $instancesTable = "CREATE TABLE IF NOT EXISTS streak_instances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            instance_name VARCHAR(255) UNIQUE NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            config JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $streaksTable = "CREATE TABLE IF NOT EXISTS streak_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            instance_name VARCHAR(255) NOT NULL,
            player_name VARCHAR(255) NOT NULL,
            current_streak INT DEFAULT 0,
            highest_streak INT DEFAULT 0,
            total_count INT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_instance_player (instance_name, player_name),
            INDEX idx_instance_name (instance_name),
            INDEX idx_player_name (player_name)
        )";
        
        if (!$connection->query($instancesTable)) {
            throw new \Exception("Failed to create instances table: " . $connection->error);
        }
        
        if (!$connection->query($streaksTable)) {
            throw new \Exception("Failed to create streaks table: " . $connection->error);
        }
        
        return true;
    }
    
    private function saveInstance(\mysqli $connection): bool {
        $instanceName = $this->data['instance_name'];
        $displayName = $this->data['display_name'];
        $configJson = json_encode($this->data['config']);
        
        $stmt = $connection->prepare("INSERT INTO streak_instances (instance_name, display_name, config) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_name = ?, config = ?");
        $stmt->bind_param("sssss", $instanceName, $displayName, $configJson, $displayName, $configJson);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function loadInstances(\mysqli $connection): array {
        $result = $connection->query("SELECT instance_name, config FROM streak_instances");
        $instances = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $instances[$row['instance_name']] = json_decode($row['config'], true);
            }
            $result->free();
        }
        
        return $instances;
    }
    
    private function deleteInstance(\mysqli $connection): bool {
        $instanceName = $this->data['instance_name'];
        
        $stmt = $connection->prepare("DELETE FROM streak_instances WHERE instance_name = ?");
        $stmt->bind_param("s", $instanceName);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function saveStreakData(\mysqli $connection): bool {
        $instanceName = $this->data['instance_name'];
        $playerName = $this->data['player_name'];
        $currentStreak = $this->data['current_streak'];
        $highestStreak = $this->data['highest_streak'];
        $totalCount = $this->data['total_count'];
        
        $stmt = $connection->prepare("INSERT INTO streak_data (instance_name, player_name, current_streak, highest_streak, total_count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak = ?, highest_streak = ?, total_count = ?");
        $stmt->bind_param("ssiiiiiii", $instanceName, $playerName, $currentStreak, $highestStreak, $totalCount, $currentStreak, $highestStreak, $totalCount);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function batchSaveStreaks(\mysqli $connection): bool {
        $connection->autocommit(false);
        
        try {
            $stmt = $connection->prepare("INSERT INTO streak_data (instance_name, player_name, current_streak, highest_streak, total_count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak = ?, highest_streak = ?, total_count = ?");
            
            foreach ($this->data['streaks'] as $streakData) {
                $stmt->bind_param("ssiiiiiii", 
                    $streakData['instance_name'], 
                    $streakData['player_name'], 
                    $streakData['current_streak'], 
                    $streakData['highest_streak'], 
                    $streakData['total_count'],
                    $streakData['current_streak'], 
                    $streakData['highest_streak'], 
                    $streakData['total_count']
                );
                $stmt->execute();
            }
            
            $stmt->close();
            $connection->commit();
            $connection->autocommit(true);
            
            return true;
        } catch (\Exception $e) {
            $connection->rollback();
            $connection->autocommit(true);
            throw $e;
        }
    }
    
    private function loadStreakData(\mysqli $connection): array {
        $result = $connection->query("SELECT * FROM streak_data");
        $streaks = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $streaks[$row['instance_name']][$row['player_name']] = [
                    'current_streak' => (int)$row['current_streak'],
                    'highest_streak' => (int)$row['highest_streak'],
                    'total_count' => (int)$row['total_count'],
                    'last_updated' => strtotime($row['last_updated'])
                ];
            }
            $result->free();
        }
        
        return $streaks;
    }
    
    private function getPlayerData(\mysqli $connection): ?array {
        $instanceName = $this->data['instance_name'];
        $playerName = $this->data['player_name'];
        
        $stmt = $connection->prepare("SELECT * FROM streak_data WHERE instance_name = ? AND player_name = ?");
        $stmt->bind_param("ss", $instanceName, $playerName);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) return null;
        
        return [
            'current_streak' => (int)$row['current_streak'],
            'highest_streak' => (int)$row['highest_streak'],
            'total_count' => (int)$row['total_count'],
            'last_updated' => strtotime($row['last_updated'])
        ];
    }
    
    private function getAllStreaks(\mysqli $connection): array {
        $instanceName = $this->data['instance_name'];
        
        $stmt = $connection->prepare("SELECT * FROM streak_data WHERE instance_name = ?");
        $stmt->bind_param("s", $instanceName);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $streaks = [];
        
        while ($row = $result->fetch_assoc()) {
            $streaks[$row['player_name']] = [
                'current_streak' => (int)$row['current_streak'],
                'highest_streak' => (int)$row['highest_streak'],
                'total_count' => (int)$row['total_count'],
                'last_updated' => strtotime($row['last_updated'])
            ];
        }
        
        $stmt->close();
        return $streaks;
    }
    
    public function onCompletion(): void {
        $plugin = $this->getOwner();
        if ($plugin instanceof Main && $plugin->isEnabled()) {
            $result = $this->getResult();
            $plugin->handleAsyncResult($result);
        }
    }
}