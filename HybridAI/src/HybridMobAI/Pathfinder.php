<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Air;

class Pathfinder {
    private int $maxPathLength = 100;
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

    Public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $closedSet = [];
    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
    $visitedNodes = 0;

    $logData = "🔍 A* Search Start: ({$start->x}, {$start->y}, {$start->z}) → ({$goal->x}, {$goal->y}, {$goal->z})\n";

    $openSet->insert($start, -$fScore[self::vectorToStr($start)]);

    while (!$openSet->isEmpty()) {
        if ($visitedNodes >= $this->maxPathLength) {
            $logData .= "❌ A* 탐색 실패: 최대 탐색 노드 초과 ({$this->maxPathLength})\n";
            file_put_contents("path_logs/astar_log.txt", $logData, FILE_APPEND);
            return null;
        }

        $current = $openSet->extract();
        $currentKey = self::vectorToStr($current);
        if (isset($closedSet[$currentKey])) continue;
        $closedSet[$currentKey] = true;
        $visitedNodes++;

        if (isset($closedSet[$currentKey])) continue;
        $closedSet[$currentKey] = true;

        if ($current->equals($goal)) {
            $logData .= "✅ 경로 발견! 방문 노드 수: {$visitedNodes}\n";
            file_put_contents("path_logs/astar_log.txt", $logData, FILE_APPEND);
            return $this->reconstructPath($cameFrom, $current);
        }

        $neighbors = $this->getNeighbors($world, $current);

        foreach ($neighbors as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            if (isset($closedSet[$neighborKey])) continue;

            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);
                $openSet->insert($neighbor, -$fScore[$neighborKey]);

                $logData .= "🔹 Add Node: ({$neighbor->x}, {$neighbor->y}, {$neighbor->z}) | gScore: {$gScore[$neighborKey]} | fScore: {$fScore[$neighborKey]}\n";
            }
        }
    }

    $logData .= "⚠️ 경로 없음 (노드 방문: {$visitedNodes})\n";
    file_put_contents("path_logs/astar_log.txt", $logData, FILE_APPEND);
    
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
    return abs($a->x - $b->x) + abs($a->y - $b->y) + abs($a->z - $b->z);
}

/**
 * 이웃 노드 가져오기 (최적화 버전)
 */
private function getNeighbors(World $world, Vector3 $pos): array {
    $neighbors = [];
    $logData = "Neighbors for: ({$pos->x}, {$pos->y}, {$pos->z})\n";

    $directions = [
        [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1], // 기본 수평 이동
        [1, -1, 0], [-1, -1, 0], [0, -1, 1], [0, -1, -1], // 내려가기 가능 여부 확인
        [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1] // 점프 가능 여부 확인
    ];

    foreach ($directions as $dir) {
        $x = (int) $pos->x + $dir[0];
        $y = (int) $pos->y + $dir[1];
        $z = (int) $pos->z + $dir[2];

        $block = $world->getBlockAt($x, $y, $z);
        $blockBelow = $world->getBlockAt($x, $y - 1, $z);
        $blockBelow2 = $world->getBlockAt($x, $y - 2, $z);
        $blockAbove = $world->getBlockAt($x, $y + 1, $z);
        $blockAbove2 = $world->getBlockAt($x, $y + 2, $z); // 머리 위 추가 검사

        // ✅ 1. 공기(Air) 블록은 무조건 제외
        if ($block instanceof Air) {
            continue;
        }

        // ✅ 2. 발밑 블록이 Solid가 아니면 이동 불가 (예외: blockBelow2가 Solid면 가능)
        if ($blockBelow instanceof Air && $blockBelow2 instanceof Air) {
            $logData .= "❌ Block Below Not Solid: ({$x}, " . ($y - 1) . ", {$z}) - " . $blockBelow->getName() . "\n";
            continue;
        }

        // ✅ 3. 1칸 블록 위 점프 가능 (머리 위 공간이 있어야 함)
        if ($this->isSolidBlock($block)) {
            if (!$this->isSolidBlock($blockAbove)) {
                $logData .= "✅ Jumpable Block: ({$x}, {$y}, {$z}) - " . $block->getName() . "\n";
                $neighbors[] = new Vector3($x, $y + 1, $z);
                continue;
            } else {
                $logData .= "❌ Block Too High (Obstacle): ({$x}, {$y}, {$z}) - " . $block->getName() . "\n";
                continue;
            }
        }

        // ✅ 4. 머리 위 장애물 감지 (이동 가능하려면 2칸 이상 공간 필요)
        if ($this->isSolidBlock($blockAbove) && $this->isSolidBlock($blockAbove2)) {
            $logData .= "❌ Block Above Solid (Blocked): ({$x}, " . ($y + 1) . ", {$z}) - " . $blockAbove->getName() . "\n";
            continue;
        }

        // ✅ 이동 가능한 블록 추가
        $neighbors[] = new Vector3($x, $y, $z);
        $logData .= "✅ Valid Neighbor: ({$x}, {$y}, {$z}) - " . $block->getName() . "\n";
    }

    Server::getInstance()->broadcastMessage(" [AI] 탐색된 neighbors 수: " . count($neighbors) . " | 위치: " . (int)$pos->x . ", " . (int)$pos->y . ", " . (int)$pos->z);
    foreach ($neighbors as $neighbor) {
        Server::getInstance()->broadcastMessage("➡️ [AI] 이동 가능: " . (int)$neighbor->x . ", " . (int)$neighbor->y . ", " . (int)$neighbor->z);
    }
    // 파일로 로그 저장
    file_put_contents("path_logs/neighbors_log.txt", $logData . "\n", FILE_APPEND);

    return $neighbors;
}


private function isPassableBlock(Block $block): bool {
    $passableBlocks = [
        "air", "grass", "tall_grass", "carpet", "flower", "red_flower", "yellow_flower",
        "vine", "lily_pad", "button", "lever", "redstone_wire", "repeater",
        "comparator", "wall_torch", "ladder", "snow"
    ];

    return in_array(strtolower($block->getName()), $passableBlocks);
}

private function isSolidBlock(Block $block): bool {
    $solidBlockNames = [
        "stone", "dirt", "cobblestone", "log", "planks", "brick", "sandstone",
        "obsidian", "bedrock", "iron_block", "gold_block", "diamond_block",
        "concrete", "concrete_powder", "netherrack", "end_stone", "deepslate" // 통과 불가능한 블록만 포함
    ];

    return in_array(strtolower($block->getName()), $solidBlockNames);
}

}
