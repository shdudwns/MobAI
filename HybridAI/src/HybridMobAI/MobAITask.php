<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\entity\Zombie; // Zombie 클래스 임포트

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $isJumping = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) { // Zombie 엔티티만 처리
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
    }

    // ... (findNearestPlayer, moveToPlayer, moveRandomly 함수는 동일)

    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $entityId = $mob->getId();

        if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
            if ($mob->isOnGround()) {
                $this->isJumping[$entityId] = false;
            }
            return;
        }

        $position = $mob->getPosition();
        $world = $mob->getWorld();

        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        // 대각선 이동 감지 제거 (모서리 문제의 원인)

        // 좌우 블록 감지 로직 개선 (더 정확한 충돌 감지)
        $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
        $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

        $leftBlock = $world->getBlockAt($position->add($leftVector)->floor());
        $rightBlock = $world->getBlockAt($position->add($rightVector)->floor());

        if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
            return;
        }


        // 앞 블록 감지 로직 개선 (높이차 고려, 1칸만 확인)
        $frontBlockPos = $position->add($directionVector)->floor();
        $frontBlock = $world->getBlockAt($frontBlockPos);
        $frontBlockAbove = $world->getBlockAt($frontBlockPos->add(0, 1, 0));

        $currentHeight = (int)floor($position->getY());
        $blockHeight = (int)floor($frontBlock->getPosition()->getY());
        $heightDiff = $blockHeight - $currentHeight;

        // 내려가는 상황 감지 및 점프 방지
        if ($heightDiff < 0) {
            return;
        }

        if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
            $this->jump($mob, $heightDiff);
            $this->isJumping[$entityId] = true;
            return;
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
        $jumpForce = min(0.6 + ($heightDiff * 0.2), 1.0);

        if (!$mob->isOnGround()) {
            return;
        }

        $mob->setMotion(new Vector3(
            $mob->getMotion()->getX(),
            $jumpForce,
            $mob->getMotion()->getZ()
        ));
    }
}
