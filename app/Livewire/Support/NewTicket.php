<?php

namespace App\Livewire\Support;

use App\Domain\Federation\Models\Application;
use App\Domain\Helpdesk\Actions\OpenTicket;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Models\TicketCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
#[Title('Get IT help')]
class NewTicket extends Component
{
    use WithFileUploads;

    /** Application slug from ?app=, preselects the application. */
    #[Url(as: 'app', except: '')]
    public string $app = '';

    public ?int $application_id = null;

    public ?int $category_id = null;

    public string $priority = 'normal';

    public string $subject = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        if ($this->app !== '') {
            $this->application_id = Application::query()->active()->where('slug', $this->app)->value('id');
        }
    }

    public function updatedApplicationId(): void
    {
        $this->category_id = null;
    }

    public function submit(OpenTicket $open): void
    {
        $key = 'ticket:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['subject' => __('You have raised many tickets in the last hour. Please add to an existing one or call the IT helpdesk.')]);
        }

        $this->validate([
            'application_id' => ['nullable', 'integer', Rule::exists('applications', 'id')->where('active', true)],
            'category_id' => ['nullable', 'integer', Rule::in($this->categories()->pluck('id')->all())],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachments' => ['array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,pdf'],
        ], attributes: ['application_id' => 'application', 'category_id' => 'category']);

        RateLimiter::hit($key, 3600);

        $ticket = $open->handle(
            auth()->user(),
            $this->application_id ? Application::find($this->application_id) : null,
            $this->category_id ? TicketCategory::find($this->category_id) : null,
            $this->subject,
            $this->description,
            TicketPriority::from($this->priority),
            $this->attachments,
        );

        session()->flash('status', __('Ticket :ref raised. The IT helpdesk will reply here.', ['ref' => $ticket->reference]));
        $this->redirectRoute('support.show', $ticket->ulid);
    }

    public function render(): View
    {
        return view('livewire.support.new-ticket', [
            'applications' => Application::query()->active()->orderBy('name')->get(['id', 'name']),
            // Opened with ?app=: the "Suggest an improvement" link keeps the application.
            'contextApp' => $this->app !== '' ? Application::query()->active()->where('slug', $this->app)->first() : null,
            'categories' => $this->categories(),
            'priorities' => TicketPriority::cases(),
        ]);
    }

    private function categories()
    {
        return TicketCategory::query()->offeredFor($this->application_id)->get(['id', 'name', 'description', 'application_id']);
    }
}
