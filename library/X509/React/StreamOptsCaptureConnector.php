<?php

// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509\React;

use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

use function React\Promise\resolve;

/**
 * Connector that captures stream context options upon close of the underlying connection
 */
class StreamOptsCaptureConnector implements ConnectorInterface
{
    /** @var array|null */
    protected $capturedStreamOptions;

    /** @var ConnectorInterface */
    protected $connector;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    /**
     * @return array
     */
    public function getCapturedStreamOptions()
    {
        return (array) $this->capturedStreamOptions;
    }

    /**
     * @param array $capturedStreamOptions
     *
     * @return $this
     */
    public function setCapturedStreamOptions($capturedStreamOptions)
    {
        $this->capturedStreamOptions = $capturedStreamOptions;

        return $this;
    }

    public function connect($uri)
    {
        return $this->connector->connect($uri)->then(function (ConnectionInterface $conn) {
            $conn->on('close', function () use ($conn) {
                if (is_resource($conn->stream)) {
                    $this->setCapturedStreamOptions(stream_context_get_options($conn->stream));
                }
            });

            return resolve($conn);
        });
    }
}
