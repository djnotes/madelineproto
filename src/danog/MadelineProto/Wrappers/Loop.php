<?php

declare(strict_types=1);

/**
 * Loop module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\Wrappers;

use Amp\Future;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Shutdown;
use danog\MadelineProto\Tools;
use Generator;
use Throwable;

use const PHP_SAPI;

use function Amp\async;

/**
 * Manages logging in and out.
 *
 * @property Settings $settings Settings
 */
trait Loop
{
    /**
     * Whether to stop the loop.
     */
    private bool $stopLoop = false;
    /**
     * Initialize self-restart hack.
     */
    public function initSelfRestart(): void
    {
        static $inited = false;
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !$inited) {
            try {
                if (\function_exists('set_time_limit')) {
                    \set_time_limit(-1);
                }
            } catch (Exception) {
            }
            if (isset($_REQUEST['MadelineSelfRestart'])) {
                $this->logger->logger('Self-restarted, restart token '.$_REQUEST['MadelineSelfRestart']);
            }
            $this->logger->logger('Will self-restart');
            $this->logger->logger('Adding restart callback!');
            $logger = $this->logger;
            $id = Shutdown::addCallback(static function () use (&$logger): void {
                $address = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'tls' : 'tcp').'://'.$_SERVER['SERVER_NAME'];
                $port = $_SERVER['SERVER_PORT'];
                $uri = $_SERVER['REQUEST_URI'];
                $params = $_GET;
                $params['MadelineSelfRestart'] = Tools::randomInt();
                $url = \explode('?', $uri, 2)[0] ?? '';
                $query = \http_build_query($params);
                $uri = \implode('?', [$url, $query]);
                $payload = $_SERVER['REQUEST_METHOD'].' '.$uri." HTTP/1.1\r\n".'Host: '.$_SERVER['SERVER_NAME']."\r\n\r\n";
                $logger->logger("Connecting to {$address}:{$port}");
                $a = \fsockopen($address, (int) $port);
                $logger->logger('Sending self-restart payload');
                $logger->logger($payload);
                \fwrite($a, $payload);
                $logger->logger("Payload sent with token {$params['MadelineSelfRestart']}, waiting for self-restart");
                // Keep around resource for a bit more
                $GLOBALS['MadelineShutdown'] = $a;
                $logger->logger('Shutdown of self-restart callback');
            }, 'restarter');
            $this->logger->logger("Added restart callback with ID $id!");
            $this->logger->logger('Done webhost init process!');
            Tools::closeConnection($this->getWebMessage('The bot was started!'));
            $inited = true;
        } elseif (PHP_SAPI === 'cli') {
            try {
                if (\function_exists('shell_exec') && \file_exists('/data/data/com.termux/files/usr/bin/termux-wake-lock')) {
                    /** @psalm-suppress ForbiddenCode */
                    \shell_exec('/data/data/com.termux/files/usr/bin/termux-wake-lock');
                }
            } catch (Throwable) {
            }
        }
    }
    /**
     * Start MadelineProto's update handling loop, or run the provided async callable.
     *
     * @param callable|null $callback Async callable to run
     */
    public function loop(?callable $callback = null)
    {
        if (\is_callable($callback)) {
            $this->logger->logger('Running async callable');
            return Tools::call($callback())->await();
        }
        if (!$this->authorized) {
            $this->logger->logger('Not authorized, not starting event loop', Logger::FATAL_ERROR);
            return false;
        }
        if ($this->updateHandler === self::GETUPDATES_HANDLER) {
            $this->logger->logger('Getupdates event handler is enabled, exiting from loop', Logger::FATAL_ERROR);
            return false;
        }
        $this->logger->logger('Starting event loop');
        $this->initSelfRestart();
        $this->startUpdateSystem();
        $this->logger->logger('Started update loop', Logger::NOTICE);
        $this->stopLoop = false;
        do {
            if (!$this->updateHandler) {
                $this->waitUpdate();
                /** @psalm-suppress RedundantCondition */
                if (!$this->updateHandler) {
                    $this->logger->logger('Exiting update loop, no handler!', Logger::NOTICE);
                    continue;
                }
            }
            while ($this->updates) {
                $updates = $this->updates;
                $this->updates = [];
                foreach ($updates as $update) {
                    async(function () use ($update): void {
                        $r = ($this->updateHandler)($update);
                        if ($r instanceof Generator) {
                            $r = Tools::call($r);
                        }
                        if ($r instanceof Future) {
                            $r->await();
                        }
                    });
                }
                $updates = [];
            }
            $this->waitUpdate();
            $this->logger->logger('Resuming update loop!', Logger::VERBOSE);
            /** @var bool $this->stopLoop */
        } while (!$this->stopLoop);
        $this->logger->logger('Exiting update loop!', Logger::NOTICE);
        $this->stopLoop = false;
    }
    /**
     * Stop update loop.
     */
    public function stop(): void
    {
        Shutdown::removeCallback('restarter');
        $this->restart();
    }
    /**
     * Restart update loop.
     */
    public function restart(): void
    {
        $this->stopLoop = true;
        $this->signalUpdate();
    }
    /**
     * Start MadelineProto's update handling loop in background.
     */
    public function loopFork(): Future
    {
        return async($this->loop(...));
    }
}
