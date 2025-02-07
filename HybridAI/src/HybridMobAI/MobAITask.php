<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Zombie;
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
    private array $currentPaths = [];
    private array $lastPathUpdate = [];
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
                    $this->moveAlongPath($entity); // 이동 로직 호출
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);

        if ($nearestPlayer !== null) {
            // ✅ PathfinderTask를 20틱(1초)에 한 번만 실행
            if (!isset($this->lastPathUpdate[$mob->getId()]) || (microtime(true) - $this->lastPathUpdate[$mob->getId()]) > 1) {
                $this->lastPathUpdate[$mob->getId()] = microtime(true);
                
                // AsyncPool을 통해 PathfinderTask 제출
                $this->plugin->getServer()->getAsyncPool()->submitTask(
                    new PathfinderTask(
                        $mob->getPosition()->x, $mob->getPosition()->y, $mob->getPosition()->z,
                        $nearestPlayer->getPosition()->x, $nearestPlayer->getPosition()->y, $nearestPlayer->getPosition()->z,
                        $mob->getId(), $this->algorithm, $mob->getWorld()->getFolderName()
                    )
                );
            }
        } else {
            $this->moveRandomly($mob);
        }
    }

    private function moveAlongPath(Zombie $mob): void {
        $mobId = $mob->getId();

        if (isset($this->currentPaths[$mobId])) {
            $path = $this->currentPaths[$mobId];

            // 경로가 남아있으면
            if (count($path) > 0) {
                $nextStep = array_shift($path); // 다음 지점
                $mob->lookAt($nextStep); // 다음 지점을 바라보게 함
                
                // 현재 위치와 다음 지점의 차이를 계산하여 이동
                $motion = $nextStep->subtractVector($mob->getPosition())->normalize()->multiply(0.15);
                $mob->setMotion($motion); // 몬스터 이동

                // 경로 업데이트
                $this->currentPaths[$mobId] = $path;
            } else {
                // 경로가 끝나면 경로 초기화
                unset($this->currentPaths[$mobId]);
            }
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

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }
}
