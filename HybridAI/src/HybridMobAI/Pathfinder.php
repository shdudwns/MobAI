<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Air;

class Pathfinder {
    private int $maxPathLength = 200;
    private array $vectorPool = [];
    private array $cachedNeighbors = []; // ✅ 이웃 노드 캐싱

    private function getVector(float $x, float $y, float $z): Vector3 {
        $key = "{$x}:{$y}:{$z}";
        if (!isset($this->vectorPool[$key])) {
            $this->vectorPool[$key] = new Vector3($x, $y, $z);
        }
        return $this->vectorPool[$key];
    }

    /**
     * 🔥 Vector3 → 문자열 변환 (키 생성)
     */
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
        $openSet = new \SplPriorityQueue();
        $openSet->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        $openSet->insert($start, 0);
        
        $cameFrom = [];
        $gScore = [self::vectorToStr($start) => 0];
        $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
        $visitedNodes = 0;

        while (!$openSet->isEmpty()) {
            $current = $openSet->extract();
            $currentKey = self::vectorToStr($current);

            if ($current->distanceSquared($goal) <= 2) {
                return $this->reconstructPath($cameFrom, $current);
            }

            if ($visitedNodes++ >= $this->maxPathLength) {
                return null;
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                $tentativeGScore = $gScore[$currentKey] + 1;

                if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                    $cameFrom[$neighborKey] = $current;
                    $gScore[$neighborKey] = $tentativeGScore;
                    $fScore[$neighborKey] = $tentativeGScore + $this->heuristic($neighbor, $goal);
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

    private function reconstructPath(array $cameFrom, Vector3 $current): array {
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
        $dx = abs($a->x - $b->x);
        $dy = abs($a->y - $b->y);
        $dz = abs($a->z - $b->z);
        $dmax = max($dx, $dz);
        $dmin = min($dx, $dz);
        return $dmax + 0.414 * $dmin + $dy;
    }
/**
 * 이웃 노드 가져오기 (최적화 버전)
 */
public function getNeighbors(World $world, Vector3 $pos): array {
        $key = self::vectorToStr($pos);

        // ✅ 캐싱 적용
        if (isset($this->cachedNeighbors[$key])) {
            return $this->cachedNeighbors[$key];
        }

        $neighbors = [];
        $directions = [
            [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1],
            [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1],
            [1, -1, 0], [-1, -1, 0], [0, -1, 1], [0, -1, -1],
            [1, 0, 1], [-1, 0, -1], [1, 0, -1], [-1, 0, 1],
            [0, 1, 0], [0, -1, 0]
        ];

        foreach ($directions as $dir) {
            $x = (int)$pos->x + $dir[0];
            $y = (int)$pos->y + $dir[1];
            $z = (int)$pos->z + $dir[2];

            $block = $world->getBlockAt($x, $y, $z);
            $blockBelow = $world->getBlockAt($x, $y - 1, $z);
            $blockAbove = $world->getBlockAt($x, $y + 1, $z);

            // ✅ 머리 위 두 칸 검사
            if ($this->isSolidBlock($block) || $this->isSolidBlock($blockAbove)) {
                continue;
            }

            // ✅ 발밑이 단단한 블록이어야 이동 가능
            if ($this->isSolidBlock($blockBelow)) {
                $neighbors[] = $this->getVector($x, $y, $z);
            }
        }

        // ✅ 캐싱 저장
        $this->cachedNeighbors[$key] = $neighbors;
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
