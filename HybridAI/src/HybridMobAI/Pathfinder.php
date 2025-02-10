<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Air;

class Pathfinder {
    private int $maxPathLength = 500;
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

    $openSet->insert($start, -$fScore[self::vectorToStr($start)]);

    while (!$openSet->isEmpty()) {
        if ($visitedNodes >= $this->maxPathLength) {
            Server::getInstance()->broadcastMessage("❌ [AI] A* 탐색 실패: 최대 탐색 노드 초과 ({$this->maxPathLength})");
            return null;
        }

        $current = $openSet->extract();
        $currentKey = self::vectorToStr($current);
        $visitedNodes++;

        if (isset($closedSet[$currentKey])) continue;
        $closedSet[$currentKey] = true;

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        $neighbors = $this->getNeighbors($world, $current);

        // ✅ 탐색 노드 개수를 줄이기 위해 랜덤 섞기 (우선순위 변경)
        shuffle($neighbors);
        $neighbors = array_slice($neighbors, 0, 4); // 최대 4개만 처리

        foreach ($neighbors as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            if (isset($closedSet[$neighborKey])) continue;

            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);
                $openSet->insert($neighbor, -$fScore[$neighborKey]);
            }
        }
    }

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
        [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1], // 기본 수평 이동
        [1, -1, 0], [-1, -1, 0], [0, -1, 1], [0, -1, -1], // 한 칸 내려가기
        [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1] // 한 칸 점프 가능
    ];

    foreach ($directions as $dir) {
        $x = (int)$pos->x + $dir[0];
        $y = (int)$pos->y + $dir[1];
        $z = (int)$pos->z + $dir[2];

        $block = $world->getBlockAt($x, $y, $z);
        $blockBelow = $world->getBlockAt($x, $y - 1, $z);
        $blockAbove = $world->getBlockAt($x, $y + 1, $z);
        
        // ✅ 장애물(벽, 2칸 이상 블록) 감지
        if ($this->isSolidBlock($blockAbove)) {
            continue;
        }

        // ✅ 한 칸 내려가기 가능 여부 체크
        if ($dir[1] === -1 && !$this->isSolidBlock($blockBelow)) {
            continue;
        }

        // ✅ 한 칸 점프 가능 여부 체크
        if ($dir[1] === 1 && $this->isSolidBlock($block)) {
            continue;
        }

        // ✅ 이동 가능 블록 체크
        if (!$this->isObstacle($block)) {
            $neighbors[] = new Vector3($x, $y, $z);
        }
    }

    // ✅ 탐색된 `neighbors` 값 디버깅 메시지 출력
    //Server::getInstance()->broadcastMessage("🔍 [AI] 탐색된 neighbors 수: " . count($neighbors) . " | 위치: {$pos->x}, {$pos->y}, {$pos->z}");
   /* foreach ($neighbors as $neighbor) {
        Server::getInstance()->broadcastMessage("➡️ [AI] 이동 가능: {$neighbor->x}, {$neighbor->y}, {$neighbor->z}");
    }*/

    return $neighbors;
}

    private function isObstacle(Block $block): bool {
    $obstacleBlocks = [
        "fence", "wall", "iron_door", "wooden_door", "trapdoor",
        "cactus", "fire", "lava", "water"
    ];

    $blockName = strtolower($block->getName());

    return in_array($blockName, $obstacleBlocks) || $this->isSolidBlock($block);
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
