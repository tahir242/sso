<x-filament-panels::page>
    @php
        $values = [
            ['Issuer', $connection->issuer, 'Most libraries only need this; they read the rest from discovery.'],
            ['Discovery', $connection->issuer.'/.well-known/openid-configuration', null],
            ['Client ID', $connection->clientId, null],
            ['Client secret', 'Shown once when created. Use “New client secret” on the settings page if it was lost.', null],
            ['Scopes', $connection->scopes, '“roles” gives this application’s role keys; “employee” gives user_id, emp_id and department.'],
            ['Registered redirect URIs', $connection->redirectUris ? implode("\n", $connection->redirectUris) : 'None yet', null],
            ['Post-logout redirect', $connection->postLogoutUri, null],
            ['Back-channel logout URI', $connection->backchannelUri ?? 'Not set', 'Optional but recommended: the SSO calls it when a user signs out, so this application ends its session too.'],
        ];
        $stacks = [
            ['php', 'PHP', 'One line per page with the downloadable kit. No Composer needed.', 'PHP 8.1+'],
            ['dotnet', '.NET', 'ASP.NET Core (8+) and ASP.NET MVC 5 / Web Forms (OWIN).', 'Microsoft OpenID Connect middleware'],
            ['java', 'Java', 'Spring Boot 3 with Spring Security’s OAuth2 client.', 'Configuration only'],
            ['python', 'Python', 'Django, Flask and FastAPI with Authlib.', 'Authlib'],
            ['node', 'Node.js', 'Express with express-openid-connect.', 'Configuration only'],
            ['api', 'Server-to-server API', 'Read users and roles, request access, from any language.', 'Client credentials'],
        ];
    @endphp

    <x-filament::section heading="Values for this application" description="Give these to the application’s developers. Everything except the secret is safe to share.">
        <dl class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($values as [$label, $value, $hint])
                <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="sm:col-span-2">
                        <span class="whitespace-pre-line break-all font-mono text-sm text-gray-950 dark:text-white">{{ $value }}</span>
                        @if ($hint)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Connect it" description="Each guide opens with this application’s values already filled in.">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($stacks as [$key, $name, $what, $how])
                <a href="{{ route('docs.integration', ['page' => $key, 'app' => $application->slug]) }}" target="_blank"
                   class="group rounded-xl p-4 ring-1 ring-gray-950/10 transition hover:bg-gray-50 hover:ring-primary-500 dark:ring-white/10 dark:hover:bg-white/5">
                    <p class="flex items-center justify-between font-semibold text-gray-950 dark:text-white">
                        {{ $name }}
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4 text-gray-400 group-hover:text-primary-500" />
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $what }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $how }}</p>
                </a>
            @endforeach
        </div>

        @unless ($usesGate)
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Using the PHP kit? Its redirect URI ({{ $connection->gatePage() }}?sso=callback) isn’t registered yet; use “Register PHP gate URLs” above.
            </p>
        @endunless
    </x-filament::section>
</x-filament-panels::page>
