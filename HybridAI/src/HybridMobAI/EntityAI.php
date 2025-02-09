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

    // ✅ 몬스터 눈높이 기준 장애물 감지 (Raycasting)
    $start = $position->add(0, $mob->getEyeHeight(), 0);
    $directionVector = new Vector3(cos(deg2rad($yaw)), 0, sin(deg2rad($yaw)));
    $end = $start->addVector($directionVector->multiply(2));

    $hitPos = $this->raycast($world, $start, $end, function(Block $block) {
        return $this->isSolidBlock($block); // ✅ 오직 단단한 블록만 감지
    });

    if ($hitPos instanceof Vector3) {
        $hitBlock = $world->getBlockAt((int)$hitPos->x, (int)$hitPos->y, (int)$hitPos->z);
        if ($this->isSolidBlock($hitBlock)) {
            Server::getInstance()->broadcastMessage("⚠️ [AI] Raycast: 장애물 감지! 우회 시도... (" . $hitBlock->getName() . ")");
            $this->initiatePathfind($mob, $position, $hitBlock, $world);
            return;
        }
    }

    // ✅ 직접 탐색 (Raycasting 실패 시)
    $frontBlockPos = $position->addVector($directionVector);
    $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);

    // ✅ 공기(Air) 또는 통과 가능한 블록이면 장애물로 인식하지 않음
    if (!$this->isSolidBlock($frontBlock) || $frontBlock instanceof Air || $frontBlock->isTransparent()) {
        return;
    }

    Server::getInstance()->broadcastMessage("⚠️ [AI] 직접 탐색: 장애물 감지됨! 우회 시도... (" . $frontBlock->getName() . ")");
    $this->initiatePathfind($mob, $position, $frontBlock, $world);
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

    for ($i = 0; $i <= $length; $i += 0.5) { // 정밀도 vs 성능을 위해 스텝 크기(0.5)를 조정합니다.
        $block = $world->getBlockAt((int)$x, (int)$y, (int)$z);

        if ($filter($block)) {
            return new Vector3((int)$x, (int)$y, (int)$z); // 블록의 정수 좌표를 반환합니다.
        }

        $x += $dx * 0.5;
        $y += $dy * 0.5;
        $z += $dz * 0.5;
    }

    return null; // solid 블록에 부딪히지 않았습니다.
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
    $solidBlockNames = [  // Use block names (strings) instead of IDs
        "stone", "dirt", "cobblestone", "log", "planks", "brick", "sandstone",
        "obsidian", "bedrock", "iron_block", "gold_block", "diamond_block",
        "concrete", "concrete_powder",  // ... other solid blocks
    ];

    $nonSolidBlockNames = [
        "air", "grass", "tall_grass", "snow", "carpet", "flower", "red_flower", "yellow_flower",
        "mushroom", "wheat", "carrot", "potato", "beetroot", "nether_wart",
        "sugar_cane", "cactus", "reed", "vine", "lily_pad",
        "door", "trapdoor", "fence", "fence_gate", "wall",
        "glass_pane", "iron_bars", "cauldron", "brewing_stand", "enchanting_table",
        "workbench", "furnace", "chest", "trapped_chest", "dispenser", "dropper",
        "hopper", "anvil", "beacon", "daylight_detector", "note_block",
        "piston", "sticky_piston", "lever", "button", "pressure_plate",
        "redstone_torch", "redstone_wire", "repeater", "comparator",
        "sign", "wall_sign", "painting", "item_frame",
        // ... other non-solid blocks
    ];

    $blockName = strtolower($block->getName()); // Get the block name (string), convert to lowercase

    if (in_array($blockName, $nonSolidBlockNames)) {
        return false; // Definitely not solid
    }

    return true;
}

private function isNonSolidBlock(Block $block): bool {
    $nonSolidBlocks = [
        "air", "grass", "tall_grass", "snow", "carpet", "flower", "red_flower", "yellow_flower",
        "mushroom", "wheat", "carrot", "potato", "beetroot", "nether_wart",
        "sugar_cane", "cactus", "reed", "vine", "lily_pad",
        "door", "trapdoor", "fence", "fence_gate", "wall",
        "glass_pane", "iron_bars", "cauldron", "brewing_stand", "enchanting_table",
        "workbench", "furnace", "chest", "trapped_chest", "dispenser", "dropper",
        "hopper", "anvil", "beacon", "daylight_detector", "note_block",
        "piston", "sticky_piston", "lever", "button", "pressure_plate",
        "redstone_torch", "redstone_wire", "repeater", "comparator",
        "sign", "wall_sign", "painting", "item_frame",
    ];

    $blockName = strtolower($block->getName()); // 블록 이름을 소문자로 변환하여 비교

    return in_array($blockName, $nonSolidBlocks);
}


    public function escapePit(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    // ✅ 착지 후에만 웅덩이 감지
    if (!$mob->isOnGround()) {
        return;
    }

    // ✅ 현재 위치의 아래 블록 검사
    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    $blockBelow2 = $world->getBlockAt((int)$position->x, (int)$position->y - 2, (int)$position->z);

    // ✅ 웅덩이 감지: 바닥이 없고, 주변이 벽으로 둘러싸여 있는 경우
    if (!$blockBelow->isSolid() && !$blockBelow2->isSolid()) {
        Server::getInstance()->broadcastMessage("⚠️ [AI] 웅덩이에 빠짐! 탈출 시도...");

        // ✅ 주변 블록 탐색하여 탈출 가능 지점 찾기
        $escapeGoal = $this->findEscapeBlock($world, $position);
        if ($escapeGoal !== null) {
            Server::getInstance()->broadcastMessage("🟢 [AI] 탈출 경로 발견! 이동 중...");
            $this->findPathAsync($world, $position, $escapeGoal, "A*", function (?array $path) use ($mob) {
                if ($path !== null) {
                    $this->setPath($mob, $path);
                }
            });
            return;
        }

        // ✅ 탈출 경로를 찾지 못한 경우 점프 시도
        Server::getInstance()->broadcastMessage("❌ [AI] 탈출 경로 없음! 점프 시도...");
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
    if ($path !== null) {
        $this->setPath($mob, $path);
        Server::getInstance()->broadcastMessage("✅ 몬스터 {$mob->getId()} 경로 탐색 성공! 이동 시작...");
        (new EntityNavigator())->moveAlongPath($mob, $path);
    } else {
        Server::getInstance()->broadcastMessage("⚠️ 경로 탐색 실패! 기본 이동 유지...");
        (new EntityNavigator())->moveToPlayer($mob, (new EntityTracker())->findNearestPlayer($mob));
    }
}
    

    public function moveAlongPath(Living $mob): void {
    $path = $this->getPath($mob);
    if (empty($path)) return;

    $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
    if ($nextPosition instanceof Vector3) {
        $speed = 0.22;
        $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply($speed));
        $mob->lookAt($nextPosition);
    }
}
}
