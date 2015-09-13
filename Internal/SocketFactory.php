<?php

namespace EXSyst\Component\Worker\Internal;

use EXSyst\Component\Worker\Exception;

final class SocketFactory
{
    private function __construct()
    {
    }

    private static function doCreateServerSocket($socketAddress, $socketContext = null)
    {
        set_error_handler(null);
        if ($socketContext !== null) {
            $socket = @stream_socket_server($socketAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $socketContext);
        } else {
            $socket = @stream_socket_server($socketAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        }
        restore_error_handler();
        if ($socket === false) {
            throw new Exception\BindOrListenException($errstr, $errno);
        }

        return $socket;
    }

    public static function createServerSocket($socketAddress, $socketContext = null)
    {
        try {
            return self::doCreateServerSocket($socketAddress, $socketContext);
        } catch (Exception\BindOrListenException $e) {
            if (strpos($e->getMessage(), 'Address already in use') !== false && ($socketFile = IdentificationHelper::getSocketFile($socketAddress)) !== null) {
                try {
                    fclose(self::createClientSocket($socketAddress, 1, $socketContext));
                    // Really in use
                    throw $e;
                } catch (Exception\ConnectException $e2) {
                    // False positive due to a residual socket file
                    unlink($socketFile);

                    return self::doCreateServerSocket($socketAddress, $socketContext);
                }
            } else {
                throw $e;
            }
        }
    }

    public static function createClientSocket($socketAddress, $timeout = null, $socketContext = null)
    {
        if ($timeout === null) {
            $timeout = intval(ini_get('default_socket_timeout'));
        }
        set_error_handler(null);
        if ($socketContext !== null) {
            $socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        }
        restore_error_handler();
        if ($socket === false) {
            throw new Exception\ConnectException($errstr, $errno);
        }

        return $socket;
    }
}
