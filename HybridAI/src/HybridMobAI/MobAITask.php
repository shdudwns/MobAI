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

        $mobVec3 = new Vector3($mobPos->getX(), $mobPos->getY(), $mobPos->getZ());
        $playerVec3 = new Vector3($playerPos->getX(), $playerPos->getY(), $playerPos->getZ());

        $distance = $mobVec3->distance($playerVec3);
        $speed = 0.2;
        if ($distance < 5) {
            $speed *= $distance / 5;
        }

        $motion = $playerVec3->subtractVector($mobVec3)->normalize()->multiply($speed);

        $mob->setMotion($motion);
        $mob->lookAt($playerPos);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        static $isJumping = [];
        $entityId = $mob->getId();

        if (isset($isJumping[$entityId]) && $isJumping[$entityId]) {
            if ($mob->isOnGround()) {
                $isJumping[$entityId] = false;
            }
            return;
        }

        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);

        $positionVec3 = new Vector3($position->getX(), $position->getY(), $position->getZ());
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        $blockBelow = $world->getBlockAt((int)$positionVec3->floor()->getX(), (int)$positionVec3->floor()->getY() - 1, (int)$positionVec3->floor()->getZ());

        // 시야 범위 확장 (좌우 30도)
        for ($angle = -30; $angle <= 30; $angle += 10) {
            $rotatedDirection = $directionVector->rotateY($angle);

            for ($i = 1; $i <= 2; $i++) {
                $frontX = $positionVec3->getX() + ($rotatedDirection->getX() * $i);
                $frontZ = $positionVec3->getZ() + ($rotatedDirection->getZ() * $i);
                $frontPosition = new Vector3($frontX, $positionVec3->getY(), $frontZ);

                $blockInFront = $world->getBlockAt((int)$frontPosition->floor()->getX(), (int)$frontPosition->floor()->getY(), (int)$frontPosition->floor()->getZ());
                $blockAboveInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y + 1, (int)$frontPosition->z);
                $blockBelowInFront = $world->getBlockAt((int)$frontPosition->x, (int)$frontPosition->y - 1, (int)$frontPosition->z);

                if (
                    $blockInFront->isSolid() &&
                    $blockAboveInFront->isTransparent() &&
                    ($blockBelowInFront->isSolid() || $blockBelow->isSolid())
                ) {
                    $heightDiff = $blockInFront->getPosition()->getY() - $positionVec3->getY();

                    if ($heightDiff <= 1) {
                        $this->jump($mob);
                        $isJumping[$entityId] = true;
                        return;
                    }
                }
            }
        }
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
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
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
