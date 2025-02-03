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
            $this->getLogger()->info("몹이 플레이어에게 피해를 입음: " . $entity->getName());
            $this->handleDamageResponse($entity, $event->getDamager());
        }
    }

    private function handleDamageResponse(Creature $mob, $damager): void {
        if ($damager instanceof Player) {
            $this->getLogger()->info("몹이 플레이어를 향해 이동: " . $mob->getName());
            $mob->lookAt($damager->getPosition());
            $mob->move($damager->getDirectionVector()->multiply(0.25)); // 공격자에게 이동
        }
    }

    private function spawnRandomZombies(): void {
        $this->getLogger()->info("랜덤 좀비 생성 시작");
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getPlayers() as $player) {
                $playerPosition = $player->getPosition();
                $this->getServer()->getAsyncPool()->submitTask(new SpawnZombiesTask(
                    $world->getId(),
                    (float)$playerPosition->x,
                    (float)$playerPosition->y,
                    (float)$playerPosition->z
                ));
                $this->getLogger()->info("좀비 생성 요청됨: " . $playerPosition->__toString());
            }
        }
    }

    public function spawnZombieAt(World $world, Vector3 $position): void {
        $this->getLogger()->info("좀비 스폰 위치: " . $position->__toString());
        $nbt = Entity::createBaseNBT($position);
        $zombie = new Zombie($world, $nbt);
        $zombie->spawnToAll();
    }
}
