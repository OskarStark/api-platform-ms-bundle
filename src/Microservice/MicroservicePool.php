<?php

namespace Mtarld\ApiPlatformMsBundle\Microservice;

use IteratorAggregate;
use Mtarld\ApiPlatformMsBundle\Exception\MicroserviceConfigurationException;
use Mtarld\ApiPlatformMsBundle\Exception\MicroserviceNotConfiguredException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Traversable;

/**
 * @final
 */
class MicroservicePool implements IteratorAggregate
{
    private const SUPPORTED_FORMATS = ['jsonld', 'jsonapi', 'jsonhal'];

    private $validator;

    /**
     * @var array<array-key, array<array-key, string>>
     */
    private $configs;

    /**
     * @var array<array-key, Microservice>
     */
    private $microservices = [];

    public function __construct(
        ValidatorInterface $validator,
        array $microserviceConfigs = []
    ) {
        $this->validator = $validator;
        $this->configs = $microserviceConfigs;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->configs);
    }

    public function get(string $name): Microservice
    {
        if (!array_key_exists($name, $this->microservices)) {
            $this->microservices[$name] = $this->createMicroservice($name);
        }

        return $this->microservices[$name];
    }

    public function getIterator(): Traversable
    {
        foreach (array_keys($this->configs) as $name) {
            yield $this->get($name);
        }
    }

    private function createMicroservice(string $name): Microservice
    {
        if (!$this->has($name)) {
            throw new MicroserviceNotConfiguredException($name);
        }

        $config = $this->configs[$name];

        $microservice = new Microservice($name, $config['base_uri'], $config['api_path'] ?? '', $config['format']);
        $this->validateMicroservice($microservice);

        return $microservice;
    }

    /**
     * @throws MicroserviceConfigurationException
     */
    private function validateMicroservice(Microservice $microservice): void
    {
        $violations = $this->validator->validate($microservice);
        if ($violations->has(0)) {
            throw new MicroserviceConfigurationException($microservice->getName(), sprintf("'%s': %s", $violations->get(0)->getPropertyPath(), (string) $violations->get(0)->getMessage()));
        }

        if (!in_array($microservice->getFormat(), self::SUPPORTED_FORMATS)) {
            throw new MicroserviceConfigurationException($microservice->getName(), sprintf("'%s' format isn't supported by API Platform microservice bundle, which are %s", $microservice->getFormat(), implode(',', self::SUPPORTED_FORMATS)));
        }
    }
}