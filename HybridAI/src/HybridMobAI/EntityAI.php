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
use pocketmine\math\RaycastResult;
use pocketmine\event\entity\EntityDeathEvent;

class EntityAI {
    private bool $enabled;
    private array $path = [];
    private ?Vector3 $target = null;
    private array $entityPaths = [];
    private PluginBase $plugin;
    private array $targets = [];
    private array $enabledAlgorithms;
    private $currentPathIndices = [];
    private array $pathCache = []; // ✅ 캐싱 시스템 추가
    private const MAX_Y_DIFFERENCE = 2; // ✅ Y축 감지 최대 높이 차이
    private const ROTATION_SPEED = 10; // ✅ 회전 속도 제한

    public function __construct(PluginBase $plugin, bool $enabled) {
        $this->plugin = $plugin;
        $this->enabledAlgorithms = $plugin->getConfig()->get("AI")["pathfinding_priority"] ?? ["A*"];
        $this->enabled = $plugin->getConfig()->get("AI")["enabled"];
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
        // 1. Convert Position objects to Vector3 objects:
        $startVector = new Vector3($start->x, $start->y, $start->z);
        $goalVector = new Vector3($goal->x, $goal->y, $goal->z);

        // 2. Prepare data for the AsyncTask:
        $worldName = $world->getFolderName();
        $startX = $startVector->x;
        $startY = $startVector->y;
        $startZ = $startVector->z;
        $goalX = $goalVector->x;
        $goalY = $goalVector->y;
        $goalZ = $goalVector->z;

        $callbackId = spl_object_hash((object)$callback);
        EntityAI::storeCallback($callbackId, $callback);

        // 3. Create and submit the AsyncTask:
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

                        // 4. Use the coordinates directly (they are already Vector3-compatible):
                        $start = new Vector3($result["startX"], $result["startY"], $result["startZ"]);
                        $goal = new Vector3($result["goalX"], $result["goalY"], $result["goalZ"]);
                        $pathfinder = new Pathfinder(); // Make sure you have a Pathfinder class

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

                    // 경로 탐색 결과를 파일로 저장
                    $this->savePathToFile($result["worldName"], $start, $goal, $path);

                    $callback($path);
                }
            }
        }

        private function savePathToFile(string $worldName, Vector3 $start, Vector3 $goal, ?array $path): void {
    // 디렉토리 경로 설정
    $directoryPath = "path_results";
    
    // 디렉토리가 존재하지 않으면 생성
    if (!is_dir($directoryPath)) {
        mkdir($directoryPath, 0777, true); // 재귀적으로 디렉토리 생성
    }

    // 파일 경로 설정
    $filePath = "{$directoryPath}/{$worldName}_path_result.txt";
    
    // 파일 내용 작성
    $content = "Start: {$start->x}, {$start->y}, {$start->z}\n";
    $content .= "Goal: {$goal->x}, {$goal->y}, {$goal->z}\n";
    $content .= "Path: " . ($path !== null ? json_encode($path) : "No path found") . "\n";
    
    // 파일에 내용 저장
    file_put_contents($filePath, $content, FILE_APPEND);
}
    };

    Server::getInstance()->getAsyncPool()->submitTask($task);
}

    public function basicObstacle(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    if ($yaw === null) {
        return;
    }

    // 1. 앞쪽 2칸 블록 확인
    $x = (int)$position->x;
    $y = (int)$position->y + 1; // 눈높이를 블록 높이(1)로 설정
    $z = (int)$position->z;

    $frontBlock1 = $world->getBlockAt($x + (int)cos(deg2rad($yaw)), $y, $z + (int)sin(deg2rad($yaw)));
    $frontBlock2 = $world->getBlockAt($x + 2 * (int)cos(deg2rad($yaw)), $y, $z + 2 * (int)sin(deg2rad($yaw)));

    // 2. 장애물 여부 확인
    if ($this->isSolidBlock($frontBlock1) && $this->isSolidBlock($frontBlock2)) {
        Server::getInstance()->broadcastMessage(" [AI] 눈앞에 2칸 이상 장애물 감지: " . $frontBlock1->getName());
        $this->moveAroundObstacle($mob); // 장애물 우회
        return;
    }

    // ... (다른 탐색 방식)
}

    private function isObstacleAhead(Living $mob): bool {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    // ✅ 몬스터 정면의 2칸 블록 확인
    $frontBlockPos1 = $position->add(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));
    $frontBlockPos2 = $frontBlockPos1->add(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));

    $frontBlock1 = $world->getBlockAt((int)$frontBlockPos1->x, (int)$frontBlockPos1->y, (int)$frontBlockPos1->z);
    $frontBlock2 = $world->getBlockAt((int)$frontBlockPos2->x, (int)$frontBlockPos2->y, (int)$frontBlockPos2->z);
    
    // ✅ 위 블록도 확인 (두 칸 높이 장애물 체크)
    $frontBlockAbove1 = $world->getBlockAt((int)$frontBlockPos1->x, (int)$frontBlockPos1->y + 1, (int)$frontBlockPos1->z);
    $frontBlockAbove2 = $world->getBlockAt((int)$frontBlockPos2->x, (int)$frontBlockPos2->y + 1, (int)$frontBlockPos2->z);

    // ✅ 장애물 감지: 두 개의 블록이 모두 solid(단단한 블록)이면 이동 불가
    if ($this->isSolidBlock($frontBlock1) && $this->isSolidBlock($frontBlock2) && $this->isSolidBlock($frontBlockAbove1) && $this->isSolidBlock($frontBlockAbove2)) {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 장애물 감지됨: " . $frontBlock1->getName() . " & " . $frontBlock2->getName());
        return true;
    }

    return false;
}
    
