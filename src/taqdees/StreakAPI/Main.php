<?php

namespace taqdees\StreakAPI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

use taqdees\StreakAPI\Commands\CreateStreakCommand;
use taqdees\StreakAPI\Commands\DeleteStreakCommand;
use taqdees\StreakAPI\Commands\ListStreaksCommand;
use taqdees\StreakAPI\Commands\StreakCommand;
use taqdees\StreakAPI\Commands\ResetStreakCommand;
use taqdees\StreakAPI\Commands\TopStreaksCommand;
use taqdees\StreakAPI\Events\StreakIncrementEvent;
use taqdees\StreakAPI\Events\StreakResetEvent;
use taqdees\StreakAPI\Events\StreakMilestoneEvent;
use taqdees\StreakAPI\Utils\MessageFormatter;

class Main extends PluginBase implements Listener {
    
    private $streaks = [];
    private $instances = [];
    private $config;
    private $streakAPI;
    private $databaseManager;
    private $useDatabase;
    private $commands = [];
        
    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->useDatabase = $this->config->get("use-database", false);
        
        $this->initializeArrays();
        
        $this->initializeDatabase();
        $this->streakAPI = new StreakAPI($this);
        $this->loadData();
        $this->registerCommands();
        
        $storageType = $this->useDatabase ? "MySQL Database" : "JSON Files";
    }
    
    public function onDisable(): void {
        $this->saveStreakData();
        $this->saveInstances();
        
        if ($this->databaseManager) {
            $this->databaseManager->close();
        }
    }
    
    private function initializeDatabase(): void {
        if ($this->useDatabase) {
            try {
                $this->databaseManager = new DatabaseManager($this, $this->config);
            } catch (\Exception $e) {
                $this->getLogger()->error(TF::RED . "Failed to initialize database: " . $e->getMessage());
                $this->getLogger()->info(TF::YELLOW . "Falling back to JSON file storage...");
                $this->useDatabase = false;
            }
        }
    }

    public function handleAsyncResult(array $result): void {
        if ($this->databaseManager) {
            $this->databaseManager->handleAsyncResult($result);
        }
    }
    
    private function loadData(): void {
        $this->loadStreakData();
        $this->loadInstances();
    }
    
    private function registerCommands(): void {
        $this->commands = [
            'createstreak' => new CreateStreakCommand($this),
            'deletestreak' => new DeleteStreakCommand($this),
            'liststreaks' => new ListStreaksCommand($this),
            'streak' => new StreakCommand($this),
            'resetstreak' => new ResetStreakCommand($this),
            'topstreaks' => new TopStreaksCommand($this)
        ];
    }
    
    public function getStreakAPI(): StreakAPI {
        return $this->streakAPI;
    }
    
    public function isUsingDatabase(): bool {
        return $this->useDatabase;
    }
    
    public function getDatabaseManager(): ?DatabaseManager {
        return $this->databaseManager;
    }
    
    private function loadStreakData(): void {
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->loadStreakData(function($data) {
                $this->streaks = $data ?? [];
            });
        } else {
            $data = new Config($this->getDataFolder() . "streaks.json", Config::JSON);
            $this->streaks = $data->getAll() ?? [];
        }
    }
        
    public function saveStreakData(): void {
        if ($this->useDatabase && $this->databaseManager) {
            if (is_array($this->streaks)) {
                foreach ($this->streaks as $instanceName => $instanceData) {
                    if (is_array($instanceData)) {
                        foreach ($instanceData as $playerName => $playerData) {
                            $this->databaseManager->saveStreakData($instanceName, $playerName, $playerData);
                        }
                    }
                }
            }
        } else {
            if (!file_exists($this->getDataFolder())) {
                mkdir($this->getDataFolder(), 0777, true);
            }
            $data = new Config($this->getDataFolder() . "streaks.json", Config::JSON);
            $data->setAll($this->streaks ?? []);
            $data->save();
        }
    }

    
    private function loadInstances(): void {
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->loadInstances(function($data) {
                $this->instances = $data ?? [];
            });
        } else {
            $data = new Config($this->getDataFolder() . "instances.json", Config::JSON);
            $this->instances = $data->getAll() ?? [];
        }
    }
        
    public function saveInstances(): void {
        if ($this->useDatabase && $this->databaseManager) {
            return;
        } else {
            if (!file_exists($this->getDataFolder())) {
                mkdir($this->getDataFolder(), 0777, true);
            }
            $data = new Config($this->getDataFolder() . "instances.json", Config::JSON);
            $data->setAll($this->instances ?? []);
            $data->save();
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        if (!is_array($this->instances)) {
            $this->instances = [];
        }
        
        foreach ($this->instances as $instanceName => $config) {
            if (!isset($this->streaks[$instanceName])) {
                $this->streaks[$instanceName] = [];
            }
            
            if (!isset($this->streaks[$instanceName][$name])) {
                $this->streaks[$instanceName][$name] = [
                    'current_streak' => 0,
                    'highest_streak' => 0,
                    'total_count' => 0,
                    'last_updated' => time()
                ];
                
                if ($this->useDatabase && $this->databaseManager) {
                    $this->databaseManager->saveStreakData($instanceName, $name, $this->streaks[$instanceName][$name]);
                }
            }
        }
    }
    
    public function createInstance(string $instanceName, string $displayName = "", array $config = []): bool {
        if (isset($this->instances[$instanceName])) {
            return false;
        }
        
        $defaultConfig = [
            "display_name" => $displayName ?: $instanceName,
            "milestones" => [],
            "reset_on_death" => false,
            "milestone_message" => "&6{player} &ereached &c{streak} &e{instance}!",
            "milestone_commands" => [],
            "created" => time()
        ];
        
        $this->instances[$instanceName] = array_merge($defaultConfig, $config);
        $this->streaks[$instanceName] = [];
        
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->saveInstance($instanceName, $this->instances[$instanceName]);
        } else {
            $this->saveInstances();
        }
        
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $name = $player->getName();
            $this->streaks[$instanceName][$name] = [
                'current_streak' => 0,
                'highest_streak' => 0,
                'total_count' => 0,
                'last_updated' => time()
            ];
            
            if ($this->useDatabase && $this->databaseManager) {
                $this->databaseManager->saveStreakData($instanceName, $name, $this->streaks[$instanceName][$name]);
            }
        }
        
        return true;
    }
    
    public function deleteInstance(string $instanceName): bool {
        if (!isset($this->instances[$instanceName])) {
            return false;
        }
        
        unset($this->instances[$instanceName]);
        unset($this->streaks[$instanceName]);
        
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->deleteInstance($instanceName);
        } else {
            $this->saveInstances();
            $this->saveStreakData();
        }
        
        return true;
    }
    
    public function instanceExists(string $instanceName): bool {
        return isset($this->instances[$instanceName]);
    }
    
    public function getInstances(): array {
        return array_keys($this->instances);
    }
    
    public function getInstanceConfig(string $instanceName): ?array {
        return $this->instances[$instanceName] ?? null;
    }
    
    public function updateInstanceConfig(string $instanceName, array $config): bool {
        if (!$this->instanceExists($instanceName)) {
            return false;
        }
        
        $this->instances[$instanceName] = array_merge($this->instances[$instanceName], $config);
        
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->saveInstance($instanceName, $this->instances[$instanceName]);
        } else {
            $this->saveInstances();
        }
        
        return true;
    }
    
    public function addStreak(string $instanceName, string $playerName, int $amount = 1): bool {
        if (!$this->instanceExists($instanceName)) {
            return false;
        }
        
        if (!isset($this->streaks[$instanceName][$playerName])) {
            $this->streaks[$instanceName][$playerName] = [
                'current_streak' => 0,
                'highest_streak' => 0,
                'total_count' => 0,
                'last_updated' => time()
            ];
        }
        
        $oldStreak = $this->streaks[$instanceName][$playerName]['current_streak'];
        $player = $this->getServer()->getPlayerExact($playerName);
        if ($player !== null) {
            $event = new StreakIncrementEvent($this, $player, $instanceName, $oldStreak, $oldStreak + $amount, $amount);
            $event->call();
            
            if ($event->isCancelled()) {
                return false;
            }
            
            $amount = $event->getIncrementAmount();
        }
        
        $this->streaks[$instanceName][$playerName]['current_streak'] += $amount;
        $this->streaks[$instanceName][$playerName]['total_count'] += $amount;
        $this->streaks[$instanceName][$playerName]['last_updated'] = time();
        
        $isNewRecord = false;
        if ($this->streaks[$instanceName][$playerName]['current_streak'] > $this->streaks[$instanceName][$playerName]['highest_streak']) {
            $this->streaks[$instanceName][$playerName]['highest_streak'] = $this->streaks[$instanceName][$playerName]['current_streak'];
            $isNewRecord = true;
        }
        
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->saveStreakData($instanceName, $playerName, $this->streaks[$instanceName][$playerName]);
        }
        
        $this->checkStreakMilestones($instanceName, $playerName, $isNewRecord);
        return true;
    }

    
    public function setStreak(string $instanceName, string $playerName, int $amount): bool {
        if (!$this->instanceExists($instanceName)) {
            return false;
        }
        
        if (!isset($this->streaks[$instanceName][$playerName])) {
            $this->streaks[$instanceName][$playerName] = [
                'current_streak' => 0,
                'highest_streak' => 0,
                'total_count' => 0,
                'last_updated' => time()
            ];
        }
        
        $this->streaks[$instanceName][$playerName]['current_streak'] = $amount;
        $this->streaks[$instanceName][$playerName]['last_updated'] = time();
        
        if ($this->streaks[$instanceName][$playerName]['current_streak'] > $this->streaks[$instanceName][$playerName]['highest_streak']) {
            $this->streaks[$instanceName][$playerName]['highest_streak'] = $this->streaks[$instanceName][$playerName]['current_streak'];
        }
        
        if ($this->useDatabase && $this->databaseManager) {
            $this->databaseManager->saveStreakData($instanceName, $playerName, $this->streaks[$instanceName][$playerName]);
        }
        
        return true;
    }
        
    public function resetStreak(string $instanceName, string $playerName, string $reason = "manual"): bool {
        if (!$this->instanceExists($instanceName)) {
            return false;
        }
        
        if (isset($this->streaks[$instanceName][$playerName])) {
            $oldStreak = $this->streaks[$instanceName][$playerName]['current_streak'];
            $player = $this->getServer()->getPlayerExact($playerName);
            $event = new StreakResetEvent($this, $player, $instanceName, $oldStreak, $reason);
            $event->call();
            
            if ($event->isCancelled()) {
                return false;
            }
            
            $this->streaks[$instanceName][$playerName]['current_streak'] = 0;
            $this->streaks[$instanceName][$playerName]['last_updated'] = time();
            
            if ($this->useDatabase && $this->databaseManager) {
                $this->databaseManager->saveStreakData($instanceName, $playerName, $this->streaks[$instanceName][$playerName]);
            }
            
            return true;
        }
        return false;
    }

    
    public function getStreak(string $instanceName, string $playerName): int {
        return $this->streaks[$instanceName][$playerName]['current_streak'] ?? 0;
    }
    
    public function getHighestStreak(string $instanceName, string $playerName): int {
        return $this->streaks[$instanceName][$playerName]['highest_streak'] ?? 0;
    }
    
    public function getTotalCount(string $instanceName, string $playerName): int {
        return $this->streaks[$instanceName][$playerName]['total_count'] ?? 0;
    }
    
    public function getPlayerData(string $instanceName, string $playerName): ?array {
        if (isset($this->streaks[$instanceName][$playerName])) {
            return $this->streaks[$instanceName][$playerName];
        }
        
        if ($this->useDatabase && $this->databaseManager) {
            $data = $this->databaseManager->getPlayerData($instanceName, $playerName);
            if ($data !== null) {
                $this->streaks[$instanceName][$playerName] = $data;
                return $data;
            }
        }
        
        return null;
    }
    
    public function getAllStreaks(string $instanceName): array {
        if (isset($this->streaks[$instanceName])) {
            return $this->streaks[$instanceName];
        }
        
        if ($this->useDatabase && $this->databaseManager) {
            $streaks = $this->databaseManager->getAllStreaks($instanceName);
            $this->streaks[$instanceName] = $streaks;
            return $streaks;
        }
        
        return [];
    }

    private function initializeArrays(): void {
        if (!is_array($this->streaks)) {
            $this->streaks = [];
        }
        if (!is_array($this->instances)) {
            $this->instances = [];
        }
    }
    
    public function getAllInstancesData(): array {
        return $this->streaks;
    }
    
    private function checkStreakMilestones(string $instanceName, string $playerName, bool $isNewRecord = false): void {
        $streak = $this->getStreak($instanceName, $playerName);
        $player = $this->getServer()->getPlayerExact($playerName);
        $config = $this->instances[$instanceName];
        
        if ($player === null) return;
        
        $milestones = $config['milestones'] ?? [];
        
        if (in_array($streak, $milestones)) {
            $event = new StreakMilestoneEvent($this, $player, $instanceName, $streak, $streak, $isNewRecord);
            $event->call();
            
            $message = str_replace(
                ["{player}", "{streak}", "{instance}"], 
                [$playerName, $streak, $config['display_name']], 
                $config['milestone_message'] ?? "{player} reached {streak} {instance}!"
            );
            
            $this->getServer()->broadcastMessage(TF::colorize($message));
            
            $commands = $config['milestone_commands'] ?? [];
            foreach ($commands as $command) {
                $cmd = str_replace(
                    ["{player}", "{streak}", "{instance}"], 
                    [$playerName, $streak, $instanceName], 
                    $command
                );
                $this->getServer()->dispatchCommand($this, $cmd);
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $commandName = $command->getName();
        
        if (isset($this->commands[$commandName])) {
            return $this->commands[$commandName]->execute($sender, $args);
        }
        
        return false;
    }
}
