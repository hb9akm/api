<?php declare(strict_types=1);

namespace HB9AKM\API\V2\Model\Entity;

use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use HB9AKM\API\V2\Model\Repository\AbstractRepository as AbstractRepository;
use HB9AKM\API\V2\Model\Entity\Endpoint;

class Request {
    const MAX_LIMIT = 10000;
    protected ?ServerRequest $serverRequest = null;

    /**
     * @var array 2 dimensional. First dimension key is fieldname, second
     *              dimension key is operation. Value is comparision value.
     */
    protected array $filter = array();

    /**
     * @var array Key is fieldname, value is either "ASC" or "DESC"
     */
    protected array $sorting = array();
    protected int $offset = 0;
    protected int $limit = self::MAX_LIMIT;
    protected string $endpointName;
    protected int $apiVersion;
    protected ?Endpoint $endpoint = null;

    public static function fromServerRequest(ServerRequest $serverRequest): Request {
        $query = $serverRequest->getQueryParams();
        $request = new static(explode('/', $serverRequest->getPath()), $query);
        $request->setServerRequest($serverRequest);
        return $request;
    }

    public static function fromCurrentRequest(): Request {
        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
        $request = new static(explode('/', $path), $_GET);
        return $request;
    }

    public function __construct(array $pathParts, array $query) {
        array_shift($pathParts);
        if (!isset($pathParts[0]) || !preg_match('/v\d/', $pathParts[0])) {
            throw new \Exception('Illegal API version format');
        }
        $this->apiVersion = (int) substr($pathParts[0], 1);
        if (empty($pathParts[1])) {
            throw new \Exception('No endpoint specified');
        }
        $this->endpointName = $pathParts[1];
        if (isset($query['filter'])) {
            if (!is_array($query['filter'])) {
                throw new \Exception('Filter needs to be an array');
            }
            foreach ($query['filter'] as $fieldname=>&$ops) {
                foreach ($ops as $operation=>$value) {
                    if (!in_array($operation, AbstractRepository::$knownFilters)) {
                        throw new \Exception('Filter operation needs to be one of "' . implode('", "', AbstractRepository::$knownFilters) . '"');
                    }
                }
            }
            $this->filter = $query['filter'];
        }
        if (isset($query['sort'])) {
            if (!is_array($query['sort'])) {
                throw new \Exception('Sort needs to be an array');
            }
            foreach ($query['sort'] as $key=>&$value) {
                $value = strtoupper($value);
                if (!in_array($value, array('ASC', 'DESC'))) {
                    throw new \Exception('Sort array value needs to be "ASC" or "DESC"');
                }
            }
            $this->sorting = $query['sort'];
        }
        if (isset($query['offset'])) {
            if (!is_numeric($query['offset'])) {
                throw new \Exception('Offset must be numeric');
            }
            if ($query['offset'] < 0) {
                throw new \Exception('Offset cannot be smaller than 0');
            }
            $this->offset = (int) $query['offset'];
        }
        if (isset($query['limit'])) {
            if (!is_numeric($query['limit'])) {
                throw new \Exception('Limit must be numeric');
            }
            if ($query['limit'] > static::MAX_LIMIT) {
                throw new \Exception('Limit cannot be larger than ' . static::MAX_LIMIT);
            }
            if ($query['limit'] < 1) {
                throw new \Exception('Limit cannot be smaller than 1');
            }
            $this->limit = (int) $query['limit'];
        }
    }

    public function setServerRequest(ServerRequest $serverRequest) {
        $this->serverRequest = $serverRequest;
    }

    public function getFilter(): array {
        return $this->filter;
    }

    public function getSorting(): array {
        return $this->sorting;
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function getLimit(): int {
        return $this->limit;
    }

    public function getApiVersion(): int {
        return $this->apiVersion;
    }

    public function getEndpointName(): string {
        return $this->endpointName;
    }

    public function getEndpoint(): ?Endpoint {
        return $this->endpoint;
    }

    public function setEndpoint(Endpoint $endpoint): void {
        $this->endpoint = $endpoint;
    }

    public function getParsed(): array {
        return array(
            'apiVersion' => $this->getApiVersion(),
            'endpoint' => $this->getEndpointName(),
            'filter' => $this->getFilter(),
            'sort' => $this->getSorting(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
        );
    }
}