private function moveAroundObstacle(Living $mob): void {
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;
    $x = (int)$mob->getX();
    $z = (int)$mob->getZ();

    // 1. 우회 방향 결정 (오른쪽 또는 왼쪽)
    $side = mt_rand(0, 1) ? 1 : -1; // 1: 오른쪽, -1: 왼쪽

    // 2. 우회 거리 및 방향 설정
    $distance = 3; // 우회 거리 (블록 단위)
    $newX = $x + $side * $distance * (int)sin(deg2rad($yaw));
    $newZ = $z - $side * $distance * (int)cos(deg2rad($yaw));

    // 3. 이동 가능한 위치인지 확인
    $newBlock = $world->getBlockAt((int)$newX, (int)$mob->getY(), (int)$newZ);
    $newBlockAbove = $world->getBlockAt((int)$newX, (int)$mob->getY() + 1, (int)$newZ);

    if ($this->isPassableBlock($newBlock) && $this->isPassableBlock($newBlockAbove)) {
        // 4. 이동
        $mob->teleport(new Vector3($newX, $mob->getY(), $newZ));
    } else {
        // 이동 불가능한 경우, 반대 방향으로 재시도
        $side = -$side;
        $newX = $x + $side * $distance * (int)sin(deg2rad($yaw));
        $newZ = $z - $side * $distance * (int)cos(deg2rad($yaw));

        $newBlock = $world->getBlockAt((int)$newX, (int)$mob->getY(), (int)$newZ);
        $newBlockAbove = $world->getBlockAt((int)$newX, (int)$mob->getY() + 1, (int)$newZ);

        if ($this->isPassableBlock($newBlock) && $this->isPassableBlock($newBlockAbove)) {
            $mob->teleport(new Vector3($newX, $mob->getY(), $newZ));
        } else {
            // 여전히 이동 불가능한 경우, 제자리에서 잠시 멈추거나 다른 행동을 취하도록 설정
            $mob->setMotion(new Vector3(0, 0, 0)); // 정지
            Server::getInstance()->broadcastMessage("⚠️ [AI] 우회 경로를 찾지 못했습니다!");
        }
    }
}
    
    private function isObstacle(Block $block, Block $blockAbove): bool {
    if ($block instanceof Air) return false; // ✅ 공기는 장애물이 아님
    if ($this->isPassableBlock($block)) return false; // ✅ 지나갈 수 있는 블록은 장애물 X
    if (!$this->isSolidBlock($block)) return false; // ✅ 단단하지 않은 블록은 장애물 X

    // ✅ 위에도 블록이 있어서 이동 불가능한 경우 장애물로 판단
    return $this->isSolidBlock($blockAbove);
}

public function avoidObstacle(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    // ✅ 몬스터 앞의 장애물 감지
    $frontBlockPos = $position->add(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));
    $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
    $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);

    if ($this->isObstacle($frontBlock, $frontBlockAbove)) {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 장애물 감지됨: {$frontBlock->getName()} at {$frontBlockPos->x}, {$frontBlockPos->y}, {$frontBlockPos->z}");
        $this->findAlternativePath($mob, $position, $world);
    }
}
    
