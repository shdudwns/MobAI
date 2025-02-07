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

    // 항상 일정한 속도로 이동
    $speed = 0.2; // 속도를 일정하게 설정

    $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
    $currentMotion = $mob->getMotion();

    // 관성 동적 조절
    $inertiaFactor = 0.2; // 관성을 줄여서 부드럽게 이동하도록 설정
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

    for ($i = 1; $i <= 2; $i++) { // 2블록 거리까지 감지
        $frontBlockX = (int)floor($position->x + $directionVector->x * $i);
        $frontBlockY = (int)$position->y;
        $frontBlockZ = (int)floor($position->z + $directionVector->z * $i);

        $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
        $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);
        $frontBlockBelow = $world->getBlockAt($frontBlockX, $frontBlockY - 1, $frontBlockZ);

        $heightDiff = $frontBlock->getPosition()->y + 0.5 - $position->y;

        // 블록 아래가 투명하면 점프하지 않음
        if ($frontBlockBelow->isTransparent() && $heightDiff <= 0) {
            return;
        }

        // 작은 높이 차이는 무시 (부드러운 이동)
        if (abs($heightDiff) < 0.5) {
            continue;
        }

        // 점프 가능한 블록인지 확인
        if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
            if ($heightDiff <= 1.5 && $heightDiff > 0) {
                $this->jump($mob, $heightDiff);
                $this->landedTick[$mobId] = $currentTick;
                return;
            }
        }

        // 계단 로직 추가
        if ($frontBlock->getTypeId() === 43 || $frontBlock->getTypeId() === 44) { // 43: 계단, 44: 더블 계단
            if ($heightDiff <= 1.2 && $mob->isOnGround()) {
                $this->stepUp($mob); // 계단에 올라가는 로직
                return;
            }
        }

        // 벽 감지 후 방향 변경
        if ($frontBlock->isSolid() && $heightDiff > 1.5) {
            $this->changeDirection($mob);
            return;
        }
    }
}
    private function avoidFalling(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    
    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    
    if ($blockBelow->isTransparent()) {
        $this->changeDirection($mob);
    }
}
private function changeDirection(Living $mob): void {
    $randomYaw = mt_rand(0, 360); // 무작위 회전
    $mob->teleport($mob->getLocation()->setYaw($randomYaw));
}    
    public function jump(Living $mob, float $heightDiff = 1.0): void {
    // 낙하 속도 리셋 (너무 빠르게 낙하하지 않도록)
    if ($mob->getMotion()->y < -0.08) {
        $mob->setMotion(new Vector3(
            $mob->getMotion()->x,
            -0.08,
            $mob->getMotion()->z
        ));
    }

    // 기본 점프 힘 설정
    $baseJumpForce = 0.42; // 기본 점프력
    $extraJumpBoost = min(0.1 * $heightDiff, 0.3); // 높이에 따라 추가 점프력 조정

    $jumpForce = $baseJumpForce + $extraJumpBoost;
    
    if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
        $direction = $mob->getDirectionVector();
        $horizontalSpeed = 0.1; // 수평 이동 속도 추가

        $mob->setMotion(new Vector3(
            $mob->getMotion()->x * 0.5 + ($direction->x * $horizontalSpeed),
            $jumpForce,
            $mob->getMotion()->z * 0.5 + ($direction->z * $horizontalSpeed)
        ));
    }
}

    private function stepUp(Living $mob): void {
    if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
        $mob->setMotion(new Vector3(
            $mob->getMotion()->x,
            0.35, // 계단을 오를 때 자연스럽게 상승
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
