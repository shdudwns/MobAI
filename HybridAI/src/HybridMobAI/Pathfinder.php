<?php

namespace HybridMobAI;

class Pathfinder {

    public function findPath($start, $goal, $grid, $algorithm) {
        switch ($algorithm) {
            case "AStar":
                return $this->findPathAStar($start, $goal, $grid);
            case "BFS":
                return $this->findPathBFS($start, $goal, $grid);
            case "DFS":
                return $this->findPathDFS($start, $goal, $grid);
            default:
                return null;
        }
    }

    private function findPathAStar($start, $goal, $grid) {
        $openList = new \SplPriorityQueue();
        $closedList = [];
        $openList->insert($start, 0);

        while (!$openList->isEmpty()) {
            $current = $openList->extract();

            if ($current == $goal) {
                return $this->reconstructPath($current);
            }

            $closedList[] = $current;

            foreach ($this->getNeighbors($current, $grid) as $neighbor) {
                if (in_array($neighbor, $closedList)) {
                    continue;
                }

                $tentativeGScore = $current->g + $this->distance($current, $neighbor);

                if (!in_array($neighbor, $openList) || $tentativeGScore < $neighbor->g) {
                    $neighbor->cameFrom = $current;
                    $neighbor->g = $tentativeGScore;
                    $neighbor->f = $neighbor->g + $this->heuristic($neighbor, $goal);

                    if (!in_array($neighbor, $openList)) {
                        $openList->insert($neighbor, -$neighbor->f);
                    }
                }
            }
        }

        return null; // 경로 없음
    }

    private function findPathBFS($start, $goal, $grid) {
        $queue = new \SplQueue();
        $queue->enqueue($start);
        $visited = [$start];

        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();

            if ($current == $goal) {
                return $this->reconstructPath($current);
            }

            foreach ($this->getNeighbors($current, $grid) as $neighbor) {
                if (!in_array($neighbor, $visited)) {
                    $neighbor->cameFrom = $current;
                    $visited[] = $neighbor;
                    $queue->enqueue($neighbor);
                }
            }
        }

        return null; // 경로 없음
    }

    private function findPathDFS($start, $goal, $grid) {
        $stack = new \SplStack();
        $stack->push($start);
        $visited = [$start];

        while (!$stack->isEmpty()) {
            $current = $stack->pop();

            if ($current == $goal) {
                return $this->reconstructPath($current);
            }

            foreach ($this->getNeighbors($current, $grid) as $neighbor) {
                if (!in_array($neighbor, $visited)) {
                    $neighbor->cameFrom = $current;
                    $visited[] = $neighbor;
                    $stack->push($neighbor);
                }
            }
        }

        return null; // 경로 없음
    }

    private function getNeighbors($node, $grid) {
        // 이웃 노드 반환 로직
    }

    private function distance($nodeA, $nodeB) {
        // 노드 간의 실제 거리 계산
    }

    private function heuristic($nodeA, $nodeB) {
        // 휴리스틱 함수
    }

    private function reconstructPath($current) {
        $totalPath = [$current];
        while (isset($current->cameFrom)) {
            $current = $current->cameFrom;
            $totalPath[] = $current;
        }
        return array_reverse($totalPath);
    }
}