public function findAlternativePath(Living $mob, Vector3 $position, World $world): void {
    $maxAttempts = 5;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $offsetX = mt_rand(-3, 3);
        $offsetZ = mt_rand(-3, 3);
        $alternativeGoalVector = $position->addVector(new Vector3($offsetX, 0, $offsetZ));

        $alternativeBlock = $world->getBlockAt((int)$alternativeGoalVector->x, (int)$alternativeGoalVector->y, (int)$alternativeGoalVector->z);

        if (!$this->isObstacle($alternativeBlock, $world->getBlockAt((int)$alternativeGoalVector->x, (int)$alternativeGoalVector->y + 1, (int)$alternativeGoalVector->z))) {
            // Create a Position object from the Vector3 and the world
            $alternativeGoal = new Position((int)$alternativeGoalVector->x, (int)$alternativeGoalVector->y, (int)$alternativeGoalVector->z, $world);

            Server::getInstance()->broadcastMessage("🔄 [AI] 장애물 우회: {$alternativeGoal->x}, {$alternativeGoal->y}, {$alternativeGoal->z}");

            $this->findPathAsync($world, $position, $alternativeGoal, "A*", function (?array $path) use ($mob) {
                if ($path !== null) {
                    $this->setPath($mob, $path);
                    $this->moveAlongPath($mob);
                }
            });
            return;
        }
    }

    // ✅ 모든 시도가 실패하면 랜덤으로 강제 이동 (강제 탈출)
    $randomOffsetX = mt_rand(-5, 5);
    $randomOffsetZ = mt_rand(-5, 5);
    $fallbackVector = $position->addVector(new Vector3($randomOffsetX, 0, $randomOffsetZ));

    // Create a Position object for the fallback
    $fallbackPosition = new Position((int)$fallbackVector->x, (int)$fallbackVector->y, (int)$fallbackVector->z, $world);

    Server::getInstance()->broadcastMessage("⚠️ [AI] 모든 우회 실패 → 강제 이동 시도!");

    $this->findPathAsync($world, $position, $fallbackPosition, "A*", function (?array $path) use ($mob) {
        if ($path !== null) {
            $this->setPath($mob, $path);
            $this->moveAlongPath($mob);
        } else {
            Server::getInstance()->broadcastMessage("❌ [AI] 강제 이동 실패! 랜덤 이동 시작...");
            $this->moveRandomly($mob); // ✅ 최후의 방법으로 랜덤 이동
        }
    });
}


private function moveRandomly(Living $mob): void {
    $randomDir = new Vector3(mt_rand(-3, 3), 0, mt_rand(-3, 3)); // ✅ 랜덤 이동 범위 조정
    $newPos = $mob->getPosition()->addVector($randomDir);
    
    Server::getInstance()->broadcastMessage("🔄 [AI] 랜덤 이동 시도: {$newPos->x}, {$newPos->y}, {$newPos->z}");
    
    $mob->lookAt($newPos); // ✅ 랜덤 방향 바라보도록 설정
    $mob->setMotion($randomDir->normalize()->multiply(0.2)); // ✅ 이동 속도 조정
}
    
private function isNonSolidBlock(Block $block): bool {
    $nonSolidBlocks = [
        "air", "grass", "tall_grass", "snow", "carpet", "flower", "red_flower", "yellow_flower",
        "mushroom", "wheat", "carrot", "potato", "beetroot", "nether_wart",
        "sugar_cane", "cactus", "reed", "vine", "lily_pad",
        "glass_pane", "iron_bars", "cauldron", "brewing_stand", "enchanting_table",
        "sign", "wall_sign", "painting", "item_frame",
    ];

    return in_array(strtolower($block->getName()), $nonSolidBlocks);
}
    private function isSolidBlock(Block $block): bool {
    return $block->isSolid() && !$this->isPassableBlock($block); // isSolid() && isPassableBlock()을 같이 사용해서 좀더 정확하게 판별
}

