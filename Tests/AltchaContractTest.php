<?php

namespace Omnishield\Altcha\Tests;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Omnishield\Altcha\AltchaGatewayFactory;
use Omnishield\ChallengeIssuerInterface;
use Omnishield\GatewayFactoryInterface;
use Omnishield\GatewayInterface;
use Omnishield\Model\Attempt;
use Omnishield\Replay\InMemoryReplayStore;
use Omnishield\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The family's contract: challenges issued and solved here, nothing asked of anyone. */
final class AltchaContractTest extends TestCase
{
    use GatewayContractTests;

    protected function factory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new AltchaGatewayFactory(new InMemoryReplayStore());
    }

    protected function options(): array
    {
        return ['hmac_key' => 'a-long-random-secret-of-the-site', 'cost' => 10];
    }

    protected function recordedCases(): iterable
    {
        yield 'a solution' => [static fn (GatewayInterface $gateway) => new Attempt(self::solve($gateway, 'contact'), '192.0.2.10', 'contact'), true];
        yield 'a solution for another action' => [static fn (GatewayInterface $gateway) => new Attempt(self::solve($gateway, 'signup'), '192.0.2.10', 'contact'), false];
        yield 'a forged token' => [new Attempt(base64_encode('{"challenge":{},"solution":{}}')), false];
        yield 'not a token' => [new Attempt('not-base64-at-all'), false];
    }

    private static function solve(GatewayInterface $gateway, string $action): string
    {
        \assert($gateway instanceof ChallengeIssuerInterface);
        $challenge = Challenge::fromArray($gateway->issue($action));
        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(algorithm: AltchaGatewayFactory::algorithm('PBKDF2/SHA-256'), challenge: $challenge));
        self::assertNotNull($solution);

        return (new Payload($challenge, $solution))->toBase64();
    }
}
