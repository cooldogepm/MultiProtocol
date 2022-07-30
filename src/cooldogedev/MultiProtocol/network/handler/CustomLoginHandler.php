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

namespace cooldogedev\MultiProtocol\network\handler;

use Closure;
use InvalidArgumentException;
use JsonMapper;
use JsonMapper_Exception;
use pocketmine\entity\InvalidSkinException;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\network\mcpe\auth\ProcessLoginTask;
use pocketmine\network\mcpe\convert\SkinAdapterSingleton;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationData;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\protocol\types\login\ClientDataToSkinDataHelper;
use pocketmine\network\mcpe\protocol\types\login\JwtChain;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;

final class CustomLoginHandler extends PacketHandler
{
    /**
     * @phpstan-param \Closure(PlayerInfo) : void $playerInfoConsumer
     * @phpstan-param \Closure(bool $isAuthenticated, bool $authRequired, ?string $error, ?string $clientPubKey) : void $authCallback
     */
    public function __construct(
        private Server         $server,
        private NetworkSession $session,
        private Closure        $playerInfoConsumer,
        private Closure        $authCallback
    )
    {
    }

    public function handleLogin(LoginPacket $packet): bool
    {
        if (!$this->isCompatibleProtocol($packet->protocol)) {
            $this->session->sendDataPacket(PlayStatusPacket::create($packet->protocol < ProtocolInfo::CURRENT_PROTOCOL ? PlayStatusPacket::LOGIN_FAILED_CLIENT : PlayStatusPacket::LOGIN_FAILED_SERVER), true);

            //This pocketmine disconnect message will only be seen by the console (PlayStatusPacket causes the messages to be shown for the client)
            $this->session->disconnect(
                $this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_disconnect_incompatibleProtocol((string)$packet->protocol)),
                false
            );

            return true;
        }

        $extraData = $this->fetchAuthData($packet->chainDataJwt);

        if (!Player::isValidUserName($extraData->displayName)) {
            $this->session->disconnect(KnownTranslationKeys::DISCONNECTIONSCREEN_INVALIDNAME);

            return true;
        }

        $clientData = $this->parseClientData($packet->clientDataJwt);
        try {
            $skin = SkinAdapterSingleton::get()->fromSkinData(ClientDataToSkinDataHelper::fromClientData($clientData));
        } catch (InvalidArgumentException|InvalidSkinException $e) {
            $this->session->getLogger()->debug("Invalid skin: " . $e->getMessage());
            $this->session->disconnect(KnownTranslationKeys::DISCONNECTIONSCREEN_INVALIDSKIN);

            return true;
        }

        if (!Uuid::isValid($extraData->identity)) {
            throw new PacketHandlingException("Invalid login UUID");
        }
        $uuid = Uuid::fromString($extraData->identity);
        if ($extraData->XUID !== "") {
            $playerInfo = new XboxLivePlayerInfo(
                $extraData->XUID,
                $extraData->displayName,
                $uuid,
                $skin,
                $clientData->LanguageCode,
                (array)$clientData
            );
        } else {
            $playerInfo = new PlayerInfo(
                $extraData->displayName,
                $uuid,
                $skin,
                $clientData->LanguageCode,
                (array)$clientData
            );
        }
        ($this->playerInfoConsumer)($playerInfo);

        $ev = new PlayerPreLoginEvent(
            $playerInfo,
            $this->session->getIp(),
            $this->session->getPort(),
            $this->server->requiresAuthentication()
        );
        if ($this->server->getNetwork()->getConnectionCount() > $this->server->getMaxPlayers()) {
            $ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_SERVER_FULL, KnownTranslationKeys::DISCONNECTIONSCREEN_SERVERFULL);
        }
        if (!$this->server->isWhitelisted($playerInfo->getUsername())) {
            $ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_SERVER_WHITELISTED, "Server is whitelisted");
        }
        if ($this->server->getNameBans()->isBanned($playerInfo->getUsername()) || $this->server->getIPBans()->isBanned($this->session->getIp())) {
            $ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_BANNED, "You are banned");
        }

        $ev->call();
        if (!$ev->isAllowed()) {
            $this->session->disconnect($ev->getFinalKickMessage());
            return true;
        }

        $this->processLogin($packet, $ev->isAuthRequired());

        return true;
    }

    protected function isCompatibleProtocol(int $protocolVersion): bool
    {
        return $protocolVersion === ProtocolInfo::CURRENT_PROTOCOL;
    }

    /**
     * @throws PacketHandlingException
     */
    protected function fetchAuthData(JwtChain $chain): AuthenticationData
    {
        /** @var AuthenticationData|null $extraData */
        $extraData = null;
        foreach ($chain->chain as $k => $jwt) {
            //validate every chain element
            try {
                [, $claims,] = JwtUtils::parse($jwt);
            } catch (JwtException $e) {
                throw PacketHandlingException::wrap($e);
            }
            if (isset($claims["extraData"])) {
                if ($extraData !== null) {
                    throw new PacketHandlingException("Found 'extraData' more than once in chainData");
                }

                if (!is_array($claims["extraData"])) {
                    throw new PacketHandlingException("'extraData' key should be an array");
                }
                $mapper = new JsonMapper;
                $mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
                $mapper->bExceptionOnMissingData = true;
                $mapper->bExceptionOnUndefinedProperty = true;
                try {
                    /** @var AuthenticationData $extraData */
                    $extraData = $mapper->map($claims["extraData"], new AuthenticationData);
                } catch (JsonMapper_Exception $e) {
                    throw PacketHandlingException::wrap($e);
                }
            }
        }
        if ($extraData === null) {
            throw new PacketHandlingException("'extraData' not found in chain data");
        }
        return $extraData;
    }

    /**
     * @throws PacketHandlingException
     */
    protected function parseClientData(string $clientDataJwt): ClientData
    {
        try {
            [, $clientDataClaims,] = JwtUtils::parse($clientDataJwt);
        } catch (JwtException $e) {
            throw PacketHandlingException::wrap($e);
        }

        if (!isset($clientDataClaims["IsEditorMode"])) {
            $clientDataClaims["IsEditorMode"] = false;
        }

        $mapper = new JsonMapper;
        $mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
        $mapper->bExceptionOnMissingData = true;
        $mapper->bExceptionOnUndefinedProperty = true;
        try {
            $clientData = $mapper->map($clientDataClaims, new ClientData);
        } catch (JsonMapper_Exception $e) {
            throw PacketHandlingException::wrap($e);
        }
        return $clientData;
    }

    protected function processLogin(LoginPacket $packet, bool $authRequired): void
    {
        $this->server->getAsyncPool()->submitTask(new ProcessLoginTask($packet->chainDataJwt->chain, $packet->clientDataJwt, $authRequired, $this->authCallback));
        $this->session->setHandler(null); //drop packets received during login verification
    }
}
