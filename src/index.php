<?php
declare(strict_types=1);
namespace HB9AKM\API;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/Endpoint.php');

class Main {
    protected \Slim\App $app;
    protected array $endpoints = array();

    public function __construct() {
        $this->app = AppFactory::create();

        $this->endpoints = $this->loadEndpoints();

        header('Access-Control-Allow-Origin: *');

        $this->app->run();
    }

    protected function loadEndpoints(): array {
        $endpoints = array();
        foreach (new \DirectoryIterator(__DIR__ . '/v1/endpoints/') as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isDir()) {
                continue;
            }

            try {
                $endpoints[] = $this->loadEndpoint($fileInfo->getFilename());
            } catch (\Exception $e) {
                // todo: handle exception
                echo $e->getMessage();
                die();
            }
        }
        return $endpoints;
    }

    protected function loadEndpoint($name): Endpoint {
        $mainFile = __DIR__ . '/v1/endpoints/' . $name . '/Main.php';

        if (!file_exists($mainFile)) {
            throw new \Exception('Endpoint main file does not exist');
        }

        require_once($mainFile);

        $className = '\HB9AKM\API\Endpoint\\' . ucfirst($name) . '\Main';
        $endpoint = new $className($this, $name);
        if (!is_a($endpoint, '\HB9AKM\API\Endpoint', true)) {
            throw new \Exception('Endpoint main class error');
        }
        return $endpoint;
    }

    public function getApp(): \Slim\App {
        return $this->app;
    }
}

new \HB9AKM\API\Main();
