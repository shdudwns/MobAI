<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\entity\Entity;
use pocketmine\entity\Creature;
use pocketmine\math\Vector3;
use pocketmine\Server;

class MobAITask extends Task {
    private $plugin;
    private $aiModel;
    private $useAI;
    private $updateInterval; // 업데이트 간격
    private $tickCounter = 0; // 틱 카운터

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->useAI = $plugin->getConfig()->get("use_ai_model", true);
        $this->updateInterval = $plugin->getConfig()->get("ai_update_interval", 20); // 기본값 20 ticks (1초)
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
                        $this->plugin->getLogger()->info("AI 처리 중: " . $entity->getName());
                        $this->handleMobAI($entity);
                    }
                }
            }
        }
    }

    private function handleMobAI(Creature $mob): void {
        $this->plugin->getLogger()->info("몬스터 AI 처리 시작: " . $mob->getName());
        $start = $mob->getPosition();
        $goal = $this->findClosestPlayerPosition($mob);
        $grid = $this->createGrid($mob->getWorld());

        $algorithm = $this->selectAlgorithm();
        $task = new PathfindingTask($start, $goal, $grid, $mob->getId(), $algorithm);
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);

        if ($this->useAI) {
            $state = $this->getState($mob);
            $action = $this->aiModel->chooseAction($state);

            switch ($action) {
                case 0:
                    $this->moveRandomly($mob);
                    break;
                case 1:
                    $this->moveToPlayer($mob);
                    break;
                case 2:
                    $this->attackPlayer($mob);
                    break;
                case 3:
                    $this->retreat($mob);
                    break;
                case 4:
                    $this->jump($mob);
                    break;
            }

            $next_state = $this->getState($mob);
            $reward = $this->getReward($mob, $action);
            $this->aiModel->learn($state, $action, $reward, $next_state);
        } else {
            $this->moveRandomly($mob); // 랜덤 이동
        }
    }

    private function getState(Creature $mob): int {
        // 몹의 상태를 나타내는 정수 반환 (예: 플레이어와의 거리 등)
    }

    private function getReward(Creature $mob, int $action): float {
        // 행동에 따른 보상 반환 (예: 플레이어에게 데미지를 주면 높은 보상)
    }

    private function moveRandomly(Creature $mob): void {
        $this->plugin->getLogger()->info("랜덤 이동 중: " . $mob->getName());
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->move($randomDirection->multiply(0.25 + mt_rand(0, 10) / 100));
        $mob->setRotation(mt_rand(0, 360), mt_rand(-90, 90));
    }

    private function moveToPlayer(Creature $mob): void {
        // 플레이어에게 이동하는 로직
    }

    private function attackPlayer(Creature $mob): void {
        // 플레이어를 공격하는 로직
    }

    private function retreat(Creature $mob): void {
        // 후퇴하는 로직
    }

    private function jump(Creature $mob): void {
        $jumpForce = 0.5;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }

    private function findClosestPlayerPosition(Creature $mob) {
        // 가장 가까운 플레이어의 위치 찾기
    }

    private function createGrid($world) {
        // 월드로부터 그리드 생성
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }
}
