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

    $angle = deg2rad($yaw);
    $directionVector = new Vector3(cos($angle), 0, sin($angle));

    $start = $position->add(0, $mob->getEyeHeight(), 0);
    $end = $position->addVector($directionVector->multiply(2));

    $result = $this->raycast($world, $start, $end, function(Block $block) {
        return $this->isSolidBlock($block);
    });

    if ($result instanceof Vector3) {
    $block = $world->getBlockAt((int)$result->x, (int)$result->y, (int)$result->z);
    Server::getInstance()->broadcastMessage("⚠️ [AI] Raycast: 장애물 감지됨! 우회 경로 탐색 중... (Block: " . $block->getName() . ")");
    $this->initiatePathfind($mob, $position, $block, $world); // 경로 탐색 시작
    return; // Raycast 성공 시 다른 검사 건너뛰기
    }


    // 2. 정면 블록 + 주변 블록 검사 (Raycasting 실패 시)
    $checkPositions = [
        $position->addVector($directionVector), // 정면
        $position->add($directionVector->x + 1, $directionVector->y, $directionVector->z), // 우측
        $position->add($directionVector->x - 1, $directionVector->y, $directionVector->z), // 좌측
        $position->add($directionVector->x + 1, $directionVector->y, $directionVector->z + 1), // 우측 대각선
        $position->add($directionVector->x + 1, $directionVector->y, $directionVector->z - 1), // 우측 대각선
        $position->add($directionVector->x - 1, $directionVector->y, $directionVector->z + 1), // 좌측 대각선
        $position->add($directionVector->x - 1, $directionVector->y, $directionVector->z - 1), // 좌측 대각선
    ];

    foreach ($checkPositions as $checkPos) {
        $block = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y, (int)$checkPos->z);

        // 공기 블록 또는 통과 가능한 블록은 건너뜀
        if ($block instanceof Air || $block instanceof TallGrass || $block->isTransparent() || $this->isNonSolidBlock($block)) {
            continue;
        }

        // 충돌 상자가 null이 아니고, 좀비의 충돌 상자와 겹치는 경우에만 장애물로 인식
        if ($this->isSolidBlock($block) && method_exists($block, 'getBoundingBox') && $block->getBoundingBox() !== null && $block->getBoundingBox()->intersectsWith($mob->getBoundingBox())) {
            Server::getInstance()->broadcastMessage("⚠️ [AI] 주변 블록 검사: 장애물 감지됨! 우회 경로 탐색 중... (Block: " . $block->getName() . ")");
            $this->initiatePathfind($mob, $position, $block, $world); // 경로 탐색 시작
            return; // 주변 블록 검사에서 장애물 발견 시 종료
        }
    }


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
// Helper function to check if a block is solid for collision
private function isSolidBlock(Block $block): bool {
    $solidBlocks = [
        "Stone", "Dirt", "Cobblestone", "Log", "Planks", "Brick", "Sandstone",
        "Obsidian", "Bedrock", "IronBlock", "GoldBlock", "DiamondBlock",
        "Concrete", "ConcretePowder",
        // ... other solid blocks
    ];

    $nonSolidBlocks = [
        "Grass", "TallGrass", "Snow", "Carpet", "Flower", "RedFlower", "YellowFlower",
        "Mushroom", "Wheat", "Carrot", "Potato", "Beetroot", "NetherWart",
        "SugarCane", "Cactus", "Reed", "Vine", "LilyPad",
        "Door", "Trapdoor", "Fence", "FenceGate", "Wall",
        "GlassPane", "IronBars", "Cauldron", "BrewingStand", "EnchantingTable",
        "Workbench", "Furnace", "Chest", "TrappedChest", "Dispenser", "Dropper",
        "Hopper", "Anvil", "Beacon", "DaylightDetector", "NoteBlock",
        "Piston", "StickyPiston", "Lever", "Button", "PressurePlate",
        "RedstoneTorch", "RedstoneWire", "Repeater", "Comparator",
        "Sign", "WallSign", "Painting", "ItemFrame",
        "Dirt", // Example: if you don't want dirt to be solid (adjust as needed)
    ];

    $blockName = strtolower($block->getName()); // 블록 이름을 소문자로 변환하여 비교

    if (in_array($blockName, $nonSolidBlocks)) {
        return false;
    }

    return in_array($blockName, $solidBlocks);
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
