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
    private array $pathfindingTasks = [];

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

    // 착지 상태 감지 ▼
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
        $worldName = $mob->getWorld()->getFolderName();

        $callback = function (Creature $mob, ?array $path) {
            if ($path === null) {
                $this->moveRandomly($mob);
            } else {
                $this->followPath($mob, $path);
            }
        };

        $task = new PathfindingTask($mobPos, $playerPos, null, $mob->getId(), "AStar", $worldName, $callback);
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
        $this->pathfindingTasks[$mob->getId()] = $task;
    }

    private function followPath(Zombie $mob, array $path): void {
        if (empty($path)) {
            return;
        }

        $nextStep = array_shift($path);
        $mob->lookAt($nextStep);
        $mob->setMotion($nextStep->subtractVector($mob->getPosition())->normalize()->multiply(0.25));

        if (!empty($path)) {
            $this->path[$mob->getId()] = $path;
        }
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

        // 5틱(0.25초)마다 검사 ▼
        if (isset($this->landedTick[$mobId]) && $currentTick - $this->landedTick[$mobId] < 5) return;

        $yaw = $mob->getLocation()->yaw;
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        $leftVector = new Vector3(-$directionVector->z, 0, $directionVector->x);
        $rightVector = new Vector3($directionVector->z, 0, -$directionVector->x);

        $leftBlock = $world->getBlockAt((int)floor($position->x + $leftVector->x), (int)$position->y, (int)floor($position->z + $leftVector->z));
        $rightBlock = $world->getBlockAt((int)floor($position->x + $rightVector->x), (int)$position->y, (int)floor($position->z + $rightVector->z));

        if ($leftBlock->isSolid() && $rightBlock->isSolid()) return;

        $maxJumpDistance = 1.2;
        for ($i = 0; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                $frontBlockX = (int)floor($position->x + $directionVector->x * $i + $leftVector->x * $j);
                $frontBlockY = (int)$position->y;
                $frontBlockZ = (int)floor($position->z + $directionVector->z * $i + $leftVector->z * $j);

                $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
                $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);
                $frontBlockBelow = $world->getBlockAt($frontBlockX, $frontBlockY - 1, $frontBlockZ);

                $blockHeight = $frontBlock->getPosition()->y + 0.5;
                $heightDiff = $blockHeight - $position->y;

                if ($heightDiff < 0 || $frontBlockBelow->isTransparent()) continue;

                $blockCenterX = $frontBlockX + 0.5;
                $blockCenterZ = $frontBlockZ + 0.5;
                $dx = $blockCenterX - $position->x;
                $dz = $blockCenterZ - $position->z;
                $distance = sqrt($dx * $dx + $dz * $dz);

                // 착지 직후 점프 우선권 ▼
                $isJustLanded = isset($this->landedTick[$mobId]) 
                             && ($currentTick - $this->landedTick[$mobId] <= 2);

                if ($this->isClimbable($frontBlock) 
                    && $frontBlockAbove->isTransparent() 
                    && $distance <= $maxJumpDistance 
                    && ($isJustLanded || $heightDiff <= 1.2)
                ) {
                    $this->jump($mob, $heightDiff);
                    $this->landedTick[$mobId] = $currentTick; // 점프 시간 기록
                    return;
                }
            }
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
        // 낙하 속도 리셋 ▼
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
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x + ($direction->x * $jumpBoost),
                $jumpForce,
                $mob->getMotion()->z + ($direction->z * $jumpBoost)
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
