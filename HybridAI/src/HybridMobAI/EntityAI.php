<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use pocketmine\plugin\PluginBase;

class EntityAI {
    private bool $enabled = false;
    private array $path = [];
    private ?Vector3 $target = null;
    private array $entityPaths = [];
    private PluginBase $plugin;

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
    }

    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPathAsync(World $world, Position $start, Position $goal, string $algorithm, callable $callback): void {
        $worldName = $world->getFolderName();
        $startX = $start->x;
        $startY = $start->y;
        $startZ = $start->z;
        $goalX = $goal->x;
        $goalY = $goal->y;
        $goalZ = $goal->z;

        // 필요한 데이터 캡처 (개선)
        $capturedWorldName = $worldName;
        $capturedStartX = $startX;
        $capturedStartY = $startY;
        $capturedStartZ = $startZ;
        $capturedGoalX = $goalX;
        $capturedGoalY = $goalY;
        $capturedGoalZ = $goalZ;
        $capturedAlgorithm = $algorithm;

        // 콜백 클로저 개선 (캡처한 데이터 직접 사용)
        $callbackFunction = function($result) use ($capturedWorldName, $capturedStartX, $capturedStartY, $capturedStartZ, $capturedGoalX, $capturedGoalY, $capturedGoalZ, $capturedAlgorithm, $callback) {
            $plugin = Server::getInstance()->getPluginManager()->getPlugin("HybridAI");
            if ($plugin instanceof Main) {
                $entityAI = $plugin->getEntityAI();
                $world = Server::getInstance()->getWorldManager()->getWorldByName($capturedWorldName);  // 캡처한 worldName 사용
                if ($world instanceof World) {
                    $startPos = new Position($capturedStartX, $capturedStartY, $capturedStartZ, $world); // 캡처한 좌표 사용
                    $goalPos = new Position($capturedGoalX, $capturedGoalY, $capturedGoalZ, $world); // 캡처한 좌표 사용

                    // 타입 힌팅 추가 (개선)
                    $callback($result, $entityAI, $world, $startPos, $goalPos, $capturedAlgorithm);
                } else {
                    // 월드 로드 실패 처리 (오류 처리 추가)
                    $callback(null, null, null, null, null, $capturedAlgorithm); // 예시: null 전달
                }
            } else {
                // 플러그인 로드 실패 처리 (오류 처리 추가)
                $callback(null, null, null, null, null, $capturedAlgorithm); // 예시: null 전달
            }
        };

        // 원시 데이터만 사용하여 비동기 작업 생성
        $task = new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callbackFunction) extends AsyncTask {
            private string $worldName;
            private float $startX;
            private float $startY;
            private float $startZ;
            private float $goalX;
            private float $goalY;
            private float $goalZ;
            private string $algorithm;
            private \Closure $callback;

            public function __construct(
                string $worldName,
                float $startX, float $startY, float $startZ,
                float $goalX, float $goalY, float $goalZ,
                string $algorithm,
                \Closure $callback
            ) {
                $this->worldName = $worldName;
                $this->startX = $startX;
                $this->startY = $startY;
                $this->startZ = $startZ;
                $this->goalX = $goalX;
                $this->goalY = $goalY;
                $this->goalZ = $goalZ;
                $this->algorithm = $algorithm;
                $this->callback = $callback;
            }

            public function onRun(): void {
                $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
                if (!$world instanceof World) {
                    $this->setResult(null);
                    return;
                }

                $pathfinder = new Pathfinder();
                $start = new Vector3($this->startX, $this->startY, $this->startZ);
                $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

                switch ($this->algorithm) {
                    case "A*":
                        $path = $pathfinder->findPathAStar($world, $start, $goal);
                        break;
                    case "Dijkstra":
                        $path = $pathfinder->findPathDijkstra($world, $start, $goal);
                        break;
                    case "Greedy":
                        $path = $pathfinder->findPathGreedy($world, $start, $goal);
                        break;
                    case "BFS":
                        $path = $pathfinder->findPathBFS($world, $start, $goal);
                        break;
                    case "DFS":
                        $path = $pathfinder->findPathDFS($world, $start, $goal);
                        break;
                    default:
                        $path = null; // 알 수 없는 알고리즘
                }

                $this->setResult($path);
            }

            public function onCompletion(): void {
                if (isset($this->callback)) {
                    ($this->callback)($this->getResult());
                }
            }
        };

        Server::getInstance()->getAsyncPool()->submitTask($task);
    }


    public function findPath(World $world, Vector3 $start, Vector3 $goal, string $algorithm): ?array {
        $pathfinder = new Pathfinder();

        switch ($algorithm) {
            case "A*":
                return $pathfinder->findPathAStar($world, $start, $goal);
            case "Dijkstra":
                return $pathfinder->findPathDijkstra($world, $start, $goal);
            case "Greedy":
                return $pathfinder->findPathGreedy($world, $start, $goal);
            case "BFS":
                return $pathfinder->findPathBFS($world, $start, $goal);
            case "DFS":
                return $pathfinder->findPathDFS($world, $start, $goal);
            default:
                return null;
        }
    }

    public function setPath(Living $mob, array $path): void {
        $this->entityPaths[$mob->getId()] = $path;
    }

    public function hasPath(Living $mob): bool {
        return isset($this->entityPaths[$mob->getId()]);
    }

    public function moveAlongPath(Living $mob): void {
        if (!isset($this->entityPaths[$mob->getId()]) || empty($this->entityPaths[$mob->getId()])) {
            return;
        }

        $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
        if ($nextPosition instanceof Vector3) {
            $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
            $mob->lookAt($nextPosition);
        }
    }
}
