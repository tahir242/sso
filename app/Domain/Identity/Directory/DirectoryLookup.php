<?php

namespace App\Domain\Identity\Directory;

use App\Domain\Identity\Actions\ImportDirectoryUser;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Who is this username in Active Directory?" for the user form, cached
 * for a minute as a plain array so typing does not hammer the domain
 * controller. Never throws: an unreachable directory is reported as such.
 */
final readonly class DirectoryLookup
{
    public function __construct(private ActiveDirectory $directory) {}

    /**
     * @return array{status: 'off'|'found'|'missing'|'error', guid?: string, name?: string, email?: ?string, emp_id?: ?string, title?: ?string, department?: ?string, mobile?: ?string, disabled?: bool, linked_to?: ?array{id: int, name: string, username: string}, error?: string}
     */
    public function username(?string $username): array
    {
        $username = trim((string) $username);

        if (! $this->directory->isEnabled()) {
            return ['status' => 'off'];
        }

        if ($username === '') {
            return ['status' => 'missing'];
        }

        $summary = Cache::remember('ad-lookup:'.Str::lower($username), 60, function () use ($username): array {
            try {
                $person = $this->directory->findUser($username);
            } catch (Throwable $e) {
                return ['status' => 'error', 'error' => ActiveDirectory::explain($e->getMessage())];
            }

            if (! $person) {
                return ['status' => 'missing'];
            }

            return [
                'status' => 'found',
                'guid' => (string) $person->getConvertedGuid(),
                'name' => ImportDirectoryUser::displayName($person),
                'given_name' => $person->getFirstAttribute('givenname'),
                'middle_name' => $person->getFirstAttribute('middlename'),
                'family_name' => $person->getFirstAttribute('sn'),
                'email' => ImportDirectoryUser::email($person),
                'emp_id' => $person->getFirstAttribute('employeeid'),
                'title' => $person->getFirstAttribute('title'),
                'department' => $person->getFirstAttribute('department'),
                'mobile' => $person->getFirstAttribute('mobile'),
                'disabled' => $person->isDisabled(),
            ];
        });

        if ($summary['status'] === 'found') {
            $owner = User::query()->where('ldap_guid', $summary['guid'])->first(['id', 'name', 'username']);
            $summary['linked_to'] = $owner ? ['id' => $owner->id, 'name' => $owner->name, 'username' => $owner->username] : null;
        }

        return $summary;
    }
}
