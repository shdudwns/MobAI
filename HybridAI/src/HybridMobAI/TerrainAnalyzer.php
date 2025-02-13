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

    public function isWalkable(Vector3 $position, Vector3 $currentPosition): bool {
    $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
    $block = $world->getBlockAt((int)$position->x, (int)$position->y, (int)$position->z);
    $blockAbove = $world->getBlockAt((int)$position->x, (int)$position->y + 1, (int)$position->z);
    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);

    // ✅ 높낮이 극복: 최대 2블록 차이까지 이동 가능
    $heightDiff = $position->y - $currentPosition->y;
    if ($heightDiff > 2 || $heightDiff < -2) {
        return false;
    }

    // ✅ 대각선 이동 및 높낮이 극복 개선
    if (!$block->isTransparent() || !$blockAbove->isTransparent()) {
        return false;
    }

    // ✅ 내려올 때 아래 블록이 단단해야 함
    if ($heightDiff < 0 && !$blockBelow->isSolid()) {
        return false;
    }

    return true;
}
}
