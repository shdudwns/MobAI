<?php

namespace HybridMobAI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\Zombie as PmmpZombie;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->getLogger()->info("HybridMobAI 플러그인 활성화");

        // 커스텀 좀비 엔티티 등록
        EntityFactory::getInstance()->register(Zombie::class, function(World $world, CompoundTag $nbt): Zombie {
            return new Zombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['Zombie', 'minecraft:zombie']);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();

        // 기본 좀비인지 확인
        if ($entity instanceof PmmpZombie) {
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($entity): void {
                $this->replaceWithCustomZombie($entity);
            }), 1); // 1 tick 후에 교체
        }
    }

    private function replaceWithCustomZombie(PmmpZombie $pmmpZombie): void {
        $world = $pmmpZombie->getWorld();
        $location = $pmmpZombie->getLocation();

        // 기본 좀비 제거
        $pmmpZombie->flagForDespawn();

        // 커스텀 좀비 생성 및 스폰
        $nbt = CompoundTag::create();
        $customZombie = new Zombie($location, $nbt);
        $customZombie->spawnToAll();
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        // 엔티티가 Living(생명체)인지 확인
        if ($entity instanceof Living) {
            $this->getLogger()->info("몹이 피해를 입음: " . $entity->getName());

            // 공격자가 있는 경우(EntityDamageByEntityEvent인지 확인)
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                $this->handleDamageResponse($entity, $damager);
            }
        }
    }

    private function handleDamageResponse(Living $mob, $damager): void {
        if ($damager instanceof Player) {
            $this->getLogger()->info("몹이 플레이어를 향해 이동: " . $mob->getName());
            if ($mob instanceof Zombie) {
                $mob->lookAt($damager->getPosition());
                $direction = new Vector3(
                    $damager->getPosition()->getX() - $mob->getPosition()->getX(),
                    $damager->getPosition()->getY() - $mob->getPosition()->getY(),
                    $damager->getPosition()->getZ() - $mob->getPosition()->getZ()
                );
                $mob->setMotion($direction->normalize()->multiply(0.25));
            }
        }
    }
}
