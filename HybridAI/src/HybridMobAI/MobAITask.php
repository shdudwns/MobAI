<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\entity\Creature;
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
        $this->useAI = $plugin->getConfig()->get("use_ai_model", true);
        $this->updateInterval = $plugin->getConfig()->get("ai_update_interval", 20);

        if ($this->useAI) {
            $this->aiModel = new AIModel();
        }
    }

    public function onRun(): void {
        $this->tickCounter++;
        if ($this->tickCounter >= $this->updateInterval) {
            $this->tickCounter = 0;
            foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
                foreach ($world->getEntities() as $entity) {
                    if ($entity instanceof Creature) {
                        $this->handleMobAI($entity);
                    }
                }
            }
        }
    }

    private function handleMobAI(Creature $mob): void {
        $start = $mob->getPosition();
        $goal = $this->findClosestPlayerPosition($mob);

        if ($goal === null) {
            $this->moveRandomly($mob);
            return;
        }

        $grid = $this->createGrid($mob->getWorld());
        $algorithm = $this->selectAlgorithm();
        $task = new PathfindingTask($start, $goal, $mob->getId(), $algorithm);
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);

        if ($this->useAI && $this->aiModel !== null) {
            $state = $this->getState($mob);
            $action = $this->aiModel->chooseAction($state);
            switch ($action) {
                case 0: $this->moveRandomly($mob); break;
                case 1: $this->moveToPlayer($mob); break;
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

    private function getState(Creature $mob): int {
        return 0; // TODO: AI 모델에서 상태 학습 구현 필요
    }

    private function getReward(Creature $mob, int $action): float {
        return 1.0; // TODO: AI 학습용 보상 시스템 추가
    }

    private function moveRandomly(Creature $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }

    private function moveToPlayer(Creature $mob): void {
        $playerPosition = $this->findClosestPlayerPosition($mob);
        if ($playerPosition === null) return;

        $direction = $playerPosition->subtract($mob->getPosition())->normalize();
        $mob->setMotion($direction->multiply(0.2));
    }

    private function attackPlayer(Creature $mob): void {
        // TODO: 몬스터 공격 로직 추가
    }

    private function retreat(Creature $mob): void {
        // TODO: 후퇴 로직 추가
    }

    private function jump(Creature $mob): void {
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), 0.6, $mob->getMotion()->getZ()));
    }

    private function findClosestPlayerPosition(Creature $mob): ?Vector3 {
        $nearestPlayer = null;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distanceSquared($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer !== null ? $nearestPlayer->getPosition() : null;
    }

    private function createGrid(World $world): array {
        return []; // TODO: 실제 맵 데이터 기반 경로 탐색 구현
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }
}
