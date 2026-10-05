<?php

namespace SilverShop\VatCompliance\Tests\Vat;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use SilverShop\VatCompliance\Vat\ViesLookup;
use SilverShop\VatCompliance\Vat\ViesStatus;
use SilverShop\VatCompliance\Vat\ViesUnavailableException;
use SilverShop\VatCompliance\Vat\ViesValidator;

class ViesValidatorTest extends TestCase
{
    public function testValidNumberReturnsValidWithDetails(): void
    {
        $lookup = new FakeViesLookup(['valid' => true, 'name' => 'ACME GMBH', 'address' => 'Berlin']);
        $result = (new ViesValidator($lookup))->validate('DE123456789');

        $this->assertSame(ViesStatus::Valid, $result->status);
        $this->assertTrue($result->isValid());
        $this->assertSame('DE123456789', $result->vatNumber);
        $this->assertSame('ACME GMBH', $result->name);
        $this->assertSame(1, $lookup->calls);
    }

    public function testInvalidNumberReturnsInvalid(): void
    {
        $lookup = new FakeViesLookup(['valid' => false]);
        $result = (new ViesValidator($lookup))->validate('DE000000000');

        $this->assertSame(ViesStatus::Invalid, $result->status);
    }

    public function testMalformedInputIsInvalidWithoutHittingVies(): void
    {
        $lookup = new FakeViesLookup(['valid' => true]);
        $result = (new ViesValidator($lookup))->validate('???');

        $this->assertSame(ViesStatus::Invalid, $result->status);
        $this->assertSame(0, $lookup->calls, 'malformed input must not call VIES');
    }

    public function testTransportFailureIsUnavailable(): void
    {
        $lookup = new FakeViesLookup(null, throw: true);
        $result = (new ViesValidator($lookup))->validate('DE123456789');

        $this->assertSame(ViesStatus::Unavailable, $result->status);
    }

    public function testValidResultIsCachedButUnavailableIsNot(): void
    {
        $cache = new ArrayCache();

        // First: VIES up and valid -> cached.
        $up = new FakeViesLookup(['valid' => true]);
        (new ViesValidator($up, $cache))->validate('DE123456789');
        $this->assertSame(1, $up->calls);

        // Second call with a lookup that would throw: served from cache, lookup not called.
        $down = new FakeViesLookup(null, throw: true);
        $cached = (new ViesValidator($down, $cache))->validate('DE123456789');
        $this->assertSame(ViesStatus::Valid, $cached->status);
        $this->assertSame(0, $down->calls, 'cached result must not hit VIES again');

        // An Unavailable result is never cached.
        $downOnly = new FakeViesLookup(null, throw: true);
        $validator = new ViesValidator($downOnly, $cache);
        $validator->validate('FR999999999');
        $validator->validate('FR999999999');
        $this->assertSame(2, $downOnly->calls, 'Unavailable results are retried, not cached');
    }
}

class FakeViesLookup implements ViesLookup
{
    public int $calls = 0;

    public function __construct(private ?array $response, private bool $throw = false)
    {
    }

    public function check(string $countryCode, string $number): array
    {
        $this->calls++;
        if ($this->throw) {
            throw new ViesUnavailableException('simulated outage');
        }
        return [
            'valid' => (bool) ($this->response['valid'] ?? false),
            'name' => $this->response['name'] ?? null,
            'address' => $this->response['address'] ?? null,
            'requestIdentifier' => $this->response['requestIdentifier'] ?? null,
        ];
    }
}

class ArrayCache implements CacheInterface
{
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->store[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }
        return $out;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}
