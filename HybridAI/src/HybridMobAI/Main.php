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
use pocketmine\entity\Location; // ✅ 올바른 네임스페이스 사용
use pocketmine\scheduler\ClosureTask;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntityDataHelper;
use HybridMobAI\Zombie;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->getLogger()->info("HybridMobAI 플러그인 활성화");

        // 좀비 엔티티 등록
        EntityFactory::getInstance()->register(Zombie::class, function(World $world, CompoundTag $nbt): Zombie {
            return new Zombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['Zombie', 'minecraft:zombie']);

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

        // 올바른 Location 객체 생성 (yaw, pitch 추가)
        $location = new Location($position->getX(), $position->getY(), $position->getZ(), $world, 0.0, 0.0);

        // 직접 엔티티 인스턴스 생성
        $zombie = new Zombie($location, new CompoundTag());

        // 좀비가 유효한지 확인 후 스폰
        if ($zombie !== null) {
            $zombie->spawnToAll();
            $this->getLogger()->info("좀비 스폰 완료");
        } else {
            $this->getLogger()->error("좀비 인스턴스 생성 실패");
        }
    }
}
