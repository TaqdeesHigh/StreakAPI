# StreakAPI Plugin

A comprehensive PocketMine-MP plugin that provides a flexible API for tracking player streaks across multiple instances. Perfect for gamemodes like KitPvP, SkyWars, or any plugin that needs to track consecutive achievements.

## Features

* **Multiple Streak Instances**: Create separate streak trackers for different activities
* **Dual Storage Options**: Choose between JSON files or MySQL database storage
* **Milestone System**: Configure rewards and announcements for streak milestones
* **Comprehensive Commands**: Full set of commands for players and administrators
* **Developer API**: Easy-to-use API for other plugins to integrate streak tracking
* **Automatic Data Management**: Handles player data initialization and cleanup
* **Flexible Configuration**: Customizable display names, messages, and behaviors

## Installation

1. Download the latest release of StreakAPI
2. Place the `.phar` file in your server's `plugins/` directory
3. Restart your server
4. Configure the plugin settings in `plugins/StreakAPI/config.yml`

## Configuration

### Basic Configuration (`config.yml`)

```yaml
# Storage configuration
use-database: false

# Database settings (only used if use-database is true)
database:
  host: "localhost"
  port: 3306
  username: "root"
  password: ""
  database: "streakapi"

# Default instance settings
default-settings:
  milestone-message: "&6{player} &ereached &c{streak} &e{instance}!"
  reset-on-death: false
```

### Database Setup (Optional)

If you prefer database storage over JSON files:

1. Set `use-database: true` in `config.yml`
2. Configure your database credentials
3. Create a MySQL database for the plugin
4. The plugin will automatically create required tables

## Commands

### Player Commands

| Command                       | Permission | Description                  |
| ----------------------------- | ---------- | ---------------------------- |
| `/streak [instance] [player]` | None       | View streak information      |
| `/topstreaks [instance]`      | None       | View top streaks leaderboard |

### Administrator Commands

| Command                               | Permission      | Description                  |
| ------------------------------------- | --------------- | ---------------------------- |
| `/createstreak <name> [display_name]` | streakapi.admin | Create a new streak instance |
| `/deletestreak <name>`                | streakapi.admin | Delete a streak instance     |
| `/liststreaks`                        | None            | List all streak instances    |
| `/resetstreak <instance> <player>`    | streakapi.reset | Reset a player's streak      |

## Permissions

```yaml
permissions:
  streakapi.admin:
    description: "Access to administrative commands"
    default: op
  streakapi.reset:
    description: "Reset player streaks"
    default: op
```

## Developer API

### Getting Started

```php
$streakAPI = $this->getServer()->getPluginManager()->getPlugin("StreakAPI")->getStreakAPI();
```

### Creating Streak Instances

```php
$streakAPI->createInstance("kills", "Kill Streak");

$config = [
    "display_name" => "Win Streak",
    "milestones" => [5, 10, 25, 50, 100],
    "milestone_message" => "&6{player} &eachieved a &c{streak} &e{instance}!",
    "milestone_commands" => [
        "give {player} diamond 1",
        "broadcast {player} is on fire with {streak} wins!"
    ],
    "reset_on_death" => false
];
$streakAPI->createInstance("wins", "Win Streak", $config);
```

### Managing Streaks

```php
$streakAPI->addStreak("kills", "PlayerName", 1);
$streakAPI->setStreak("kills", "PlayerName", 10);
$streakAPI->resetStreak("kills", "PlayerName");
$currentStreak = $streakAPI->getStreak("kills", "PlayerName");
$highestStreak = $streakAPI->getHighestStreak("kills", "PlayerName");
$totalCount = $streakAPI->getTotalCount("kills", "PlayerName");
```

### Retrieving Data

```php
$playerData = $streakAPI->getPlayerData("kills", "PlayerName");
$allStreaks = $streakAPI->getAllStreaks("kills");
$instances = $streakAPI->getInstances();
if ($streakAPI->instanceExists("kills")) {
    // Instance exists
}
```

### Instance Management

