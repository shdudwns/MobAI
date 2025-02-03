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
    private $updateInterval;
    private $tickCounter = 0;

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
            $this->plugin->getLogger()->info("AI 작업 실행 중");
            foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
                foreach ($world->getEntities() as $entity) {
                    if ($entity instanceof Creature) {
                        $this->plugin->getLogger()->info("AI 처리 중: " . $entity->getName() . " (" . $entity->getId() . ")");
                        $this->handleMobAI($entity);
                    }
                }
            }
        }
    }

    private function handleMobAI(Creature $mob): void {
        $this->plugin->getLogger()->info("몬스터 AI 처리 시작: " . $mob->getName() . " (" . $mob->getId() . ")");
        $start = $mob->getPosition();
        $goal = $this->findClosestPlayerPosition($mob);
        $grid = $this->createGrid($mob->getWorld());

        $algorithm = $this->selectAlgorithm();
        $task = new PathfindingTask($start, $goal, $grid, $mob->getId(), $algorithm);
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
        $this->plugin->getLogger()->info("AI 모델 사용 여부: " . ($this->useAI ? "예" : "아니오"));

        if ($this->useAI) {
            $state = $this->getState($mob);
            $this->plugin->getLogger()->info("현재 상태: " . $state);
            $action = $this->aiModel->chooseAction($state);
            $this->plugin->getLogger()->info("선택된 행동: " . $action);

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
            $this->plugin->getLogger()->info("다음 상태: " . $next_state . ", 보상: " . $reward);
            $this->aiModel->learn($state, $action, $reward, $next_state);
        } else {
            $this->moveRandomly($mob);
        }
    }

    private function getState(Creature $mob): int {
        // 몹의 상태를 나타내는 정수 반환 (예: 플레이어와의 거리 등)
        return 0; // 예시를 위해 기본값 0 반환
    }

    private function getReward(Creature $mob, int $action): float {
        // 행동에 따른 보상 반환 (예: 플레이어에게 데미지를 주면 높은 보상)
        return 1.0; // 예시를 위해 기본값 1.0 반환
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
        $this->plugin->getLogger()->info("플레이어에게 이동 중: " . $mob->getName());
        // 플레이어에게 이동하는 로직
    }

    private function attackPlayer(Creature $mob): void {
        $this->plugin->getLogger()->info("플레이어 공격 중: " . $mob->getName());
        // 플레이어를 공격하는 로직
    }

    private function retreat(Creature $mob): void {
        $this->plugin->getLogger()->info("후퇴 중: " . $mob->getName());
        // 후퇴하는 로직
    }

    private function jump(Creature $mob): void {
        $this->plugin->getLogger()->info("점프 중: " . $mob->getName());
        $jumpForce = 0.5;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }

    private function findClosestPlayerPosition(Creature $mob) {
        $this->plugin->getLogger()->info("가장 가까운 플레이어 위치 찾기 중: " . $mob->getName());
        // 가장 가까운 플레이어의 위치 찾기
        return new Vector3(0, 0, 0); // 예시를 위해 기본값 반환
    }

    private function createGrid($world) {
        $this->plugin->getLogger()->info("그리드 생성 중");
        // 월드로부터 그리드 생성
        return []; // 예시를 위해 빈 배열 반환
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        $selectedAlgorithm = $algorithms[array_rand($algorithms)];
        $this->plugin->getLogger()->info("선택된 알고리즘: " . $selectedAlgorithm);
        return $selectedAlgorithm;
    }
}
