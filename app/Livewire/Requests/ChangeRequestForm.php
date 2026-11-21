<?php

namespace App\Livewire\Requests;

use App\Domain\Federation\Models\Application;
use App\Domain\Requests\Actions\SubmitChangeRequest;
use App\Domain\Requests\Enums\ChangeRequestType;
use App\Domain\Requests\Enums\Priority;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Suggest an improvement to an application; it goes to the application's
 * team. Problems are not reported here: they go to the IT helpdesk.
 * Opened from "New request" (/suggest); ?app=slug preselects the application.
 */
#[Layout('layouts::app')]
class ChangeRequestForm extends Component
{
    use WithFileUploads;

    /** The application the suggestion is for (slug). */
    #[Url(as: 'app', except: '')]
    public string $app = '';

    public string $type = 'feature';

    public string $priority = 'normal';

    public string $title = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        // Ignore an application the user cannot open.
        if ($this->app !== '' && ! $this->applications()->contains('slug', $this->app)) {
            $this->app = '';
        }
    }

    public function submit(SubmitChangeRequest $submit): void
    {
        $this->validate([
            'app' => ['required', Rule::in($this->applications()->pluck('slug')->all())],
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, ChangeRequestType::offered()))],
            'priority' => ['required', Rule::enum(Priority::class)],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'attachments' => ['array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,pdf'],
        ], attributes: ['app' => 'application']);

        $application = $this->application();
        $submit->handle(auth()->user(), $application, ChangeRequestType::from($this->type), $this->title, $this->description, Priority::from($this->priority), $this->attachments);

        session()->flash('status', __('Thanks — your suggestion was sent to the :app team. Their reply will appear here.', ['app' => $application->name]));
        $this->redirectRoute('access.requests', ['tab' => 'changes']);
    }

    public function render(): View
    {
        $application = $this->application();

        return view('livewire.requests.change-request-form', [
            'application' => $application,
            'applications' => $this->applications(),
            'types' => ChangeRequestType::offered(),
            'priorities' => Priority::cases(),
        ])->title($application ? __('Suggest an improvement: :app', ['app' => $application->name]) : __('Suggest an improvement'));
    }

    private function application(): ?Application
    {
        return $this->app === '' ? null : $this->applications()->firstWhere('slug', $this->app);
    }

    /**
     * Applications the user may send suggestions for: the ones they can open.
     *
     * @return Collection<int, Application>
     */
    private function applications(): Collection
    {
        return once(fn () => Application::query()->openableBy(auth()->user())->orderBy('name')->get());
    }
}
