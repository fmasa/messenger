<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use Exception;
use ReflectionNamedType;
use ReflectionType;

use function implode;
use function sprintf;

final class InvalidHandlerService extends Exception
{
    public static function missingHandlerMethod(string $serviceName, string $className, string $methodName): self
    {
        return new self(sprintf(
            'Invalid handler service "%s": class "%s" must have an "%s()" method.',
            $serviceName,
            $className,
            $methodName
        ));
    }

    public static function missingArgumentType(string $serviceName, string $className, string $methodName, string $parameterName): self
    {
        return new self(sprintf(
            'Invalid handler service "%s": argument "$%s" of method "%s::%s()"'
            . ' must have a type-hint corresponding to the message class it handles.',
            $serviceName,
            $parameterName,
            $className,
            $methodName
        ));
    }

    public static function wrongAmountOfArguments(string $serviceName, string $className, string $methodName): self
    {
        return new self(sprintf(
            'Invalid handler service "%s": method "%s::%s()" requires exactly one argument,'
            . ' first one being the message it handles.',
            $serviceName,
            $className,
            $methodName
        ));
    }

    public static function invalidArgumentType(
        string $serviceName,
        string $className,
        string $methodName,
        string $parameterName,
        ReflectionType $type
    ): self {
        return new self(sprintf(
            'Invalid handler service "%s": type-hint of argument "$%s"'
            . ' in method "%s::%s()" must be a class , "%s" given.',
            $serviceName,
            $parameterName,
            $className,
            $methodName,
            $type instanceof ReflectionNamedType ? $type->getName() : (string) $type
        ));
    }

    /**
     * @param array<string> $types
     */
    public static function invalidArgumentUnionType(
        string $serviceName,
        string $className,
        string $methodName,
        string $parameterName,
        array $types
    ): self {
        return new self(sprintf(
            'Invalid handler service "%s": type-hint of argument "$%s"'
            . ' in method "%s::%s()" must be a class , "%s" given.',
            $serviceName,
            $parameterName,
            $className,
            $methodName,
            implode('|', $types)
        ));
    }
}
