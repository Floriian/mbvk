<?php

namespace App\Service;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class XmlParserService
{
    public function __construct(
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function parse(string $xml, mixed $dtoClass): object
    {
        libxml_use_internal_errors(true);
        if (simplexml_load_string($xml) === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \InvalidArgumentException(
                'Invalid XML: ' . implode(', ', array_map(fn($e) => trim($e->message), $errors))
            );
        }

        if(!class_exists($dtoClass)) {
            throw new \InvalidArgumentException("DTO class $dtoClass does not exist");
        }

        $dto = $dtoClass::fromXml($xml);
        if(!is_object($dto)) {
            throw new \InvalidArgumentException("DTO class $dtoClass::fromXml did not return an object");
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations);
        }

        return $dto;
    }
}
