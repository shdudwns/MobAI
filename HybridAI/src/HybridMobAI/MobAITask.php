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
    private string $algorithm;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->algorithm = $this->selectAlgorithm();
        $this->plugin->getLogger()->info("🔹 사용 알고리즘: " . $this->algorithm);
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
        $speed = 0.23;
        if ($distance < 5) $speed *= $distance / 5;

        $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
        $currentMotion = $mob->getMotion();

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
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
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

        if (isset($this->landedTick[$mobId]) && $currentTick - $this->landedTick[$mobId] < 5) return;

        $yaw = $mob->getLocation()->yaw;
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        $frontPosition = $position->add($directionVector->multiply(1.1));

        $blockInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y, (int)$frontPosition->z);
        $blockAboveInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y + 1, (int)$frontPosition->z);

        if ($this->isClimbable($blockInFront) && $blockAboveInFront->isTransparent()) {
            $this->jump($mob, 1.0);
        }

        // ✅ 계단 감지 및 자동 등반 추가
        $stairBlock = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y - 1, (int)$frontPosition->z);
        if ($this->isStair($stairBlock)) {
            $this->jump($mob, 0.5);
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
        if ($mob->getMotion()->y < -0.08) {
            $mob->setMotion(new Vector3($mob->getMotion()->x, -0.08, $mob->getMotion()->z));
        }

        $baseForce = 0.5;
        $jumpForce = $baseForce + ($heightDiff * 0.15);
        $jumpForce = min($jumpForce, 0.75);

        if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
            $direction = $mob->getDirectionVector();
            $jumpBoost = 0.06;
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x + ($direction->x * $jumpBoost),
                $jumpForce,
                $mob->getMotion()->z + ($direction->z * $jumpBoost)
            ));
        }
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }

    private function isClimbable(Block $block): bool {
        return $block->isSolid();
    }

    private function isStair(Block $block): bool {
        $stairBlocks = [
            "pocketmine:block:stairs",
            "pocketmine:block:stone_stairs",
            "pocketmine:block:wooden_stairs"
        ];
        return in_array($block->getName(), $stairBlocks);
    }
}
