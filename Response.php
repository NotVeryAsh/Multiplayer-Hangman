<?php

class Response {

    const MESSAGE_INPUT = 0;
    const MESSAGE_INFO = 1;
    const MESSAGE_SET_VAR = 2;
    const MESSAGE_HANDSHAKE = 3;
    const MESSAGE_PING = 4;

    static function formatResponse($message, $type, $var = null)
    {
        $response = [
            'message' => $message,
            'type' => $type
        ];

        if($var) {
            $response['var'] = $var;
        }

        return json_encode($response);
    }

    static function getVarFromString($string)
    {
        return explode(":", $string, 2);
    }
}
