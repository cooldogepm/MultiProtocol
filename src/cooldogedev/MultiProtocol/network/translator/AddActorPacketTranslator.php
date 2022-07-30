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
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\entity\Attribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;

final class AddActorPacketTranslator extends AddActorPacket
{
    public const NETWORK_ID = ProtocolInfo::ADD_ACTOR_PACKET;

    public int $actorUniqueId;
    public int $actorRuntimeId;
    public string $type;
    public Vector3 $position;
    public ?Vector3 $motion = null;
    public float $pitch = 0.0;
    public float $yaw = 0.0;
    public float $headYaw = 0.0;
//    public float $bodyYaw = 0.0; //???

    /** @var Attribute[] */
    public array $attributes = [];
    /**
     * @var MetadataProperty[]
     * @phpstan-var array<int, MetadataProperty>
     */
    public array $metadata = [];
    /** @var EntityLink[] */
    public array $links = [];

    /**
     * @generate-create-func
     * @param Attribute[] $attributes
     * @param MetadataProperty[] $metadata
     * @param EntityLink[] $links
     * @phpstan-param array<int, MetadataProperty> $metadata
     */
    public static function create(
        int      $actorUniqueId,
        int      $actorRuntimeId,
        string   $type,
        Vector3  $position,
        ?Vector3 $motion,
        float    $pitch,
        float    $yaw,
        float    $headYaw,
        ?float   $bodyYaw = null,
        array    $attributes,
        array    $metadata,
        array    $links,
    ): self
    {
        $result = new self;
        $result->actorUniqueId = $actorUniqueId;
        $result->actorRuntimeId = $actorRuntimeId;
        $result->type = $type;
        $result->position = $position;
        $result->motion = $motion;
        $result->pitch = $pitch;
        $result->yaw = $yaw;
        $result->headYaw = $headYaw;
//        $result->bodyYaw = $bodyYaw;
        $result->attributes = $attributes;
        $result->metadata = $metadata;
        $result->links = $links;
        return $result;
    }

    public function handle(PacketHandlerInterface $handler): bool
    {
        return $handler->handleAddActor($this);
    }

    protected function decodePayload(PacketSerializer $in): void
    {
        $this->actorUniqueId = $in->getActorUniqueId();
        $this->actorRuntimeId = $in->getActorRuntimeId();
        $this->type = $in->getString();
        $this->position = $in->getVector3();
        $this->motion = $in->getVector3();
        $this->pitch = $in->getLFloat();
        $this->yaw = $in->getLFloat();
        $this->headYaw = $in->getLFloat();
//        $this->bodyYaw = $in->getLFloat();

        $attrCount = $in->getUnsignedVarInt();
        for ($i = 0; $i < $attrCount; ++$i) {
            $id = $in->getString();
            $min = $in->getLFloat();
            $current = $in->getLFloat();
            $max = $in->getLFloat();
            $this->attributes[] = new Attribute($id, $min, $max, $current, $current);
        }

        $this->metadata = $in->getEntityMetadata();
        $linkCount = $in->getUnsignedVarInt();
        for ($i = 0; $i < $linkCount; ++$i) {
            $this->links[] = $in->getEntityLink();
        }
    }

    protected function encodePayload(PacketSerializer $out): void
    {
        $out->putActorUniqueId($this->actorUniqueId);
        $out->putActorRuntimeId($this->actorRuntimeId);
        $out->putString($this->type);
        $out->putVector3($this->position);
        $out->putVector3Nullable($this->motion);
        $out->putLFloat($this->pitch);
        $out->putLFloat($this->yaw);
        $out->putLFloat($this->headYaw);
//        $out->putLFloat($this->bodyYaw);

        $out->putUnsignedVarInt(count($this->attributes));
        foreach ($this->attributes as $attribute) {
            $out->putString($attribute->getId());
            $out->putLFloat($attribute->getMin());
            $out->putLFloat($attribute->getCurrent());
            $out->putLFloat($attribute->getMax());
        }

        $out->putEntityMetadata($this->metadata);
        $out->putUnsignedVarInt(count($this->links));
        foreach ($this->links as $link) {
            $out->putEntityLink($link);
        }
    }
}
