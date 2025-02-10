<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Air;

class Pathfinder {
    private int $maxPathLength = 5000;
    private array $vectorPool = [];
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    private function getVector(float $x, float $y, float $z): Vector3
    {
        $key = "$x:$y:$z";
        if (isset($this->vectorPool[$key])) {
            return $this->vectorPool[$key];
        }
        $vector = new Vector3($x, $y, $z);
        $this->vectorPool[$key] = $vector;
        return $vector;
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $closedSet = [];

    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
    $visitedNodes = 0;

    $startKey = self::vectorToStr($start);
    $openSet->insert($start, -$fScore[$startKey]); // 초기 노드 추가

    Server::getInstance()->broadcastMessage("🔍 [AI] A* 탐색 시작: {$start->x}, {$start->y}, {$start->z} → {$goal->x}, {$goal->y}, {$goal->z}");

    while (!$openSet->isEmpty()) {
        if ($visitedNodes >= $this->maxPathLength) {
            Server::getInstance()->broadcastMessage("❌ [AI] A* 탐색 실패: 최대 탐색 노드 초과 ({$this->maxPathLength})");
            return null;
        }

        $current = $openSet->extract(); // 우선순위 큐에서 노드 추출
        $currentKey = self::vectorToStr($current);
        $visitedNodes++;

        if (isset($closedSet[$currentKey])) continue; // 이미 처리된 노드 건너뜀
        $closedSet[$currentKey] = true; // 닫힌 목록에 추가

        if ($current->equals($goal)) {
            Server::getInstance()->broadcastMessage("✅ [AI] 경로 발견! 노드 방문 수: {$visitedNodes}");
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);
                $openSet->insert($neighbor, -$fScore[$neighborKey]); // 우선순위 큐에 추가
            }
        }
    }

    Server::getInstance()->broadcastMessage("⚠️ [AI] A* 탐색 종료: 경로 없음 (노드 방문: {$visitedNodes})");
    return null;
}    
    public function findPathDijkstra(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, 0);

    $cameFrom = [];
    $cost = [self::vectorToStr($start) => 0];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract();
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $newCost = $cost[$currentKey] + 1;

            if (!isset($cost[$neighborKey]) || $newCost < $cost[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $cost[$neighborKey] = $newCost;
                $openSet->insert($neighbor, -$newCost);
            }
        }
    }
    return null;
}
    
    public function findPathGreedy(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, -$this->heuristic($start, $goal));

    $cameFrom = [];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract();
        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $cameFrom[self::vectorToStr($neighbor)] = $current;
            $openSet->insert($neighbor, -$this->heuristic($neighbor, $goal));
        }
    }
    return null;
}

    public function findPathBFS(World $world, Vector3 $start, Vector3 $goal): ?array {
        $queue = [[$start]];
        $visited = [self::vectorToStr($start) => true];

        while (!empty($queue)) {
            $path = array_shift($queue);
            $current = end($path);

            if ($current->equals($goal)) {
                return $path;
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                if (!isset($visited[$neighborKey])) {
                    $visited[$neighborKey] = true;
                    $newPath = $path;
                    $newPath[] = $neighbor;
                    $queue[] = $newPath;
                }
            }
        }
        return null;
    }

    public function findPathDFS(World $world, Vector3 $start, Vector3 $goal): ?array {
        $stack = [[$start]];
        $visited = [self::vectorToStr($start) => true];

        while (!empty($stack)) {
            $path = array_pop($stack);
            $current = end($path);

            if ($current->equals($goal)) {
                return $path;
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                if (!isset($visited[$neighborKey])) {
                    $visited[$neighborKey] = true;
                    $newPath = $path;
                    $newPath[] = $neighbor;
                    $stack[] = $newPath;
                }
            }
        }
        return null;
    }

    private function reconstructPath(array $cameFrom, Vector3 $current): array
    {
        $path = [$current];
        $currentKey = self::vectorToStr($current);
        while (isset($cameFrom[$currentKey])) {
            $current = $cameFrom[$currentKey];
            array_unshift($path, $current);
            $currentKey = self::vectorToStr($current);
        }
        return $path;
    }

    private function heuristic(Vector3 $a, Vector3 $b): float {
    // 유클리드 거리 사용
    return sqrt(pow($a->x - $b->x, 2) + pow($a->y - $b->y, 2) + pow($a->z - $b->z, 2));
}

/**
 * 이웃 노드 가져오기 (최적화 버전)
 */
private function getNeighbors(World $world, Vector3 $pos): array {
    $neighbors = [];
    $directions = [
        // 기본 4방향 (상하좌우)
        new Vector3(1, 0, 0),  // 오른쪽
        new Vector3(-1, 0, 0), // 왼쪽
        new Vector3(0, 0, 1),  // 앞쪽
        new Vector3(0, 0, -1), // 뒤쪽

        // 상하 이동 (필요한 경우)
        new Vector3(0, 1, 0),  // 위쪽
        new Vector3(0, -1, 0), // 아래쪽

        // 대각선 이동 (필요한 경우)
        new Vector3(1, 0, 1),  // 오른쪽 앞
        new Vector3(-1, 0, 1), // 왼쪽 앞
        new Vector3(1, 0, -1), // 오른쪽 뒤
        new Vector3(-1, 0, -1) // 왼쪽 뒤
    ];

    foreach ($directions as $dir) {
        $neighbor = $pos->addVector($dir);

        // 월드 경계 확인
        if (!$this->isWithinWorldBounds($neighbor)) {
            continue;
        }

        // 이동 가능한 노드인지 확인
        if ($this->isWalkable($world, $neighbor)) {
            $neighbors[] = $neighbor;
        }
    }

    return $neighbors;
}

/**
 * 월드 경계 내에 있는지 확인
 */
private function isWithinWorldBounds(Vector3 $pos): bool {
    $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
    $minX = 0;
    $maxX = $world->getMaxX();
    $minY = 0;
    $maxY = $world->getMaxY();
    $minZ = 0;
    $maxZ = $world->getMaxZ();

    return $pos->x >= $minX && $pos->x <= $maxX &&
           $pos->y >= $minY && $pos->y <= $maxY &&
           $pos->z >= $minZ && $pos->z <= $maxZ;
}

/**
 * 이동 가능한 노드인지 확인
 */
private function isWalkable(World $world, Vector3 $pos): bool {
    $block = $world->getBlockAt($pos->x, $pos->y, $pos->z);

    // 이동 가능한 블록인지 확인 (예: 공기, 풀 등)
    $walkableBlocks = [
        Block::AIR,
        Block::GRASS,
        Block::DIRT_PATH
    ];

    return in_array($block->getId(), $walkableBlocks);
}

private function isSolidBlock(Block $block): bool {
    $solidBlockNames = [
        "stone", "dirt", "cobblestone", "log", "planks", "brick", "sandstone",
        "obsidian", "bedrock", "iron_block", "gold_block", "diamond_block",
        "concrete", "concrete_powder", "netherrack", "end_stone", "deepslate",
        "water", "lava", "cactus" // 물, 용암, 선인장 추가
    ];

    $blockName = strtolower($block->getName());

    return in_array($blockName, $solidBlockNames);
}

private function isWalkableBlock(Block $block): bool {
    $walkableBlockNames = [
        "grass", "gravel", "sand", "stair", "slab", "path", "carpet",
        "farmland", "snow_layer", "soul_sand", "grass_path" // 추가적인 이동 가능 블록
    ];

    $blockName = strtolower($block->getName());

    return in_array($blockName, $walkableBlockNames);
}

}