```php
$config = $streakAPI->getInstanceConfig("kills");
$newConfig = [
    "display_name" => "Updated Kill Streak",
    "milestones" => [3, 7, 15, 30]
];
$streakAPI->updateInstanceConfig("kills", $newConfig);
$streakAPI->deleteInstance("kills");
```

## Example Usage

### Basic Kill Streak Implementation

```php
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;

class KillStreakHandler implements Listener {
    private $streakAPI;

    public function __construct($plugin) {
        $this->streakAPI = $plugin->getServer()->getPluginManager()->getPlugin("StreakAPI")->getStreakAPI();

        if (!$this->streakAPI->instanceExists("kills")) {
            $config = [
                "display_name" => "Kill Streak",
                "milestones" => [5, 10, 15, 20, 25],
                "milestone_message" => "&6{player} &eis on a &c{streak} &ekill streak!",
                "milestone_commands" => ["give {player} golden_apple 1"],
                "reset_on_death" => true
            ];
            $this->streakAPI->createInstance("kills", "Kill Streak", $config);
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        $cause = $player->getLastDamageCause();

        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();

            if ($killer instanceof Player) {
                $this->streakAPI->addStreak("kills", $killer->getName());
                $this->streakAPI->resetStreak("kills", $player->getName());
            }
        }
    }
}
```

### Win Streak for Minigames

```php
class GameWinHandler {
    private $streakAPI;

    public function __construct($plugin) {
        $this->streakAPI = $plugin->getServer()->getPluginManager()->getPlugin("StreakAPI")->getStreakAPI();

        $this->streakAPI->createInstance("wins", "Win Streak", [
            "milestones" => [3, 5, 10, 15, 25],
            "milestone_message" => "&6{player} &ewon &c{streak} &egames in a row!",
            "reset_on_death" => false
        ]);
    }

    public function onGameWin(Player $winner) {
        $this->streakAPI->addStreak("wins", $winner->getName());
        $streak = $this->streakAPI->getStreak("wins", $winner->getName());
        $winner->sendMessage("§aYou won! Current win streak: §c$streak");
    }

    public function onGameLose(Player $loser) {
        $streak = $this->streakAPI->getStreak("wins", $loser->getName());
        if ($streak > 0) {
            $this->streakAPI->resetStreak("wins", $loser->getName());
            $loser->sendMessage("§cYou lost your win streak of $streak!");
        }
    }
}
```

## Milestone System

The milestone system allows you to configure automatic rewards and announcements when players reach certain streak values.

```php
$config = [
    "display_name" => "Kill Streak",
    "milestones" => [5, 10, 25, 50, 100],
    "milestone_message" => "&6{player} &eachieved a &c{streak} &e{instance}!",
    "milestone_commands" => [
        "give {player} diamond_sword 1",
        "give {player} golden_apple 3",
        "eco give {player} 1000"
    ]
];
```

### Available Placeholders

* `{player}` - Player's name
* `{streak}` - Current streak value
* `{instance}` - Instance display name

## Storage Options

### JSON Files (Default)

* Stored in `plugins/StreakAPI/streaks.json` and `instances.json`
* No additional setup required
* Good for smaller servers

### MySQL Database

* Better performance for larger servers
* Supports advanced queries
* Automatic table creation
* Data persistence and reliability

## API Events

```php
// TODO: Custom events in future updates
// - StreakIncrementEvent
// - StreakResetEvent
// - StreakMilestoneEvent
```

## Troubleshooting

**Database Connection Failed**

* Check your database credentials in `config.yml`
* Ensure MySQL server is running
* Verify database exists

**Permission Denied**

* Check permissions in your permissions plugin
* Ensure players have required permissions

**Instance Not Found**

* Use `/liststreaks` to see available instances
* Create instances using `/createstreak` or the API

## Support

* Create an issue on the GitHub repository

## License

This plugin is released under the MIT License. See LICENSE file for details.

---

**Version:** 1.0.0
**Compatible with:** PocketMine-MP 5.0+
**PHP Version:** 8.0+
