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

namespace DayKoala\entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Attribute;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use pocketmine\entity\Location;

use pocketmine\nbt\tag\CompoundTag;

use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\player\Player;

use pocketmine\network\mcpe\protocol\AddActorPacket;

use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;

use DayKoala\utils\traits\StackableTrait;

use DayKoala\utils\SpawnerNames;

use DayKoala\SakuraSpawners;
use function array_search;

class SpawnerEntity extends Living{

    use StackableTrait;

    public static function getNetworkTypeId() : String{ return EntityIds::AGENT; }

    protected string $networkTypeId;
    protected int $legacyNetworkTypeId;

    protected string $display;

    public function __construct(Location $location, string $entityTypeId, ?CompoundTag $nbt = null){
        $this->setEntityTypeId($entityTypeId);
        $this->display = SakuraSpawners::getInstance()->getDefaultEntityName();
        parent::__construct($location, $nbt);

        $this->setNameTagAlwaysVisible(true);
    }

    public function setEntityTypeId(string $type) : SpawnerEntity{
        $legacyNetworkTypeId = array_search($this->networkTypeId = $type, LegacyEntityIdToStringIdMap::getInstance()->getLegacyToStringMap(), true);
        $this->legacyNetworkTypeId = $legacyNetworkTypeId === false ? 0 : $legacyNetworkTypeId;
        return $this;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);

        $this->setStackSize($nbt->getInt("sakuraspawners:stackSize", 1));
    }

    public function saveNBT() : CompoundTag{
        return parent::saveNBT()
            ->setString("sakuraspawners:entityTypeId", $this->networkTypeId)
            ->setInt("sakuraspawners:stackSize", $this->getStackSize());
    }

    public function getModifiedNetworkTypeId() : String{
        return $this->networkTypeId;
    }

    public function getModifiedLegacyNetworkTypeId() : Int{
        return $this->legacyNetworkTypeId;
    }

    public function getName() : String{
        return SpawnerNames::getName($this->networkTypeId);
    }

    public function getDrops() : array{
        return SakuraSpawners::getInstance()->getSpawnerDrops($this->legacyNetworkTypeId);
    }

    public function getXpDropAmount() : Int{
        return SakuraSpawners::getInstance()->getSpawnerXp($this->legacyNetworkTypeId);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return SakuraSpawners::getInstance()->getSpawnerSize($this->legacyNetworkTypeId);
    }

    public function getNameTag() : String{
        $args = [
            '{health}' => $this->getHealth(),
            '{max-health}' => $this->getMaxHealth(),
            '{stack}' => $this->getStackSize(),
            '{max-stack}' => $this->getMaxStackSize(),
            '{name}' => $this->getName()
        ];
        return str_replace(array_keys($args), array_values($args), $this->display);
    }

    public function kill() : Void{
        if($this->stack > 1){
           for($i = 1; $i < $this->stack; $i++) $this->onDeath();
        }
        parent::kill();
    }

    public function attack(EntityDamageEvent $source) : Void{
        if($source->isCancelled()){
           return;
        }
        if($source instanceof EntityDamageByEntityEvent){
           $source->setKnockBack(0);
        }
        if($source->getFinalDamage() >= $this->getHealth() and $this->stack > 1){
           $source->cancel();
           $this->onDeath();
        }
        parent::attack($source);
    }

    protected function onDeath() : Void{
        if($this->stack > 1){
           $this->stack--;
           $this->setHealth($this->getMaxHealth());
        }
        parent::onDeath();
    }

    protected function startDeathAnimation() : Void{
        if(!$this->isAlive()) parent::startDeathAnimation();
    }

    public function onUpdate(Int $currentTick) : Bool{
        if($this->closed){
           return false;
        }
        $this->setNameTag($this->getNameTag());
        return parent::onUpdate($currentTick);
    }
    
    protected function sendSpawnPacket(Player $player) : Void{
        $player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
            $this->getId(),
            $this->getId(),
            $this->getModifiedNetworkTypeId(),
            $this->location->asVector3(),
            $this->getMotion(),
            $this->location->pitch,
            $this->location->yaw,
            $this->location->yaw,
            $this->location->yaw,
            array_map(function(Attribute $attr) : NetworkAttribute{
                return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []);
            }, $this->attributeMap->getAll()), $this->getAllNetworkData(), new PropertySyncData([], []), []
        ));
    }

}