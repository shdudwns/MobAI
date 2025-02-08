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
    private PluginBase $plugin;
    private array $enabledAlgorithms;
    private array $entityPaths = [];
    private array $targets = [];

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        // ✅ Config에서 활성화된 알고리즘 목록 가져오기
        $this->enabledAlgorithms = $plugin->getConfig()->get("pathfinding")["enabled_algorithms"] ?? ["A*"];
    }

    public function setTarget(Living $mob, Vector3 $target): void {
        $this->targets[$mob->getId()] = $target;
    }

    public function getTarget(Living $mob): ?Vector3 {
        return $this->targets[$mob->getId()] ?? null;
    }

    public function findPathAsync(World $world, Position $start, Position $goal, callable $callback): void {
        $worldName = $world->getFolderName();
        $startX = $start->x;
        $startY = $start->y;
        $startZ = $start->z;
        $goalX = $goal->x;
        $goalY = $goal->y;
        $goalZ = $goal->z;

        // ✅ 사용 가능한 알고리즘 목록에서 랜덤 선택 (우선순위 적용 가능)
        $algorithm = $this->enabledAlgorithms[array_rand($this->enabledAlgorithms)];

        $callbackId = spl_object_hash((object) $callback);
        EntityAI::storeCallback($callbackId, $callback);

        // ✅ 비동기 경로 탐색 실행
        $task = new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callbackId) extends AsyncTask {
            private string $worldName;
            private float $startX, $startY, $startZ;
            private float $goalX, $goalY, $goalZ;
            private string $algorithm;
            private string $callbackId;

            public function __construct(
                string $worldName,
                float $startX, float $startY, float $startZ,
                float $goalX, float $goalY, float $goalZ,
                string $algorithm,
                string $callbackId
            ) {
                $this->worldName = $worldName;
                $this->startX = $startX;
                $this->startY = $startY;
                $this->startZ = $startZ;
                $this->goalX = $goalX;
                $this->goalY = $goalY;
                $this->goalZ = $goalZ;
                $this->algorithm = $algorithm;
                $this->callbackId = $callbackId;
            }

            public function onRun(): void {
                $this->setResult([
                    "worldName" => $this->worldName,
                    "startX" => $this->startX, "startY" => $this->startY, "startZ" => $this->startZ,
                    "goalX" => $this->goalX, "goalY" => $this->goalY, "goalZ" => $this->goalZ,
                    "algorithm" => $this->algorithm,
                    "callbackId" => $this->callbackId
                ]);
            }

            public function onCompletion(): void {
                $result = $this->getResult();
                if ($result !== null && isset($result["callbackId"])) {
                    $callback = EntityAI::getCallback($result["callbackId"]);
                    if ($callback !== null) {
                        $world = $this->plugin->getWorldManager()->getWorldByName($result["worldName"]);
                        if (!$world instanceof World) {
                            $callback(null);
                            return;
                        }

                        $start = new Vector3($result["startX"], $result["startY"], $result["startZ"]);
                        $goal = new Vector3($result["goalX"], $result["goalY"], $result["goalZ"]);
                        $pathfinder = new Pathfinder();

                        switch ($result["algorithm"]) {
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
                                $path = null;
                        }

                        $callback($path);
                    }
                }
            }
        };

        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    // ✅ 클로저 저장 및 호출을 위한 정적 변수 추가
    private static array $callbacks = [];

    public static function storeCallback(string $id, callable $callback): void {
        self::$callbacks[$id] = $callback;
    }

    public static function getCallback(string $id): ?callable {
        return self::$callbacks[$id] ?? null;
    }
}
