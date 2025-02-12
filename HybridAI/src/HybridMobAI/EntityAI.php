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

    public function avoidObstacle(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = (float)$mob->getLocation()->yaw;

    if ($yaw === null) {
        return;
    }

    // ✅ 몬스터 정면 장애물 감지 (광선 추적)
    $start = $position->add(0, $mob->getEyeHeight(), 0);
    $directionVector = new Vector3(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));
    $end = $start->addVector($directionVector->multiply(2.5));

    $hitPos = $this->raycast($world, $start, $end, fn(Block $block) => $this->isSolidBlock($block));

    if ($hitPos instanceof Vector3) {
        $hitBlock = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y, (int)$hitPos->z);
        $blockAbove = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y + 1, (int)$hitPos->z);
        $blockAbove2 = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y + 2, (int)$hitPos->z);

        // ✅ 장애물 정보 출력
        Server::getInstance()->broadcastMessage("🛑 [AI] 장애물 감지됨! 블록: " . $hitBlock->getName() . " (위치: {$hitPos->x}, {$hitPos->y}, {$hitPos->z})");

        // ✅ 이동 가능한 블록이면 장애물로 인식하지 않음
        if ($this->isNonSolidBlock($hitBlock) || $hitBlock->getId() === $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z)->getId()) {
            Server::getInstance()->broadcastMessage("🚫 [AI] 장애물 아님 (무시됨): " . $hitBlock->getName());
            return;
        }

        // ✅ 2칸 이상 블록이 있는 경우 장애물로 인식
        if ($this->isSolidBlock($hitBlock) && $this->isSolidBlock($blockAbove) && $this->isSolidBlock($blockAbove2)) {
            Server::getInstance()->broadcastMessage("⚠️ [AI] 장애물 감지! 우회 시도...");
            $this->findAlternativePath($mob, $position, $world);
            return;
        }
    }

    // ✅ 장애물 감지 실패 시 감지된 블록 정보 출력
    Server::getInstance()->broadcastMessage("🔍 [AI] 장애물 감지 실패! 직접 탐색 시작...");
    $find = new Pathfinder();
    $neighbors = $find->getNeighbors($world, $position);

    foreach ($neighbors as $neighbor) {
        $neighborBlock = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        
        // ✅ 직접 탐색한 블록 정보 출력
        Server::getInstance()->broadcastMessage(
        "🔎 [AI] 직접 탐색 블록: " . $neighborBlock->getVanillaName() . 
        " (위치: {$neighbor->x}, {$neighbor->y}, {$neighbor->z})"
    );

    // ✅ 이동 가능한 블록은 무시
    if ($this->isNonSolidBlock($neighborBlock)) {
        Server::getInstance()->broadcastMessage(
            "🚫 [AI] 이동 가능한 블록 (무시됨): " . $neighborBlock->getName()
        );
        continue;
    }

        if ($this->isSolidBlock($neighborBlock) && !$this->isNonSolidBlock($neighborBlock)) {
            Server::getInstance()->broadcastMessage("⚠️ [AI] 직접 탐색 장애물 감지: " . $neighborBlock->getName());
            $this->findAlternativePath($mob, $position, $world);
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
        $block1 = $world->getBlockAt((int)$x, (int)$y, (int)$z); // 1칸 높이
        $block2 = $world->getBlockAt((int)$x, (int)$y + 1, (int)$z); // 2칸 높이

        // ✅ 2칸 모두 장애물인지 확인
        if ($filter($block1) && $filter($block2)) {
            Server::getInstance()->broadcastMessage(
                "🛑 [AI] 2칸 장애물 감지: " . $block1->getVanillaName() . 
                " (위치: {$x}, {$y}, {$z})"
            );
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
    $nonObstacleBlocks = [ 
        "grass", "dirt", "stone", "sand", "gravel", "clay", "coarse_dirt",
        "podzol", "red_sand", "mycelium", "snow", "sandstone", "andesite",
        "diorite", "granite", "netherrack", "end_stone", "terracotta", "concrete",
    ];

    $obstacleBlocks = [ 
        "fence", "fence_gate", "wall", "cobweb", "water", "lava", "magma_block",
        "soul_sand", "honey_block", "nether_wart_block", "scaffolding", "cactus"
    ];

    $blockName = strtolower($block->getName());

    // ✅ 이동 가능한 블록이면 false 반환 (장애물 아님)
    if (in_array($blockName, $nonObstacleBlocks)) {
        return false;
    }

    // ✅ 장애물 블록이면 true 반환
    if (in_array($blockName, $obstacleBlocks) || $block->isSolid()) {
        return true;
    }

    return false;
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
        // ✅ Y 좌표를 고정하여 플레이어가 점프하거나 날아도 따라가지 않도록 수정
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

    // ✅ 점프 판정 수정 (플레이어 점프와 무관하게 블록 높이만 감지)
    if ($direction->y > 0.5) {
        $direction = new Vector3($direction->x, 0.42, $direction->z);
    } elseif ($direction->y < -0.5) {
        $direction = new Vector3($direction->x, -0.2, $direction->z);
    }

    // ✅ 대각선 이동 보정 (이전보다 부드러운 움직임)
    if (abs($direction->x) > 0 && abs($direction->z) > 0) {
        $direction = new Vector3($direction->x * 0.85, $direction->y, $direction->z * 0.85);
    }

    // ✅ 부드러운 이동 적용
    $blendedMotion = new Vector3(
        ($currentMotion->x * $inertiaFactor) + ($direction->normalize()->x * $speed * (1 - $inertiaFactor)),
        $direction->y > 0 ? $direction->y : $currentMotion->y,
        ($currentMotion->z * $inertiaFactor) + ($direction->normalize()->z * $speed * (1 - $inertiaFactor))
    );

    $mob->setMotion($blendedMotion);
}
}
