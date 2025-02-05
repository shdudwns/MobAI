<?php

namespace HybridMobAI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\entity\EntityFactory;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\entity\Location;
use pocketmine\scheduler\ClosureTask;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntityDataHelper;
use HybridMobAI\Zombie;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\entity\Zombie as PmmpZombie;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->getLogger()->info("HybridMobAI 플러그인 활성화");

        // 커스텀 좀비 엔티티 등록
        EntityFactory::getInstance()->register(Zombie::class, function(World $world, CompoundTag $nbt): Zombie {
            return new Zombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['Zombie', 'minecraft:zombie']);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new MobAITask($this), 20);

        $spawnInterval = 600; // 스폰 간격을 600으로 설정
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->spawnRandomZombies();
        }), $spawnInterval);
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

        // 청크가 로딩되어 있는지 확인
        if (!$world->isChunkLoaded($location->getFloorX() >> 4, $location->getFloorZ() >> 4)) {
            $this->getLogger()->warning("Chunk not loaded, cannot replace zombie.");
            return;
        }

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
        $spawnPosition = new Vector3(
            $player->getPosition()->getX() + $direction->getX(),
            $player->getPosition()->getY() + $direction->getY(),
            $player->getPosition()->getZ() + $direction->getZ()
        );
        $spawnPosition->y += 1; // 좀비가 블록 안에 생성되지 않도록 높이를 조정
        $this->spawnZombieAt($player->getWorld(), $spawnPosition);
    }

    public function spawnZombieAt(World $world, Vector3 $position): void {
        $this->getLogger()->info("좀비 스폰 위치: " . $position->__toString());

        // 청크가 로딩되어 있는지 확인
        if (!$world->isChunkLoaded($position->getFloorX() >> 4, $position->getFloorZ() >> 4)) {
            $this->getLogger()->warning("Chunk not loaded, cannot spawn zombie.");
            return;
        }

        // 올바른 Location 객체 생성 (yaw, pitch 추가)
        $location = new Location($position->getX(), $position->getY(), $position->getZ(), $world, 0.0, 0.0);

        // 직접 엔티티 인스턴스 생성
        $zombie = new Zombie($location, CompoundTag::create());

        // 좀비가 유효한지 확인 후 스폰
        if ($zombie !== null) {
            $zombie->spawnToAll();
            $this->getLogger()->info("좀비 스폰 완료");
        } else {
            $this->getLogger()->error("좀비 인스턴스 생성 실패");
        }
    }
}
