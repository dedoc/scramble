<?php

namespace Dedoc\Scramble\Support\ExceptionToResponseExtensions;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class HttpExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(HttpException::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        /*
         * So you (Roman from future) are wondering what 7 or 0 is.
         * When index is 7 – the type is honestly inferred – this the index of `TCode` template.
         * When index is 0 - the type is manually constructed in other extensions.
         */
        $codeType = count($type->templateTypes ?? []) > 3
            ? ($type->templateTypes[7] ?? null)
            : ($type->templateTypes[0] ?? null);

        $responseCode = $this->getResponseCode($codeType, $type);
        if ($responseCode === null) {
            return null;
        }

        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'message',
                tap((new OpenApiTypes\StringType)->setDescription('Error overview.'), function (OpenApiTypes\StringType $t) use ($type) {
                    $messageType = $type->templateTypes[1] ?? null;
                    if (! $messageType instanceof LiteralStringType) {
                        return;
                    }
                    $t->example($messageType->value);
                })
            )
            ->setRequired(['message']);

        return Response::make($responseCode)
            ->setDescription($this->getDescription($type))
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType),
            );
    }

    protected function getResponseCode(?Type $codeType, Type $type): ?int
    {
        if (! $codeType instanceof LiteralIntegerType) {
            return match (true) {
                $type->isInstanceOf(AccessDeniedHttpException::class) => 403,
                $type->isInstanceOf(BadRequestHttpException::class) => 400,
                $type->isInstanceOf(ConflictHttpException::class) => 409,
                $type->isInstanceOf(GoneHttpException::class) => 410,
                $type->isInstanceOf(LengthRequiredHttpException::class) => 411,
                $type->isInstanceOf(LockedHttpException::class) => 423,
                $type->isInstanceOf(MethodNotAllowedHttpException::class) => 405,
                $type->isInstanceOf(NotAcceptableHttpException::class) => 406,
                $type->isInstanceOf(PreconditionFailedHttpException::class) => 412,
                $type->isInstanceOf(PreconditionRequiredHttpException::class) => 428,
                $type->isInstanceOf(ServiceUnavailableHttpException::class) => 503,
                $type->isInstanceOf(TooManyRequestsHttpException::class) => 429,
                $type->isInstanceOf(UnauthorizedHttpException::class) => 401,
                $type->isInstanceOf(UnprocessableEntityHttpException::class) => 422,
                $type->isInstanceOf(UnsupportedMediaTypeHttpException::class) => 415,
                default => null,
            };
        }

        return $codeType->value;
    }

    protected function getDescription(Type $type): string
    {
        return match (true) {
            $type->isInstanceOf(AccessDeniedHttpException::class) => 'Access denied',
            $type->isInstanceOf(BadRequestHttpException::class) => 'Bad request',
            $type->isInstanceOf(ConflictHttpException::class) => 'Conflict',
            $type->isInstanceOf(GoneHttpException::class) => 'Gone',
            $type->isInstanceOf(LengthRequiredHttpException::class) => 'Length required',
            $type->isInstanceOf(LockedHttpException::class) => 'Locked',
            $type->isInstanceOf(MethodNotAllowedHttpException::class) => 'Method not allowed',
            $type->isInstanceOf(NotAcceptableHttpException::class) => 'Not acceptable',
            $type->isInstanceOf(NotFoundHttpException::class) => 'Not found',
            $type->isInstanceOf(PreconditionFailedHttpException::class) => 'Precondition failed',
            $type->isInstanceOf(PreconditionRequiredHttpException::class) => 'Precondition required',
            $type->isInstanceOf(ServiceUnavailableHttpException::class) => 'Service unavailable',
            $type->isInstanceOf(TooManyRequestsHttpException::class) => 'Too many requests',
            $type->isInstanceOf(UnauthorizedHttpException::class) => 'Unauthorized',
            $type->isInstanceOf(UnprocessableEntityHttpException::class) => 'Unprocessable entity',
            $type->isInstanceOf(UnsupportedMediaTypeHttpException::class) => 'Unsupported media type',
            default => 'An error',
        };
    }
}
