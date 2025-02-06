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
    private int $tickCounter = 0;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        // ✅ 실행 주기를 2 ticks(0.1초)로 설정하여 더 빠르게 반응
        if ($this->tickCounter % 2 !== 0) {
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

        $motion = $direction->normalize()->multiply(0.12); // ✅ 속도를 줄여 더 부드럽게 이동

        // ✅ 기존 모션과 새로운 모션을 70:30 비율로 보간 (Lerp)
        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.7) + ($motion->getX() * 0.3),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.7) + ($motion->getZ() * 0.3)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    /** ✅ 장애물 감지 후 점프 (높이 2 블록까지 가능) */
    private function checkForObstaclesAndJump(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    // ✅ `addVector()`를 사용하여 `Vector3` 더하기
    $frontPosition = $position->addVector($directionVector);

    $blockInFront = $world->getBlockAt((int) $frontPosition->getX(), (int) $frontPosition->getY(), (int) $frontPosition->getZ());
    $blockAboveInFront = $world->getBlockAt((int) $frontPosition->getX(), (int) $frontPosition->getY() + 1, (int) $frontPosition->getZ());
    //$blockAbove2InFront = $world->getBlockAt((int) $frontPosition->getX(), (int) $frontPosition->getY() + 2, (int) $frontPosition->getZ());

    if ($blockInFront !== null && !$blockInFront->isTransparent() && $blockAboveInFront !== null && $blockAboveInFront->isTransparent()) {
        $this->jump($mob);
    }
}

    /** ✅ 랜덤 이동 (부드러운 방향 전환) */
    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        
        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.8) + ($randomDirection->getX() * 0.2),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.8) + ($randomDirection->getZ() * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    public function jump(Living $mob): void {
        $jumpForce = 0.6;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
