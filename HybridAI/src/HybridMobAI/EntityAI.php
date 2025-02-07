<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;

class EntityAI {
    private bool $enabled = false; // AI 활성화 여부
    private array $path = []; // A* 경로
    private ?Vector3 $target = null; // 목표 위치

    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPath(World $world, Vector3 $start, Vector3 $goal): ?array {
        $pathfinder = new Pathfinder($world);
        return $pathfinder->findPath($start, $goal);
    }

    public function moveAlongPath(Living $mob): void {
        if (!$this->enabled || empty($this->path)) return;

        $nextPosition = array_shift($this->path); // 다음 이동할 위치 가져오기
        if ($nextPosition instanceof Vector3) {
            $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
            $mob->lookAt($nextPosition);
        }
    }
}
