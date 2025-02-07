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
    private array $lastPathUpdate = [];
    private string $algorithm;
    private array $path = []; // 몬스터별 경로 저장
    private array $pathIndex = []; // 몬스터별 현재 경로 인덱스
    

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
            $currentTime = microtime(true);
            if (!isset($this->lastPathUpdate[$mob->getId()]) || ($currentTime - $this->lastPathUpdate[$mob->getId()]) >= 1) {
                $this->lastPathUpdate[$mob->getId()] = $currentTime;

                $this->plugin->getServer()->getAsyncPool()->submitTask(
                    new PathfinderTask(
                        $mob->getPosition()->x, $mob->getPosition()->y, $mob->getPosition()->z,
                        $nearestPlayer->getPosition()->x, $nearestPlayer->getPosition()->y, $nearestPlayer->getPosition()->z,
                        $mob->getId(), $this->algorithm, $mob->getWorld()->getFolderName()
                    )
                );
            }

            if (isset($this->path[$mob->getId()]) && !empty($this->path[$mob->getId()])) {
                $this->moveAlongPath($mob);
            }

        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
    }


    public function setPath(int $mobId, ?array $path): void {
        $this->path[$mobId] = $path;
        $this->pathIndex[$mobId] = 0; // 경로 시작 인덱스 초기화
    }

    private function moveAlongPath(Zombie $mob): void {
        $mobId = $mob->getId();
        $path = $this->path[$mobId];
        $index = $this->pathIndex[$mobId];

        if ($index < count($path) - 1 && $path[$index] instanceof Vector3 && $path[$index + 1] instanceof Vector3) {
            $currentPos = $mob->getPosition();
            $nextPos = $path[$index + 1];

            $diffX = $nextPos->x - $currentPos->x;
            $diffZ = $nextPos->z - $currentPos->z;

            $distance = sqrt($diffX ** 2 + $diffZ ** 2);

            if ($distance > 0.1) { // 0.1은 이동 오차 범위
                $motion = $nextPos->subtractVector($currentPos)->normalize()->multiply(0.15);
                if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                    $mob->setMotion($motion);
                    $mob->lookAt($nextPos); // 몹 바라보게 하기
                }
            } else {
                $this->pathIndex[$mobId]++; // 다음 경로 지점으로 이동
                if ($this->pathIndex[$mobId] >= count($path) - 1) {
                    unset($this->path[$mobId]);
                    unset($this->pathIndex[$mobId]);
                }
            }
        } else {
            unset($this->path[$mobId]);
            unset($this->pathIndex[$mobId]);
        }
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

    private function usePathfinder(Zombie $mob, Player $player): void {
    $start = $mob->getPosition();
    $goal = $player->getPosition();
    $worldName = $mob->getWorld()->getFolderName();

    $pathfinderTask = new PathfinderTask(
        $start->x, $start->y, $start->z,
        $goal->x, $goal->y, $goal->z,
        $mob->getId(), $this->algorithm, $worldName
    );

    $this->plugin->getServer()->getAsyncPool()->submitTask($pathfinderTask);
}

    public function moveRandomly(Living $mob): void {
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

        $frontPosition = $position->addVector($directionVector->multiply(1.1));

        $blockInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y, (int)$frontPosition->z);
        $blockAboveInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y + 1, (int)$frontPosition->z);

        if ($this->isClimbable($blockInFront) && $blockAboveInFront->isTransparent()) {
            $this->jump($mob, 1.0);
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
}
