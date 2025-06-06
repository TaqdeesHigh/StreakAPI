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
    private $serializedData;
    private $pluginName;
    
    public function __construct(array $dbConfig, string $operation, array $data = [], string $pluginName = "") {
        $this->host = $dbConfig['host'];
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];
        $this->database = $dbConfig['database'];
        $this->port = $dbConfig['port'];
        $this->operation = $operation;
        $this->serializedData = serialize($data);
        $this->pluginName = $pluginName;
    }

    private function getData(): array {
        return unserialize($this->serializedData);
    }
    
    public function onRun(): void {
        try {
            $connection = new \mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
            
            if ($connection->connect_error) {
                $this->setResult(serialize(['success' => false, 'error' => $connection->connect_error]));
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
            $this->setResult(serialize(['success' => true, 'data' => $result, 'operation' => $this->operation]));
            
        } catch (\Exception $e) {
            $this->setResult(serialize(['success' => false, 'error' => $e->getMessage(), 'operation' => $this->operation]));
        }
    }
    
    private function saveInstance(\mysqli $connection): bool {
        $data = $this->getData();
        $instanceName = $data['instance_name'];
        $displayName = $data['display_name'];
        $configJson = json_encode($data['config']);
        
        $stmt = $connection->prepare("INSERT INTO streak_instances (instance_name, display_name, config) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_name = ?, config = ?");
        $stmt->bind_param("sssss", $instanceName, $displayName, $configJson, $displayName, $configJson);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function deleteInstance(\mysqli $connection): bool {
        $data = $this->getData();
        $instanceName = $data['instance_name'];
        
        $stmt = $connection->prepare("DELETE FROM streak_instances WHERE instance_name = ?");
        $stmt->bind_param("s", $instanceName);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function saveStreakData(\mysqli $connection): bool {
        $data = $this->getData();
        $instanceName = $data['instance_name'];
        $playerName = $data['player_name'];
        $currentStreak = $data['current_streak'];
        $highestStreak = $data['highest_streak'];
        $totalCount = $data['total_count'];
        
        $stmt = $connection->prepare("INSERT INTO streak_data (instance_name, player_name, current_streak, highest_streak, total_count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak = ?, highest_streak = ?, total_count = ?");
        $stmt->bind_param("ssiiiiiii", $instanceName, $playerName, $currentStreak, $highestStreak, $totalCount, $currentStreak, $highestStreak, $totalCount);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    private function batchSaveStreaks(\mysqli $connection): bool {
        $data = $this->getData();
        $connection->autocommit(false);
        
        try {
            $stmt = $connection->prepare("INSERT INTO streak_data (instance_name, player_name, current_streak, highest_streak, total_count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak = ?, highest_streak = ?, total_count = ?");
            
            foreach ($data['streaks'] as $streakData) {
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
    
    private function getPlayerData(\mysqli $connection): ?array {
        $data = $this->getData();
        $instanceName = $data['instance_name'];
        $playerName = $data['player_name'];
        
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
        $data = $this->getData();
        $instanceName = $data['instance_name'];
        
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
        $server = \pocketmine\Server::getInstance();
        $plugin = $server->getPluginManager()->getPlugin($this->pluginName);
        
        if ($plugin instanceof Main && $plugin->isEnabled()) {
            $result = unserialize($this->getResult());
            $plugin->handleAsyncResult($result);
        }
    }
}