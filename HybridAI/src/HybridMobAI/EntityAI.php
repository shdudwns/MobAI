<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\block\Block;
use pocketmine\math\VectorMath;

class EntityAI {
    private bool $enabled = false;
    private array $path = [];
    private ?Vector3 $target = null;
    private array $entityPaths = [];
    private PluginBase $plugin;
    private array $targets = [];
    private array $enabledAlgorithms;

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        $this->enabledAlgorithms = $plugin->getConfig()->get("AI")["pathfinding_priority"] ?? ["A*"];
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

    public function findPathAsync(World $world, Position $start, Vector3 $goal, string $algorithm, callable $callback): void {
    // ✅ Vector3 → Position 변환 (오류 수정)
    $goalPosition = new Position($goal->x, $goal->y, $goal->z, $world);

    $worldName = $world->getFolderName();
    $startX = $start->x;
    $startY = $start->y;
    $startZ = $start->z;
    $goalX = $goalPosition->x;
    $goalY = $goalPosition->y;
    $goalZ = $goalPosition->z;

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
            $this->setResult([
                "worldName" => $this->worldName,
                "startX" => $this->startX, "startY" => $this->startY, "startZ" => $this->startZ,
                "goalX" => $this->goalX, "goalY" => $this->goalY, "goalZ" => $this->goalZ,
                "algorithm" => $this->algorithm, "callbackId" => $this->callbackId
            ]);
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

public function avoidObstacle(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    if ($yaw !== null) {
        $angle = deg2rad($yaw);
        $directionVector = new Vector3(cos($angle), 0, sin($angle));

        $frontBlockPos = $position->addVector($directionVector);
        $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);

        // 공기 블록이거나 통과 가능한 블록은 장애물로 처리하지 않음
        if ($frontBlock instanceof Air || $frontBlock instanceof TallGrass || $frontBlock->isTransparent()) {
           return;
        }

        $blockBB = $frontBlock->getBoundingBox();

        if ($blockBB !== null && $blockBB->intersectsWith($mob->getBoundingBox())) {
            $this->plugin->getLogger()->info("⚠️ [AI] 장애물 감지, 우회 경로 찾는 중...");
            $alternativeGoal = $position->addVector(new Vector3(mt_rand(-2, 2), 0, mt_rand(-2, 2)));
            $this->findPathAsync($world, $position, $alternativeGoal, "A*", function (?array $path) use ($mob) {
                if ($path !== null) {
                    $this->setPath($mob, $path);
                }
            });
        }
    } else {
        $this->plugin->getLogger()->error("Yaw is null for mob: " . $mob->getId());
        return;
    }
}



    public function escapePit(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $blockAbove = $world->getBlockAt((int)$position->x, (int)$position->y + 1, (int)$position->z);

        if ($blockAbove->isTransparent()) {
            // 위로 점프 가능
            $mob->setMotion(new Vector3(0, 0.5, 0));
        } else {
            // 주변 블록 탐색 후 탈출
            $this->plugin->getLogger()->info("🔄 [AI] 구덩이 감지, 탈출 시도 중...");
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dz = -1; $dz <= 1; $dz++) {
                    $newPos = $position->addVector(new Vector3($dx, 1, $dz));
                    if ($world->getBlockAt((int)$newPos->x, (int)$newPos->y, (int)$newPos->z)->isTransparent()) {
                        $this->findPathAsync($world, $position, $newPos, "BFS", function (?array $path) use ($mob) {
                            if ($path !== null) {
                                $this->setPath($mob, $path);
                            }
                        });
                        return;
                    }
                }
            }
        }
    }
    
// ✅ 클로저 저장 및 호출을 위한 정적 변수 추가
private static array $callbacks = [];

public static function storeCallback(string $id, callable $callback): void {
    self::$callbacks[$id] = $callback;
}

public static function getCallback(string $id): ?callable {
    return self::$callbacks[$id] ?? null;
}
    public function findPath(World $world, Vector3 $start, Vector3 $goal): ?array {
    $enabledAlgorithms = $this->plugin->getConfig()->get("pathfinding")["enabled_algorithms"];

    if (in_array("A*", $enabledAlgorithms)) {
        return (new Pathfinder())->findPathAStar($world, $start, $goal);
    }

    return null; // A*만 사용하도록 설정
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
