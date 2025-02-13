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

    // ✅ 무제한 높낮이 극복 및 자연스러운 이동
    if (abs($position->y - $currentPosition->y) > 5) {
        return false;
    }

    // ✅ 내려오기 로직 개선
    if ($position->y < $currentPosition->y && !$blockBelow->isSolid()) {
        return false;
    }

    return $block->isTransparent() && $blockAbove->isTransparent();
}
}
