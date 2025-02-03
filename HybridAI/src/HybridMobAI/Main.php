<?php

namespace HybridMobAI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Creature;
use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->saveDefaultConfig(); // 기본 구성 파일 저장
        $this->reloadConfig(); // 구성 파일 불러오기

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new MobAITask($this), 20); // 20 ticks (1 second)마다 반복

        // 주기적으로 좀비 생성하는 작업 추가
        $spawnInterval = $this->getConfig()->get("spawn_interval", 600); // 기본값 600 ticks (30 seconds)
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->spawnRandomZombies();
        }), $spawnInterval);
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Creature) {
            $this->handleDamageResponse($entity, $event->getDamager());
        }
    }

    private function handleDamageResponse(Creature $mob, $damager): void {
        if ($damager instanceof Player) {
            $mob->lookAt($damager->getPosition());
            $mob->move($damager->getDirectionVector()->multiply(0.25)); // 공격자에게 이동
        }
    }

    private function spawnRandomZombies(): void {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getPlayers() as $player) {
                $this->getServer()->getAsyncPool()->submitTask(new SpawnZombiesTask($world->getId(), $player->getPosition()));
            }
        }
    }

    public function spawnZombieAt(World $world, Vector3 $position): void {
        $nbt = Entity::createBaseNBT($position);
        $zombie = new Zombie($world, $nbt);
        $zombie->spawnToAll();
    }
}
