<?php
declare(strict_types=1);
namespace HB9AKM\API;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class Endpoint {
    protected \HB9AKM\API\Main $main;
    protected string $name;

    public function __construct(\HB9AKM\API\Main $main, string $name) {
        $this->main = $main;
        $this->name = $name;
        $this->init();
    }

    protected abstract function init(): void;
}

