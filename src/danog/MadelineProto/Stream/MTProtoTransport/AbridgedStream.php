<?php

declare(strict_types=1);

/**
 * Abridged stream wrapper.
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

namespace danog\MadelineProto\Stream\MTProtoTransport;

use Amp\Socket\EncryptableSocket;
use danog\MadelineProto\Stream\BufferedStreamInterface;
use danog\MadelineProto\Stream\ConnectionContext;
use danog\MadelineProto\Stream\MTProtoBufferInterface;
use danog\MadelineProto\Stream\RawStreamInterface;

/**
 * Abridged stream wrapper.
 *
 * @author Daniil Gentili <daniil@daniil.it>
 */
final class AbridgedStream implements BufferedStreamInterface, MTProtoBufferInterface
{
    private $stream;
    /**
     * Connect to stream.
     *
     * @param ConnectionContext $ctx The connection context
     */
    public function connect(ConnectionContext $ctx, string $header = ''): void
    {
        $this->stream = ($ctx->getStream(\chr(239).$header));
    }
    /**
     * Async close.
     */
    public function disconnect(): void
    {
        $this->stream->disconnect();
    }
    /**
     * Get write buffer asynchronously.
     *
     * @param int $length Length of data that is going to be written to the write buffer
     */
    public function getWriteBuffer(int $length, string $append = ''): \danog\MadelineProto\Stream\WriteBufferInterface
    {
        $length >>= 2;
        if ($length < 127) {
            $message = \chr($length);
        } else {
            $message = \chr(127).\substr(\pack('V', $length), 0, 3);
        }
        $buffer = $this->stream->getWriteBuffer(\strlen($message) + $length, $append);
        $buffer->bufferWrite($message);
        return $buffer;
    }
    /**
     * Get read buffer asynchronously.
     *
     * @param int $length Length of payload, as detected by this layer
     */
    public function getReadBuffer(?int &$length): \danog\MadelineProto\Stream\ReadBufferInterface
    {
        $buffer = $this->stream->getReadBuffer($l);
        $length = \ord($buffer->bufferRead(1));
        if ($length >= 127) {
            $length = \unpack('V', ($buffer->bufferRead(3))."\0")[1];
        }
        $length <<= 2;
        return $buffer;
    }
    /**
     * {@inheritdoc}
     */
    public function getSocket(): EncryptableSocket
    {
        return $this->stream->getSocket();
    }
    /**
     * {@inheritDoc}
     */
    public function getStream(): RawStreamInterface
    {
        return $this->stream;
    }
    public static function getName(): string
    {
        return self::class;
    }
}
