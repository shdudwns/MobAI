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
    private array $targets = [];

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
    }

    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function setTarget(Living $mob, Vector3 $target): void {
        $this->targets[$mob->getId()] = $target;
    }
    
    public function getTarget(Living $mob): ?Vector3 {
        return $this->targets[$mob->getId()] ?? null;
    }

    public function findPathAsync(World $world, Position $start, Position $goal, string $algorithm, callable $callback): void {
    $worldName = $world->getFolderName(); // ❌ World 객체를 AsyncTask에 직접 전달할 수 없음 (비동기 스레드에서 사용 불가)
    $startX = $start->x;
    $startY = $start->y;
    $startZ = $start->z;
    $goalX = $goal->x;
    $goalY = $goal->y;
    $goalZ = $goal->z;

    $callbackId = spl_object_hash((object) $callback);
    EntityAI::storeCallback($callbackId, $callback);

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
            // ❌ 비동기 스레드에서는 Server::getInstance()를 사용할 수 없음
            $this->setResult(["worldName" => $this->worldName, "startX" => $this->startX, "startY" => $this->startY, "startZ" => $this->startZ, "goalX" => $this->goalX, "goalY" => $this->goalY, "goalZ" => $this->goalZ, "algorithm" => $this->algorithm, "callbackId" => $this->callbackId]);
        }

        public function onCompletion(): void {
            $result = $this->getResult();
            if ($result !== null && isset($result["callbackId"])) {
                $callback = EntityAI::getCallback($result["callbackId"]);
                if ($callback !== null) {
                    $world = Server::getInstance()->getWorldManager()->getWorldByName($result["worldName"]);
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

public function getPath(Living $mob): ?array {
    return $this->entityPaths[$mob->getId()] ?? null;
}

public function removePath(Living $mob): void {
    unset($this->entityPaths[$mob->getId()]);
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
