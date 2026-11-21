<?php

namespace App\Domain\Requests\Actions;

use App\Domain\Access\Actions\DecideApprovalStep;
use App\Domain\Access\Actions\SubmitAccessRequest;
use App\Domain\Access\Enums\ApprovalStepType;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\NewStaffStatus;
use App\Domain\Requests\Models\NewStaffRequest;
use App\Domain\Settings\GeneralSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The helpdesk creates the SSO account for an approved new-staff request and
 * files access requests for the applications asked for. The HOD already
 * confirmed, so those requests start at the application-owner step.
 */
final readonly class CompleteNewStaffRequest
{
    public function __construct(
        private SubmitAccessRequest $submit,
        private DecideApprovalStep $decide,
    ) {}

    /**
     * @param  array{username: string, auth_source: string, password?: ?string}  $account
     */
    public function handle(NewStaffRequest $request, User $helpdesk, array $account): User
    {
        if ($request->status !== NewStaffStatus::Approved) {
            throw ValidationException::withMessages(['username' => __('Only approved requests can be completed.')]);
        }

        $user = DB::transaction(function () use ($request, $helpdesk, $account) {
            $source = $account['auth_source'] instanceof AuthSource ? $account['auth_source'] : AuthSource::from($account['auth_source']);

            $user = User::create([
                'username' => $account['username'],
                'emp_id' => $request->emp_id,
                'name' => $request->full_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'designation' => $request->designation,
                'department_id' => $request->department_id,
                'auth_source' => $source,
                'password' => $source === AuthSource::Local ? $account['password'] : null,
                'password_changed_at' => $source === AuthSource::Local ? now() : null,
                'status' => UserStatus::Active,
            ]);

            $request->update([
                'status' => NewStaffStatus::Completed,
                'created_user_id' => $user->getKey(),
                'completed_by' => $helpdesk->getKey(),
                'completed_at' => now(),
            ]);

            return $user;
        });

        foreach ($request->requestedApplications()->where('active', true) as $application) {
            $access = $this->submit->handle($helpdesk, $application, $user, [], __('New staff member; head of department confirmed on :date.', [
                'date' => $request->hod_decided_at?->format('j M Y'),
            ]));

            // Carry the HOD's confirmation over to the access request.
            $step = $access->fresh()->currentStep();
            if ($step?->type === ApprovalStepType::HeadOfDepartment && $request->hod && $step->canBeDecidedBy($request->hod)) {
                $this->decide->handle($step, $request->hod, true, __('Confirmed on the new staff request.'));
            }
        }

        self::emailRequester($request->fresh());

        return $user;
    }

    /**
     * The person who filled in the form has no account yet: tell them by
     * email (if they gave one) what happened.
     */
    public static function emailRequester(NewStaffRequest $request): void
    {
        if (! $request->email) {
            return;
        }

        $message = match ($request->status) {
            NewStaffStatus::Approved => [__('Your head of department confirmed your request. The IT helpdesk will now create your account.')],
            NewStaffStatus::Rejected => [__('Your request was not confirmed.'), __('Reason: :reason', ['reason' => $request->hod_comment])],
            NewStaffStatus::Completed => [
                __('Your account is ready. Username: :username', ['username' => $request->createdUser?->username]),
                __('Sign in at :url. If you were not given a password, use your Windows (network) password.', ['url' => route('login')]),
            ],
            default => null,
        };

        if (! $message) {
            return;
        }

        try {
            $mail = (new MailMessage)
                ->subject(__('Your account request (:ref)', ['ref' => strtoupper(substr($request->ulid, -6))]))
                ->greeting(__('Hello :name,', ['name' => $request->full_name]));
            foreach ($message as $line) {
                $mail->line($line);
            }
            $mail->salutation(app(GeneralSettings::class)->organization_name.' IT');

            Mail::html((string) $mail->render(), fn ($m) => $m->to($request->email)->subject($mail->subject));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
