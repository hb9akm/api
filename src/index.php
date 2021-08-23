<?php
declare(strict_types=1);
namespace HB9AKM\API;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/Endpoint.php');

/**
 * This handles the request initializations for the different API versions
 * @TODO: This does way too much. Most of this code should be withing the respective API version
 */
class Main {
    protected static ?Main $main = null;
    protected float $startTime;
    protected \Slim\App $app;
    protected array $endpoints = array();
    protected ?object $em = null;
    protected $response = null;

    public static function getInstance(): Main {
        return static::$main;
    }

    public function __construct() {
        $this->startTime = microtime(true);
        static::$main = $this;
        $requestUrlParts = explode('/', $_SERVER['REQUEST_URI']);
        if (!isset($requestUrlParts[1])) {
            header('Content-Type: application/json');
            die(json_encode(array(
                'response' => array(
                    'messages' => array(
                        array(
                            'type' => 'error',
                            'message' => 'No API version specified',
                        ),
                    ),
                ),
            )));
        }
        switch ($requestUrlParts[1]) {
            case 'v1':
                $this->app = AppFactory::create();
                $this->endpoints = $this->loadEndpoints();
                header('Access-Control-Allow-Origin: *');
                $this->app->run();
                break;
            case 'v2':
                // TODO: caching
                try {
                    $this->app = AppFactory::create();
                    $this->endpoints = $this->loadEndpointsV2();
                    header('Access-Control-Allow-Origin: *');
                    $this->app->run();
                } catch (\Exception $e) {
                    die(json_encode(array(
                        'response' => array(
                            'messages' => array(
                                array(
                                    'type' => 'error',
                                    'message' => $e->getMessage(),
                                ),
                            ),
                        ),
                    )));
                }
                break;
            default:
                header('Content-Type: application/json');
                die(json_encode(array(
                    'response' => array(
                        'messages' => array(
                            array(
                                'type' => 'error',
                                'message' => 'Unknown API version',
                            ),
                        ),
                    ),
                )));
                break;
        }
    }

    public function getEm(): EntityManager {
        if ($this->em) {
            return $this->em;
        }
        $config = new \Doctrine\ORM\Configuration;
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Proxy');
        $config->setAutoGenerateProxyClasses(false); // this can be based on production config.
        $cache = new \Doctrine\Common\Cache\ArrayCache();
        $config->setMetadataCacheImpl($cache);
        $config->setQueryCacheImpl($cache);

        $conn = array(
            'dbname' => 'dev',
            'user' => 'root',
            'password' => '123456',
            'host' => 'db',
            'driver' => 'pdo_mysql',
        );
        //$annotationReader = new \Doctrine\Common\Annotations\AnnotationReader();
        $annotationDriver = $config->newDefaultAnnotationDriver();
        $driverChain = new \Doctrine\ORM\Mapping\Driver\DriverChain();
        /*\Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
            $driverChain, // our metadata driver chain, to hook into
            $annotationDriver->getReader() // our cached annotation reader
        );*/

        $yamlDriver = new \Doctrine\ORM\Mapping\Driver\YamlDriver(array(
            __DIR__ . '/v2/Model/Schema'
        ));
        $driverChain->addDriver($yamlDriver, 'HB9AKM');
        $config->setMetadataDriverImpl($driverChain);

        $evm = new \Doctrine\Common\EventManager();

        $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
        $timestampableListener->setAnnotationReader($annotationDriver->getReader());
        $evm->addEventSubscriber($timestampableListener);

        $translatableListener = new \Gedmo\Translatable\TranslatableListener();
        $translatableListener->setTranslatableLocale('en-US');
        $translatableListener->setDefaultLocale('en-US');
        $translatableListener->setAnnotationReader($annotationDriver->getReader());
        $translatableListener->setTranslationFallback(false);
        $evm->addEventSubscriber($translatableListener);

        /*$annotationDriver2 = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
            $annotationDriver->getReader(),
            __DIR__ . '/vendor/gedmo/doctrine-extensions/src/Translatable/Entity'
        );
        $driverChain->addDriver($annotationDriver2, 'Gedmo\Translatable');*/

        // mysql set names UTF-8 if required
        $evm->addEventSubscriber(new \Doctrine\DBAL\Event\Listeners\MysqlSessionInit());
        $this->em = EntityManager::create($conn, $config, $evm);
        return $this->em;
    }

    protected function loadEndpointsV2(): array {
        $this->request = \HB9AKM\API\V2\Model\Entity\Request::fromCurrentRequest();
        $errorMiddleware = $this->getApp()->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(array($this, 'v2ErrorHandlerfunction'));

        // TODO: Create Request object here and hash its parsed form
        // TODO: Check if cache for the hash exists (and is not too old)

        $endpointRepo = $this->getEm()->getRepository('HB9AKM\API\V2\Model\Entity\Endpoint');
        $endpoints = array();
        foreach ($endpointRepo->findAll() as $endpoint) {
            $endpoints[$endpoint->getName()] = $endpoint;
        }

        if (!isset($endpoints[$this->request->getEndpointName()])) {
            die(json_encode(array(
                'response' => array(
                    'messages' => array(
                        array(
                            'type' => 'error',
                            'message' => 'No endpoint named "' . $this->request->getEndpointName() .
                                '". Valid endpoints are: "' .
                                implode('", "', array_keys($endpoints)) . '"',
                        ),
                    ),
                ),
            )));
        }
        $endpoint = $endpoints[$this->request->getEndpointName()];
        $this->request->setEndpoint($endpoint);

        $this->getApp()->get(
            '/v2/' . $endpoint->getName(),
            function (Request $request, Response $response, $args) use ($endpoint) {
                $this->response = new \HB9AKM\API\V2\Model\Entity\Response($this, $response);
                $this->response->setRequest($this->request);
                $repo = $this->getEm()->getRepository(
                    get_class($endpoint)
                );
                return $repo->handleRequest($this, $this->request, $this->response, $args);
            }
        );
        return $endpoints;
    }

    public function v2ErrorHandlerfunction (
        \Psr\Http\Message\ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        $status = 500;
        if ($exception->getCode() > 400 && $exception->getCode() < 600) {
            $status = $exception->getCode();
        }
        $response = $this->getApp()->getResponseFactory()->createResponse()->withStatus(
            $status
        );

        $this->request->setServerRequest($request);

        if (!$this->response) {
            $this->response = new \HB9AKM\API\V2\Model\Entity\Response($this, $response);
            $this->response->setRequest($this->request);
        }
        $this->response->addMessage(\HB9AKM\API\V2\Model\Entity\Response::MSG_ERROR, $exception->getMessage());
        return $this->response->getServerResponse();
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

    public function getStartTime(): float {
        return $this->startTime;
    }
}

new \HB9AKM\API\Main();
