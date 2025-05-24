<?php

namespace taqdees\StreakAPI;

use pocketmine\utils\Config;
use mysqli;

class DatabaseManager {
    
    private $config;
    private $connection;
    private $useDatabase;
    
    public function __construct(Config $config) {
        $this->config = $config;
        $this->useDatabase = $config->get("use-database", false);
        
        if ($this->useDatabase) {
            $this->initializeDatabase();
        }
    }
    
    private function initializeDatabase(): void {
        $dbConfig = $this->config->get("database", []);
        
        $host = $dbConfig["host"];
        $port = $dbConfig["port"];
        $username = $dbConfig["username"];
        $password = $dbConfig["password"];
        $database = $dbConfig["database"];
        
        try {
            $this->connection = new mysqli($host, $username, $password, $database, $port);
            
            if ($this->connection->connect_error) {
                throw new \Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->createTables();
        } catch (\Exception $e) {
            throw new \Exception("Failed to initialize database: " . $e->getMessage());
        }
    }
    
    private function createTables(): void {
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
            FOREIGN KEY (instance_name) REFERENCES streak_instances(instance_name) ON DELETE CASCADE
        )";
        
        if (!$this->connection->query($instancesTable)) {
            throw new \Exception("Failed to create instances table: " . $this->connection->error);
        }
        
        if (!$this->connection->query($streaksTable)) {
            throw new \Exception("Failed to create streaks table: " . $this->connection->error);
        }
    }
    
    public function saveInstance(string $instanceName, array $config): bool {
        if (!$this->useDatabase) return false;
        
        $displayName = $config['display_name'] ?? $instanceName;
        $configJson = json_encode($config);
        
        $stmt = $this->connection->prepare("INSERT INTO streak_instances (instance_name, display_name, config) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_name = ?, config = ?");
        $stmt->bind_param("sssss", $instanceName, $displayName, $configJson, $displayName, $configJson);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function loadInstances(): array {
        if (!$this->useDatabase) return [];
        
        $result = $this->connection->query("SELECT instance_name, config FROM streak_instances");
        $instances = [];
        
        while ($row = $result->fetch_assoc()) {
            $instances[$row['instance_name']] = json_decode($row['config'], true);
        }
        
        return $instances;
    }
    
    public function deleteInstance(string $instanceName): bool {
        if (!$this->useDatabase) return false;
        
        $stmt = $this->connection->prepare("DELETE FROM streak_instances WHERE instance_name = ?");
        $stmt->bind_param("s", $instanceName);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function saveStreakData(string $instanceName, string $playerName, array $data): bool {
        if (!$this->useDatabase) return false;
        
        $stmt = $this->connection->prepare("INSERT INTO streak_data (instance_name, player_name, current_streak, highest_streak, total_count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak = ?, highest_streak = ?, total_count = ?");
        $stmt->bind_param("ssiiiiiii", $instanceName, $playerName, $data['current_streak'], $data['highest_streak'], $data['total_count'], $data['current_streak'], $data['highest_streak'], $data['total_count']);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function loadStreakData(): array {
        if (!$this->useDatabase) return [];
        
        $result = $this->connection->query("SELECT * FROM streak_data");
        $streaks = [];
        
        while ($row = $result->fetch_assoc()) {
            $streaks[$row['instance_name']][$row['player_name']] = [
                'current_streak' => (int)$row['current_streak'],
                'highest_streak' => (int)$row['highest_streak'],
                'total_count' => (int)$row['total_count'],
                'last_updated' => strtotime($row['last_updated'])
            ];
        }
        
        return $streaks;
    }
    
    public function getPlayerData(string $instanceName, string $playerName): ?array {
        if (!$this->useDatabase) return null;
        
        $stmt = $this->connection->prepare("SELECT * FROM streak_data WHERE instance_name = ? AND player_name = ?");
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
    
    public function getAllStreaks(string $instanceName): array {
        if (!$this->useDatabase) return [];
        
        $stmt = $this->connection->prepare("SELECT * FROM streak_data WHERE instance_name = ?");
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
    
    public function close(): void {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}