<?php

namespace StasPiv\ChessBestMove\Web;

use JMS\Serializer\SerializerBuilder;
use StasPiv\ChessBestMove\Service\ChessBestMove;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WebSocket\Client;

class Router
{
    private ChessBestMove $bestMove;

    /**
     * Router constructor.
     *
     * @param ChessBestMove $bestMove
     */
    public function __construct(ChessBestMove $bestMove)
    {
        $this->bestMove = $bestMove;
    }

    /**
     * @param string $httpMethod
     * @param string $requestUri
     * @param array  $request
     *
     * @return array
     */
    public function getResponse(string $httpMethod, string $requestUri, array $request): array
    {
        $result = parse_url($requestUri);

        switch ($httpMethod) {
            case Request::METHOD_POST:
                switch ($result['path']) {
                    case '/infinite/start':
                        return $this->startInfinite($request);
                    case '/infinite/stop':
                        return $this->stopInfinite($request);
                }
                break;
            case Request::METHOD_GET:
                switch ($result['path']) {
                    case '/bestmove':
                        return $this->getBestMove($request);
                }
                break;
        }

        http_response_code(Response::HTTP_NOT_FOUND);
        return [
            'error'   => 'Unknown request'
        ];
    }

    public function startInfinite(array $request)
    {
        if (!isset($request['fen']) || !isset($request['ws_url'])) {
            http_response_code(Response::HTTP_BAD_REQUEST);
            return [
                'error' => 'fen and ws_url required'
            ];
        }

        $wsClient = new Client(
            $request['ws_url'],
            [
                'timeout' => 60 * 60 * 24
            ]
        );

        $wsClient->send('start-infinite ' . $request['fen']);

        return ['success' => true];
    }

    public function stopInfinite(array $request)
    {
        if (!isset($request['ws_url'])) {
            http_response_code(Response::HTTP_BAD_REQUEST);
            return [
                'error' => 'ws_url required'
            ];
        }

        $wsClient = new Client(
            $request['ws_url'],
            [
                'timeout' => 60 * 60 * 24
            ]
        );

        $wsClient->send('stop-infinite');

        return ['success' => true];
    }

    public function getBestMove(array $request)
    {
        if (!isset($request['fen'])) {
            http_response_code(Response::HTTP_BAD_REQUEST);
            return [
                'error' => 'no fen'
            ];
        }

        return SerializerBuilder::create()->build()->toArray(
            $this->bestMove->getBestMoveFromFen(
                $request['fen'],
                isset($request['movetime']) ? $request['movetime'] : 3000
            )
        );
    }
}