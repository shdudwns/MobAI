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

        $blockBelow = $world->getBlockAt((int)$position->getX(), (int)$position->getY() - 1, (int)$position->getZ());

        if ($blockBelow instanceof Block && !$blockBelow->isTransparent()) {
            for ($i = 1; $i <= 2; $i++) {
                $frontPosition = $position->addVector($directionVector->multiply($i));
                $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
                $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());
                $blockAbove2InFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 2, (int)$frontPosition->getZ());

                if ($blockInFront instanceof Block && !$blockInFront->isTransparent() && 
                    $blockAboveInFront instanceof Block && $blockAboveInFront->isTransparent() &&
                    $blockAbove2InFront instanceof Block && $blockAbove2InFront->isTransparent()) {
                    $this->jump($mob);
                    return;
                }
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
        $jumpForce = 0.6;
        $currentMotion = $mob->getMotion();
        $newMotion = new Vector3(
            $currentMotion->getX(),
            $jumpForce,
            $currentMotion->getZ()
        );
        $mob->setMotion($newMotion);
    }
}
