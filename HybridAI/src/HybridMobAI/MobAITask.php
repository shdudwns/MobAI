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

        // 관성 동적 조절 ▼
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

    // ✅ 착지 후 최근 3틱 이내면 점프 우선 감지
    $recentlyLanded = isset($this->landedTick[$mobId]) && ($currentTick - $this->landedTick[$mobId] <= 3);

    $yaw = $mob->getLocation()->yaw;
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

    // ✅ 앞으로 이동할 블록 위치 계산 (앞쪽 1~2칸 감지)
    for ($i = 1; $i <= 2; $i++) {
        $frontPosition = $position->add(
            $directionVector->getX() * $i,
            0,
            $directionVector->getZ() * $i
        );

        $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
        $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());

        // ✅ 장애물 높이 차이 계산
        $currentHeight = (int)floor($position->getY());
        $blockHeight = (int)floor($blockInFront->getPosition()->getY());
        $heightDiff = $blockHeight - $currentHeight;

        // ✅ 착지 후 3틱 이내이거나 정상적인 장애물이 있을 때 점프 수행
        if (($recentlyLanded || $this->isClimbable($blockInFront)) && $blockAboveInFront->isTransparent()) {
            $this->jump($mob, $heightDiff);
            $this->landedTick[$mobId] = $currentTick; // 점프 시간 기록
            return;
        }
    }
}
    public function jump(Living $mob, float $heightDiff = 1.0): void {
    // ✅ 착지 후 즉시 점프할 수 있도록 설정
    if (!$mob->isOnGround()) {
        return;
    }

    $baseForce = 0.52;
    $jumpForce = $baseForce + ($heightDiff * 0.2);
    $jumpForce = min($jumpForce, 0.65); // ✅ 최대 점프 높이 제한

    $direction = $mob->getDirectionVector();
    $jumpBoost = 0.08;

    // ✅ 착지 후 점프 시 X/Z 방향 이동을 더 부드럽게 조정
    $mob->setMotion(new Vector3(
        $mob->getMotion()->x + ($direction->x * $jumpBoost),
        $jumpForce,
        $mob->getMotion()->z + ($direction->z * $jumpBoost)
    ));
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
