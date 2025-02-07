<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;

class Pathfinder {
    private World $world;

    public function __construct(World $world) {
        $this->world = $world;
    }

    public function findPath(Vector3 $start, Vector3 $goal): ?array {
        $openSet = [$start];
        $cameFrom = [];
        $gScore = [spl_object_hash($start) => 0];
        $fScore = [spl_object_hash($start) => $this->heuristic($start, $goal)];

        while (!empty($openSet)) {
            usort($openSet, function($a, $b) use ($fScore) {
                return $fScore[spl_object_hash($a)] <=> $fScore[spl_object_hash($b)];
            });

            $current = array_shift($openSet);

            if ($current->equals($goal)) {
                return $this->reconstructPath($cameFrom, $current);
            }

            foreach ($this->getNeighbors($current) as $neighbor) {
                $tentativeGScore = $gScore[spl_object_hash($current)] + 1;
                $neighborHash = spl_object_hash($neighbor);

                if (!isset($gScore[$neighborHash]) || $tentativeGScore < $gScore[$neighborHash]) {
                    $cameFrom[$neighborHash] = $current;
                    $gScore[$neighborHash] = $tentativeGScore;
                    $fScore[$neighborHash] = $gScore[$neighborHash] + $this->heuristic($neighbor, $goal);

                    if (!in_array($neighbor, $openSet, true)) {
                        $openSet[] = $neighbor;
                    }
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

        while (isset($cameFrom[spl_object_hash($current)])) {
            $current = $cameFrom[spl_object_hash($current)];
            array_unshift($path, $current);
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

            if (!$block->isSolid()) {
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }
}
