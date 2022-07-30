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

namespace cooldogedev\MultiProtocol\network;

use Closure;
use cooldogedev\MultiProtocol\network\handler\CustomPreSpawnHandler;
use cooldogedev\MultiProtocol\network\translator\AddActorPacketTranslator;
use cooldogedev\MultiProtocol\network\translator\AddPlayerPacketTranslator;
use cooldogedev\MultiProtocol\traits\ReflectionTrait;
use LogicException;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

final class CustomNetworkSession extends NetworkSession
{
    use ReflectionTrait;

    public int $protocolId = -1;

    public function syncGameMode(GameMode $mode, bool $isRollback = false): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->invoke("syncGameMode", [$mode, $isRollback], $this, NetworkSession::class);
            return;
        }

        $this->sendDataPacket(SetPlayerGameTypePacket::create(TypeConverter::getInstance()->coreGameModeToProtocol($mode)));
        if ($this->getPlayer() !== null) {
            $this->syncAdventureSettings(); //TODO: we might be able to do this with the abilities packet alone
        }
        if (!$isRollback && $this->getInvManager() !== null) {
            $this->getInvManager()->syncCreative();
        }
    }

    public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false): bool
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            return parent::sendDataPacket($packet, $immediate);
        }

        return parent::sendDataPacket(self::getPacketTranslator($packet), $immediate);
    }

    public static function getPacketTranslator(ClientboundPacket $packet): ClientboundPacket
    {
        if ($packet instanceof AddActorPacket) {
            $packet = AddActorPacketTranslator::create(
                $packet->actorUniqueId,
                $packet->actorRuntimeId,
                $packet->type,
                $packet->position,
                $packet->motion,
                $packet->pitch,
                $packet->yaw,
                $packet->headYaw,
                null,
                $packet->attributes,
                $packet->metadata,
                $packet->links
            );
        }

        if ($packet instanceof AddPlayerPacket) {
            $packet = AddPlayerPacketTranslator::create(
                $packet->uuid,
                $packet->username,
                $packet->actorRuntimeId,
                $packet->platformChatId,
                $packet->position,
                $packet->motion,
                $packet->pitch,
                $packet->yaw,
                $packet->yaw, // Head yaw
                $packet->item,
                $packet->gameMode,
                $packet->metadata,
                null,
                $packet->links,
                $packet->deviceId,
                $packet->buildPlatform,
                // TODO: Initiate the abilities packet for already-translated packets, and use its permission parameters
                AdventureSettingsPacket::create(0, CommandPermissions::NORMAL, 0, CommandPermissions::NORMAL, 0, $packet->actorRuntimeId),
            );
        }

        if ($packet instanceof UpdateAbilitiesPacket) {
            $packet = AdventureSettingsPacket::create(0, $packet->getCommandPermission(), 0, $packet->getPlayerPermission(), 0, $packet->getTargetActorUniqueId());
        }

        return $packet;
    }

    public function syncAdventureSettings(): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->invoke("syncAdventureSettings", [], $this, NetworkSession::class);
            return;
        }

        $for = $this->getPlayer();

        if ($this->getPlayer() === null) {
            throw new LogicException("Cannot sync adventure settings for a player that is not yet created");
        }

        $isOp = $for->hasPermission(DefaultPermissions::ROOT_OPERATOR);
        $pk = AdventureSettingsPacket::create(
            0,
            $isOp ? CommandPermissions::OPERATOR : CommandPermissions::NORMAL,
            0,
            $isOp ? PlayerPermissions::OPERATOR : PlayerPermissions::MEMBER,
            0,
            $for->getId()
        );

        $pk->setFlag(AdventureSettingsPacket::WORLD_IMMUTABLE, $for->isSpectator());
        $pk->setFlag(AdventureSettingsPacket::NO_PVP, $for->isSpectator());
        $pk->setFlag(AdventureSettingsPacket::AUTO_JUMP, $for->hasAutoJump());
        $pk->setFlag(AdventureSettingsPacket::ALLOW_FLIGHT, $for->getAllowFlight());
        $pk->setFlag(AdventureSettingsPacket::NO_CLIP, !$for->hasBlockCollision());
        $pk->setFlag(AdventureSettingsPacket::FLYING, $for->isFlying());

        $this->sendDataPacket($pk);
    }

    public function addToSendBuffer(ClientboundPacket $packet): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            parent::addToSendBuffer($packet);
            return;
        }

        parent::addToSendBuffer(self::getPacketTranslator($packet));
    }

    public function onServerRespawn(): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->invoke("onServerRespawn", [$this->getPlayer()], $this, NetworkSession::class);
            return;
        }

        if ($this->getPlayer() === null) {
            throw new LogicException("Cannot respawn a player that is not yet created");
        }

        $this->syncAttributes($this->getPlayer(), $this->getPlayer()->getAttributeMap()->getAll());
        $this->getPlayer()->sendData(null);

        $this->syncAdventureSettings();
        $this->getInvManager()->syncAll();
        $this->setHandler(new InGamePacketHandler($this->getPlayer(), $this, $this->getInvManager()));
    }

    public function syncAbilities(Player $for): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            parent::syncAbilities($for);
        }

        $this->syncAdventureSettings();
    }

    protected function createPlayer(): void
    {
        $this->getProperty("server", $this, NetworkSession::class)->createPlayer($this, $this->getProperty("info", $this, NetworkSession::class), $this->getProperty("authenticated", $this, NetworkSession::class), $this->getProperty("cachedOfflinePlayerData", $this, NetworkSession::class))->onCompletion(
            Closure::fromCallable([$this, 'onPlayerCreated']),
            fn() => $this->disconnect("Player creation failed")
        );
    }

    protected function onPlayerCreated(Player $player): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->invoke("onPlayerCreated", [$player], $this, NetworkSession::class);
            return;
        }

        if (!$this->isConnected()) {
            return;
        }

        $this->setProperty("player", $player, $this, NetworkSession::class);

        if (!$this->getProperty("server", $this, NetworkSession::class)->addOnlinePlayer($player)) {
            return;
        }

        $this->setProperty("invManager", new InventoryManager($this->getProperty("player", $this, NetworkSession::class), $this), $this, NetworkSession::class);

        $effectManager = $this->getProperty("player", $this, NetworkSession::class)->getEffects();
        $effectManager->getEffectAddHooks()->add($effectAddHook = function (EffectInstance $effect, bool $replacesOldEffect): void {
            $this->onEntityEffectAdded($this->getProperty("player", $this, NetworkSession::class), $effect, $replacesOldEffect);
        });
        $effectManager->getEffectRemoveHooks()->add($effectRemoveHook = function (EffectInstance $effect): void {
            $this->onEntityEffectRemoved($this->getProperty("player", $this, NetworkSession::class), $effect);
        });
        $this->getProperty("disposeHooks", $this, NetworkSession::class)->add(static function () use ($effectManager, $effectAddHook, $effectRemoveHook): void {
            $effectManager->getEffectAddHooks()->remove($effectAddHook);
            $effectManager->getEffectRemoveHooks()->remove($effectRemoveHook);
        });

        $permissionHooks = $this->getProperty("player", $this, NetworkSession::class)->getPermissionRecalculationCallbacks();
        $permissionHooks->add($permHook = function (): void {
            $this->getProperty("logger", $this, NetworkSession::class)->debug("Syncing available commands and abilities/permissions due to permission recalculation");
            $this->syncAdventureSettings();
            $this->syncAvailableCommands();
        });
        $this->getProperty("disposeHooks", $this, NetworkSession::class)->add(static function () use ($permissionHooks, $permHook): void {
            $permissionHooks->remove($permHook);
        });
        $this->beginSpawnSequence();
    }

    public function beginSpawnSequence(): void
    {
        if ($this->protocolId >= ProtocolInfo::CURRENT_PROTOCOL) {
            $this->invoke("beginSpawnSequence", [], $this, NetworkSession::class);
            return;
        }

        $this->setHandler(new CustomPreSpawnHandler($this->getProperty("server", $this, NetworkSession::class), $this->getProperty("player", $this, NetworkSession::class), $this, $this->getProperty("invManager", $this, NetworkSession::class)));
        $this->getProperty("player", $this, NetworkSession::class)->setImmobile(); //TODO: HACK: fix client-side falling pre-spawn

        $this->getProperty("logger", $this, NetworkSession::class)->debug("Waiting for chunk radius request");
    }
}
