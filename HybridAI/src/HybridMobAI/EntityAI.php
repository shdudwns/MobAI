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

    if ($yaw === null) {
        Server::getInstance()->broadcastMessage("❌ [AI] Yaw 값이 null입니다! (Mob ID: " . $mob->getId() . ")");
        return;
    }

    $angle = deg2rad($yaw);
    $directionVector = new Vector3(cos($angle), 0, sin($angle));

    // 정면 블록 탐지 (1블록 앞까지 탐지)
    $frontBlockPos = $position->addVector($directionVector); // 탐지 거리 1로 변경
    $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);

    // ✅ 불투명 블록만 장애물로 처리
    if (!($frontBlock instanceof Air || $frontBlock instanceof TallGrass || $frontBlock->isTransparent()) && $frontBlock->isFullBlock()) { // isFullBlock() 추가
        // ✅ 충돌 박스 null 체크
        if ($frontBlock->getBoundingBox() !== null) { // getBoundingBox() null 체크
            Server::getInstance()->broadcastMessage("⚠️ [AI] 장애물 감지됨! 우회 경로 탐색 중...");

            // ✅ 5번까지 랜덤 방향으로 우회 시도
            for ($i = 0; $i < 5; $i++) {
                $offsetX = mt_rand(-3, 3);
                $offsetZ = mt_rand(-3, 3);
                $alternativeGoal = $position->addVector(new Vector3($offsetX, 0, $offsetZ));
                $alternativeBlock = $world->getBlockAt((int)$alternativeGoal->x, (int)$alternativeGoal->y, (int)$alternativeGoal->z);

                // ✅ 이동 가능한 블록인지 확인 (Air 또는 투명 블록 허용)
                if ($alternativeBlock instanceof Air || $alternativeBlock->isTransparent()) {
                    $this->findPathAsync($world, $position, $alternativeGoal, "A*", function (?array $path) use ($mob) {
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


    public function escapePit(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    // 아래 두 블록 검사
    $blockBelow1 = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    $blockBelow2 = $world->getBlockAt((int)$position->x, (int)$position->y - 2, (int)$position->z);

    // ✅ 웅덩이 감지 (아래 두 블록이 Air인 경우)
    if ($blockBelow1 instanceof Air && $blockBelow2 instanceof Air) {
        Server::getInstance()->broadcastMessage(" [AI] 웅덩이에 빠짐! 탈출 시도...");

        // ✅ 1. 주변 블록 탐색하여 한 칸짜리 블록 찾기
        $escapeGoal = $this->findEscapeBlock($world, $position);
        if ($escapeGoal !== null) {
            Server::getInstance()->broadcastMessage(" [AI] 탈출 경로 설정: " . json_encode([$escapeGoal->x, $escapeGoal->y, $escapeGoal->z]));
            $this->findPathAsync($world, $position, $escapeGoal, "A*", function (?array $path) use ($mob) {
                if ($path !== null) {
                    $this->setPath($mob, $path);
                }
            });
            return;
        }

        // ✅ 2. 탈출 경로를 찾지 못한 경우 점프 시도
        Server::getInstance()->broadcastMessage("❌ [AI] 탈출 경로를 찾지 못함! 점프 시도");
        if ($mob->isOnGround()) {
            $mob->setMotion(new Vector3(0, 0.5, 0)); // 점프
        }
    }
}

/**
 * 주변 블록을 탐색하여 한 칸짜리 탈출 블록을 찾습니다.
 */
private function findEscapeBlock(World $world, Vector3 $position): ?Vector3 {
    $searchRadius = 3; // 탐색 반경
    for ($x = -$searchRadius; $x <= $searchRadius; $x++) {
        for ($z = -$searchRadius; $z <= $searchRadius; $z++) {
            if ($x === 0 && $z === 0) continue; // 현재 위치는 제외

            // 한 칸 위 블록 검사
            $escapeGoal = $position->addVector(new Vector3($x, 1, $z));
            $escapeBlock = $world->getBlockAt((int)$escapeGoal->x, (int)$escapeGoal->y, (int)$escapeGoal->z);

            // 아래 블록이 단단한지 확인
            $blockBelow = $world->getBlockAt((int)$escapeGoal->x, (int)$escapeGoal->y - 1, (int)$escapeGoal->z);

            // ✅ 이동 가능한 블록인지 확인 (Air 또는 투명 블록 허용 + 아래 블록이 단단한지)
            if (($escapeBlock instanceof Air || $escapeBlock->isTransparent()) && $blockBelow->isSolid()) {
                return $escapeGoal;
            }
        }
    }
    return null; // 탈출 블록을 찾지 못한 경우
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
