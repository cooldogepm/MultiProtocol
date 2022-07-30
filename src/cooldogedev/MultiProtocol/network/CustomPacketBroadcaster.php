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

use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\Server;

final class CustomPacketBroadcaster implements PacketBroadcaster
{
    public function __construct(private Server $server)
    {
    }

    public function broadcastPackets(array $recipients, array $basePackets): void
    {
        $buffers = [];
        $compressors = [];
        $targetMap = [];

        $packetMap = [];

        foreach ($recipients as $recipient) {
            $packets = $basePackets;

            if ($recipient->protocolId < ProtocolInfo::CURRENT_PROTOCOL) {
                foreach ($packets as $key => $packet) {
                    $packets[$key] = CustomNetworkSession::getPacketTranslator($packet);
                }
            }

            $serializerContext = $recipient->getPacketSerializerContext();
            $bufferId = spl_object_id($serializerContext);

            if (!isset($buffers[$bufferId])) {
                $buffers[$bufferId] = PacketBatch::fromPackets($serializerContext, ...$packets);
            }

            $compressor = $recipient->getCompressor();
            $compressors[spl_object_id($compressor)] = $compressor;

            $targetMap[$bufferId][spl_object_id($compressor)][] = $recipient;
            $packetMap[$bufferId] = $packets;
        }

        foreach ($targetMap as $bufferId => $compressorMap) {
            $buffer = $buffers[$bufferId];
            $packets = $packetMap[$bufferId];
            foreach ($compressorMap as $compressorId => $compressorTargets) {
                $compressor = $compressors[$compressorId];
                if (!$compressor->willCompress($buffer->getBuffer())) {
                    foreach ($compressorTargets as $target) {
                        foreach ($packets as $pk) {
                            $target->addToSendBuffer($pk);
                        }
                    }
                } else {
                    $promise = $this->server->prepareBatch($buffer, $compressor);
                    foreach ($compressorTargets as $target) {
                        $target->queueCompressed($promise);
                    }
                }
            }
        }
    }
}
