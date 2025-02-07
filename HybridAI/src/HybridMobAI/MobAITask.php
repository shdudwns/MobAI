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
    $currentTick = Server::getInstance()->getTick();

    if ($isOnGround) {
        if (!isset($this->landedTick[$mobId]) || ($currentTick - $this->landedTick[$mobId]) > 3) {
            $this->landedTick[$mobId] = $currentTick;
        }
    }
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

    // ✅ 착지 후 3틱 이상 경과해야 점프 가능
    if (isset($this->landedTick[$mobId]) && ($currentTick - $this->landedTick[$mobId] < 3)) {
        return;
    }

    // ✅ 이동 방향 계산 (Yaw 값 기반)
    $yaw = $mob->getLocation()->yaw;
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

    // ✅ 앞으로 이동할 블록 위치 계산 (앞쪽 1~2칸 감지)
    for ($i = 1; $i <= 2; $i++) {
        $frontPosition = new Vector3(
            $position->getX() + ($directionVector->getX() * $i),
            $position->getY(),
            $position->getZ() + ($directionVector->getZ() * $i)
        );

        $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
        $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());

        // ✅ 장애물 높이 차이 계산
        $currentHeight = (int)floor($position->getY());
        $blockHeight = (int)floor($blockInFront->getPosition()->getY());
        $heightDiff = $blockHeight - $currentHeight;

        // ✅ 높이 차이가 `0.25 이상`일 때 점프 수행 (더 민감하게 반응)
        if ($heightDiff >= 0.25 && ($this->isClimbable($blockInFront) || $blockAboveInFront->isTransparent())) {
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

    $baseForce = 0.55;
    $jumpForce = $baseForce + ($heightDiff * 0.2);
    $jumpForce = min($jumpForce, 0.75); // ✅ 최대 점프 높이 증가

    $direction = $mob->getDirectionVector();
    $jumpBoost = 0.06; // ✅ X/Z 이동을 더 부드럽게 만듦

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
    
    // ✅ 계단, 반블록, 눈 등 낮은 블록도 고려
    return in_array($block->getName(), $climbableBlocks) || !$block->isTransparent();
}
}
