<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use Exception;
use ReflectionParameter;
use RuntimeException;

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

    public static function missingArgumentType(string $serviceName, ReflectionParameter $parameter) : self
    {
        return new self(sprintf(
            'Invalid handler service "%s": argument "$%s" of method "%s::__invoke()"'
            . ' must have a type-hint corresponding to the message class it handles.',
            $serviceName,
            $parameter->getName(),
            $parameter->getClass()->getName()
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

    public static function invalidArgumentType(string $serviceName, ReflectionParameter $parameter) : self
    {
        $type = $parameter->getType();

        return new self(sprintf(
            'Invalid handler service "%s": type-hint of argument "$%s"'
            . ' in method "%s::__invoke()" must be a class , "%s" given.',
                $serviceName,
                $parameter->getName(),
                $parameter->getClass()->getName(),
                $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type
        ));
    }
}
