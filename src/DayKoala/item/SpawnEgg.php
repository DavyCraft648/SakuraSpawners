<?php

/*
 *   _____       __                    _____                                          
 *  / ___/____ _/ /____  ___________ _/ ___/____  ____ __      ______  ___  __________
 *  \__ \/ __ `/ //_/ / / / ___/ __ `/\__ \/ __ \/ __ `/ | /| / / __ \/ _ \/ ___/ ___/
 *  ___/ / /_/ / ,< / /_/ / /  / /_/ /___/ / /_/ / /_/ /| |/ |/ / / / /  __/ /  (__  ) 
 * /____/\__,_/_/|_|\__,_/_/   \__,_//____/ .___/\__,_/ |__/|__/_/ /_/\___/_/  /____/  
 *                                        /_/                                           
 *
 * This program is free software made for PocketMine-MP,
 * currently under the GNU Lesser General Public License published by
 * the Free Software Foundation, use according to the license terms.
 * 
 * @author DayKoala
 * @link https://github.com/DayKoala/SakuraSpawners
 * 
 * 
*/

namespace DayKoala\item;

use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;

use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;

use pocketmine\player\Player;

use pocketmine\block\Block;
use pocketmine\block\tile\MonsterSpawner;

use pocketmine\math\Vector3;

use DayKoala\entity\SpawnerEntity;

use DayKoala\SakuraSpawners;
use function mt_rand;

class SpawnEgg extends \pocketmine\item\SpawnEgg{

    public function __construct(ItemIdentifier $identifier, string $name, private string $entityTypeId){ parent::__construct($identifier, $name); }

    protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
        return new SpawnerEntity(Location::fromObject($pos, $world), $this->entityTypeId);
    }

    public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
        $tile = $player->getWorld()->getTile($pos = $blockClicked->getPosition());
        if($tile instanceof MonsterSpawner){
            $world = $player->getWorld();
            $entityTypeIdProp = new \ReflectionProperty($tile, "entityTypeId");
            if(($entityTypeId = $entityTypeIdProp->getValue($tile)) === $this->entityTypeId){
                return ItemUseResult::FAIL();
            }
            $entityTypeIdProp->setValue($tile, $this->entityTypeId);
            $spawnDelay = mt_rand(MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, MonsterSpawner::DEFAULT_MAX_SPAWN_DELAY);
            (new \ReflectionProperty($tile, "spawnDelay"))->setValue($tile, $spawnDelay);
            if($entityTypeId === ":"){
                SakuraSpawners::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($tile) : void{
                    SakuraSpawners::getInstance()->updateSpawner($tile);
                }), $spawnDelay);
            }
            $world->setBlock($pos, $world->getBlock($pos));
            $this->pop();
            return ItemUseResult::SUCCESS();
        }
        return parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
    }

    public function onInteractEntity(Player $player, Entity $entity, Vector3 $click) : Bool{
        if(!$entity instanceof SpawnerEntity or $entity->getModifiedNetworkTypeId() !== $this->entityTypeId){
           return false;
        }
        $entity->addStackSize(1);
        $this->pop();
        return true;
    }

}