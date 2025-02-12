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
    
    public function avoidObstacle(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    if ($yaw === null) {
        return;
    }

    // ✅ 몬스터 눈높이 기준 장애물 감지 (광선 추적)
    $start = $position->add(0, $mob->getEyeHeight(), 0);
    $directionVector = new Vector3(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));
    $end = $start->addVector($directionVector->multiply(2));

    $hitPos = $this->raycast($world, $start, $end, fn(Block $block) => $this->isSolidBlock($block));

    if ($hitPos instanceof Vector3) {
        $hitBlock = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y, (int)$hitPos->z);
        $blockAbove = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y + 1, (int)$hitPos->z);
        $blockAbove2 = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y + 2, (int)$hitPos->z);

        // ✅ 두 칸 이상 블록이 막혀 있으면 장애물로 인식
        if ($this->isSolidBlock($hitBlock) && $this->isSolidBlock($blockAbove) && $this->isSolidBlock($blockAbove2)) {
            Server::getInstance()->broadcastMessage("⚠️ [AI] 장애물 감지됨: " . $hitBlock->getName());
            $this->findAlternativePath($mob, $position, $world);
            return;
        }
    }

    // ✅ 광선 추적 실패 → 직접 탐색 실행
    $this->directObstacleSearch($mob, $world, $position);
}

private function directObstacleSearch(Living $mob, World $world, Vector3 $position): void {
    $front = $position->add(1, 0, 0);
    $frontAbove = $position->add(1, 1, 0);
    $frontAbove2 = $position->add(1, 2, 0);

    $blockFront = $world->getBlockAt((int)$front->x, (int)$front->y, (int)$front->z);
    $blockAbove = $world->getBlockAt((int)$frontAbove->x, (int)$frontAbove->y, (int)$frontAbove->z);
    $blockAbove2 = $world->getBlockAt((int)$frontAbove2->x, (int)$frontAbove2->y, (int)$frontAbove2->z);

    // ✅ 공기(Air)나 이동 가능한 블록이면 장애물 아님
    if (!$this->isSolidBlock($blockFront) || $blockFront instanceof Air) {
        return;
    }

    // ✅ 두 칸 이상 막혀 있으면 장애물로 인식
    if ($this->isSolidBlock($blockFront) && $this->isSolidBlock($blockAbove) && $this->isSolidBlock($blockAbove2)) {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 직접 탐색 장애물 감지됨: " . $blockFront->getName());
        $this->findAlternativePath($mob, $position, $world);
    }
}

