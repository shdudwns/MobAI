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
        $this->getLogger()->info("HybridMobAI 플러그인 활성화");
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new MobAITask($this), 20);

        $spawnInterval = $this->getConfig()->get("spawn_interval", 600);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->spawnRandomZombies();
        }), $spawnInterval);
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Creature) {
            $this->getLogger()->info("몹이 플레이어에게 피해를 입음: " . $entity->getName());
            $this->handleDamageResponse($entity, $event->getDamager());
        }
    }

    private function handleDamageResponse(Creature $mob, $damager): void {
        if ($damager instanceof Player) {
            $this->getLogger()->info("몹이 플레이어를 향해 이동: " . $mob->getName());
            $mob->lookAt($damager->getPosition());
            $mob->move($damager->getDirectionVector()->multiply(0.25));
        }
    }

    private function spawnRandomZombies(): void {
        $this->getLogger()->info("랜덤 좀비 생성 시작");
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getPlayers() as $player) {
                $this->spawnZombieInFrontOfPlayer($player);
            }
        }
    }

    public function spawnZombieInFrontOfPlayer(Player $player): void {
        $this->getLogger()->info("플레이어 앞에 좀비 스폰 위치: " . $player->getPosition()->__toString());
        $direction = $player->getDirectionVector()->normalize()->multiply(2); // 2 블록 앞에 좀비 생성
        $spawnPosition = $player->getPosition()->add($direction);
        $this->spawnZombieAt($player->getWorld(), $spawnPosition);
    }

    public function spawnZombieAt(World $world, Vector3 $position): void {
        $this->getLogger()->info("좀비 스폰 위치: " . $position->__toString());
        $nbt = Entity::createBaseNBT($position);
        $zombie = new Zombie($world, $nbt);
        $this->getLogger()->info("좀비 인스턴스 생성 완료");
        $zombie->spawnToAll();
        $this->getLogger()->info("좀비 스폰 완료");
    }
}
