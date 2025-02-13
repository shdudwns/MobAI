<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\block\Air;
use pocketmine\block\Transparent;
use pocketmine\Server;

class TerrainAnalyzer {

    private World $world;

    public function __construct(World $world) {
        $this->world = $world;
    }

    public function isJumpable(Vector3 $current, Vector3 $neighbor): bool {
        $yDiff = $neighbor->y - $current->y;
        $block = $this->world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        $blockAbove = $this->world->getBlockAt((int)$neighbor->x, (int)$neighbor->y + 1, (int)$neighbor->z);

        // 🔥 1~2블록 높이 차이는 점프 가능
        if ($yDiff > 0 && $yDiff <= 2 && $blockAbove instanceof Air) {
            return true;
        }

        return false;
    }

    public function isDownhill(Vector3 $current, Vector3 $neighbor): bool {
        $yDiff = $neighbor->y - $current->y;

        // 🔥 1~2블록 아래로 내려갈 수 있음
        if ($yDiff < 0 && $yDiff >= -2) {
            return true;
        }

        return false;
    }

    public function isWalkable(Vector3 $position): bool {
    $block = $this->world->getBlockAt((int)$position->x, (int)$position->y, (int)$position->z);
    $blockAbove = $this->world->getBlockAt((int)$position->x, (int)$position->y + 1, (int)$position->z);
    $blockBelow = $this->world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);

    // 🔥 디버깅 메시지 추가
    Server::getInstance()->broadcastMessage("🔍 [TerrainAnalyzer] isWalkable: Checking position: ({$position->x}, {$position->y}, {$position->z})");
    Server::getInstance()->broadcastMessage("🔍 [TerrainAnalyzer] Block: {$block->getName()}, BlockAbove: {$blockAbove->getName()}, BlockBelow: {$blockBelow->getName()}");

    // ✅ 1. 현재 밟고 있는 블록은 이동 가능
    if ($block instanceof Air || $block instanceof Transparent) {
        return true;
    }

    // ✅ 2. 머리 위 공간이 비어있고 발 밑 블록이 단단해야 이동 가능
    if (($blockAbove instanceof Air || $blockAbove instanceof Transparent) && $blockBelow->isSolid()) {
        return true;
    }

    Server::getInstance()->broadcastMessage("⛔ [TerrainAnalyzer] 이동 불가 위치!");
    return false;
}
}
