<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Pathfinder {
    private int $maxPathLength = 50;
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

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array
    {
        $openSet = new \SplPriorityQueue();
        $openSet->insert($start, 0);

        $cameFrom = [];
        $gScore = [self::vectorToStr($start) => 0];
        $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
        $visitedNodes = 0;

        while (!$openSet->isEmpty() && $visitedNodes < $this->maxPathLength) {
            $current = $openSet->extract();
            $visitedNodes++;
            if ($current->equals($goal)) {
                return $this->reconstructPath($cameFrom, $current);
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                $tentativeGScore = $gScore[self::vectorToStr($current)] + 1;

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

    private function heuristic(Vector3 $a, Vector3 $b): float
    {
        $dx = abs($a->x - $b->x);
        $dy = abs($a->y - $b->y);
        $dz = abs($a->z - $b->z);
        return $dx + $dy + $dz; // 맨해튼 거리 사용
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

    private function getNeighbors(World $world, Vector3 $pos): iterable {
        $x = (int)$pos->x;
        $y = (int)$pos->y;
        $z = (int)$pos->z;

        // 핵심 이동 방향 (상, 하, 좌, 우)
        $coreDirections = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1),
        ];

        foreach ($coreDirections as $dir) {
            $neighbor = $this->getVector($x + $dir->x, $y + $dir->y, $z + $dir->z);
            $block = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
            $blockBelow = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);
            if (!$block->isSolid() && $blockBelow->isSolid()) {
                yield $neighbor;
            }
        }

        // 수직 이동 (위, 아래) - 필요에 따라 활성화
        $verticalDirections = [
            new Vector3(0, 1, 0), new Vector3(0, -1, 0),
        ];

        foreach ($verticalDirections as $dir) {
            $neighbor = $this->getVector($x + $dir->x, $y + $dir->y, $z + $dir->z);
            if ($dir->y === 1) { // 위로 이동
                $blockAbove = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y + 1), (int)$neighbor->z);
                $block = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);

                if (!$block->isSolid() && !$blockAbove->isSolid()) {
                    yield $neighbor;
                }
            } elseif ($dir->y === -1) { // 아래로 이동
                $blockBelow = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);
                if (!$blockBelow->isSolid()) {
                    yield $neighbor;
                }
            }
        }


        // 대각선 이동 (x, z) - 필요에 따라 활성화 및 가중치 조절
        $diagonalXZDirections = [
            new Vector3(1, 0, 1), new Vector3(1, 0, -1),
            new Vector3(-1, 0, 1), new Vector3(-1, 0, -1),
        ];

        foreach ($diagonalXZDirections as $dir) {
            $neighbor = $this->getVector($x + $dir->x, $y + $dir->y, $z + $dir->z);
            $block = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
            $blockBelow = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);
            if (!$block->isSolid() && $blockBelow->isSolid()) {
                yield $neighbor;
            }
        }
    }
}
