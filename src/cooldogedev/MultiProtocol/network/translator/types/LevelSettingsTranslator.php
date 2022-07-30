<?php

/**
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

namespace cooldogedev\MultiProtocol\network\translator\types;

use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\EducationUriResource;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\utils\BinaryDataException;

final class LevelSettingsTranslator
{
    /**
     * @throws BinaryDataException
     * @throws PacketDecodeException
     */
    public static function read(PacketSerializer $in): LevelSettings
    {
        //TODO: in the future we'll use promoted properties + named arguments for decoding, but for now we stick with
        //this shitty way to limit BC breaks (needs more R&D)
        $result = new LevelSettings();
        self::internalRead($result, $in);
        return $result;
    }

    /**
     * @throws BinaryDataException
     * @throws PacketDecodeException
     */
    public static function internalRead(LevelSettings $settings, PacketSerializer $in): void
    {
        $settings->seed = $in->getLLong();
        $settings->spawnSettings = SpawnSettings::read($in);
        $settings->generator = $in->getVarInt();
        $settings->worldGamemode = $in->getVarInt();
        $settings->difficulty = $in->getVarInt();
        $settings->spawnPosition = $in->getBlockPosition();
        $settings->hasAchievementsDisabled = $in->getBool();
//        $settings->isEditorMode = $in->getBool();
        $settings->time = $in->getVarInt();
        $settings->eduEditionOffer = $in->getVarInt();
        $settings->hasEduFeaturesEnabled = $in->getBool();
        $settings->eduProductUUID = $in->getString();
        $settings->rainLevel = $in->getLFloat();
        $settings->lightningLevel = $in->getLFloat();
        $settings->hasConfirmedPlatformLockedContent = $in->getBool();
        $settings->isMultiplayerGame = $in->getBool();
        $settings->hasLANBroadcast = $in->getBool();
        $settings->xboxLiveBroadcastMode = $in->getVarInt();
        $settings->platformBroadcastMode = $in->getVarInt();
        $settings->commandsEnabled = $in->getBool();
        $settings->isTexturePacksRequired = $in->getBool();
        $settings->gameRules = $in->getGameRules();
        $settings->experiments = Experiments::read($in);
        $settings->hasBonusChestEnabled = $in->getBool();
        $settings->hasStartWithMapEnabled = $in->getBool();
        $settings->defaultPlayerPermission = $in->getVarInt();
        $settings->serverChunkTickRadius = $in->getLInt();
        $settings->hasLockedBehaviorPack = $in->getBool();
        $settings->hasLockedResourcePack = $in->getBool();
        $settings->isFromLockedWorldTemplate = $in->getBool();
        $settings->useMsaGamertagsOnly = $in->getBool();
        $settings->isFromWorldTemplate = $in->getBool();
        $settings->isWorldTemplateOptionLocked = $in->getBool();
        $settings->onlySpawnV1Villagers = $in->getBool();
        $settings->vanillaVersion = $in->getString();
        $settings->limitedWorldWidth = $in->getLInt();
        $settings->limitedWorldLength = $in->getLInt();
        $settings->isNewNether = $in->getBool();
        $settings->eduSharedUriResource = EducationUriResource::read($in);
        if ($in->getBool()) {
            $settings->experimentalGameplayOverride = $in->getBool();
        } else {
            $settings->experimentalGameplayOverride = null;
        }
    }

    public static function legacy(LevelSettings $old): LevelSettings
    {
        $settings = new LevelSettings();

        $settings->seed = $old->seed;
        $settings->spawnSettings = $old->spawnSettings;
        $settings->generator = $old->generator;
        $settings->worldGamemode = $old->worldGamemode;
        $settings->difficulty = $old->difficulty;
        $settings->spawnPosition = $old->spawnPosition;
        $settings->hasAchievementsDisabled = $old->hasAchievementsDisabled;
//        $settings->isEditorMode = $old->getBool();
        $settings->time = $old->time;
        $settings->eduEditionOffer = $old->eduEditionOffer;
        $settings->hasEduFeaturesEnabled = $old->hasEduFeaturesEnabled;
        $settings->eduProductUUID = $old->eduProductUUID;
        $settings->rainLevel = $old->rainLevel;
        $settings->lightningLevel = $old->lightningLevel;
        $settings->hasConfirmedPlatformLockedContent = $old->hasConfirmedPlatformLockedContent;
        $settings->isMultiplayerGame = $old->isMultiplayerGame;
        $settings->hasLANBroadcast = $old->hasLANBroadcast;
        $settings->xboxLiveBroadcastMode = $old->xboxLiveBroadcastMode;
        $settings->platformBroadcastMode = $old->platformBroadcastMode;
        $settings->commandsEnabled = $old->commandsEnabled;
        $settings->isTexturePacksRequired = $old->isTexturePacksRequired;
        $settings->gameRules = $old->gameRules;
        $settings->experiments = $old->experiments;
        $settings->hasBonusChestEnabled = $old->hasBonusChestEnabled;
        $settings->hasStartWithMapEnabled = $old->hasStartWithMapEnabled;
        $settings->defaultPlayerPermission = $old->defaultPlayerPermission;
        $settings->serverChunkTickRadius = $old->serverChunkTickRadius;
        $settings->hasLockedBehaviorPack = $old->hasLockedBehaviorPack;
        $settings->hasLockedResourcePack = $old->hasLockedResourcePack;
        $settings->isFromLockedWorldTemplate = $old->isFromLockedWorldTemplate;
        $settings->useMsaGamertagsOnly = $old->useMsaGamertagsOnly;
        $settings->isFromWorldTemplate = $old->isFromWorldTemplate;
        $settings->isWorldTemplateOptionLocked = $old->isWorldTemplateOptionLocked;
        $settings->onlySpawnV1Villagers = $old->onlySpawnV1Villagers;
        $settings->vanillaVersion = $old->vanillaVersion;
        $settings->limitedWorldWidth = $old->limitedWorldWidth;
        $settings->limitedWorldLength = $old->limitedWorldLength;
        $settings->isNewNether = $old->isNewNether;
        $settings->eduSharedUriResource = $old->eduSharedUriResource;
        $settings->experimentalGameplayOverride = $old->experimentalGameplayOverride;

        return $settings;
    }

    public static function write(LevelSettings $settings, PacketSerializer $out): void
    {
        $out->putLLong($settings->seed);
        $settings->spawnSettings->write($out);
        $out->putVarInt($settings->generator);
        $out->putVarInt($settings->worldGamemode);
        $out->putVarInt($settings->difficulty);
        $out->putBlockPosition($settings->spawnPosition);
        $out->putBool($settings->hasAchievementsDisabled);
//        $out->putBool($this->isEditorMode);
        $out->putVarInt($settings->time);
        $out->putVarInt($settings->eduEditionOffer);
        $out->putBool($settings->hasEduFeaturesEnabled);
        $out->putString($settings->eduProductUUID);
        $out->putLFloat($settings->rainLevel);
        $out->putLFloat($settings->lightningLevel);
        $out->putBool($settings->hasConfirmedPlatformLockedContent);
        $out->putBool($settings->isMultiplayerGame);
        $out->putBool($settings->hasLANBroadcast);
        $out->putVarInt($settings->xboxLiveBroadcastMode);
        $out->putVarInt($settings->platformBroadcastMode);
        $out->putBool($settings->commandsEnabled);
        $out->putBool($settings->isTexturePacksRequired);
        $out->putGameRules($settings->gameRules);
        $settings->experiments->write($out);
        $out->putBool($settings->hasBonusChestEnabled);
        $out->putBool($settings->hasStartWithMapEnabled);
        $out->putVarInt($settings->defaultPlayerPermission);
        $out->putLInt($settings->serverChunkTickRadius);
        $out->putBool($settings->hasLockedBehaviorPack);
        $out->putBool($settings->hasLockedResourcePack);
        $out->putBool($settings->isFromLockedWorldTemplate);
        $out->putBool($settings->useMsaGamertagsOnly);
        $out->putBool($settings->isFromWorldTemplate);
        $out->putBool($settings->isWorldTemplateOptionLocked);
        $out->putBool($settings->onlySpawnV1Villagers);
        $out->putString($settings->vanillaVersion);
        $out->putLInt($settings->limitedWorldWidth);
        $out->putLInt($settings->limitedWorldLength);
        $out->putBool($settings->isNewNether);
        ($settings->eduSharedUriResource ?? new EducationUriResource("", ""))->write($out);
        $out->putBool($settings->experimentalGameplayOverride !== null);
        if ($settings->experimentalGameplayOverride !== null) {
            $out->putBool($settings->experimentalGameplayOverride);
        }
    }
}
