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
        $entityAI = $this;

        // *** 캡처할 모든 데이터를 이 곳에서 캡처합니다. ***
        $capturedThis = $this; // $this (EntityAI 인스턴스) 캡처
        $capturedWorld = $world; // $world 캡처
        $capturedStart = $start; // $start 캡처
        $capturedGoal = $goal; // $goal 캡처
        $capturedAlgorithm = $algorithm; // $algorithm 캡처

        // *** 콜백 함수를 래핑하는 함수를 만듭니다. ***
        $wrappedCallback = function($result) use ($capturedThis, $capturedWorld, $capturedStart, $capturedGoal, $capturedAlgorithm, $callback) {
            // 이제 $capturedThis (및 기타 캡처된 데이터)를 안전하게 사용할 수 있습니다.
            $callback($result, $capturedThis, $capturedWorld, $capturedStart, $capturedGoal, $capturedAlgorithm);
        };


        Server::getInstance()->getAsyncPool()->submitTask(new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $wrappedCallback, $entityAI, $plugin) extends AsyncTask {
            private string $worldName;
            private float $startX;
            private float $startY;
            private float $startZ;
            private float $goalX;
            private float $goalY;
            private float $goalZ;
            private string $algorithm;
            private $wrappedCallback; // 래핑된 콜백 저장
            private EntityAI $entityAI;
            private PluginBase $plugin;

            // *** 캡처한 데이터를 저장할 속성을 추가합니다. ***
            private EntityAI $capturedThis;
            private World $capturedWorld;
            private Position $capturedStart;
            private Position $capturedGoal;
            private string $capturedAlgorithm;


            public function __construct(string $worldName, float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, string $algorithm, callable $wrappedCallback, EntityAI $entityAI, PluginBase $plugin) {
                $this->worldName = $worldName;
                $this->startX = $startX;
                $this->startY = $startY;
                $this->startZ = $startZ;
                $this->goalX = $goalX;
                $this->goalY = $goalY;
                $this->goalZ = $goalZ;
                $this->algorithm = $algorithm;
                $this->wrappedCallback = $wrappedCallback; // 래핑된 콜백 할당
                $this->entityAI = $entityAI;
                $this->plugin = $plugin;

                // *** 캡처한 데이터를 속성에 저장합니다. ***
                $this->capturedThis = $capturedThis;
                $this->capturedWorld = $capturedWorld;
                $this->capturedStart = $capturedStart;
                $this->capturedGoal = $capturedGoal;
                $this->capturedAlgorithm = $capturedAlgorithm;
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

                // *** 래핑된 콜백 함수를 호출합니다. ***
                ($this->wrappedCallback)($result);

                if ($result !== null) {
                    $this->plugin->getLogger()->info("경로 탐색 완료!");
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
