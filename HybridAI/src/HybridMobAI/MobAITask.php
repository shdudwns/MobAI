<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $hasLanded = [];
    private array $landedTick = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) return;

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
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

        $this->detectLanding($mob);
        $this->checkForObstaclesAndJump($mob);
    }

    private function detectLanding(Living $mob): void {
        $mobId = $mob->getId();
        $isOnGround = $mob->isOnGround();

        if (!isset($this->hasLanded[$mobId]) && $isOnGround) {
            $this->landedTick[$mobId] = Server::getInstance()->getTick();
        }
        $this->hasLanded[$mobId] = $isOnGround;
    }

    private function findNearestPlayer(Zombie $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX;
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distance($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $distance = $mobPos->distance($playerPos);
        $speed = 0.3;
        if ($distance < 5) $speed *= $distance / 5;

        $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
        $currentMotion = $mob->getMotion();

        // 관성 동적 조절
        $inertiaFactor = ($distance < 3) ? 0.1 : 0.2;
        $blendedMotion = new Vector3(
            ($currentMotion->x * $inertiaFactor) + ($motion->x * (1 - $inertiaFactor)),
            $currentMotion->y,
            ($currentMotion->z * $inertiaFactor) + ($motion->z * (1 - $inertiaFactor))
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    private function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->x * 0.8) + ($randomDirection->x * 0.2),
            $currentMotion->y,
            ($currentMotion->z * 0.8) + ($randomDirection->z * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $currentTick = Server::getInstance()->getTick();
        $mobId = $mob->getId();

        // 5틱(0.25초)마다 검사
        if (isset($this->landedTick[$mobId]) && $currentTick - $this->landedTick[$mobId] < 5) return;

        $yaw = $mob->getLocation()->yaw;
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        $maxJumpDistance = 1.5; // 점프 거리 조정
        for ($i = 0; $i <= 1; $i++) {
            $frontBlockX = (int)floor($position->x + $directionVector->x * $i);
            $frontBlockY = (int)$position->y;
            $frontBlockZ = (int)floor($position->z + $directionVector->z * $i);

            $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
            $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);
            $frontBlockBelow = $world->getBlockAt($frontBlockX, $frontBlockY - 1, $frontBlockZ);

            $heightDiff = $frontBlock->getPosition()->y + 0.5 - $position->y;

            // 블록 아래가 투명한지 확인하여 점프 방지
            if ($frontBlockBelow->isTransparent() && $heightDiff <= 0) {
                return; // 블록 아래가 투명하면 점프하지 않음
            }

            // 점프 조건을 유연하게 조정하여 가장자리에서도 점프 가능
            if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
                // 장애물의 높이가 몬스터의 점프 높이보다 낮다면 점프
                if ($heightDiff <= 1.5 && $heightDiff > 0) {
                    $this->jump($mob, $heightDiff);
                    $this->landedTick[$mobId] = $currentTick; // 점프 시간 기록
                    return;
                }
            }

            // 계단 로직 추가
            if ($frontBlock->getId() === Block::STEPS || $frontBlock->getId() === Block::DOUBLE_STEPS) {
                if ($heightDiff <= 1.2) {
                    $this->stepUp($mob);
                    return;
                }
            }
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
        // 낙하 속도 리셋
        if ($mob->getMotion()->y < -0.08) {
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x,
                -0.08,
                $mob->getMotion()->z
            ));
        }

        $baseForce = 0.52;
        $jumpForce = $baseForce + ($heightDiff * 0.15);
        $jumpForce = min($jumpForce, 0.65);

        if (($mob->isOnGround() || $mob->getMotion()->y <= 0.1)) {
            $direction = $mob->getDirectionVector();
            $jumpBoost = 0.08;

            // 점프 시 수평 속도 유지
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x * 0.5 + ($direction->x * $jumpBoost),
                $jumpForce,
                $mob->getMotion()->z * 0.5 + ($direction->z * $jumpBoost)
            ));
        }
    }

    private function stepUp(Living $mob): void {
        // 계단을 올라가는 로직
        if ($mob->isOnGround()) {
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x,
                0.4, // 계단 위로 올라갈 때의 Y 방향 속도
                $mob->getMotion()->z
            ));
        }
    }
    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:snow_layer",
            "pocketmine:block:fence",
            "pocketmine:block:glass",
            "pocketmine:block:frame"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }
}
