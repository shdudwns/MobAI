<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

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

        $motion = $direction->normalize()->multiply(0.12);

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.7) + ($motion->getX() * 0.3),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.7) + ($motion->getZ() * 0.3)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        // 좀비 발 위치에서 0.1블록 위를 기준으로 함
        $basePosition = new Vector3($position->getX(), $position->getY() + 0.1, $position->getZ());

        // 전방 3칸까지 확인
        for ($i = 1; $i <= 3; $i++) {
            $frontX = $basePosition->getX() + ($directionVector->getX() * $i);
            $frontZ = $basePosition->getZ() + ($directionVector->getZ() * $i);
            $frontPosition = new Vector3($frontX, $basePosition->getY(), $frontZ);

            $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
            $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());
            $blockAbove2InFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 2, (int)$frontPosition->getZ());
            $blockBelowInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() - 1, (int)$frontPosition->getZ()); // 아래 블록 확인

            // 전방 1칸 또는 2칸에 장애물이 있고, 착지 지점에 블록이 있는 경우 점프
            if (
                ($blockInFront instanceof Block && !$blockInFront->isTransparent() &&
                    $blockAboveInFront instanceof Block && $blockAboveInFront->isTransparent() &&
                    $blockAbove2InFront instanceof Block && $blockAbove2InFront->isTransparent() &&
                    $blockBelowInFront instanceof Block) ||
                ($i == 2 && $blockInFront instanceof Block && !$blockInFront->isTransparent() &&
                    $blockAboveInFront instanceof Block && $blockAboveInFront->isTransparent() &&
                    $blockBelowInFront instanceof Block)
            ) {
                $this->jump($mob);
                return;
            }
        }
    }

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
        $jumpForce = 0.42;
        $currentMotion = $mob->getMotion();
        $newMotion = new Vector3(
            $currentMotion->getX(),
            $currentMotion->getY() + $jumpForce, // 현재 Y 값에 jumpForce 추가
            $currentMotion->getZ()
        );
        $mob->setMotion($newMotion);
    }
}
