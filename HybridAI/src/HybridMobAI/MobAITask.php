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
    private array $path = []; // 경로 저장 배열 추가
    private array $pathfindingTasks = []; // PathfindingTask 저장 배열 추가
    private string $algorithm;
    private array $lastPathUpdate = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->algorithm = $this->selectAlgorithm();
        $this->plugin->getLogger()->info(" 사용 알고리즘: " . $this->algorithm);
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
            if (!isset($this->lastPathUpdate[$mob->getId()]) || (microtime(true) - $this->lastPathUpdate[$mob->getId()]) > 1) {
                $this->lastPathUpdate[$mob->getId()] = microtime(true);

                // *** 핵심: 콜백 함수 정의 및 $this 복사 ***
                $plugin = $this->plugin; // $this->plugin 복사
                $mobAITaskInstance = $this; // $this 복사 (전체 인스턴스)
                $mobId = $mob->getId();
                $worldName = $mob->getWorld()->getFolderName();


                $callback = function (Creature $entity, ?array $path) use ($plugin, $mobAITaskInstance, $mobId, $worldName) {
                    $server = Server::getInstance();
                    $world = $server->getWorldManager()->getWorldByName($worldName);

                    if ($world === null) {
                        $plugin->getLogger()->warning("World {$worldName} not found in callback!");
                        return; // 중요: 월드가 없으면 리턴 필수!
                    }

                    $entity = $world->getEntity($mobId); // 메인 스레드에서 엔티티 가져오기

                    if ($entity instanceof Creature) { // 엔티티가 유효한지 확인
                        if ($path === null) {
                            $mobAITaskInstance->moveRandomly($entity); // 복사된 $this 사용
                        } else {
                            $mobAITaskInstance->path[$entity->getId()] = $path; // 복사된 $this 사용
                        }
                    }
                };

                $task = new PathfinderTask(
                    $mob->getPosition()->x, $mob->getPosition()->y, $mob->getPosition()->z,
                    $nearestPlayer->getPosition()->x, $nearestPlayer->getPosition()->y, $nearestPlayer->getPosition()->z,
                    $mob->getId(), $this->algorithm, $mob->getWorld()->getFolderName(), $callback
                );

                $this->plugin->getServer()->getAsyncPool()->submitTask($task);
                $this->pathfindingTasks[$mob->getId()] = $task;
            }

            if (isset($this->path[$mob->getId()]) && !empty($this->path[$mob->getId()])) {
                $this->followPath($mob);
            }
        } else {
            $this->moveRandomly($mob);
        }

        $this->detectLanding($mob);
        $this->checkForObstaclesAndJump($mob);
    }



    private function followPath(Zombie $mob): void {
        if (!isset($this->path[$mob->getId()]) || empty($this->path[$mob->getId()])) {
            return; // 경로 없거나 비어있으면 종료
        }

        $path = $this->path[$mob->getId()];
        $nextStep = array_shift($path); // 다음 좌표 가져오기

        if ($nextStep instanceof Vector3) {
            $mob->lookAt($nextStep);
            $motion = $nextStep->subtractVector($mob->getPosition())->normalize()->multiply(0.15); // 이동 벡터 계산

            // NaN 값 체크 후 이동
            if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                $mob->setMotion($motion);
            }
        }

        if (empty($path)) {
            unset($this->path[$mob->getId()]); // 경로 완료 시 삭제
            unset($this->pathfindingTasks[$mob->getId()]); // 작업 완료 시 삭제
        } else {
            $this->path[$mob->getId()] = $path; // 남은 경로 업데이트
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
        $pathfinderTask = new PathfinderTask($start->x, $start->y, $start->z, $goal->x, $goal->y, $goal->z, $mob->getId(), $this->algorithm, $mob->getWorld()->getFolderName());

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
