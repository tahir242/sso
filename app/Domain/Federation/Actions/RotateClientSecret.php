<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\Models\Application;
use Laravel\Passport\ClientRepository;

/**
 * Issues a new client secret. The old one stops working immediately.
 */
final readonly class RotateClientSecret
{
    public function __construct(private ClientRepository $clients) {}

    public function handle(Application $application): string
    {
        $client = $application->client;
        $this->clients->regenerateSecret($client);

        activity()->performedOn($application)->log('client secret rotated');

        return (string) $client->plainSecret;
    }
}
