<?php

/**
 * 
 * Copyright (c) 2022 cooldogedev
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE. *
 * @auto-license
 */

declare(strict_types=1);

namespace cooldogedev\MultiProtocol\network\handler;

use cooldogedev\MultiProtocol\network\translator\StartGamePacketTranslator;
use cooldogedev\MultiProtocol\network\translator\types\LevelSettingsTranslator;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\CraftingDataCache;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\VersionInfo;
use Ramsey\Uuid\Uuid;

final class CustomPreSpawnHandler extends PacketHandler
{
    public function __construct(
        private Server           $server,
        private Player           $player,
        private NetworkSession   $session,
        private InventoryManager $inventoryManager
    )
    {
    }

    public function setUp(): void
    {
        $location = $this->player->getLocation();

        $levelSettings = new LevelSettings();
        $levelSettings->seed = -1;
        $levelSettings->spawnSettings = new SpawnSettings(SpawnSettings::BIOME_TYPE_DEFAULT, "", DimensionIds::OVERWORLD); //TODO: implement this properly
        $levelSettings->worldGamemode = TypeConverter::getInstance()->coreGameModeToProtocol($this->server->getGamemode());
        $levelSettings->difficulty = $location->getWorld()->getDifficulty();
        $levelSettings->spawnPosition = BlockPosition::fromVector3($location->getWorld()->getSpawnLocation());
        $levelSettings->hasAchievementsDisabled = true;
        $levelSettings->time = $location->getWorld()->getTime();
        $levelSettings->eduEditionOffer = 0;
        $levelSettings->rainLevel = 0; //TODO: implement these properly
        $levelSettings->lightningLevel = 0;
        $levelSettings->commandsEnabled = true;
        $levelSettings->gameRules = [
            "naturalregeneration" => new BoolGameRule(false, false) //Hack for client side regeneration
        ];
        $levelSettings->experiments = new Experiments([], false);

        if ($this->session->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->session->sendDataPacket(StartGamePacket::create(
                $this->player->getId(),
                $this->player->getId(),
                TypeConverter::getInstance()->coreGameModeToProtocol($this->player->getGamemode()),
                $this->player->getOffsetPosition($location),
                $location->pitch,
                $location->yaw,
                new CacheableNbt(CompoundTag::create()), //TODO: we don't care about this right now
                $levelSettings,
                "",
                $this->server->getMotd(),
                "",
                false,
                new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V1, 0, false),
                0,
                0,
                "",
                false,
                sprintf("%s %s", VersionInfo::NAME, VersionInfo::VERSION()->getFullVersion(true)),
                Uuid::fromString(Uuid::NIL),
                [],
                0,
                GlobalItemTypeDictionary::getInstance()->getDictionary()->getEntries()
            ));
        } else {
            $this->session->sendDataPacket(StartGamePacketTranslator::create(
                $this->player->getId(),
                $this->player->getId(),
                TypeConverter::getInstance()->coreGameModeToProtocol($this->player->getGamemode()),
                $this->player->getOffsetPosition($location),
                $location->pitch,
                $location->yaw,
                new CacheableNbt(CompoundTag::create()), //TODO: we don't care about this right now
                LevelSettingsTranslator::legacy($levelSettings),
                "",
                $this->server->getMotd(),
                "",
                false,
                new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V1, 0, false),
                0,
                0,
                "",
                false,
                sprintf("%s %s", VersionInfo::NAME, VersionInfo::VERSION()->getFullVersion(true)),
                Uuid::fromString(Uuid::NIL),
                [],
                0,
                GlobalItemTypeDictionary::getInstance()->getDictionary()->getEntries()
            ));
        }

        $this->session->sendDataPacket(StaticPacketCache::getInstance()->getAvailableActorIdentifiers());
        $this->session->sendDataPacket(StaticPacketCache::getInstance()->getBiomeDefs());
        $this->session->syncAttributes($this->player, $this->player->getAttributeMap()->getAll());
        $this->session->syncAvailableCommands();
        $this->session->syncAdventureSettings(); // This is required for both versions
        $this->session->protocolId >= ProtocolInfo::CURRENT_PROTOCOL && $this->session->syncAbilities($this->player);
        foreach ($this->player->getEffects()->all() as $effect) {
            $this->session->onEntityEffectAdded($this->player, $effect, false);
        }
        $this->player->sendData([$this->player]);

        $this->inventoryManager->syncAll();
        $this->inventoryManager->syncCreative();
        $this->inventoryManager->syncSelectedHotbarSlot();
        $this->session->sendDataPacket(CraftingDataCache::getInstance()->getCache($this->server->getCraftingManager()));

        $this->session->syncPlayerList($this->server->getOnlinePlayers());
    }

    public function handleRequestChunkRadius(RequestChunkRadiusPacket $packet): bool
    {
        $this->player->setViewDistance($packet->radius);

        return true;
    }
}
