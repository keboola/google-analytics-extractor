<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor;

use Generator;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\GoogleAnalyticsExtractor\Exception\ApplicationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ComponentTest extends TestCase
{
    /**
     * A 404 from the Google Analytics API used to fall through to ApplicationException, which makes the
     * job end with an opaque "internal error" (exit code 2). It has to surface as a UserException
     * (exit code 1) with an actionable message instead. The job still fails either way.
     */
    public function testNotFoundIsSurfacedAsUserException(): void
    {
        $body = 'The requested URL /v4/reports:batchGet was not found on this server.';
        $exception = $this->createRequestException(404, $body);

        try {
            $this->handleException($exception);
            self::fail('Expected a UserException to be thrown.');
        } catch (UserException $e) {
            self::assertSame(404, $e->getCode());
            self::assertStringContainsString('404 Not Found', $e->getMessage());
            self::assertStringContainsString('GA4 properties', $e->getMessage());
            self::assertStringContainsString($body, $e->getMessage());
            self::assertSame($exception, $e->getPrevious());
        }
    }

    /**
     * Guards the scope of the change above: every other status code that was not already mapped
     * keeps falling through to ApplicationException exactly as before.
     *
     * @dataProvider unmappedStatusCodeProvider
     */
    public function testUnmappedStatusCodesStillRaiseApplicationException(int $statusCode): void
    {
        $this->expectException(ApplicationException::class);

        $this->handleException($this->createRequestException($statusCode, 'some error body'));
    }

    public function unmappedStatusCodeProvider(): Generator
    {
        yield '405 Method Not Allowed' => [405];
        yield '409 Conflict' => [409];
        yield '500 Internal Server Error' => [500];
    }

    /**
     * Guards the neighbouring branches of the change: the status codes that were already mapped to a
     * UserException keep their existing behaviour.
     *
     * @dataProvider alreadyMappedStatusCodeProvider
     */
    public function testAlreadyMappedStatusCodesKeepRaisingUserException(int $statusCode): void
    {
        $this->expectException(UserException::class);

        $this->handleException($this->createRequestException($statusCode, 'some error body'));
    }

    public function alreadyMappedStatusCodeProvider(): Generator
    {
        yield '400 Bad Request' => [400];
        yield '401 Unauthorized' => [401];
        yield '429 Too Many Requests' => [429];
        yield '502 Bad Gateway' => [502];
        yield '503 Service Unavailable' => [503];
    }

    private function createRequestException(int $statusCode, string $body): RequestException
    {
        $request = new Request('POST', 'https://analyticsreporting.googleapis.com/v4/reports:batchGet');
        $response = new Response($statusCode, [], $body);
        $message = sprintf('HTTP error: %d', $statusCode);

        return $statusCode >= 500
            ? new ServerException($message, $request, $response)
            : new ClientException($message, $request, $response);
    }

    /**
     * Component::handleException() is private and the surrounding BaseComponent constructor needs a
     * data directory, a config and a manifest manager. The method only ever touches the exception and
     * the logger, so the instance is created without the constructor and only the logger is injected.
     */
    private function handleException(RequestException $exception): void
    {
        $component = (new ReflectionClass(Component::class))->newInstanceWithoutConstructor();

        $logger = new ReflectionProperty(BaseComponent::class, 'logger');
        $logger->setValue($component, new NullLogger());

        (new ReflectionMethod(Component::class, 'handleException'))->invoke($component, $exception);
    }
}
