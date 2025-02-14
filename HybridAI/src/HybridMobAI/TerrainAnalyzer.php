<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\block\Air;
use pocketmine\block\Transparent;
use pocketmine\Server;
use pocketmine\block\BlockTypeIds;

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
    $block = $this->world->getBlockAt((int)$position->x, (int)$position->y, (int)$position->z);
    $blockBelow = $this->world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    $blockAbove = $this->world->getBlockAt((int)$position->x, (int)$position->y + 1, (int)$position->z);

    // ✅ 블록 및 투명 블록 확인
    if ($block->isTransparent() || $block instanceof Air) {
        return false;
    }

    // ✅ 머리 위 공간 확인 (2칸 확보)
    $headSpace = $this->world->getBlockAt((int)$position->x, (int)$position->y + 2, (int)$position->z);
    if (!$headSpace->isTransparent()) {
        return false;
    }

    // ✅ 아래 블록이 단단한 블록인지 확인
    if (!$blockBelow->isSolid()) {
        return false;
    }

    // ✅ 높낮이 차이 극복
    $heightDiff = abs($position->y - $currentPosition->y);
    if ($heightDiff > 3) { // 최대 3칸까지 이동 가능
        return false;
    }

    return true;
}
}
