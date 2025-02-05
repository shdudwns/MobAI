<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\entity\Creature;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0; // ✅ 실행 주기 관리

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        // ✅ AI를 10 ticks(0.5초)마다 한 번만 실행
        if ($this->tickCounter % 10 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }

        // ✅ 서버 과부하 방지를 위해 10초마다 1번만 로그 출력
        if ($this->tickCounter % 200 === 0) {
            $this->plugin->getLogger()->info("MobAITask 실행 중...");
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        // ✅ 장애물 감지 후 점프 실행
        $this->checkForObstaclesAndJump($mob);
    }

    private function findNearestPlayer(Zombie $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX;
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distance($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $direction = new Vector3(
            $playerPos->getX() - $mobPos->getX(),
            0,
            $playerPos->getZ() - $mobPos->getZ()
        );

        $motion = $direction->normalize()->multiply(0.15);
        $mob->setMotion($motion);
        $mob->lookAt($playerPos);
    }

    /** ✅ 장애물 감지 후 점프 */
    
    private function checkForObstaclesAndJump(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    
    // ✅ `VectorMath::getDirection2D()`는 `Vector2`를 반환하므로, `Vector3`로 변환 필요
    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw); // ✅ Vector2 반환
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY()); // ✅ Vector3 변환

    $frontPosition = new Vector3(
        $position->getX() + $directionVector->getX(),
        $position->getY(),
        $position->getZ() + $directionVector->getZ()
    );

    $blockInFront = $world->getBlockAt((int) $frontPosition->getX(), (int) $frontPosition->getY(), (int) $frontPosition->getZ());
    $blockAboveInFront = $world->getBlockAt((int) $frontPosition->getX(), (int) $frontPosition->getY() + 1, (int) $frontPosition->getZ());

    if ($blockInFront !== null && !$blockInFront->isTransparent() && $blockAboveInFront !== null && $blockAboveInFront->isTransparent()) {
        $this->jump($mob);
        }
    }

    /** ✅ 랜덤 이동 */
    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }

    /** ✅ 점프 */
    public function jump(Living $mob): void {
        $jumpForce = 0.6;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }

    private function createGrid(World $world): array {
        return []; // TODO: 실제 맵 데이터 기반 경로 탐색 구현
    }

    private function selectAlgorithm(): string {
        $algorithms = ["AStar", "BFS", "DFS"];
        return $algorithms[array_rand($algorithms)];
    }

    private function attackPlayer(Creature $mob): void {
        // TODO: 몬스터 공격 로직 추가
    }

    private function retreat(Creature $mob): void {
        // TODO: 후퇴 로직 추가
    }
}
