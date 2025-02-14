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
use pocketmine\block\BlockTypeIds;

class ObstacleDetector {

    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function checkForObstaclesAndJump(Living $mob, World $world): void {
        $position = $mob->getPosition();
        $yaw = $mob->getLocation()->yaw;

        // ✅ 정면 방향만 검사 (대각선 제외)
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);
        $frontBlockPos = $position->addVector($directionVector);
        
        $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
        $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
        $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);

        $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y; // ✅ +1 추가하여 정확한 점프 감지

        // ✅ 1. 평지에서 점프 방지
        if ($heightDiff <= 0 || $frontBlock->isTransparent()) {
            return;
        }

        // ✅ 2. 블록에서 내려올 때 점프 방지 (더 강화된 조건)
        if ($blockBelow->getPosition()->y > $position->y - 0.5) {
            return;
        }

        // ✅ 3. 계단 및 연속 이동 지원
        if ($this->isStairOrSlab($frontBlock) && $frontBlockAbove->isTransparent()) {
            $this->stepUp($mob, $heightDiff);
            return;
        }

        // ✅ 4. 점프 가능한 일반 블록 감지 (정면 블록만 대상)
        if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
            if ($heightDiff <= 1.2) {
                $this->jump($mob, $heightDiff);
                return;
            }
        }

        // ✅ 5. 블록 모서리 정중앙에서 점프 지원
        if ($this->isEdgeOfBlock($position, $frontBlockPos)) {
            $this->jump($mob, $heightDiff);
        }
    }

public function handleJumpAndFall(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $direction = $mob->getDirectionVector()->normalize();
    
    // ✅ addVector() 사용
    $frontBlockPos = $position->addVector($direction);

    $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
    $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);

    $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y;
    $motion = $mob->getMotion();

    // ✅ 평지에서는 점프하지 않음
    if ($heightDiff <= 0) {
        return;
    }

    // ✅ 점프 타이밍 및 높이 동적 조정
    if ($heightDiff > 0 && $heightDiff <= 1.2 && $mob->isOnGround()) {
        $jumpForce = 0.42;
        $approachDistance = 0.3; // ✅ 블록에 가까워지면 점프
        $distanceToBlock = $position->distance($frontBlockPos);

        if ($distanceToBlock <= $approachDistance) {
            $mob->setMotion(new Vector3(
                $direction->x * 0.2,
                $jumpForce,
                $direction->z * 0.2
            ));
        }
        return;
    }

    // ✅ 2블록 점프: 조금씩 점프하면서 올라오기
    if ($heightDiff > 1.2 && $heightDiff <= 2.2 && $mob->isOnGround()) {
        $jumpForce = 0.62;
        $approachDistance = 0.5; // ✅ 좀 더 멀리서 점프
        $distanceToBlock = $position->distance($frontBlockPos);

        if ($distanceToBlock <= $approachDistance) {
            $mob->setMotion(new Vector3(
                $direction->x * 0.2,
                $jumpForce,
                $direction->z * 0.2
            ));
        }
        return;
    }

    // ✅ 점프 중 수평 이동 관성 유지
    if (!$mob->isOnGround()) {
        $horizontalSpeed = 0.23;
        $mob->setMotion(new Vector3(
            $motion->x * 0.95,
            $motion->y,
            $motion->z * 0.95
        ));
    }

    // ✅ 자연스럽게 내려오기 (중력 적용)
    if ($heightDiff < 0 && !$mob->isOnGround()) {
        $fallSpeed = max($motion->y - 0.08, -0.5);
        $mob->setMotion(new Vector3(
            $motion->x,
            $fallSpeed,
            $motion->z
        ));
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

    private function isEdgeOfBlock(Vector3 $position, Vector3 $frontBlockPos): bool {
        // ✅ 블록 모서리 정중앙인지 확인
        $xDiff = abs($position->x - $frontBlockPos->x);
        $zDiff = abs($position->z - $frontBlockPos->z);

        // 모서리 정중앙이라면 true 반환
        return ($xDiff > 0.4 && $xDiff < 0.6) || ($zDiff > 0.4 && $zDiff < 0.6);
    }
}
