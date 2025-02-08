<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;
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

        $plugin = $this->plugin;
        $entityAI = $this; // $this 캡쳐!

        Server::getInstance()->getAsyncPool()->submitTask(new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callback, $entityAI, $plugin) extends AsyncTask {
            private string $worldName;
            private float $startX;
            private float $startY;
            private float $startZ;
            private float $goalX;
            private float $goalY;
            private float $goalZ;
            private string $algorithm;
            private $callback;
            private EntityAI $entityAI; // 캡쳐한 $this 저장
            private PluginBase $plugin;

            public function __construct(string $worldName, float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, string $algorithm, callable $callback, EntityAI $entityAI, PluginBase $plugin) {
                $this->worldName = $worldName;
                $this->startX = $startX;
                $this->startY = $startY;
                $this->startZ = $startZ;
                $this->goalX = $goalX;
                $this->goalY = $goalY;
                $this->goalZ = $goalZ;
                $this->algorithm = $algorithm;
                $this->callback = $callback;
                $this->entityAI = $entityAI; // 캡쳐한 $this 할당
                $this->plugin = $plugin;
            }

            public function onRun(): void {
                $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
                if (!$world instanceof World) {
                    $this->setResult(null);
                    return;
                }

                $start = new Vector3($this->startX, $this->startY, $this->startZ);
                $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
                $pathfinder = new Pathfinder();

                $path = match ($this->algorithm) {
                    "A*" => $pathfinder->findPathAStar($world, $start, $goal),
                    "Dijkstra" => $pathfinder->findPathDijkstra($world, $start, $goal),
                    "Greedy" => $pathfinder->findPathGreedy($world, $start, $goal),
                    "BFS" => $pathfinder->findPathBFS($world, $start, $goal),
                    "DFS" => $pathfinder->findPathDFS($world, $start, $goal),
                    default => null,
                };

                $this->setResult($path);
            }

            public function onCompletion(): void {
                $result = $this->getResult();
                ($this->callback)($result);

                if ($result !== null) {
                    $this->plugin->getLogger()->info("경로 탐색 완료!");
                    // $this 사용 예시 (캡쳐한 $entityAI 사용):
                     $this->entityAI->setPath($someMob, $result); // someMob은 Living Entity
                } else {
                    $this->plugin->getLogger()->info("경로를 찾을 수 없습니다.");
                }
            }
        });
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
