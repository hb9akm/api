<?php
declare(strict_types=1);
namespace HB9AKM\API\Endpoint\Repeater;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Main extends \HB9AKM\API\Endpoint {
    protected function init(): void {
        $this->main->getApp()->get(
            '/v1/repeater',
            function (Request $request, Response $response, $args) {
                $response->getBody()->write(
                    file_get_contents(__DIR__ . '/voiceRepeater.json')
                );
                return $response->withHeader('Content-Type', 'application/json');
            }
        );
    }
}
