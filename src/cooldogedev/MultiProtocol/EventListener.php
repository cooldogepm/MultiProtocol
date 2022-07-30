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

namespace cooldogedev\MultiProtocol;

use cooldogedev\MultiProtocol\network\constant\ProtocolConstants;
use cooldogedev\MultiProtocol\network\CustomRaklibInterface;
use cooldogedev\MultiProtocol\network\handler\CustomLoginHandler;
use cooldogedev\MultiProtocol\traits\ReflectionTrait;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\query\DedicatedQueryNetworkInterface;
use pocketmine\player\PlayerInfo;
use pocketmine\utils\TextFormat;

final class EventListener implements Listener
{
    use ReflectionTrait;

    public function __construct(protected MultiProtocol $plugin)
    {
    }

    /**
     * @param NetworkInterfaceRegisterEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onNetworkInterfaceRegister(NetworkInterfaceRegisterEvent $event): void
    {
        $network = $event->getInterface();

        if ($network instanceof DedicatedQueryNetworkInterface) {
            $event->cancel();
            return;
        }

        if ($network instanceof RakLibInterface && !$network instanceof CustomRaklibInterface) {
            $event->cancel();
            $this->plugin->getServer()->getNetwork()->registerInterface(new CustomRaklibInterface($this->plugin->getServer(), $this->plugin->getServer()->getIp(), $this->plugin->getServer()->getPort(), false));
        }
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();

        if ($packet instanceof LoginPacket) {
            $event->getOrigin()->protocolId = $packet->protocol;

            if ($packet->protocol === ProtocolInfo::CURRENT_PROTOCOL || !isset(ProtocolConstants::SUPPORTED_PROTOCOLS[$packet->protocol])) {
                return;
            }

            $packet->protocol = ProtocolInfo::CURRENT_PROTOCOL;

            $event->getOrigin()->setHandler(new CustomLoginHandler($this->plugin->getServer(), $event->getOrigin(),
                function (PlayerInfo $info) use ($packet, $event): void {
                    $this->setProperty("info", $info, $event->getOrigin(), NetworkSession::class);
                    $event->getOrigin()->getLogger()->info("Player: " . TextFormat::AQUA . $info->getUsername() . TextFormat::RESET);
                    $event->getOrigin()->getLogger()->setPrefix("NetworkSession: " . $event->getOrigin()->getDisplayName());
                },
                function ($isAuthenticated, $authRequired, $error, $clientPubKey) use ($event): void {
                    $this->invoke("setAuthenticationStatus", [$isAuthenticated, $authRequired, $error, $clientPubKey], $event->getOrigin(), NetworkSession::class);
                }));
        }
    }

    public function getPlugin(): MultiProtocol
    {
        return $this->plugin;
    }
}
