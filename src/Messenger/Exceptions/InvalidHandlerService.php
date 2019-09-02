<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use Exception;
use ReflectionNamedType;
use ReflectionType;
use function sprintf;

final class InvalidHandlerService extends Exception
{
    public static function missingInvokeMethod(string $serviceName, string $className) : self
    {
        return new self(sprintf(
            'Invalid handler service "%s": class "%s" must have an "__invoke()" method.',
            $serviceName,
            $className
        ));
    }

    public static function missingArgumentType(string $serviceName, string $className, string $parameterName) : self
    {
        return new self(sprintf(
            'Invalid handler service "%s": argument "$%s" of method "%s::__invoke()"'
            . ' must have a type-hint corresponding to the message class it handles.',
            $serviceName,
            $parameterName,
            $className
        ));
    }

    public static function wrongAmountOfArguments(string $serviceName, string $className) : self
    {
        return new self(sprintf(
            'Invalid handler service "%s": method "%s::__invoke()" requires exactly one argument,'
            . ' first one being the message it handles.',
            $serviceName,
            $className
        ));
    }

    public static function invalidArgumentType(
        string $serviceName,
        string $className,
        string $parameterName,
        ReflectionType $type
    ) : self {
        return new self(sprintf(
            'Invalid handler service "%s": type-hint of argument "$%s"'
            . ' in method "%s::__invoke()" must be a class , "%s" given.',
            $serviceName,
            $parameterName,
            $className,
            $type instanceof ReflectionNamedType ? $type->getName() : (string) $type
        ));
    }
}