private function isPassableBlock(Block $block): bool {
    $nonPassableBlocks = [ // 통과 불가능한 블록 목록
        "air", "grass", "tall_grass", "snow", "carpet", "flower", "red_flower", "yellow_flower",
        "mushroom", "wheat", "carrot", "potato", "beetroot", "nether_wart",
        "sugar_cane", "cactus", "reed", "vine", "lily_pad",
        "glass_pane", "iron_bars", "cauldron", "brewing_stand", "enchanting_table",
        "sign", "wall_sign", "painting", "item_frame",
    ];
    return !in_array(strtolower($block->getName()), $nonPassableBlocks) && ($block instanceof Air || $block instanceof TallGrass || $block->isTransparent() || !$block->isSolid());
}

    public function escapePit(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    if (!$mob->isOnGround()) {
        return;
    }

    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    $blockBelow2 = $world->getBlockAt((int)$position->x, (int)$position->y - 2, (int)$position->z);

    if (!$blockBelow->isSolid() && !$blockBelow2->isSolid()) {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 웅덩이에 빠짐! 탈출 시도...");

        $escapeGoal = $this->findEscapeBlock($world, $position);
        if ($escapeGoal !== null) {
            Server::getInstance()->broadcastMessage("🟢 [AI] 탈출 경로 발견! 이동 중...");
            $this->findPathAsync($world, $position, $escapeGoal, "A*", function (?array $path) use ($mob) {
                if (!empty($path)) {
                    $this->setPath($mob, $path);
                    (new EntityNavigator())->moveAlongPath($mob);
                } else {
                    Server::getInstance()->broadcastMessage("❌ [AI] 탈출 실패! 점프 시도...");
                    $mob->setMotion(new Vector3(0, 0.5, 0));
                }
            });
            return;
        }

        Server::getInstance()->broadcastMessage("❌ [AI] 탈출 경로 없음! 점프 시도...");
        $mob->setMotion(new Vector3(0, 0.5, 0));
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

public function removePath(Living $mob): void {
    unset($this->entityPaths[$mob->getId()]);
}


    private function adjustVerticalLook(Living $mob, Vector3 $target): void {
        $dy = $target->y - $mob->getPosition()->y;
        $mob->setRotation($mob->getLocation()->yaw, rad2deg(atan2($dy, 1)));
    }

    public function getPath(Living $mob): ?array {
        return $this->entityPaths[$mob->getId()] ?? null;
    }

    public function setPath(Living $mob, array $path): void {
        $this->entityPaths[$mob->getId()] = $path;
    }

    public function hasPath(Living $mob): bool {
        return isset($this->entityPaths[$mob->getId()]);
    }

    public function moveAlongPath(Living $mob): void {
    $path = $this->getPath($mob);
    if (empty($path)) {
        return;
    }

    $tracker = new EntityTracker();
    $player = $tracker->findNearestPlayer($mob);
    $currentPosition = $mob->getPosition();
    $nextPosition = array_shift($this->entityPaths[$mob->getId()]);

    // ✅ 플레이어 바라보기
    if ($player !== null) {
        $mob->lookAt($player->getPosition());
    }

    // ✅ 너무 가까운 노드는 건너뜀
    while (!empty($this->entityPaths[$mob->getId()]) && $currentPosition->distanceSquared($nextPosition) < 0.4) {
        $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
    }

    // ✅ 이동 방향 벡터 계산
    $direction = $nextPosition->subtractVector($currentPosition);
    $distanceSquared = $direction->lengthSquared();
    
    // ✅ 너무 작은 거리는 무시
    if ($distanceSquared < 0.01) {
        return;
    }

    $speed = 0.23;
    $currentMotion = $mob->getMotion();
    $inertiaFactor = 0.35; // ✅ 관성 보정

    // ✅ 몬스터가 먼저 몸을 돌린 후 이동
    $yaw = rad2deg(atan2(-$direction->x, $direction->z));
    $mob->setRotation($yaw, 0);

    // ✅ 장애물 감지 후 우회
    if ($this->isObstacleAhead($mob, $nextPosition)) {
        $this->avoidObstacle($mob);
        return;
    }

    // 🔥 점프 및 내려가기 로직 개선 (2블록 이하)
    if ($direction->y > 0 && $direction->y <= 2.0) {
        $direction = new Vector3($direction->x, 0.6, $direction->z); // ✅ 2블록 이하는 점프
    } elseif ($direction->y < 0 && $direction->y >= -2.0) {
        $direction = new Vector3($direction->x, -0.3, $direction->z); // ✅ 2블록 이하는 내려가기
    }

    // ✅ 대각선 이동 보정 (Normalize 적용)
    if (abs($direction->x) > 0 && abs($direction->z) > 0) {
        $direction = $direction->normalize()->multiply($speed);
    }

    // ✅ 이동 모션 적용 (관성 보정 및 블렌딩)
    $blendedMotion = new Vector3(
        ($currentMotion->x * $inertiaFactor) + ($direction->x * (1 - $inertiaFactor)),
        $currentMotion->y,
        ($currentMotion->z * $inertiaFactor) + ($direction->z * (1 - $inertiaFactor))
    );

    $mob->setMotion($blendedMotion);
}
}
