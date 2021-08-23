<?php declare(strict_types=1);

namespace HB9AKM\API\V2\Model\Entity;

use Psr\Http\Message\ResponseInterface as ServerResponse;

class Response {

    const MSG_ERROR = 'error';

    protected \HB9AKM\API\Main $main;
    protected ServerResponse $serverResponse;
    protected ?Request $request = null;
    protected array $messages = array();
    protected array $entities = array();
    protected int $totalCount = 0;
    protected int $totalFilteredCount = 0;

    public function __construct(\HB9AKM\API\Main $main, ServerResponse $serverResponse) {
        $this->main = $main;
        $this->serverResponse = $serverResponse;

        // TODO: Register error handler
        $errorMiddleware = $this->main->getApp()->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(array($this, 'errorHandler'));
    }

    public function errorHandler (
        \Psr\Http\Message\ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {
        $this->addMessage(static::MSG_ERROR, $exception->getMessage());
        return $this->getServerResponse();
    }

    public function setRequest(Request $request): void {
        $this->request = $request;
    }

    public function addMessage(string $type, string $msg): void {
        if (!isset($this->messages[$type])) {
            $this->messages[$type] = array();
        }
        $this->messages[$type][] = $msg;
    }

    public function getMessages(): array {
        return $this->messages;
    }

    public function setEntities(array $entities): void {
        foreach ($entities as $entity) {
            $this->entities[] = $entity->toArray();
        }
    }

    public function setTotalCount(int $count): void {
        $this->totalCount = $count;
    }

    public function setTotalFilteredCount(int $count): void {
        $this->totalFilteredCount = $count;
    }

    public function getServerResponse(): ServerResponse {
        $endpointClass = get_class($this->request->getEndpoint());
        $this->serverResponse->getBody()->write(
            json_encode(array(
                'request' => ($this->request ? $this->request->getParsed() : null),
                'response' => array(
                    'messages' => $this->getMessages(),
                    'parseInfo' => array(
                        'apiVersion' => 2.0,
                        'fromCache' => false,
                        'lastCacheUpdate' => null,
                        'parseTime' => (microtime(true) - $this->main->getStartTime()),
                    ),
                    'endpoint' => array(
                        'name' => $this->request->getEndpoint()->getName(),
                        'label' => array(),
                        'description' => array(),
                        'fields' => $endpointClass::getFieldDefinitions(),
                    ),
                    'numberOfEntries' => array(
                        'total' => $this->totalCount,
                        'filtered' => $this->totalFilteredCount,
                        'returned' => count($this->entities),
                    ),
                ),
                'entities' => $this->entities,
            ))
        );
        return $this->serverResponse->withHeader('Content-Type', 'application/json');
    }
}
