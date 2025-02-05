<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\entity\Living;
use pocketmine\entity\Creature;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;

class MobAITask extends Task {
    private Main $plugin;
    private ?AIModel $aiModel = null;
    private bool $useAI;
    private int $updateInterval;
    private int $tickCounter = 0;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->useAI = $plugin->getConfig()->get("use_ai_model", false);
        $this->updateInterval = $plugin->getConfig()->get("ai_update_interval", 100); // 20에서 100으로 변경

        if ($this->useAI) {
            $this->aiModel = new AIModel();
        }
    }

    public function onRun(): void {
        $this->tickCounter++;
        if ($this->tickCounter >= $this->updateInterval) {
            $this->tickCounter = 0;
            foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
                $entities = $world->getEntities();
                $chunks = array_chunk($entities, 10); // 엔티티를 10개씩 배치 처리
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $entity) {
                        if ($entity instanceof Creature) {
                            $this->handleMobAI($entity);
                        }
                    }
                }
            }
        }
    }

    public function handleMobAI(Living $mob): void {
        $start = $mob->getPosition();
        $goal = $this->findNearestPlayer($mob);

        if ($goal === null) {
            $this->moveRandomly($mob);
            return;
        }

        if (rand(0, 10) < 3) { // 비동기 작업 제출을 랜덤하게 줄임
            $grid = $this->createGrid($mob->getWorld());
            $algorithm = $this->selectAlgorithm();
            $task = new PathfindingTask($start->getX(), $start->getY(), $start->getZ(), $goal->getPosition()->getX(), $goal->getPosition()->getY(), $goal->getPosition()->getZ(), $mob->getId(), $algorithm, $mob->getWorld()->getDisplayName());
            $this->plugin->getServer()->getAsyncPool()->submitTask($task);
        } else {
            $this->moveRandomly($mob); // 대신 무작위 이동
        }

        if ($this->useAI && $this->aiModel !== null) {
            $state = $this->getState($mob);
            $action = $this->aiModel->chooseAction($state);
            switch ($action) {
                case 0: $this->moveRandomly($mob); break;
                case 1: $this->moveToPlayer($mob, $goal); break;
                case 2: $this->attackPlayer($mob); break;
                case 3: $this->retreat($mob); break;
                case 4: $this->jump($mob); break;
            }

            $nextState = $this->getState($mob);
            $reward = $this->getReward($mob, $action);
            $this->aiModel->learn($state, $action, $reward, $nextState);
        } else {
            $this->moveRandomly($mob);
        }
    }

    private function getState(Living $mob): int {
        return 0; // TODO: AI 모델에서 상태 학습 구현 필요
    }

    private function getReward(Living $mob, int $action): float {
        return 1.0; // TODO: AI 학습용 보상 시스템 추가
    }

    /** ✅ 가장 가까운 플레이어 찾기 */
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

 

    /** ✅ `PathfindingTask`를 사용하여 플레이어에게 이동 */
    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $direction = new Vector3(
            $playerPos->getX() - $mobPos->getX(),
            0,
            $playerPos->getZ() - $mobPos->getZ()
        );

        $motion = $direction->normalize()->multiply(0.15);
        $mob->setMotion($motion);
        $mob->lookAt($playerPos);
    }

    /** ✅ 장애물 감지 후 점프 */
    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $directionVector = $mob->getLocation()->getDirectionVector();
        $frontPosition = $position->add($directionVector->getX(), 0, $directionVector->getZ());

        $blockInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y, (int) $frontPosition->z);
        $blockAboveInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y + 1, (int) $frontPosition->z);

        if ($blockInFront !== null && !$blockInFront->isTransparent() && $blockAboveInFront !== null && $blockAboveInFront->isTransparent()) {
            $this->jump($mob);
        }
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }

    public function jump(Living $mob): void {
        $jumpForce = 0.6;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }

    private function createGrid(World $world): array {
        return []; // TODO: 실제 맵 데이터 기반 경로 탐색 구현
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }

    private function attackPlayer(Creature $mob): void {
        // TODO: 몬스터 공격 로직 추가
    }

    private function retreat(Creature $mob): void {
        // TODO: 후퇴 로직 추가
    }
}
