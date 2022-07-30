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

namespace cooldogedev\MultiProtocol\network\translator;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use Ramsey\Uuid\UuidInterface;

final class AddPlayerPacketTranslator extends AddPlayerPacket
{
    public const NETWORK_ID = ProtocolInfo::ADD_PLAYER_PACKET;

    public UuidInterface $uuid;
    public string $username;
    public int $actorUniqueId;
    public int $actorRuntimeId;
    public string $platformChatId = "";
    public Vector3 $position;
    public ?Vector3 $motion = null;
    public float $pitch = 0.0;
    public float $yaw = 0.0;
    public float $headYaw = 0.0;
    public ItemStackWrapper $item;
    public int $gameMode;
    /**
     * @var MetadataProperty[]
     * @phpstan-var array<int, MetadataProperty>
     */
    public array $metadata = [];

    public AdventureSettingsPacket $adventureSettingsPacket;
    public UpdateAbilitiesPacket $abilitiesPacket;

    /** @var EntityLink[] */
    public array $links = [];
    public string $deviceId = ""; //TODO: fill player's device ID (???)
    public int $buildPlatform = DeviceOS::UNKNOWN;

    /**
     * @generate-create-func
     * @param MetadataProperty[] $metadata
     * @param EntityLink[] $links
     * @phpstan-param array<int, MetadataProperty> $metadata
     */
    public static function create(
        UuidInterface            $uuid,
        string                   $username,
        int                      $actorUniqueId,
        string                   $platformChatId,
        Vector3                  $position,
        ?Vector3                 $motion,
        float                    $pitch,
        float                    $yaw,
        float                    $headYaw,
        ItemStackWrapper         $item,
        int                      $gameMode,
        array                    $metadata,
        ?UpdateAbilitiesPacket   $abilitiesPacket = null,
        array                    $links,
        string                   $deviceId,
        int                      $buildPlatform,
        ?AdventureSettingsPacket $adventureSettingsPacket = null,
    ): self
    {
        $result = new self;
        $result->uuid = $uuid;
        $result->username = $username;
        $result->actorUniqueId = $actorUniqueId;
        $result->actorRuntimeId = $actorUniqueId;
        $result->platformChatId = $platformChatId;
        $result->position = $position;
        $result->motion = $motion;
        $result->pitch = $pitch;
        $result->yaw = $yaw;
        $result->headYaw = $headYaw;
        $result->item = $item;
        $result->gameMode = $gameMode;
        $result->metadata = $metadata;
        $result->adventureSettingsPacket = $adventureSettingsPacket;
//        $result->abilitiesPacket = $abilitiesPacket;
        $result->links = $links;
        $result->deviceId = $deviceId;
        $result->buildPlatform = $buildPlatform;
        return $result;
    }

    public function handle(PacketHandlerInterface $handler): bool
    {
        return $handler->handleAddPlayer($this);
    }

    protected function decodePayload(PacketSerializer $in): void
    {
        $this->uuid = $in->getUUID();
        $this->username = $in->getString();
        $this->actorUniqueId = $in->getActorUniqueId();
        $this->actorRuntimeId = $in->getActorRuntimeId();
        $this->platformChatId = $in->getString();
        $this->position = $in->getVector3();
        $this->motion = $in->getVector3();
        $this->pitch = $in->getLFloat();
        $this->yaw = $in->getLFloat();
        $this->headYaw = $in->getLFloat();
        $this->item = ItemStackWrapper::read($in);
        $this->gameMode = $in->getVarInt();
        $this->metadata = $in->getEntityMetadata();

        $this->adventureSettingsPacket = new AdventureSettingsPacket();
        $this->adventureSettingsPacket->decodePayload($in);
//        $this->abilitiesPacket = new UpdateAbilitiesPacket();
//        $this->abilitiesPacket->decodePayload($in);

        $linkCount = $in->getUnsignedVarInt();
        for ($i = 0; $i < $linkCount; ++$i) {
            $this->links[$i] = $in->getEntityLink();
        }
        $this->deviceId = $in->getString();
        $this->buildPlatform = $in->getLInt();
    }

    protected function encodePayload(PacketSerializer $out): void
    {
        $out->putUUID($this->uuid);
        $out->putString($this->username);
        $out->putActorUniqueId($this->actorUniqueId);
        $out->putActorRuntimeId($this->actorRuntimeId);
        $out->putString($this->platformChatId);
        $out->putVector3($this->position);
        $out->putVector3Nullable($this->motion);
        $out->putLFloat($this->pitch);
        $out->putLFloat($this->yaw);
        $out->putLFloat($this->headYaw);
        $this->item->write($out);
        $out->putVarInt($this->gameMode);
        $out->putEntityMetadata($this->metadata);

        $this->adventureSettingsPacket->encodePayload($out);
//        $this->abilitiesPacket->encodePayload($out);

        $out->putUnsignedVarInt(count($this->links));
        foreach ($this->links as $link) {
            $out->putEntityLink($link);
        }
        $out->putString($this->deviceId);
        $out->putLInt($this->buildPlatform);
    }
}