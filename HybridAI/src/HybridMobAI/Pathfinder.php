<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;

class Pathfinder {
    private World $world;

    public function __construct(World $world) {
        $this->world = $world;
    }

    public function findPathAStar(Vector3 $start, Vector3 $goal): ?array {
    $openSet = [$start];
    $openSetHash = [self::vectorToStr($start) => true];
    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];

    $maxDepth = 500;
    $depth = 0;

    while (!empty($openSet) && $depth < $maxDepth) {
        usort($openSet, fn($a, $b) => $fScore[self::vectorToStr($a)] <=> $fScore[self::vectorToStr($b)]);
        $current = array_shift($openSet);
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);

                if (!in_array($neighbor, $openSet, true)) {
                    $openSet[] = $neighbor;
                    $openSetHash[$neighborKey] = true;
                }
            }
        }

        $depth++;
    }

    return null;
}

private static function vectorToStr(Vector3 $vector): string {
    return "{$vector->x}:{$vector->y}:{$vector->z}";
}
public function findPathDijkstra(Vector3 $start, Vector3 $goal): ?array {
    $openSet = [$start];
    $cameFrom = [];
    $cost = [self::vectorToStr($start) => 0];

    while (!empty($openSet)) {
        usort($openSet, fn($a, $b) => $cost[self::vectorToStr($a)] <=> $cost[self::vectorToStr($b)]);
        $current = array_shift($openSet);
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $newCost = $cost[$currentKey] + 1;

            if (!isset($cost[$neighborKey]) || $newCost < $cost[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $cost[$neighborKey] = $newCost;
                $openSet[] = $neighbor;
            }
        }
    }
    return null;
}

public function findPathGreedy(Vector3 $start, Vector3 $goal): ?array {
    $current = $start;
    $path = [];

    while (!$current->equals($goal)) {
        $neighbors = $this->getNeighbors($current);
        usort($neighbors, fn($a, $b) => $this->heuristic($a, $goal) <=> $this->heuristic($b, $goal));

        if (empty($neighbors)) return null;

        $current = array_shift($neighbors);
        $path[] = $current;
    }

    return $path;
}
    public function findPathBFS(Vector3 $start, Vector3 $goal): ?array {
    $queue = [[$start]];
    $visited = [self::vectorToStr($start) => true];

    while (!empty($queue)) {
        $path = array_shift($queue);
        $current = end($path);

        if ($current->equals($goal)) {
            return $path;
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
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

public function findPathDFS(Vector3 $start, Vector3 $goal): ?array {
    $stack = [[$start]];
    $visited = [self::vectorToStr($start) => true];

    while (!empty($stack)) {
        $path = array_pop($stack);
        $current = end($path);

        if ($current->equals($goal)) {
            return $path;
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
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

    private function heuristic(Vector3 $a, Vector3 $b): float {
        return abs($a->x - $b->x) + abs($a->y - $b->y) + abs($a->z - $b->z);
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

    private function getNeighbors(Vector3 $pos): array {
    $neighbors = [];
    $directions = [
        new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
        new Vector3(0, 0, 1), new Vector3(0, 0, -1)
    ];

    foreach ($directions as $dir) {
        $neighbor = $pos->addVector($dir);
        $block = $this->world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        $blockBelow = $this->world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);

        if (!$block->isSolid() && $blockBelow->isSolid()) {
            $neighbors[] = $neighbor;
        }
    }

    return $neighbors;
}
}

class PathfinderTask extends AsyncTask {
    private string $worldName;
    private string $startPos;
    private string $goalPos;
    private string $algorithm;

    public function __construct(string $worldName, Vector3 $start, Vector3 $goal, string $algorithm) {
        $this->worldName = $worldName;
        $this->startPos = "{$start->x}:{$start->y}:{$start->z}";
        $this->goalPos = "{$goal->x}:{$goal->y}:{$goal->z}";
        $this->algorithm = $algorithm;
    }

    public function onRun(): void {
        // ✅ 비동기 경로 탐색 로직 수행
        $start = $this->parseVector($this->startPos);
        $goal = $this->parseVector($this->goalPos);
        
        $pathfinder = new Pathfinder();
        $path = match ($this->algorithm) {
            "A*" => $pathfinder->findPathAStar($start, $goal),
            "BFS" => $pathfinder->findPathBFS($start, $goal),
            "DFS" => $pathfinder->findPathDFS($start, $goal),
            "Dijkstra" => $pathfinder->findPathDijkstra($start, $goal),
            "Greedy" => $pathfinder->findPathGreedy($start, $goal),
            default => null
        };

        $this->setResult($path);
    }

    private function parseVector(string $str): Vector3 {
        [$x, $y, $z] = explode(":", $str);
        return new Vector3((float)$x, (float)$y, (float)$z);
    }
}
