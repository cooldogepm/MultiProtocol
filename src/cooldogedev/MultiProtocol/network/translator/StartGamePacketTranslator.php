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

namespace cooldogedev\MultiProtocol\network\translator;

use cooldogedev\MultiProtocol\network\translator\types\LevelSettingsTranslator;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use Ramsey\Uuid\UuidInterface;

final class StartGamePacketTranslator extends StartGamePacket
{

    /**
     * @generate-create-func
     * @param BlockPaletteEntry[] $blockPalette
     * @param ItemTypeEntry[] $itemTable
     * @phpstan-param CacheableNbt<CompoundTag> $playerActorProperties
     * @phpstan-param list<BlockPaletteEntry>   $blockPalette
     * @phpstan-param list<ItemTypeEntry>       $itemTable
     */
    public static function create(
        int                    $actorUniqueId,
        int                    $actorRuntimeId,
        int                    $playerGamemode,
        Vector3                $playerPosition,
        float                  $pitch,
        float                  $yaw,
        CacheableNbt           $playerActorProperties,
        LevelSettings          $levelSettings,
        string                 $levelId,
        string                 $worldName,
        string                 $premiumWorldTemplateId,
        bool                   $isTrial,
        PlayerMovementSettings $playerMovementSettings,
        int                    $currentTick,
        int                    $enchantmentSeed,
        string                 $multiplayerCorrelationId,
        bool                   $enableNewInventorySystem,
        string                 $serverSoftwareVersion,
        UuidInterface          $worldTemplateId,
        array                  $blockPalette,
        int                    $blockPaletteChecksum,
        array                  $itemTable,
    ): self
    {
        $result = new self;
        $result->actorUniqueId = $actorUniqueId;
        $result->actorRuntimeId = $actorRuntimeId;
        $result->playerGamemode = $playerGamemode;
        $result->playerPosition = $playerPosition;
        $result->pitch = $pitch;
        $result->yaw = $yaw;
        $result->playerActorProperties = $playerActorProperties;
        $result->levelSettings = $levelSettings;
        $result->levelId = $levelId;
        $result->worldName = $worldName;
        $result->premiumWorldTemplateId = $premiumWorldTemplateId;
        $result->isTrial = $isTrial;
        $result->playerMovementSettings = $playerMovementSettings;
        $result->currentTick = $currentTick;
        $result->enchantmentSeed = $enchantmentSeed;
        $result->multiplayerCorrelationId = $multiplayerCorrelationId;
        $result->enableNewInventorySystem = $enableNewInventorySystem;
        $result->serverSoftwareVersion = $serverSoftwareVersion;
        $result->worldTemplateId = $worldTemplateId;
        $result->blockPalette = $blockPalette;
        $result->blockPaletteChecksum = $blockPaletteChecksum;
        $result->itemTable = $itemTable;
        return $result;
    }

    public function handle(PacketHandlerInterface $handler): bool
    {
        return $handler->handleStartGame($this);
    }

    protected function decodePayload(PacketSerializer $in): void
    {
        $this->actorUniqueId = $in->getActorUniqueId();
        $this->actorRuntimeId = $in->getActorRuntimeId();
        $this->playerGamemode = $in->getVarInt();

        $this->playerPosition = $in->getVector3();

        $this->pitch = $in->getLFloat();
        $this->yaw = $in->getLFloat();

        $this->levelSettings = LevelSettingsTranslator::read($in);

        $this->levelId = $in->getString();
        $this->worldName = $in->getString();
        $this->premiumWorldTemplateId = $in->getString();
        $this->isTrial = $in->getBool();
        $this->playerMovementSettings = PlayerMovementSettings::read($in);
        $this->currentTick = $in->getLLong();

        $this->enchantmentSeed = $in->getVarInt();

        $this->blockPalette = [];
        for ($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i) {
            $blockName = $in->getString();
            $state = $in->getNbtCompoundRoot();
            $this->blockPalette[] = new BlockPaletteEntry($blockName, new CacheableNbt($state));
        }

        $this->itemTable = [];
        for ($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i) {
            $stringId = $in->getString();
            $numericId = $in->getSignedLShort();
            $isComponentBased = $in->getBool();

            $this->itemTable[] = new ItemTypeEntry($stringId, $numericId, $isComponentBased);
        }

        $this->multiplayerCorrelationId = $in->getString();
        $this->enableNewInventorySystem = $in->getBool();
        $this->serverSoftwareVersion = $in->getString();
        $this->playerActorProperties = new CacheableNbt($in->getNbtCompoundRoot());
        $this->blockPaletteChecksum = $in->getLLong();
        $this->worldTemplateId = $in->getUUID();
    }

    protected function encodePayload(PacketSerializer $out): void
    {
        $out->putActorUniqueId($this->actorUniqueId);
        $out->putActorRuntimeId($this->actorRuntimeId);
        $out->putVarInt($this->playerGamemode);

        $out->putVector3($this->playerPosition);

        $out->putLFloat($this->pitch);
        $out->putLFloat($this->yaw);

        LevelSettingsTranslator::write($this->levelSettings, $out);

        $out->putString($this->levelId);
        $out->putString($this->worldName);
        $out->putString($this->premiumWorldTemplateId);
        $out->putBool($this->isTrial);
        $this->playerMovementSettings->write($out);
        $out->putLLong($this->currentTick);

        $out->putVarInt($this->enchantmentSeed);

        $out->putUnsignedVarInt(count($this->blockPalette));
        foreach ($this->blockPalette as $entry) {
            $out->putString($entry->getName());
            $out->put($entry->getStates()->getEncodedNbt());
        }

        $out->putUnsignedVarInt(count($this->itemTable));
        foreach ($this->itemTable as $entry) {
            $out->putString($entry->getStringId());
            $out->putLShort($entry->getNumericId());
            $out->putBool($entry->isComponentBased());
        }

        $out->putString($this->multiplayerCorrelationId);
        $out->putBool($this->enableNewInventorySystem);
        $out->putString($this->serverSoftwareVersion);
        $out->put($this->playerActorProperties->getEncodedNbt());
        $out->putLLong($this->blockPaletteChecksum);
        $out->putUUID($this->worldTemplateId);
    }
}
