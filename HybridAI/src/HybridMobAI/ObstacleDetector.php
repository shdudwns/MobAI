<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\block\Slab;
use pocketmine\block\Fence;
use pocketmine\block\Wall;
use pocketmine\block\Trapdoor;
use pocketmine\world\World;
use pocketmine\scheduler\ClosureTask;

class ObstacleDetector {

    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function checkForObstaclesAndJump(Living $mob, World $world): void {
        $position = $mob->getPosition();
        $yaw = $mob->getLocation()->yaw;
        $angles = [$yaw, $yaw + 15, $yaw - 15]; // ✅ 더 정밀한 장애물 감지 (좁은 범위)

        foreach ($angles as $angle) {
            $direction2D = VectorMath::getDirection2D($angle);
            $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);
            $frontBlockPos = $position->addVector($directionVector);
            
            $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
            $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
            $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);

            $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y; // ✅ +1 추가하여 정확한 점프 감지

            // ✅ 1. 평지에서 점프 방지
            if ($heightDiff <= 0) continue;

            // ✅ 2. 블록에서 내려올 때 점프 방지
            if ($blockBelow->getPosition()->y > $position->y - 0.5) continue;

            // ✅ 3. 계단 및 연속 이동 지원
            if ($this->isStairOrSlab($frontBlock) && $frontBlockAbove->isTransparent()) {
                $this->stepUp($mob, $heightDiff);
                return;
            }

            // ✅ 4. 점프 가능한 일반 블록 감지
            if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
                if ($heightDiff <= 1.2) {
                    $this->jump($mob, $heightDiff);
                    return;
                }
            }
        }
    }

    private function stepUp(Living $mob, float $heightDiff): void {
        $direction = $mob->getDirectionVector()->normalize()->multiply(0.12); // ✅ 수평 이동 속도 일정하게 유지

        $mob->setMotion(new Vector3(
            $direction->x,
            0.3 + ($heightDiff * 0.1),
            $direction->z
        ));

        // ✅ 착지 후 바로 다음 장애물 검사
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($mob): void {
            if ($mob->isOnGround()) {
                $this->checkForObstaclesAndJump($mob, $mob->getWorld());
            }
        }), 2); // 2틱(0.1초) 후 착지 확인
    }

    private function jump(Living $mob, float $heightDiff): void {
        $baseJumpForce = 0.42;
        $extraJumpBoost = min(0.1 * $heightDiff, 0.2); // ✅ 너무 높게 점프하는 문제 방지
        $jumpForce = $baseJumpForce + $extraJumpBoost;

        if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x,
                $jumpForce,
                $mob->getMotion()->z
            ));

            // ✅ 착지 후 바로 다음 장애물 검사
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($mob): void {
                if ($mob->isOnGround()) {
                    $this->checkForObstaclesAndJump($mob, $mob->getWorld());
                }
            }), 2); // 2틱(0.1초) 후 착지 확인
        }
    }

    private function isStairOrSlab(Block $block): bool {
        return $block instanceof Stair || $block instanceof Slab;
    }

    private function isClimbable(Block $block): bool {
        return (
            $block instanceof Fence || 
            $block instanceof Wall || 
            $block instanceof Trapdoor || 
            $block->isSolid()
        );
    }
}