private function findAlternativePath(Living $mob, Vector3 $position, World $world): void {
    for ($i = 0; $i < 3; $i++) {
        $offsetX = mt_rand(-2, 2);
        $offsetZ = mt_rand(-2, 2);
        $alternativeGoal = $position->addVector(new Vector3($offsetX, 0, $offsetZ));

        if ($this->isPassableBlock($world->getBlockAt((int)$alternativeGoal->x, (int)$alternativeGoal->y, (int)$alternativeGoal->z))) {
            $this->findPathAsync($world, $position, $alternativeGoal, "A*", function (?array $path) use ($mob) {
                if ($path !== null) {
                    $this->setPath($mob, $path);
                    $this->moveAlongPath($mob);
                }
            });
            return;
        }
    }
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
    
private function raycast(World $world, Vector3 $start, Vector3 $end, callable $filter): ?Vector3 {
    $dx = $end->x - $start->x;
    $dy = $end->y - $start->y;
    $dz = $end->z - $start->z;

    $length = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    if ($length === 0) {
        return null;
    }

    $dx /= $length;
    $dy /= $length;
    $dz /= $length;

    $x = $start->x;
    $y = $start->y;
    $z = $start->z;

    for ($i = 0; $i <= $length; $i += 0.5) {
        $block = $world->getBlockAt((int)$x, (int)$y, (int)$z);
        $blockAbove = $world->getBlockAt((int)$x, (int)$y + 1, (int)$z);

        // ✅ 두 칸 블록을 함께 감지 (벽 등 장애물 체크)
        if ($filter($block) && $filter($blockAbove)) {
            return new Vector3((int)$x, (int)$y, (int)$z);
        }

        $x += $dx * 0.5;
        $y += $dy * 0.5;
        $z += $dz * 0.5;
    }

    return null;
}
    private function initiatePathfind(Living $mob, Vector3 $position, Block $block, World $world){ // Add World $world parameter
    // ✅ 5번까지 랜덤 방향으로 우회 시도
    for ($i = 0; $i < 5; $i++) {
        $offsetX = mt_rand(-3, 3);
        $offsetZ = mt_rand(-3, 3);
        $alternativeGoal = $position->addVector(new Vector3($offsetX, 0, $offsetZ));
        $alternativeBlock = $world->getBlockAt((int)$alternativeGoal->x, (int)$alternativeGoal->y, (int)$alternativeGoal->z); // Use $world

        // ✅ 이동 가능한 블록인지 확인 (Air 또는 투명 블록 허용)
        if ($alternativeBlock instanceof Air || $alternativeBlock instanceof TallGrass || $alternativeBlock->isTransparent() || $this->isNonSolidBlock($alternativeBlock)) {
            $this->findPathAsync($world, $position, $alternativeGoal, "A*", function (?array $path) use ($mob, $world) { // Use $world in closure as well!
                if ($path !== null) {
                    $this->setPath($mob, $path);
                }
            });
            return;
        }
    }
}
// Helper function to check if a block is solid for collision
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

    public function onPathFound(Living $mob, ?array $path): void {
    $navigator = new EntityNavigator();
    $tracker = new EntityTracker();

    if ($path !== null && count($path) > 0) {
        $this->setPath($mob, $path);
        
        // ✅ 경로 저장 여부 확인
        $savedPath = $this->getPath($mob);
        if (empty($savedPath)) {
            Server::getInstance()->broadcastMessage("❌ [AI] 경로 저장 실패!");
            return;
        }

        Server::getInstance()->broadcastMessage("✅ 몬스터 {$mob->getId()} 경로 탐색 완료! 이동 시작...");
        $navigator->moveAlongPath($mob);
    } else {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 경로 탐색 실패! 기본 이동 유지...");
        $nearestPlayer = $tracker->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $navigator->moveToPlayer($mob, $nearestPlayer, $this->enabled);
        } else {
            $navigator->moveRandomly($mob);
        }
    }
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

    if ($player !== null) {
        // ✅ 몸을 먼저 회전한 후 이동
        $targetPosition = new Vector3($player->getPosition()->x, $mob->getPosition()->y, $player->getPosition()->z);
        $mob->lookAt($targetPosition);
    } else {
        $mob->lookAt($nextPosition);
    }

    // ✅ 너무 가까운 노드는 건너뜀
    while (!empty($this->entityPaths[$mob->getId()]) && $currentPosition->distanceSquared($nextPosition) < 0.5) {
        $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
    }

    $direction = $nextPosition->subtractVector($currentPosition);
    if ($direction->lengthSquared() < 0.04) {
        return;
    }

    $speed = 0.26;
    $currentMotion = $mob->getMotion();
    $inertiaFactor = 0.4;

    // ✅ 대각선 이동 보정
    if (abs($direction->x) > 0 && abs($direction->z) > 0) {
        $direction = new Vector3($direction->x * 0.85, $direction->y, $direction->z * 0.85);
    }

    // ✅ 부드러운 이동 적용
    $blendedMotion = new Vector3(
        ($currentMotion->x * $inertiaFactor) + ($direction->normalize()->x * $speed * (1 - $inertiaFactor)),
        $currentMotion->y,
        ($currentMotion->z * $inertiaFactor) + ($direction->normalize()->z * $speed * (1 - $inertiaFactor))
    );

    $mob->setMotion($blendedMotion);
}
}
