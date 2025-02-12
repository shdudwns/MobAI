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

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
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
        $visitedNodes++;

        // ✅ 이미 방문한 노드는 다시 탐색하지 않도록 함
        if (isset($closedSet[$currentKey])) continue;
        $closedSet[$currentKey] = true;

        // ✅ 목적지 근처인지 빠르게 확인하여 조기 종료
        if ($current->distanceSquared($goal) <= 2) {
            $logData .= "✅ 경로 발견! 방문 노드 수: {$visitedNodes}\n";
            file_put_contents("path_logs/astar_log.txt", $logData, FILE_APPEND);
            return $this->reconstructPath($cameFrom, $current);
        }

        $neighbors = $this->getNeighbors($world, $current);

        // ✅ 탐색 범위를 줄이기 위해 이웃 노드를 정렬 후 최대 4개만 선택
        usort($neighbors, function ($a, $b) use ($goal) {
            return $a->distanceSquared($goal) <=> $b->distanceSquared($goal);
        });

        $neighbors = array_slice($neighbors, 0, 4);

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
    // 유클리드 거리 사용
    return sqrt(pow($a->x - $b->x, 2) + pow($a->y - $b->y, 2) + pow($a->z - $b->z, 2));
}

/**
 * 이웃 노드 가져오기 (최적화 버전)
 */
public function getNeighbors(World $world, Vector3 $pos): array {
    $neighbors = [];
    $logData = "Neighbors for: (" . (int)$pos->x . ", " . (int)$pos->y . ", " . (int)$pos->z . ")\n";

    $directions = [
        [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1], // 기본 4방향 이동
        [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1], // 점프 가능 여부 확인
        [1, -1, 0], [-1, -1, 0], [0, -1, 1], [0, -1, -1], // 내려가기 가능 여부 확인
        [1, 0, 1], [-1, 0, -1], [1, 0, -1], [-1, 0, 1] // ✅ 대각선 이동 추가
    ];

    foreach ($directions as $dir) {
        $x = (int)$pos->x + $dir[0];
        $y = (int)$pos->y + $dir[1];
        $z = (int)$pos->z + $dir[2];

        $block = $world->getBlockAt($x, $y, $z);
        $blockBelow = $world->getBlockAt($x, $y - 1, $z);
        $blockAbove = $world->getBlockAt($x, $y + 1, $z);
        $blockAbove2 = $world->getBlockAt($x, $y + 2, $z); // 머리 위 추가 검사

        // ✅ 두 칸 이상 블록이 쌓여있으면 장애물로 인식
        if ($this->isSolidBlock($block) && $this->isSolidBlock($blockAbove)) {
            $logData .= "❌ 장애물 감지 (두 칸 블록): ({$x}, {$y}, {$z}) - " . $block->getName() . "\n";
            continue;
        }

        // ✅ 머리 위 두 칸이 막히면 이동 불가
        if ($this->isSolidBlock($blockAbove) && $this->isSolidBlock($blockAbove2)) {
            $logData .= "❌ 장애물 감지 (머리 위 차단): ({$x}, " . ($y + 1) . ", {$z}) - " . $blockAbove->getName() . "\n";
            continue;
        }

        $neighbors[] = new Vector3($x, $y, $z);
        $logData .= "✅ Valid Neighbor: ({$x}, {$y}, {$z}) - " . $block->getName() . "\n";
    }

    file_put_contents("path_logs/neighbors_log.txt", $logData . "\n", FILE_APPEND);
    return $neighbors;
}
    
    private function isPassableBlock(Block $block): bool {
    $passableBlocks = [
        "grass", "tall_grass", "carpet", "flower", "red_flower", "yellow_flower",
        "vine", "lily_pad", "button", "lever", "redstone_wire", "repeater",
        "comparator", "wall_torch", "ladder", "snow"
    ];

    return in_array(strtolower($block->getName()), $passableBlocks);
}
    private function isSolidBlock(Block $block): bool {
    if ($block->isSolid()) {
        return true;
    }

    $solidBlockNames = [
        "stone", "dirt", "cobblestone", "log", "planks", "brick", "sandstone",
        "obsidian", "bedrock", "iron_block", "gold_block", "diamond_block",
        "concrete", "concrete_powder", "netherrack", "end_stone", "deepslate",
        "glass", "chest", "crafting_table", "furnace", "door", "trapdoor"
    ];

    return in_array(strtolower($block->getName()), $solidBlockNames);
}
}
