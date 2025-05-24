<?php

namespace taqdees\StreakAPI\Utils;

use pocketmine\utils\TextFormat as TF;

class MessageFormatter {
    
    public static function formatMessage(string $message, array $replacements = []): string {
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        return TF::colorize($message);
    }
    
    public static function formatStreakInfo(string $playerName, string $instanceName, array $data): array {
        return [
            TF::GOLD . "=== $instanceName Info for $playerName ===",
            TF::YELLOW . "Current Streak: " . TF::WHITE . $data['current_streak'],
            TF::YELLOW . "Highest Streak: " . TF::WHITE . $data['highest_streak'],
            TF::YELLOW . "Total Count: " . TF::WHITE . $data['total_count']
        ];
    }
    
    public static function formatTopStreaks(string $instanceName, array $streaks): array {
        $messages = [TF::GOLD . "=== Top $instanceName Streaks ==="];
        
        $count = 0;
        foreach ($streaks as $playerName => $data) {
            if ($count >= 10) break;
            if ($data['current_streak'] > 0) {
                $count++;
                $messages[] = TF::YELLOW . "$count. $playerName: " . TF::WHITE . $data['current_streak'];
            }
        }
        
        if ($count === 0) {
            $messages[] = TF::RED . "No active streaks found!";
        }
        
        return $messages;
    }
}