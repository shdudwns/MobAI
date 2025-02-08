<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use pocketmine\plugin\PluginBase;

class EntityAI {
    private bool $enabled = false;
    private array $path = [];
    private ?Vector3 $target = null;
    private array $entityPaths = [];
    private PluginBase $plugin;

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
    }

    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPathAsync(World $world, Position $start, Position $goal, string $algorithm, callable $callback): void {
        $worldName = $world->getFolderName();
        $startX = $start->x;
        $startY = $start->y;
        $startZ = $start->z;
        $goalX = $goal->x;
        $goalY = $goal->y;
        $goalZ = $goal->z;

        $task = new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callback) extends AsyncTask {
            private string $worldName;
            private float $startX;
            private float $startY;
            private float $startZ;
            private float $goalX;
            private float $goalY;
            private float $goalZ;
            private string $algorithm;
            private \Closure $callback;

            public function __construct(
                string $worldName,
                float $startX, float $startY, float $startZ,
                float $goalX, float $goalY, float $goalZ,
                string $algorithm,
                \Closure $callback
            ) {
                $this->worldName = $worldName;
                $this->startX = $startX;
                $this->startY = $startY;
                $this->startZ = $startZ;
                $this->goalX = $goalX;
                $this->goalY = $goalY;
                $this->goalZ = $goalZ;
                $this->algorithm = $algorithm;
                $this->callback = $callback;
            }

            public function onRun(): void {
                $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
                if (!$world instanceof World) {
                    $this->setResult(null);
                    return;
                }

                $start = new Vector3($this->startX, $this->startY, $this->startZ);
                $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
                $pathfinder = new Pathfinder(); // Pathfinder 객체 onRun() 내부에서 생성

                switch ($this->algorithm) {
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

                $this->setResult($path);
            }

            public function onCompletion(): void {
                $result = $this->getResult();
                if (isset($this->callback)) {
                    ($this->callback)($result);
                }
            }
        };

        Server::getInstance()->getAsyncPool()->submitTask($task);
    }


    public function findPath(World $world, Vector3 $start, Vector3 $goal, string $algorithm): ?array {
        $pathfinder = new Pathfinder();

        switch ($algorithm) {
            case "A*":
                return $pathfinder->findPathAStar($world, $start, $goal);
            case "Dijkstra":
                return $pathfinder->findPathDijkstra($world, $start, $goal);
            case "Greedy":
                return $pathfinder->findPathGreedy($world, $start, $goal);
            case "BFS":
                return $pathfinder->findPathBFS($world, $start, $goal);
            case "DFS":
                return $pathfinder->findPathDFS($world, $start, $goal);
            default:
                return null;
        }
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
