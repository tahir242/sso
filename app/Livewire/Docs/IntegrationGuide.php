<?php

namespace App\Livewire\Docs;

use App\Domain\Federation\Integration\Connection;
use App\Domain\Federation\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Integration guides (resources/docs/integration/*.md): connecting in-house
 * applications, and third-party software such as Moodle or Koha. Opened
 * from an application's Connect page (?app=slug), every value is filled in
 * for that application; otherwise placeholders are shown.
 */
#[Layout('layouts::app')]
class IntegrationGuide extends Component
{
    public const PAGES = [
        'overview' => 'How it works',
        'php' => 'PHP',
        'dotnet' => '.NET',
        'java' => 'Java (Spring)',
        'python' => 'Python',
        'node' => 'Node.js',
        'api' => 'Server-to-server API',
    ];

    /**
     * Third-party products: page => [name, sign-in protocol]. Facts in these
     * guides were checked against each product's official documentation
     * and source code (November 2026).
     */
    public const SOFTWARE = [
        'moodle' => ['Moodle', 'OpenID Connect'],
        'koha' => ['Koha', 'OpenID Connect'],
        'nextcloud' => ['Nextcloud', 'OpenID Connect'],
        'grafana' => ['Grafana', 'OpenID Connect'],
    ];

    public string $page = 'overview';

    #[Url(except: '')]
    public string $app = '';

    public function mount(string $page = 'overview'): void
    {
        abort_unless(array_key_exists($page, self::PAGES) || array_key_exists($page, self::SOFTWARE), 404);
        $this->page = $page;
    }

    public function render(): View
    {
        $application = $this->application();
        $connection = $application ? Connection::for($application) : Connection::example();

        $markdown = strtr((string) file_get_contents(resource_path("docs/integration/{$this->page}.md")), $connection->placeholders());

        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['apply_id_to_heading' => true, 'insert' => 'none', 'id_prefix' => '', 'fragment_prefix' => '', 'symbol' => ''],
        ], [new HeadingPermalinkExtension]);

        // Links between guides keep the chosen application.
        if ($application) {
            $pages = implode('|', [...array_keys(self::PAGES), ...array_keys(self::SOFTWARE)]);
            $html = preg_replace('/href="('.$pages.')(#[^"]*)?"/', 'href="$1?app='.e($application->slug).'$2"', $html);
        }

        $software = self::SOFTWARE[$this->page] ?? null;

        return view('livewire.docs.integration-guide', [
            'html' => $html,
            'pages' => self::PAGES,
            'softwarePages' => array_map(fn (array $s) => $s[0], self::SOFTWARE),
            'software' => $software,
            'application' => $application,
        ])->title(($software[0] ?? self::PAGES[$this->page]).' · Integration guide');
    }

    /** The chosen application, if this person may see its settings. */
    private function application(): ?Application
    {
        if ($this->app === '') {
            return null;
        }

        $user = auth()->user();

        return Application::where('slug', $this->app)
            ->when(! $user->hasRole('super-admin'), fn ($q) => $q->where('owner_user_id', $user->getKey()))
            ->first();
    }
}
